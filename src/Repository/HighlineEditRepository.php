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
}
