<?php

namespace App\Entity;

use App\Enum\LineEditStatus;
use App\Repository\LineEditRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Unified record for any change made (or proposed) to a Line.
 *
 * Two distinct uses:
 * - APPLIED rows are the audit log (direct edits + approved proposals).
 * - PENDING rows are the admin's review queue.
 *
 * `snapshot` holds the proposed/applied field values as JSON; for APPLIED rows it's
 * the post-change snapshot, for PENDING/REJECTED it's the proposed values not yet on the entity.
 */
#[ORM\Entity(repositoryClass: LineEditRepository::class)]
#[ORM\Table(name: 'line_edit')]
#[ORM\Index(columns: ['status'], name: 'idx_line_edit_status')]
class LineEdit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Line::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Line $line;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $proposedBy = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $snapshot = [];

    /**
     * Snapshot of the line state right before this edit was applied — set at write time
     * and never recomputed. Lets the history view diff each row independently of its neighbours,
     * so deleting a middle row doesn't bleed its changes into the next one.
     *
     * NULL = creation row (no prior state).
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $beforeSnapshot = null;

    #[ORM\Column(length: 16, enumType: LineEditStatus::class)]
    private LineEditStatus $status = LineEditStatus::PENDING;

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

    public function getLine(): Line
    {
        return $this->line;
    }

    public function setLine(Line $line): static
    {
        $this->line = $line;
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

    /** @return array<string, mixed>|null */
    public function getBeforeSnapshot(): ?array
    {
        return $this->beforeSnapshot;
    }

    /** @param array<string, mixed>|null $beforeSnapshot */
    public function setBeforeSnapshot(?array $beforeSnapshot): static
    {
        $this->beforeSnapshot = $beforeSnapshot;
        return $this;
    }

    public function getStatus(): LineEditStatus
    {
        return $this->status;
    }

    public function setStatus(LineEditStatus $status): static
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
