<?php

namespace App\Service;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Normalizes an uploaded photo into a web-optimized WebP master and extracts the
 * useful metadata (capture date + GPS) into structured fields.
 *
 * Why: uploads are 99 % from phones (incl. iPhone HEIC, which browsers can't show).
 * We re-encode every upload to one canonical WebP — auto-oriented, capped to display
 * size, metadata stripped — and pull out only date + GPS (the rest, ISO/camera/etc.,
 * is noise). LiipImagine then derives thumb/medium/full from this WebP master.
 *
 * Disk-conscious on purpose (cheap 40 GB VPS): we are not a photo archive, the
 * originals live on people's phones. Tuning knobs below.
 *
 * Needs `magick` (ImageMagick w/ HEIC read + WebP write) and `exiftool` on PATH —
 * see docker/php/Dockerfile.
 */
final class PhotoNormalizer
{
    /** Longest edge of the stored master, px. We never display larger (Liip full = 2400). */
    private const MAX_EDGE = 2560;

    /** WebP quality of the master. 85 ≈ visually transparent; the downscale hides re-encode loss. */
    private const QUALITY = 85;

    /** ImageMagick CLI: `magick` on IM7 (dev/Alpine), `convert` on IM6 (prod/Ubuntu 24.04). */
    private readonly string $magick;

    public function __construct()
    {
        $finder = new ExecutableFinder();
        $this->magick = $finder->find('magick') ?? $finder->find('convert') ?? 'magick';
    }

    /**
     * @throws ProcessFailedException|\RuntimeException if the file can't be processed
     */
    public function normalize(string $sourcePath): NormalizedImage
    {
        if (!is_file($sourcePath)) {
            throw new \RuntimeException('Source file not found: ' . $sourcePath);
        }

        [$takenAt, $gpsLat, $gpsLng] = $this->extractMetadata($sourcePath);

        $out = tempnam(sys_get_temp_dir(), 'photo_') ?: throw new \RuntimeException('Cannot make temp file');

        // [0] = primary frame only (iPhone HEIC can carry aux frames / live-photo stills).
        // `2560x2560>` shrinks only when larger; -auto-orient bakes rotation; -strip drops metadata.
        $convert = new Process([
            $this->magick, $sourcePath . '[0]',
            '-auto-orient',
            '-resize', self::MAX_EDGE . 'x' . self::MAX_EDGE . '>',
            '-quality', (string) self::QUALITY,
            '-strip',
            'webp:' . $out,
        ]);
        $convert->setTimeout(120);
        try {
            $convert->mustRun();
        } catch (\Throwable $e) {
            @unlink($out); // don't leak the empty tempnam file on a failed convert
            throw $e;
        }

        if (!is_file($out) || filesize($out) === 0 || @getimagesize($out) === false) {
            @unlink($out);
            throw new \RuntimeException('Normalization produced no valid image for ' . $sourcePath);
        }

        return new NormalizedImage($out, $takenAt, $gpsLat, $gpsLng);
    }

    /**
     * @return array{0: ?\DateTimeImmutable, 1: ?string, 2: ?string} [takenAt, gpsLat, gpsLng]
     */
    private function extractMetadata(string $sourcePath): array
    {
        $p = new Process([
            'exiftool', '-n', '-j',
            '-DateTimeOriginal', '-GPSLatitude', '-GPSLongitude',
            '-GPSLatitudeRef', '-GPSLongitudeRef',
            $sourcePath,
        ]);
        $p->setTimeout(30);
        try {
            $p->run();
            $json = json_decode($p->getOutput(), true);
        } catch (\Throwable) {
            return [null, null, null];
        }
        $meta = is_array($json) ? ($json[0] ?? []) : [];

        return [
            $this->parseTakenAt($meta['DateTimeOriginal'] ?? null),
            $this->parseCoord($meta['GPSLatitude'] ?? null, $meta['GPSLatitudeRef'] ?? null, 'S'),
            $this->parseCoord($meta['GPSLongitude'] ?? null, $meta['GPSLongitudeRef'] ?? null, 'W'),
        ];
    }

    private function parseTakenAt(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || str_starts_with($value, '0000')) {
            return null;
        }
        // EXIF format "YYYY:MM:DD HH:MM:SS"; tolerate trailing timezone/subsec.
        $dt = \DateTimeImmutable::createFromFormat('Y:m:d H:i:s', substr($value, 0, 19));
        if ($dt === false || (int) $dt->format('Y') < 1990 || $dt > new \DateTimeImmutable('+1 day')) {
            return null;
        }
        return $dt;
    }

    /** exiftool -n gives unsigned magnitude; ref (S/W) flips the sign. */
    private function parseCoord(mixed $value, mixed $ref, string $negativeRef): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }
        $coord = abs((float) $value);
        if ($coord === 0.0) {
            return null;
        }
        if (is_string($ref) && strtoupper($ref) === $negativeRef) {
            $coord = -$coord;
        }
        return number_format($coord, 7, '.', '');
    }
}
