<?php
namespace App\Repository\Old;

use App\Entity\Old\Gps;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GpsRepository extends EntityRepository
{
    public function findAll(): array
    {
        return $this->createQueryBuilder('g')
            ->orderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
