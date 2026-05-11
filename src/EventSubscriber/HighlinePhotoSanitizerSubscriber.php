<?php

namespace App\EventSubscriber;

use App\Entity\HighlinePhoto;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;

/**
 * Re-encodes the uploaded original through GD to strip EXIF (privacy: GPS leak)
 * and apply the EXIF orientation flag so the file on disk is canonical.
 *
 * Runs only for `HighlinePhoto` uploads; other Vich mappings are left untouched.
 */
final class HighlinePhotoSanitizerSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [Events::POST_UPLOAD => 'onPostUpload'];
    }

    public function onPostUpload(Event $event): void
    {
        $entity = $event->getObject();
        if (!$entity instanceof HighlinePhoto) {
            return;
        }

        $mapping = $event->getMapping();
        $path = $mapping->getUploadDestination() . '/'
            . ($mapping->getUploadDir($entity) !== '' ? $mapping->getUploadDir($entity) . '/' : '')
            . $entity->getFilename();

        if (!is_file($path)) {
            return;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return;
        }

        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };
        if ($img === false) {
            return;
        }

        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            if (is_array($exif) && isset($exif['Orientation'])) {
                $img = $this->rotateByOrientation($img, (int) $exif['Orientation']);
            }
        }

        match ($info[2]) {
            IMAGETYPE_JPEG => imagejpeg($img, $path, 88),
            IMAGETYPE_PNG => imagepng($img, $path, 6),
            IMAGETYPE_WEBP => imagewebp($img, $path, 88),
            default => null,
        };
        imagedestroy($img);
    }

    /** @return \GdImage */
    private function rotateByOrientation(\GdImage $img, int $orientation): \GdImage
    {
        $rotated = match ($orientation) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => null,
        };
        if ($rotated === null || $rotated === false) {
            return $img;
        }
        imagedestroy($img);
        return $rotated;
    }
}
