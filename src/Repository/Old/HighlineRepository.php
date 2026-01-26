<?php

namespace App\Repository\Old;

use App\Entity\Old\Highline;
use Doctrine\ORM\EntityRepository;

class HighlineRepository extends EntityRepository
{
    public function findAll(): array
    {
        return $this->createQueryBuilder('h')
            ->orderBy('h.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
