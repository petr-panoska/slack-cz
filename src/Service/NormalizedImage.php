<?php

namespace App\Service;

/**
 * Result of {@see PhotoNormalizer::normalize()}: a web-optimized WebP file on disk
 * plus the only metadata worth keeping (capture date + GPS), extracted into fields
 * so the stored file can be stripped clean.
 */
final readonly class NormalizedImage
{
    public function __construct(
        /** Absolute path to the normalized WebP temp file (caller hands it to Vich, which moves it). */
        public string $path,
        public ?\DateTimeImmutable $takenAt,
        public ?string $gpsLat,
        public ?string $gpsLng,
        /** Pixel size of the normalized master (post-resize, post-rotate). */
        public int $width,
        public int $height,
    ) {
    }
}
