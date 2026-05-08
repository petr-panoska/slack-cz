<?php

namespace App\Repository;

use App\Entity\Highline;
use App\Entity\HighlineCrossing;
use App\Entity\User;
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
     * @return list<HighlineCrossing>
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('h')
            ->join('c.highline', 'h')
            ->andWhere('c.user = :u')->setParameter('u', $user)
            ->orderBy('c.crossedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findFirstCrossingDate(User $user): ?\DateTimeImmutable
    {
        $value = $this->createQueryBuilder('c')
            ->select('MIN(c.crossedAt)')
            ->andWhere('c.user = :u')->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $value instanceof \DateTimeImmutable ? $value : null;
    }

    /**
     * Unique highlines crossed by a user — used to render the deník mini-map.
     *
     * @return list<array{
     *     id:int,
     *     slug:string,
     *     name:string,
     *     latitude:string,
     *     longitude:string,
     *     crossings:int
     * }>
     */
    public function findUserHighlinesForMap(User $user): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select(
                'h.id AS id',
                'h.slug AS slug',
                'h.name AS name',
                'h.latitude AS latitude',
                'h.longitude AS longitude',
                'COUNT(c.id) AS crossings',
            )
            ->join('c.highline', 'h')
            ->andWhere('c.user = :u')->setParameter('u', $user)
            ->groupBy('h.id', 'h.slug', 'h.name', 'h.latitude', 'h.longitude')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'slug' => (string) $r['slug'],
            'name' => (string) $r['name'],
            'latitude' => (string) $r['latitude'],
            'longitude' => (string) $r['longitude'],
            'crossings' => (int) $r['crossings'],
        ], $rows);
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

    /**
     * Recent crossings for the map news-bar feed. Includes the comment when present.
     *
     * @return list<array{
     *     id:int,
     *     crossedAt:string,
     *     rating:?int,
     *     style:?string,
     *     styleLabel:?string,
     *     comment:?string,
     *     highlineSlug:string,
     *     highlineName:string,
     *     userId:int,
     *     userDisplayName:string
     * }>
     */
    public function findRecentForFeed(int $limit = 10): array
    {
        // Dedup by user — the latest crossing per unique user, matching the map's emoji markers.
        $rows = $this->buildFeedQuery()
            ->orderBy('c.crossedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults($limit * 5)
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

        return array_values(array_map([$this, 'mapFeedRow'], $byUser));
    }

    /**
     * Crossings within an inclusive date range — used for the feed when the map is in time-travel mode.
     *
     * @return list<array{
     *     id:int,
     *     crossedAt:string,
     *     rating:?int,
     *     style:?string,
     *     styleLabel:?string,
     *     comment:?string,
     *     highlineSlug:string,
     *     highlineName:string,
     *     userId:int,
     *     userDisplayName:string
     * }>
     */
    public function findForFeedInRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->buildFeedQuery()
            ->andWhere('c.crossedAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('c.crossedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return array_map([$this, 'mapFeedRow'], $rows);
    }

    private function buildFeedQuery(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->select(
                'c.id AS id',
                'c.crossedAt AS crossedAt',
                'c.rating AS rating',
                'c.style AS style',
                'c.comment AS comment',
                'h.slug AS highlineSlug',
                'h.name AS highlineName',
                'u.id AS userId',
                'u.nick AS nick',
                'u.email AS email',
            )
            ->join('c.highline', 'h')
            ->join('c.user', 'u');
    }

    /**
     * @param array<string,mixed> $r
     * @return array{
     *     id:int, crossedAt:string, rating:?int, style:?string, styleLabel:?string,
     *     comment:?string, highlineSlug:string, highlineName:string, userId:int, userDisplayName:string
     * }
     */
    private function mapFeedRow(array $r): array
    {
        $style = $r['style'] instanceof \BackedEnum ? $r['style'] : null;
        $comment = $r['comment'] !== null ? trim((string) $r['comment']) : '';

        return [
            'id' => (int) $r['id'],
            'crossedAt' => $r['crossedAt']->format('Y-m-d'),
            'rating' => $r['rating'] !== null ? (int) $r['rating'] : null,
            'style' => $style?->value,
            'styleLabel' => $style?->label(),
            'comment' => $comment !== '' ? $comment : null,
            'highlineSlug' => (string) $r['highlineSlug'],
            'highlineName' => (string) $r['highlineName'],
            'userId' => (int) $r['userId'],
            'userDisplayName' => $r['nick'] ?? (string) $r['email'],
        ];
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
