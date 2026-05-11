<?php

namespace App\Entity;

use App\Repository\HighlinePhotoLikeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HighlinePhotoLikeRepository::class)]
#[ORM\Table(name: 'highline_photo_like')]
#[ORM\UniqueConstraint(name: 'uniq_highline_photo_like_photo_user', columns: ['photo_id', 'user_id'])]
class HighlinePhotoLike
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HighlinePhoto::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private HighlinePhoto $photo;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(HighlinePhoto $photo, User $user)
    {
        $this->photo = $photo;
        $this->user = $user;
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
