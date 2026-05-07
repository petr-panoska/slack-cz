<?php

namespace App\Repository;

use App\Entity\HighlineCrossing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HighlineCrossing>
 */
class HighlineCrossingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HighlineCrossing::class);
    }

    /**
     * @return list<HighlineCrossing>
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('h', 'u')
            ->join('c.highline', 'h')
            ->join('c.user', 'u')
            ->orderBy('c.crossedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
