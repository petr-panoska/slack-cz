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

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[Vich\UploadableField(mapping: 'highline_photo', fileNameProperty: 'filename')]
    #[Assert\NotNull(groups: ['upload'])]
    #[Assert\File(
        maxSize: '4M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'Nahraj prosím JPG, PNG nebo WebP.',
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
