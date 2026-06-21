<?php

namespace App\Repository;

use App\Entity\LinePhoto;
use App\Entity\LinePhotoLike;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LinePhotoLike>
 */
class LinePhotoLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LinePhotoLike::class);
    }

    public function findOneByPhotoAndUser(LinePhoto $photo, User $user): ?LinePhotoLike
    {
        return $this->findOneBy(['photo' => $photo, 'user' => $user]);
    }

    /**
     * Counts likes for many photos in one query.
     *
     * @param list<int> $photoIds
     * @return array<int, int> photoId → count
     */
    public function countsForPhotos(array $photoIds): array
    {
        if ($photoIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.photo) AS photoId, COUNT(l.id) AS cnt')
            ->andWhere('l.photo IN (:ids)')
            ->setParameter('ids', $photoIds)
            ->groupBy('l.photo')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['photoId']] = (int) $r['cnt'];
        }
        return $out;
    }

    /**
     * For a given user, returns the photo IDs they have liked among the given set.
     *
     * @param list<int> $photoIds
     * @return list<int>
     */
    public function likedPhotoIdsForUser(?User $user, array $photoIds): array
    {
        if ($user === null || $photoIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.photo) AS photoId')
            ->andWhere('l.user = :u')
            ->andWhere('l.photo IN (:ids)')
            ->setParameter('u', $user)
            ->setParameter('ids', $photoIds)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r): int => (int) $r['photoId'], $rows);
    }
}
