<?php

namespace App\Command;

use App\Entity\LineCrossing;
use App\Enum\CrossingStyle;
use App\Legacy\UserMergeMap;
use App\Repository\LineCrossingRepository;
use App\Repository\LineRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:import:line-crossings',
    description: 'Imports highline crossings (deníček) from legacy MySQL into the new Postgres schema',
)]
final class ImportLineCrossingsCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.old_connection')]
        private readonly Connection $oldConnection,
        private readonly LineRepository $lines,
        private readonly LineCrossingRepository $crossings,
        private readonly UserMergeMap $userMergeMap,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('truncate', null, InputOption::VALUE_NONE, 'Drop existing imported rows before import')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would happen without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        if ($input->getOption('truncate') && !$dryRun) {
            $io->warning('Truncating existing highline_crossing rows (legacy-imported only)');
            $this->em->createQuery('DELETE FROM ' . LineCrossing::class . ' c WHERE c.legacyId IS NOT NULL')->execute();
            $this->userMergeMap->reset();
        }

        $rows = $this->oldConnection->fetchAllAssociative('
            SELECT id, highline_id, uzivatel_id, datum, styl, poznamka, hodnoceni
            FROM highline_prechody
            ORDER BY id ASC
        ');
        $io->info(sprintf('Found %d crossings in legacy DB', count($rows)));

        $imported = 0;
        $skipped = 0;
        $unknownStyle = [];
        $batch = 100;
        $i = 0;

        // Cache highlines by legacy id
        $linesByLegacyId = [];
        foreach ($this->lines->findAll() as $h) {
            if ($h->getLegacyId() !== null) {
                $linesByLegacyId[$h->getLegacyId()] = $h;
            }
        }

        foreach ($rows as $row) {
            $i++;

            $existing = $this->crossings->findOneBy(['legacyId' => (int) $row['id']]);
            if ($existing !== null) {
                $skipped++;
                continue;
            }

            $line = $linesByLegacyId[(int) $row['highline_id']] ?? null;
            if ($line === null) {
                $io->writeln(sprintf('  <comment>[SKIP]</comment> prechod#%d — highline#%d not found in new DB', $row['id'], $row['highline_id']));
                $skipped++;
                continue;
            }

            $user = $this->userMergeMap->find((int) $row['uzivatel_id']);
            if ($user === null) {
                $io->writeln(sprintf('  <comment>[SKIP]</comment> prechod#%d — uzivatel#%d not found (or not imported)', $row['id'], $row['uzivatel_id']));
                $skipped++;
                continue;
            }

            $crossedAt = $this->parseDate($row['datum']);
            if ($crossedAt === null) {
                $io->writeln(sprintf('  <comment>[SKIP]</comment> prechod#%d — invalid datum "%s"', $row['id'], $row['datum'] ?? ''));
                $skipped++;
                continue;
            }

            $style = CrossingStyle::fromLegacy($row['styl'] ?? null);
            if ($style === null && !empty(trim((string) ($row['styl'] ?? ''))) && trim((string) $row['styl']) !== '-') {
                $unknownStyle[trim((string) $row['styl'])] = ($unknownStyle[trim((string) $row['styl'])] ?? 0) + 1;
            }

            $rating = $row['hodnoceni'] !== null ? (int) $row['hodnoceni'] : null;
            // Legacy used 0 to mean "no rating"; treat 0 as null
            if ($rating === 0) {
                $rating = null;
            }

            $crossing = new LineCrossing();
            $crossing->setLegacyId((int) $row['id']);
            $crossing->setLine($line);
            $crossing->setUser($user);
            $crossing->setCrossedAt($crossedAt);
            $crossing->setStyle($style);
            $crossing->setRating($rating);
            $crossing->setComment($this->trimOrNull($row['poznamka'] ?? null));

            if (!$dryRun) {
                $this->em->persist($crossing);
                if ($i % $batch === 0) {
                    $this->em->flush();
                    $this->em->clear();
                    // Re-cache highlines after clear (entity references invalidated)
                    $linesByLegacyId = [];
                    foreach ($this->lines->findAll() as $h) {
                        if ($h->getLegacyId() !== null) {
                            $linesByLegacyId[$h->getLegacyId()] = $h;
                        }
                    }
                    $this->userMergeMap->reset();
                }
            }

            $imported++;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        if (!empty($unknownStyle)) {
            $io->warning('Unknown styles encountered (mapped to null):');
            foreach ($unknownStyle as $style => $count) {
                $io->writeln(sprintf('  "%s" — %d occurrence(s)', $style, $count));
            }
        }

        $io->success(sprintf(
            '%s %d crossings (%d skipped)',
            $dryRun ? 'Would import' : 'Imported',
            $imported,
            $skipped,
        ));

        return Command::SUCCESS;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!$value) {
            return null;
        }
        $str = (string) $value;
        // Legacy MySQL stored some rows as "0000-00-00" — DateTimeImmutable would parse
        // these into negative years (-0001-11-30) which Postgres rejects.
        if (str_starts_with($str, '0000') || str_starts_with($str, '-')) {
            return null;
        }
        try {
            $dt = new \DateTimeImmutable($str);
            if ((int) $dt->format('Y') < 1990) {
                return null;
            }
            return $dt;
        } catch (\Exception) {
            return null;
        }
    }

    private function trimOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
