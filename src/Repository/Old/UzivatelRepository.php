<?php
namespace App\Repository\Old;

use App\Entity\Old\Uzivatel;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
