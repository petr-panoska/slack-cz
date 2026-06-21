<?php

namespace App\Command;

use App\Entity\Line;
use App\Entity\LinePhoto;
use App\Repository\LinePhotoRepository;
use App\Repository\LineRepository;
use App\Service\PhotoNormalizer;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Imports legacy highline photos into the new schema.
 *
 * Two legacy sources, reconciled:
 *   - Gallery: `highline_foto` rows (file + caption) in the OLD MySQL DB.
 *     Files live on disk at <photos-dir>/<legacyId>/foto/<file>.
 *   - Cover: a filesystem-only convention <photos-dir>/<legacyId>/foto.jpg
 *     (NOT recorded in any DB table). Imported as a normal LinePhoto and
 *     referenced via Line.coverPhoto.
 *
 * Quirks handled (all verified against disk + legacy DB + live slack.cz):
 *   - Truncated filenames (legacy `file` is varchar(45)) recovered by prefix match.
 *   - Byte-duplicates within a line (md5) imported only once.
 *   - Orphan files (on disk, no DB row) are NOT imported — exported to a review
 *     folder and reported instead.
 *   - Stale dirs (photos for highlines deleted from the legacy DB) are reported
 *     and their files exported; there is no line to attach them to.
 *
 * Files are copied into Vich storage (public/uploads/highline/<id>/) — legacy
 * originals are never touched.
 */
