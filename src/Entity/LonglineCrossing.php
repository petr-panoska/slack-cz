<?php

namespace App\Entity;

use App\Enum\CrossingStyle;
use App\Repository\LonglineCrossingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A longline entry in a user's deník. Unlike LineCrossing these are not tied
 * to a Line (longlines have no anchor/GPS data) — the place is just free
 * text. Mirrors the legacy `longline` table (uzivatel/delka/misto/styl/datum).
 */
#[ORM\Entity(repositoryClass: LonglineCrossingRepository::class)]
#[ORM\Table(name: 'longline_crossing')]
#[ORM\Index(columns: ['crossed_at'], name: 'idx_longline_crossed_at')]
class LonglineCrossing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $crossedAt;

    #[ORM\Column]
    #[Assert\Positive]
    private int $length;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $place;

    // Shared with LineCrossing — the longline form only offers the styles
    // where CrossingStyle::appliesToLongline() is true (no leash styles).
    #[ORM\Column(length: 20, nullable: true, enumType: CrossingStyle::class)]
    private ?CrossingStyle $style = null;

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

    public function getLength(): int
    {
        return $this->length;
    }

    public function setLength(int $length): static
    {
        $this->length = $length;
        return $this;
    }

    public function getPlace(): string
    {
        return $this->place;
    }

    public function setPlace(string $place): static
    {
        $this->place = $place;
        return $this;
    }

    public function getStyle(): ?CrossingStyle
    {
        return $this->style;
    }

    public function setStyle(?CrossingStyle $style): static
    {
        $this->style = $style;
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
