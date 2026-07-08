<?php

namespace App\Repository;

use App\Entity\Line;
use App\Entity\LinePhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LinePhoto>
 */
class LinePhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LinePhoto::class);
    }

    /** @return list<LinePhoto> */
    public function findForLine(Line $line): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.line = :h')
            ->setParameter('h', $line)
            // Newest first; undated legacy photos (NULL createdAt) sort last, then by id.
            ->addSelect('CASE WHEN p.createdAt IS NULL THEN 1 ELSE 0 END AS HIDDEN createdNull')
            ->orderBy('createdNull', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Gallery chronicle: every photo, newest year first; undated last. Line is
     * fetch-joined — the grid prints its name/slug for each photo.
     * @return list<LinePhoto>
     */
    public function findForGallery(): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('l')
            ->join('p.line', 'l')
            ->addSelect('CASE WHEN p.createdAt IS NULL THEN 1 ELSE 0 END AS HIDDEN createdNull')
            ->orderBy('createdNull', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Homepage teaser: the N most recently added photos, newest first.
     * Undated legacy photos (NULL createdAt) sort last, same as findForGallery.
     * @return list<LinePhoto>
     */
    public function findRecentForHomepage(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('CASE WHEN p.createdAt IS NULL THEN 1 ELSE 0 END AS HIDDEN createdNull')
            ->orderBy('createdNull', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
