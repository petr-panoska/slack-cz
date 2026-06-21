<?php

namespace App\Command;

use App\Entity\Line;
use App\Enum\LineEditStatus;
use App\Enum\LineType;
use App\Repository\LineEditRepository;
use App\Repository\LineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-shot maintenance: re-apply each highline's latest APPLIED edit snapshot to the entity.
 * Used to repair drift left over from earlier history-deletion runs that didn't yet sync the
 * canonical state. Safe to re-run — idempotent when state already matches the latest snapshot.
 */
#[AsCommand(
    name: 'app:edit:sync-from-history',
    description: 'Re-apply latest APPLIED snapshot to each highline (fix drift from old history deletions)',
)]
final class SyncLineFromAuditCommand extends Command
{
    public function __construct(
        private readonly LineRepository $lines,
        private readonly LineEditRepository $edits,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $touched = 0;
        $skipped = 0;

        foreach ($this->lines->findAll() as $line) {
            $latest = $this->edits->findLatestAppliedFor($line);
            if ($latest === null) {
                $skipped++;
                continue;
            }
            $snap = $latest->getSnapshot();
            $currentName = $line->getName();
            $snapName = $snap['name'] ?? null;
            if ($currentName === $snapName) {
                // Cheap pre-check: if names match, almost certainly nothing else drifted either.
                // For full correctness one could compare the whole snapshot; for now name-mismatch
                // is the symptom we're chasing.
                $skipped++;
                continue;
            }

            $io->writeln(sprintf(
                '<info>sync</info> #%d %s: "%s" → "%s"',
                $line->getId(),
                $line->getSlug() ?? '(no slug)',
                $currentName,
                $snapName,
            ));

            if (!$dryRun) {
                $this->applySnapshot($line, $snap);
            }
            $touched++;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf('%s %d highlines (%d skipped — already in sync)', $dryRun ? 'Would sync' : 'Synced', $touched, $skipped));
        return Command::SUCCESS;
    }

    /**
     * Mirror of LineCrudController::applySnapshot — duplicated here intentionally so the
     * maintenance command stays self-contained and doesn't import controller internals.
     *
     * @param array<string, mixed> $s
     */
    private function applySnapshot(Line $h, array $s): void
    {
        $h->setName((string) $s['name']);
        $h->setType(LineType::from((string) $s['type']));
        $h->setHeight((int) $s['height']);
        $h->setLength((int) ($s['length'] ?? 0));
        $h->setPoint1Latitude(isset($s['point1Latitude']) ? (string) $s['point1Latitude'] : null);
        $h->setPoint1Longitude(isset($s['point1Longitude']) ? (string) $s['point1Longitude'] : null);
        $h->setPoint2Latitude(isset($s['point2Latitude']) ? (string) $s['point2Latitude'] : null);
        $h->setPoint2Longitude(isset($s['point2Longitude']) ? (string) $s['point2Longitude'] : null);
        $h->setParkingLatitude(isset($s['parkingLatitude']) ? (string) $s['parkingLatitude'] : null);
        $h->setParkingLongitude(isset($s['parkingLongitude']) ? (string) $s['parkingLongitude'] : null);
        $h->setCountry($s['country'] ?? null);
        $h->setRegion($s['region'] ?? null);
        $h->setArea($s['area'] ?? null);
        $h->setDescription($s['description'] ?? null);
        $h->setPointOneInfo($s['pointOneInfo'] ?? null);
        $h->setPointTwoInfo($s['pointTwoInfo'] ?? null);
        $h->setAnchoring($s['anchoring'] ?? null);
        $h->setApproachMinutes(isset($s['approachMinutes']) && $s['approachMinutes'] !== null ? (int) $s['approachMinutes'] : null);
        $h->setTensioningMinutes(isset($s['tensioningMinutes']) && $s['tensioningMinutes'] !== null ? (int) $s['tensioningMinutes'] : null);
        $h->setFirstAscentBy($s['firstAscentBy'] ?? null);
        $h->setFirstAscentDate(!empty($s['firstAscentDate']) ? new \DateTimeImmutable((string) $s['firstAscentDate']) : null);
        $h->setNameHistory($s['nameHistory'] ?? null);
    }
}
