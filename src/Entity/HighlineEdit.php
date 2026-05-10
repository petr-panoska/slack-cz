<?php

namespace App\Entity;

use App\Enum\HighlineEditStatus;
use App\Repository\HighlineEditRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Unified record for any change made (or proposed) to a Highline.
 *
 * Two distinct uses:
 * - APPLIED rows are the audit log (direct edits + approved proposals).
 * - PENDING rows are the admin's review queue.
 *
 * `snapshot` holds the proposed/applied field values as JSON; for APPLIED rows it's
 * the post-change snapshot, for PENDING/REJECTED it's the proposed values not yet on the entity.
 */
#[ORM\Entity(repositoryClass: HighlineEditRepository::class)]
#[ORM\Table(name: 'highline_edit')]
#[ORM\Index(columns: ['status'], name: 'idx_highline_edit_status')]
class HighlineEdit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Highline::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Highline $highline;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $proposedBy = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $snapshot = [];

    #[ORM\Column(length: 16, enumType: HighlineEditStatus::class)]
    private HighlineEditStatus $status = HighlineEditStatus::PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

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

    public function getProposedBy(): ?User
    {
        return $this->proposedBy;
    }

    public function setProposedBy(?User $proposedBy): static
    {
        $this->proposedBy = $proposedBy;
        return $this;
    }

    /** @return array<string, mixed> */
    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    /** @param array<string, mixed> $snapshot */
    public function setSnapshot(array $snapshot): static
    {
        $this->snapshot = $snapshot;
        return $this;
    }

    public function getStatus(): HighlineEditStatus
    {
        return $this->status;
    }

    public function setStatus(HighlineEditStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;
        return $this;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;
        return $this;
    }
}