#[AsCommand(
    name: 'app:import:line-photos',
    description: 'Imports legacy line cover + gallery photos (files + highline_foto) into the new schema',
)]
final class ImportLinePhotosCommand extends Command
{
    private const IMG_EXT = ['jpg', 'jpeg', 'png', 'gif'];

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.old_connection')]
        private readonly Connection $oldConnection,
        private readonly LineRepository $lines,
        private readonly LinePhotoRepository $photos,
        private readonly EntityManagerInterface $em,
        private readonly PhotoNormalizer $normalizer,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('photos-dir', null, InputOption::VALUE_REQUIRED,
                'Legacy photo root containing <legacyId>/foto.jpg and <legacyId>/foto/* (default: var/legacy-import/line/high)')
            ->addOption('orphans-dir', null, InputOption::VALUE_REQUIRED,
                'Where to export files found on disk but not importable (default: var/legacy-orphan-photos)')
            ->addOption('truncate', null, InputOption::VALUE_NONE,
                'Delete previously imported legacy photos (gallery + covers) and their files first')
            ->addOption('dry-run', null, InputOption::VALUE_NONE,
                'Report what would happen without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $photosDir = rtrim((string) ($input->getOption('photos-dir') ?? $this->projectDir . '/var/legacy-import/line/high'), '/');
        $orphansDir = rtrim((string) ($input->getOption('orphans-dir') ?? $this->projectDir . '/var/legacy-orphan-photos'), '/');

        if (!is_dir($photosDir)) {
            $io->error(sprintf('Photos dir not found: %s', $photosDir));
            $io->writeln('Stage the legacy photo tree there first (see `make importLegacyPhotos`), or pass --photos-dir.');
            return Command::FAILURE;
        }
        $io->info(sprintf('Photos dir: %s', $photosDir));
        if ($dryRun) {
            $io->warning('DRY RUN — nothing will be written.');
        }

        // legacyId -> Line
        $byLegacyId = [];
        foreach ($this->lines->findAll() as $h) {
            if ($h->getLegacyId() !== null) {
                $byLegacyId[$h->getLegacyId()] = $h;
            }
        }

        if ($input->getOption('truncate') && !$dryRun) {
            $this->truncate($io);
            // Re-fetch: entities/refs may be stale after removals.
            $byLegacyId = [];
            foreach ($this->lines->findAll() as $h) {
                if ($h->getLegacyId() !== null) {
                    $byLegacyId[$h->getLegacyId()] = $h;
                }
            }
        }

        // Gallery rows from the OLD DB, grouped by legacy highline id, ordered by foto id.
        $rows = $this->oldConnection->fetchAllAssociative('
            SELECT id, highline_id, text, file
            FROM highline_foto
            ORDER BY highline_id ASC, id ASC
        ');
        $galleryByLine = [];
        foreach ($rows as $r) {
            $galleryByLine[(int) $r['highline_id']][] = $r;
        }
        $io->info(sprintf('Legacy gallery rows: %d (across %d highlines)', count($rows), count($galleryByLine)));

        $rep = [
            'cover_imported' => 0, 'cover_reused_gallery' => 0, 'cover_skipped_existing' => 0,
            'gallery_imported' => 0, 'gallery_skipped_existing' => 0,
        ];
        $dupSkipped = [];      // [legacyFotoId => points-to]
        $missing = [];         // gallery rows with no file on disk
        $corrupt = [];         // unreadable as image
        $ambiguous = [];       // truncated prefix matched >1 file
        $orphansExported = []; // [legacyId => [file,...]]
        $orphanDupSkipped = [];// byte-dup of an imported photo, not exported
        $noPhotos = [];        // highlines with neither cover nor gallery

        ksort($byLegacyId);
        foreach ($byLegacyId as $legacyId => $line) {
            $dir = $photosDir . '/' . $legacyId;
            $galDir = $dir . '/foto';
            $galleryRows = $galleryByLine[$legacyId] ?? [];
            $coverPath = $dir . '/foto.jpg';
            $hasCover = is_file($coverPath);

            if (!$hasCover && $galleryRows === []) {
                $noPhotos[] = $legacyId;
                continue;
            }

            // Photo date = the line's first-tensioning date (legacy stored no per-photo
            // date, and the files carry no EXIF capture date). NULL when the line has none.
            $firstAscent = $line->getFirstAscentDate();
            $seen = [];               // md5 => LinePhoto  (deduped within this line)
            $referenced = [];         // disk basenames consumed by a DB row
            $n = count($galleryRows);

            // --- gallery ---
            foreach (array_values($galleryRows) as $i => $row) {
                $disk = $this->resolveDiskFile($galDir, (string) $row['file'], $ambiguous, (int) $row['id']);
                if ($disk === null) {
                    $missing[] = sprintf('foto#%d h%d "%s"', $row['id'], $legacyId, $row['file']);
                    continue;
                }
                $referenced[basename($disk)] = true;

                $existing = $this->photos->findOneBy(['legacyId' => (int) $row['id']]);
                if ($existing !== null) {
                    $rep['gallery_skipped_existing']++;
                    $seen[md5_file($disk)] = $existing;
                    continue;
                }
                if (!$this->isImage($disk)) {
                    $corrupt[] = sprintf('foto#%d h%d %s', $row['id'], $legacyId, $disk);
                    continue;
                }
                $md5 = md5_file($disk);
                if (isset($seen[$md5])) {
                    $dupSkipped[(int) $row['id']] = $seen[$md5]->getLegacyId() ?? 'cover';
                    continue;
                }

                // Earlier foto.id displays first (newest createdAt), still below the cover.
                $createdAt = $firstAscent !== null
                    ? (new \DateTimeImmutable())->setTimestamp($firstAscent->getTimestamp() + ($n - $i) * 60)
                    : null;
                $photo = $this->makePhoto($line, (int) $row['id'], $this->cleanCaption($row['text']), $createdAt);
                if (!$dryRun) {
                    $this->attachAndFlush($photo, $disk);
                }
                $seen[$md5] = $photo;
                $rep['gallery_imported']++;
            }

            // --- cover (treated as a normal photo; deduped against the gallery) ---
            if ($hasCover) {
                if ($line->getCoverPhoto() !== null) {
                    $rep['cover_skipped_existing']++;
                } elseif (!$this->isImage($coverPath)) {
                    $corrupt[] = sprintf('cover h%d %s', $legacyId, $coverPath);
                } else {
                    $md5 = md5_file($coverPath);
                    if (isset($seen[$md5])) {
                        // Cover bytes already imported as a gallery photo — reuse it.
                        if (!$dryRun) {
                            $line->setCoverPhoto($seen[$md5]);
                            $this->em->flush();
                        }
                        $rep['cover_reused_gallery']++;
                    } else {
                        $createdAt = $firstAscent !== null
                            ? (new \DateTimeImmutable())->setTimestamp($firstAscent->getTimestamp() + ($n + 10) * 60)
                            : null;
                        $photo = $this->makePhoto($line, null, null, $createdAt);
                        if (!$dryRun) {
                            $this->attachAndFlush($photo, $coverPath);
                            $line->setCoverPhoto($photo);
                            $this->em->flush();
                        }
                        $seen[$md5] = $photo;
                        $rep['cover_imported']++;
                    }
                }
            }

            // --- orphans: gallery images on disk that no DB row referenced ---
            foreach ($this->galleryImages($galDir) as $f) {
                if (isset($referenced[$f])) {
                    continue;
                }
                $path = $galDir . '/' . $f;
                $md5 = md5_file($path);
                if (isset($seen[$md5])) {
                    // byte-identical to a photo we already imported — skip, nothing lost
                    $orphanDupSkipped[] = sprintf('h%d %s (== imported)', $legacyId, $f);
                    continue;
                }
                $seen[$md5] = true; // dedupe orphans among themselves (e.g. double-dot copy)
                if (!$dryRun) {
                    $this->export($orphansDir . '/' . $legacyId, $path);
                }
                $orphansExported[$legacyId][] = $f;
            }
        }

        // --- stale dirs: photos for highlines deleted from the legacy DB ---
        $stale = [];
        foreach (scandir($photosDir) ?: [] as $entry) {
            if (!ctype_digit($entry) || isset($byLegacyId[(int) $entry])) {
                continue;
            }
            $id = (int) $entry;
            $files = [];
            if (is_file("$photosDir/$id/foto.jpg")) {
                $files[] = 'foto.jpg';
            }
            foreach ($this->galleryImages("$photosDir/$id/foto") as $f) {
                $files[] = "foto/$f";
            }
            if ($files === []) {
                continue;
            }
            $stale[$id] = $files;
            if (!$dryRun) {
                foreach ($files as $rel) {
                    $this->export($orphansDir . '/_deleted_highline_' . $id, "$photosDir/$id/$rel");
                }
            }
        }

        $this->printReport($io, $rep, $dupSkipped, $missing, $corrupt, $ambiguous,
            $orphansExported, $orphanDupSkipped, $stale, $noPhotos, $orphansDir, $dryRun);

        return Command::SUCCESS;
    }

    private function truncate(SymfonyStyle $io): void
    {
        $io->warning('Truncating previously imported legacy photos (gallery + covers) and their files');
        $coverIds = [];
        foreach ($this->lines->findAll() as $h) {
            $cp = $h->getCoverPhoto();
            if ($cp !== null) {
                $coverIds[] = $cp->getId();
                $h->setCoverPhoto(null);
            }
        }
        $this->em->flush();

        // Remove via EM so Vich deletes the stored files (delete_on_remove: true).
        $qb = $this->em->getRepository(LinePhoto::class)->createQueryBuilder('p')
            ->where('p.legacyId IS NOT NULL');
        if ($coverIds !== []) {
            $qb->orWhere('p.id IN (:ids)')->setParameter('ids', $coverIds);
        }
        $removed = 0;
        foreach ($qb->getQuery()->getResult() as $p) {
            $this->em->remove($p);
            $removed++;
        }
        $this->em->flush();
        $io->writeln(sprintf('  removed %d photo(s)', $removed));
    }

    private function makePhoto(Line $line, ?int $legacyId, ?string $caption, ?\DateTimeImmutable $createdAt): LinePhoto
    {
        $photo = new LinePhoto();
        $photo->setLine($line);
        $photo->setUploadedBy(null);   // legacy uploads have no known author
        $photo->setLegacyId($legacyId);
        $photo->setCaption($caption);
        $photo->setCreatedAt($createdAt);
        return $photo;
    }

    /**
     * Normalize the legacy original to a clean WebP master (same pipeline as user
     * uploads) and hand it to Vich, which moves it into public/uploads/highline/<id>/.
     * The legacy original is untouched. Legacy files carry no EXIF date/GPS (verified —
     * stripped upstream), so we keep the firstAscentDate-based createdAt and only adopt
     * GPS if it somehow exists.
     */
    private function attachAndFlush(LinePhoto $photo, string $sourcePath): void
    {
        $normalized = $this->normalizer->normalize($sourcePath);
        if ($normalized->gpsLat !== null) {
            $photo->setGps($normalized->gpsLat, $normalized->gpsLng);
        }
        $photo->setFile(new UploadedFile($normalized->path, 'photo.webp', 'image/webp', null, true));
        $this->em->persist($photo);
        $this->em->flush(); // Vich writes the file and sets filename here
        $photo->setFile(null);
    }

    /** exact → case-insensitive → truncated-prefix (legacy varchar(45)). */
    private function resolveDiskFile(string $galDir, string $dbFile, array &$ambiguous, int $fotoId): ?string
    {
        $exact = $galDir . '/' . $dbFile;
        if (is_file($exact)) {
            return $exact;
        }
        if (!is_dir($galDir)) {
            return null;
        }
        $files = array_values(array_filter(scandir($galDir) ?: [], static fn ($f) => is_file($galDir . '/' . $f)));

        foreach ($files as $f) {
            if (strcasecmp($f, $dbFile) === 0) {
                return $galDir . '/' . $f;
            }
        }
        if (strlen($dbFile) === 45) {
            $cands = array_values(array_filter(
                $files,
                static fn ($f) => !str_starts_with($f, 'thumb_') && str_starts_with($f, $dbFile),
            ));
            if (count($cands) === 1) {
                return $galDir . '/' . $cands[0];
            }
            if (count($cands) > 1) {
                $ambiguous[] = sprintf('foto#%d "%s" -> %s (picked first)', $fotoId, $dbFile, implode(', ', $cands));
                return $galDir . '/' . $cands[0];
            }
        }
        return null;
    }

    /** @return list<string> non-thumb image basenames in a gallery dir */
    private function galleryImages(string $galDir): array
    {
        if (!is_dir($galDir)) {
            return [];
        }
        $out = [];
        foreach (scandir($galDir) ?: [] as $f) {
            if (str_starts_with($f, 'thumb_') || !is_file($galDir . '/' . $f)) {
                continue;
            }
            if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), self::IMG_EXT, true)) {
                $out[] = $f;
            }
        }
        sort($out);
        return $out;
    }

    private function isImage(string $path): bool
    {
        return @getimagesize($path) !== false;
    }

    private function cleanCaption(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        return $text === '' ? null : mb_substr($text, 0, 255);
    }

    private function export(string $destDir, string $sourcePath): void
    {
        if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            throw new \RuntimeException('Cannot create ' . $destDir);
        }
        copy($sourcePath, $destDir . '/' . basename($sourcePath));
    }

    /**
     * @param array<string,int>     $rep
     * @param array<int,int|string> $dupSkipped
     * @param list<string>          $missing
     * @param list<string>          $corrupt
     * @param list<string>          $ambiguous
     * @param array<int,list<string>> $orphansExported
     * @param list<string>          $orphanDupSkipped
     * @param array<int,list<string>> $stale
     * @param list<int>             $noPhotos
     */
    private function printReport(
        SymfonyStyle $io, array $rep, array $dupSkipped, array $missing, array $corrupt,
        array $ambiguous, array $orphansExported, array $orphanDupSkipped, array $stale,
        array $noPhotos, string $orphansDir, bool $dryRun,
    ): void {
        $verb = $dryRun ? 'Would import' : 'Imported';
        $io->section('Summary');
        $io->listing([
            sprintf('%s gallery photos: %d (skipped existing: %d)', $verb, $rep['gallery_imported'], $rep['gallery_skipped_existing']),
            sprintf('%s covers (new photo): %d', $verb, $rep['cover_imported']),
            sprintf('Covers reusing a gallery photo (same bytes): %d', $rep['cover_reused_gallery']),
            sprintf('Covers skipped (already set): %d', $rep['cover_skipped_existing']),
            sprintf('Byte-duplicate gallery rows skipped: %d', count($dupSkipped)),
            sprintf('Orphan files exported: %d', array_sum(array_map('count', $orphansExported))),
            sprintf('Orphan files skipped (== imported): %d', count($orphanDupSkipped)),
            sprintf('Stale dirs (deleted highlines): %d', count($stale)),
            sprintf('Lines with no photos: %d', count($noPhotos)),
        ]);

        if ($ambiguous) {
            $io->warning('Ambiguous truncated names (picked first match — verify):');
            $io->listing($ambiguous);
        }
        if ($missing) {
            $io->error(sprintf('%d DB gallery row(s) with NO file on disk (data loss!):', count($missing)));
            $io->listing($missing);
        }
        if ($corrupt) {
            $io->error(sprintf('%d file(s) unreadable as image (skipped):', count($corrupt)));
            $io->listing($corrupt);
        }
        if ($dupSkipped) {
            $io->note('Duplicate gallery rows skipped (legacy foto#id => duplicates photo with legacyId):');
            $io->listing(array_map(static fn ($k, $v) => "foto#$k == $v", array_keys($dupSkipped), array_values($dupSkipped)));
        }
        if ($orphansExported) {
            $io->warning(sprintf('Orphan files (on disk, no DB row) exported to %s:', $orphansDir));
            $lines = [];
            foreach ($orphansExported as $hid => $files) {
                foreach ($files as $f) {
                    $lines[] = "h$hid: $f";
                }
            }
            $io->listing($lines);
        }
        if ($stale) {
            $io->warning('Stale dirs — photos of highlines deleted from the legacy DB (no line to attach; exported):');
            $lines = [];
            foreach ($stale as $hid => $files) {
                $lines[] = "h$hid: " . implode(', ', $files);
            }
            $io->listing($lines);
        }

        if ($missing || $corrupt) {
            $io->error('Completed WITH problems — see above.');
        } else {
            $io->success(sprintf('%s legacy highline photos with no data loss.', $dryRun ? 'Would import' : 'Imported'));
        }
    }
}
