<?php

namespace App\Repository;

use App\Entity\Highline;
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
     * @return list<HighlineCrossing>
     */
    public function findForHighline(Highline $highline): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('u')
            ->join('c.user', 'u')
            ->andWhere('c.highline = :h')->setParameter('h', $highline)
            ->orderBy('c.crossedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Most-recent crossing per unique user (top N users by recency).
     * One row per user — even if user A has 5 recent crossings, only the latest one shows.
     *
     * @return list<array{
     *     id:int,
     *     userId:int,
     *     highlineId:int,
     *     highlineName:string,
     *     latitude:string,
     *     longitude:string,
     *     userDisplayName:string,
     *     crossedAt:string,
     *     rating:?int
     * }>
     */
    /**
     * All crossings, sorted chronologically — for the time-travel playback.
     * Lightweight payload: just what the JS needs to fire pulse animations + popup.
     *
     * @return list<array{
     *     highlineId:int,
     *     userId:int,
     *     userDisplayName:string,
     *     crossedAt:string,
     *     rating:?int,
     *     style:?string
     * }>
     */
    public function findAllForTimeline(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select(
                'IDENTITY(c.highline) AS highlineId',
                'IDENTITY(c.user) AS userId',
                'c.crossedAt AS crossedAt',
                'c.rating AS rating',
                'c.style AS style',
                'u.nick AS nick',
                'u.email AS email',
            )
            ->join('c.user', 'u')
            ->orderBy('c.crossedAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $r): array {
            return [
                'highlineId' => (int) $r['highlineId'],
                'userId' => (int) $r['userId'],
                'userDisplayName' => $r['nick'] ?? (string) $r['email'],
                'crossedAt' => $r['crossedAt']->format('Y-m-d'),
                'rating' => $r['rating'] !== null ? (int) $r['rating'] : null,
                'style' => $r['style'] instanceof \BackedEnum ? $r['style']->value : ($r['style'] ?? null),
            ];
        }, $rows);
    }

    public function findRecentUsersForMap(int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select(
                'c.id AS id',
                'c.crossedAt AS crossedAt',
                'c.rating AS rating',
                'h.id AS highlineId',
                'h.name AS highlineName',
                'h.slug AS highlineSlug',
                'h.latitude AS latitude',
                'h.longitude AS longitude',
                'u.id AS userId',
                'u.nick AS nick',
                'u.email AS email',
            )
            ->join('c.highline', 'h')
            ->join('c.user', 'u')
            ->orderBy('c.crossedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults($limit * 5) // fetch extra rows so we have enough after dedup
            ->getQuery()
            ->getArrayResult();

        $byUser = [];
        foreach ($rows as $r) {
            $uid = (int) $r['userId'];
            if (!isset($byUser[$uid])) {
                $byUser[$uid] = $r;
                if (count($byUser) >= $limit) break;
            }
        }

        return array_values(array_map(static function (array $r): array {
            return [
                'id' => (int) $r['id'],
                'userId' => (int) $r['userId'],
                'highlineId' => (int) $r['highlineId'],
                'highlineName' => $r['highlineName'],
                'highlineSlug' => $r['highlineSlug'],
                'latitude' => $r['latitude'],
                'longitude' => $r['longitude'],
                'userDisplayName' => $r['nick'] ?? (string) $r['email'],
                'crossedAt' => $r['crossedAt']->format('Y-m-d'),
                'rating' => $r['rating'] !== null ? (int) $r['rating'] : null,
            ];
        }, $byUser));
    }
}
