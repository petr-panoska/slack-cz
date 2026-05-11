<?php

namespace App\Repository;

use App\Entity\Highline;
use App\Entity\HighlinePhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HighlinePhoto>
 */
class HighlinePhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HighlinePhoto::class);
    }

    /** @return list<HighlinePhoto> */
    public function findForHighline(Highline $highline): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.highline = :h')
            ->setParameter('h', $highline)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
