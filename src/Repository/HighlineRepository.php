<?php

namespace App\Repository;

use App\Entity\Highline;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Highline>
 */
class HighlineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Highline::class);
    }

    /**
     * Returns lightweight rows for the map (no description/HTML payload).
     *
     * @return list<array{id:int,name:string,type:string,length:int,height:int,latitude:string,longitude:string,area:?string,region:?string}>
     */
    public function findAllForMap(): array
    {
        return $this->createQueryBuilder('h')
            ->select('h.id, h.name, h.type, h.length, h.height, h.latitude, h.longitude, h.area, h.region')
            ->getQuery()
            ->getArrayResult();
    }
}
