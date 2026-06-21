<?php

namespace App\Repository;

use App\Entity\Line;
use App\Entity\LineEdit;
use App\Enum\LineEditStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LineEdit>
 */
class LineEditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LineEdit::class);
    }

    /**
     * @return list<LineEdit>
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('h', 'u')
            ->join('e.line', 'h')
            ->leftJoin('e.proposedBy', 'u')
            ->andWhere('e.status = :s')->setParameter('s', LineEditStatus::PENDING)
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.status = :s')->setParameter('s', LineEditStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<LineEdit>
     */
    public function findForLine(Line $line): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('u')
            ->leftJoin('e.proposedBy', 'u')
            ->andWhere('e.line = :h')->setParameter('h', $line)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Most recent APPLIED edit for a line, or null if none exists.
     * Used after history deletion to restore the line to whatever snapshot
     * is now considered canonical.
     */
    public function findLatestAppliedFor(Line $line): ?LineEdit
    {
        $row = $this->createQueryBuilder('e')
            ->andWhere('e.line = :h')->setParameter('h', $line)
            ->andWhere('e.status = :applied')->setParameter('applied', LineEditStatus::APPLIED)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $row;
    }

    public function countHistoryFor(Line $line): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.line = :h')->setParameter('h', $line)
            ->andWhere('e.status != :pending')->setParameter('pending', LineEditStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Audit history (excludes PENDING — those live in the admin queue).
     * Eager-loads proposer + reviewer to keep the template simple.
     *
     * @return list<LineEdit>
     */
    public function findHistoryFor(Line $line): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('p', 'r')
            ->leftJoin('e.proposedBy', 'p')
            ->leftJoin('e.reviewedBy', 'r')
            ->andWhere('e.line = :h')->setParameter('h', $line)
            ->andWhere('e.status != :pending')->setParameter('pending', LineEditStatus::PENDING)
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
