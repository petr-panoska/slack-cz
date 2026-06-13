<?php

namespace App\Command;

use App\Entity\LonglineCrossing;
use App\Enum\CrossingStyle;
use App\Legacy\UserMergeMap;
use App\Repository\LonglineCrossingRepository;
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
    name: 'app:import:longline-crossings',
    description: 'Imports longline diary entries (longline deník) from legacy MySQL into the new Postgres schema',
)]
final class ImportLonglineCrossingsCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.old_connection')]
        private readonly Connection $oldConnection,
        private readonly LonglineCrossingRepository $longlines,
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
            $io->warning('Truncating existing longline_crossing rows (legacy-imported only)');
            $this->em->createQuery('DELETE FROM ' . LonglineCrossing::class . ' c WHERE c.legacyId IS NOT NULL')->execute();
            $this->userMergeMap->reset();
        }

        $rows = $this->oldConnection->fetchAllAssociative('
            SELECT id, uzivatel, delka, misto, styl, datum
            FROM longline
            ORDER BY id ASC
        ');
        $io->info(sprintf('Found %d longline entries in legacy DB', count($rows)));

        $imported = 0;
        $skipped = 0;
        $unknownStyle = [];
        $batch = 100;
        $i = 0;

        foreach ($rows as $row) {
            $i++;

            $existing = $this->longlines->findOneBy(['legacyId' => (int) $row['id']]);
            if ($existing !== null) {
                $skipped++;
                continue;
            }

            $user = $this->userMergeMap->find((int) $row['uzivatel']);
            if ($user === null) {
                $io->writeln(sprintf('  <comment>[SKIP]</comment> longline#%d — uzivatel#%d not found (or not imported)', $row['id'], $row['uzivatel']));
                $skipped++;
                continue;
            }

            $crossedAt = $this->parseDate($row['datum']);
            if ($crossedAt === null) {
                $io->writeln(sprintf('  <comment>[SKIP]</comment> longline#%d — invalid datum "%s"', $row['id'], $row['datum'] ?? ''));
                $skipped++;
                continue;
            }

            $place = trim((string) ($row['misto'] ?? ''));
            if ($place === '') {
                $place = '—';
            }

            // Same CrossingStyle enum as highline (legacy longline uses the same
            // loose vocabulary: "one way", "OS fm", …). Unmapped → null + report.
            $style = CrossingStyle::fromLegacy($row['styl'] ?? null);
            $rawStyle = trim((string) ($row['styl'] ?? ''));
            if ($style === null && $rawStyle !== '' && $rawStyle !== '-') {
                $unknownStyle[$rawStyle] = ($unknownStyle[$rawStyle] ?? 0) + 1;
            }

            $longline = new LonglineCrossing();
            $longline->setLegacyId((int) $row['id']);
            $longline->setUser($user);
            $longline->setCrossedAt($crossedAt);
            $longline->setLength((int) $row['delka']);
            $longline->setPlace($place);
            $longline->setStyle($style);

            if (!$dryRun) {
                $this->em->persist($longline);
                if ($i % $batch === 0) {
                    $this->em->flush();
                    $this->em->clear();
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
            '%s %d longline entries (%d skipped)',
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
        // these into negative years which Postgres rejects.
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
}
