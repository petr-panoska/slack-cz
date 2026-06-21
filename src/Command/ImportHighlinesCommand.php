<?php

namespace App\Command;

use App\Entity\Highline;
use App\Enum\HighlineType;
use App\Repository\HighlineRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:import:highlines',
    description: 'Imports highlines from the legacy MySQL database into the new Postgres schema',
)]
final class ImportHighlinesCommand extends Command
{
    public const HIGHLINE_HEIGHT_THRESHOLD = 10;

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.old_connection')]
        private readonly Connection $oldConnection,
        private readonly HighlineRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly SluggerInterface $slugger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('truncate', null, InputOption::VALUE_NONE, 'Drop existing imported rows before import')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be imported without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        if ($input->getOption('truncate') && !$dryRun) {
            $io->warning('Truncating existing highlines table');
            $this->em->createQuery('DELETE FROM ' . Highline::class)->execute();
        }

        $rows = $this->oldConnection->fetchAllAssociative('
            SELECT h.id, h.typ, h.jmeno, h.delka, h.vyska,
                   h.hodnoceni, h.info, h.pointOneInfo, h.pointTwoInfo,
                   h.autor, h.datum,
                   COALESCE(NULLIF(h.lat, ""), CAST(g1.lat AS CHAR)) AS lat,
                   COALESCE(NULLIF(h.lng, ""), CAST(g1.lng AS CHAR)) AS lng,
                   CAST(g1.lat AS CHAR) AS point1_lat,
                   CAST(g1.lng AS CHAR) AS point1_lng,
                   CAST(g2.lat AS CHAR) AS point2_lat,
                   CAST(g2.lng AS CHAR) AS point2_lng,
                   CAST(gp.lat AS CHAR) AS parking_lat,
                   CAST(gp.lng AS CHAR) AS parking_lng,
                   h.nameHistory, h.timeApproach, h.timeTensioning
            FROM highline h
            LEFT JOIN gps g1 ON g1.id = h.point1_id
            LEFT JOIN gps g2 ON g2.id = h.point2_id
            LEFT JOIN gps gp ON gp.id = h.parking_id AND gp.type = "PARKING"
        ');

        $io->info(sprintf('Found %d highlines in legacy DB', count($rows)));

        $imported = 0;
        $skipped = 0;
        $batchSize = 50;
        $usedSlugs = [];

        foreach ($rows as $i => $row) {
            // A line is located by its first anchor (point1); skip legacy rows without it.
            $p1Lat = $row['point1_lat'] ?? null;
            $p1Lng = $row['point1_lng'] ?? null;
            if ($p1Lat === null || $p1Lng === null || trim((string) $p1Lat) === '' || trim((string) $p1Lng) === '') {
                $io->writeln(sprintf('  <comment>skip</comment> #%d "%s" — missing GPS (bod 1)', $row['id'], $row['jmeno']));
                $skipped++;
                continue;
            }

            $existing = $this->repository->findOneBy(['legacyId' => (int) $row['id']]);
            if ($existing !== null) {
                $skipped++;
                continue;
            }

            $h = new Highline();
            $h->setLegacyId((int) $row['id']);
            $h->setName($row['jmeno']);
            $h->setSlug($this->makeUniqueSlug($row['jmeno'], $usedSlugs));
            // A line rigged more than 10 m up is a highline by definition, so the height
            // overrides the legacy type (typically "unsorted", occasionally a mis-tagged midline).
            $height = (int) $row['vyska'];
            $h->setType($height >= self::HIGHLINE_HEIGHT_THRESHOLD ? HighlineType::Highline : HighlineType::fromLegacyId((int) $row['typ']));
            $h->setLength((int) $row['delka']);
            $h->setHeight($height);
            $h->setPoint1Latitude($this->normalizeNullableCoord($row['point1_lat']));
            $h->setPoint1Longitude($this->normalizeNullableCoord($row['point1_lng']));
            $h->setPoint2Latitude($this->normalizeNullableCoord($row['point2_lat']));
            $h->setPoint2Longitude($this->normalizeNullableCoord($row['point2_lng']));
            $h->setParkingLatitude($this->normalizeNullableCoord($row['parking_lat']));
            $h->setParkingLongitude($this->normalizeNullableCoord($row['parking_lng']));
            $h->setRating($row['hodnoceni'] !== null ? (int) $row['hodnoceni'] : null);
            $h->setDescription($this->normalizeText($row['info']));
            $h->setPointOneInfo($this->normalizeText($row['pointOneInfo']));
            $h->setPointTwoInfo($this->normalizeText($row['pointTwoInfo']));
            $h->setFirstAscentBy($row['autor'] ?: null);
            $h->setFirstAscentDate($this->parseDate($row['datum']));
            $h->setNameHistory($this->normalizeText($row['nameHistory']));
            $h->setApproachMinutes($row['timeApproach'] !== null ? (int) $row['timeApproach'] : null);
            $h->setTensioningMinutes($row['timeTensioning'] !== null ? (int) $row['timeTensioning'] : null);

            if (!$dryRun) {
                $this->em->persist($h);
                if (($i + 1) % $batchSize === 0) {
                    $this->em->flush();
                    $this->em->clear();
                }
            }

            $imported++;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%s %d highlines (%d skipped)',
            $dryRun ? 'Would import' : 'Imported',
            $imported,
            $skipped,
        ));

        return Command::SUCCESS;
    }

    /**
     * Legacy stores coords as strings with up to 17 decimal digits — clamp to our precision (7 digits).
     */
    private function normalizeCoord(string $raw): string
    {
        return number_format((float) trim($raw), 7, '.', '');
    }

    private function normalizeNullableCoord(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        return $this->normalizeCoord($raw);
    }

    /**
     * @param array<string,true> $usedSlugs accumulator within a single import run; ensures slug uniqueness
     *                                      even before the rows hit the DB (truncate + flush happen later).
     */
    private function makeUniqueSlug(string $name, array &$usedSlugs): string
    {
        $base = strtolower($this->slugger->slug($name)->toString());
        if ($base === '') {
            $base = 'highline';
        }

        $slug = $base;
        $i = 2;
        while (isset($usedSlugs[$slug])) {
            $slug = $base . '-' . $i++;
        }
        $usedSlugs[$slug] = true;

        return $slug;
    }

    /**
     * Legacy CKEditor stored entity-encoded HTML (e.g. `<p>m&iacute;sto</p>`). Flatten it to
     * plain text: block tags become newlines, the rest is stripped, and HTML entities are
     * decoded back to real characters. Rendered with `|nl2br` on the detail page.
     */
    private function normalizeText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = preg_replace('#</p\s*>#i', "\n\n", $text);
        $text = preg_replace('#<br\s*/?>#i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        $trimmed = trim($text);
        return $trimmed === '' ? null : $trimmed;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!$value) {
            return null;
        }
        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Exception) {
            return null;
        }
    }
}
