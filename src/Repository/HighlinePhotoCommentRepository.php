<?php

namespace App\Repository;

use App\Entity\HighlinePhoto;
use App\Entity\HighlinePhotoComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HighlinePhotoComment>
 */
class HighlinePhotoCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HighlinePhotoComment::class);
    }

    /** @return list<HighlinePhotoComment> */
    public function findForPhoto(HighlinePhoto $photo): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.photo = :p')
            ->setParameter('p', $photo)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $photoIds
     * @return array<int, int> photoId → count
     */
    public function countsForPhotos(array $photoIds): array
    {
        if ($photoIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.photo) AS photoId, COUNT(c.id) AS cnt')
            ->andWhere('c.photo IN (:ids)')
            ->setParameter('ids', $photoIds)
            ->groupBy('c.photo')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['photoId']] = (int) $r['cnt'];
        }
        return $out;
    }
}
