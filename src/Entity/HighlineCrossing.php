<?php

namespace App\Entity;

use App\Enum\HighlineCrossingStyle;
use App\Repository\HighlineCrossingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HighlineCrossingRepository::class)]
#[ORM\Table(name: 'highline_crossing')]
#[ORM\Index(columns: ['crossed_at'], name: 'idx_crossing_crossed_at')]
class HighlineCrossing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Highline::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Highline $highline;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $crossedAt;

    #[ORM\Column(length: 20, nullable: true, enumType: HighlineCrossingStyle::class)]
    private ?HighlineCrossingStyle $style = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $rating = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(nullable: true)]
    private ?int $legacyId = null;

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

    public function getHighline(): Highline
    {
        return $this->highline;
    }

    public function setHighline(Highline $highline): static
    {
        $this->highline = $highline;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getCrossedAt(): \DateTimeImmutable
    {
        return $this->crossedAt;
    }

    public function setCrossedAt(\DateTimeImmutable $crossedAt): static
    {
        $this->crossedAt = $crossedAt;
        return $this;
    }

    public function getStyle(): ?HighlineCrossingStyle
    {
        return $this->style;
    }

    public function setStyle(?HighlineCrossingStyle $style): static
    {
        $this->style = $style;
        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): static
    {
        $this->rating = $rating;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
        return $this;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
