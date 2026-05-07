<?php

namespace App\Repository;

use App\Entity\HighlineCrossing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HighlineCrossing>
 */
class HighlineCrossingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HighlineCrossing::class);
    }

    /**
     * @return list<HighlineCrossing>
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('h', 'u')
            ->join('c.highline', 'h')
            ->join('c.user', 'u')
            ->orderBy('c.crossedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Lightweight rows for the map overlay: highline coords + user display + rating.
     *
     * @return list<array{
     *     id:int,
     *     highlineId:int,
     *     highlineName:string,
     *     latitude:string,
     *     longitude:string,
     *     userDisplayName:string,
     *     crossedAt:string,
     *     rating:?int
     * }>
     */
    public function findRecentForMap(int $limit = 5): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select(
                'c.id AS id',
                'c.crossedAt AS crossedAt',
                'c.rating AS rating',
                'h.id AS highlineId',
                'h.name AS highlineName',
                'h.latitude AS latitude',
                'h.longitude AS longitude',
                'u.nick AS nick',
                'u.email AS email',
            )
            ->join('c.highline', 'h')
            ->join('c.user', 'u')
            ->orderBy('c.crossedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $r): array {
            return [
                'id' => (int) $r['id'],
                'highlineId' => (int) $r['highlineId'],
                'highlineName' => $r['highlineName'],
                'latitude' => $r['latitude'],
                'longitude' => $r['longitude'],
                'userDisplayName' => $r['nick'] ?? (string) $r['email'],
                'crossedAt' => $r['crossedAt']->format('Y-m-d'),
                'rating' => $r['rating'] !== null ? (int) $r['rating'] : null,
            ];
        }, $rows);
    }
}
