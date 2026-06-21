<?php

namespace App\Entity;

use App\Repository\LinePhotoLikeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LinePhotoLikeRepository::class)]
#[ORM\Table(name: 'line_photo_like')]
#[ORM\UniqueConstraint(name: 'uniq_line_photo_like_photo_user', columns: ['photo_id', 'user_id'])]
class LinePhotoLike
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LinePhoto::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LinePhoto $photo;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(LinePhoto $photo, User $user)
    {
        $this->photo = $photo;
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhoto(): LinePhoto
    {
        return $this->photo;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
