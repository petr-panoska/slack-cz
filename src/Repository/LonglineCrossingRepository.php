<?php

namespace App\Repository;

use App\Entity\LonglineCrossing;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LonglineCrossing>
 */
class LonglineCrossingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LonglineCrossing::class);
    }

    /**
     * @return list<LonglineCrossing>
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :u')->setParameter('u', $user)
            ->orderBy('c.crossedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
