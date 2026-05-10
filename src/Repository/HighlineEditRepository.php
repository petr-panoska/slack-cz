<?php

namespace App\Repository;

use App\Entity\Highline;
use App\Entity\HighlineEdit;
use App\Enum\HighlineEditStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HighlineEdit>
 */
class HighlineEditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HighlineEdit::class);
    }

    /**
     * @return list<HighlineEdit>
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('h', 'u')
            ->join('e.highline', 'h')
            ->leftJoin('e.proposedBy', 'u')
            ->andWhere('e.status = :s')->setParameter('s', HighlineEditStatus::PENDING)
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.status = :s')->setParameter('s', HighlineEditStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<HighlineEdit>
     */
    public function findForHighline(Highline $highline): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('u')
            ->leftJoin('e.proposedBy', 'u')
            ->andWhere('e.highline = :h')->setParameter('h', $highline)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Most recent APPLIED edit for a highline, or null if none exists.
     * Used after history deletion to restore the highline to whatever snapshot
     * is now considered canonical.
     */
    public function findLatestAppliedFor(Highline $highline): ?HighlineEdit
    {
        $row = $this->createQueryBuilder('e')
            ->andWhere('e.highline = :h')->setParameter('h', $highline)
            ->andWhere('e.status = :applied')->setParameter('applied', HighlineEditStatus::APPLIED)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $row;
    }

    public function countHistoryFor(Highline $highline): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.highline = :h')->setParameter('h', $highline)
            ->andWhere('e.status != :pending')->setParameter('pending', HighlineEditStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Audit history (excludes PENDING — those live in the admin queue).
     * Eager-loads proposer + reviewer to keep the template simple.
     *
     * @return list<HighlineEdit>
     */
    public function findHistoryFor(Highline $highline): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('p', 'r')
            ->leftJoin('e.proposedBy', 'p')
            ->leftJoin('e.reviewedBy', 'r')
            ->andWhere('e.highline = :h')->setParameter('h', $highline)
            ->andWhere('e.status != :pending')->setParameter('pending', HighlineEditStatus::PENDING)
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
