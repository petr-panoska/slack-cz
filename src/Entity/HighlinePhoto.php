<?php

namespace App\Entity;

use App\Repository\HighlinePhotoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: HighlinePhotoRepository::class)]
#[ORM\Table(name: 'highline_photo')]
#[ORM\Index(columns: ['highline_id', 'created_at'], name: 'idx_highline_photo_highline_created')]
#[Vich\Uploadable]
class HighlinePhoto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Highline::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Highline $highline;

    /** Author of the upload. NULL after a user is deleted (FK SET NULL). */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    #[ORM\Column(length: 191)]
    private ?string $filename = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $caption = null;

    /** Capture GPS, extracted from EXIF at upload (mostly phone photos). NULL when absent. */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $gpsLat = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $gpsLng = null;

    /** Legacy `highline_foto.id` for gallery photos; NULL for covers and user uploads. */
    #[ORM\Column(nullable: true)]
    private ?int $legacyId = null;

    /**
     * Photo date. New uploads: real upload time (set in constructor). Legacy imports:
     * the line's first-tensioning date, or NULL when that's unknown (legacy stored no
     * per-photo date and the files carry no EXIF capture date).
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt;

    // Raw upload limits — phones send big files; we downscale + re-encode to WebP on
    // upload (see App\Service\PhotoNormalizer), so this only guards the incoming file.
    #[Vich\UploadableField(mapping: 'highline_photo', fileNameProperty: 'filename')]
    #[Assert\NotNull(groups: ['upload'])]
    #[Assert\File(
        maxSize: '30M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'],
        mimeTypesMessage: 'Nahraj prosím JPG, PNG, WebP nebo HEIC.',
        groups: ['upload'],
    )]
    private ?File $file = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHighline(): Highline
    {
        return $this->highline;
    }

    public function setHighline(Highline $highline): static
    {
        $this->highline = $highline;
        return $this;
    }

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?User $uploadedBy): static
    {
        $this->uploadedBy = $uploadedBy;
        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): static
    {
        $this->filename = $filename;
        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): static
    {
        $this->caption = $caption;
        return $this;
    }

    public function getGpsLat(): ?string
    {
        return $this->gpsLat;
    }

    public function getGpsLng(): ?string
    {
        return $this->gpsLng;
    }

    public function setGps(?string $lat, ?string $lng): static
    {
        $this->gpsLat = $lat;
        $this->gpsLng = $lng;
        return $this;
    }

    public function hasGps(): bool
    {
        return $this->gpsLat !== null && $this->gpsLng !== null;
    }

    public function getLegacyId(): ?int
    {
        return $this->legacyId;
    }

    public function setLegacyId(?int $legacyId): static
    {
        $this->legacyId = $legacyId;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): static
    {
        $this->file = $file;
        return $this;
    }

    /** Owner check; legacy uploads without an author are never owned. */
    public function isOwnedBy(?User $user): bool
    {
        if ($user === null || $this->uploadedBy === null) {
            return false;
        }
        return $this->uploadedBy->getId() === $user->getId();
    }
}
