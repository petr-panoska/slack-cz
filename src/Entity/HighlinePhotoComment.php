<?php

namespace App\Entity;

use App\Repository\HighlinePhotoCommentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HighlinePhotoCommentRepository::class)]
#[ORM\Table(name: 'highline_photo_comment')]
#[ORM\Index(columns: ['photo_id', 'created_at'], name: 'idx_highline_photo_comment_photo_created')]
class HighlinePhotoComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HighlinePhoto::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private HighlinePhoto $photo;

    /** Author. NULL after user delete (FK SET NULL). */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Komentář nesmí být prázdný.')]
    #[Assert\Length(max: 2000, maxMessage: 'Komentář může mít maximálně 2000 znaků.')]
    private string $text = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhoto(): HighlinePhoto
    {
        return $this->photo;
    }

    public function setPhoto(HighlinePhoto $photo): static
    {
        $this->photo = $photo;
        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isOwnedBy(?User $user): bool
    {
        if ($user === null || $this->author === null) {
            return false;
        }
        return $this->author->getId() === $user->getId();
    }
}
