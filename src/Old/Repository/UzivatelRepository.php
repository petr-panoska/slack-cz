<?php
namespace App\Old\Repository;

use App\Old\Entity\Uzivatel;
use Doctrine\ORM\EntityRepository;

class UzivatelRepository extends EntityRepository
{
    public function findAll(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
