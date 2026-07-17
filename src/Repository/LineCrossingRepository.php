<?php

namespace App\Repository;

use App\Entity\Line;
use App\Entity\LineCrossing;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LineCrossing>
 */
class LineCrossingRepository extends ServiceEntityRepository
{
    /** Single source of truth for "how many recent crossings show up everywhere" (homepage list, map emoji markers, sidebar feed default). */
    public const RECENT_LIMIT = 10;

    /** Ceiling for the user-adjustable sidebar feed "last N" filter. */
    public const FEED_LIMIT_MAX = 200;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LineCrossing::class);
    }

    /**
     * @return list<LineCrossing>
     */
    public function findRecent(?int $limit = null): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('h', 'u')
            ->join('c.line', 'h')
            ->join('c.user', 'u')
            ->orderBy('c.crossedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults($limit ?? self::RECENT_LIMIT)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<LineCrossing>
     */
    public function findForLine(Line $line): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('u')
            ->join('c.user', 'u')
            ->andWhere('c.line = :h')->setParameter('h', $line)
            ->orderBy('c.crossedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<LineCrossing>
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('h')
            ->join('c.line', 'h')
            ->andWhere('c.user = :u')->setParameter('u', $user)
            ->orderBy('c.crossedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Unique lines crossed by a user — used to render the deník mini-map.
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
    public function findUserLinesForMap(User $user): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select(
                'h.id AS id',
                'h.slug AS slug',
                'h.name AS name',
                'h.point1Latitude AS latitude',
                'h.point1Longitude AS longitude',
                'COUNT(c.id) AS crossings',
            )
            ->join('c.line', 'h')
            ->andWhere('c.user = :u')->setParameter('u', $user)
            ->groupBy('h.id', 'h.slug', 'h.name', 'h.point1Latitude', 'h.point1Longitude')
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
     * All crossings, sorted chronologically — for the time-travel playback.
     * Lightweight payload: just what the JS needs to fire pulse animations + popup.
     *
     * @return list<array{
     *     lineId:int,
     *     userId:int,
     *     userDisplayName:string,
     *     userEmoji:?string,
     *     crossedAt:string,
     *     rating:?int,
     *     style:?string
     * }>
     */
    public function findAllForTimeline(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select(
                'IDENTITY(c.line) AS lineId',
                'IDENTITY(c.user) AS userId',
                'c.crossedAt AS crossedAt',
                'c.rating AS rating',
                'c.style AS style',
                'u.nick AS nick',
                'u.email AS email',
                'u.emoji AS emoji',
            )
            ->join('c.user', 'u')
            ->orderBy('c.crossedAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $r): array {
            return [
                'lineId' => (int) $r['lineId'],
                'userId' => (int) $r['userId'],
                'userDisplayName' => $r['nick'] ?? (string) $r['email'],
                'userEmoji' => $r['emoji'] !== null ? (string) $r['emoji'] : null,
                'crossedAt' => $r['crossedAt']->format('Y-m-d'),
                'rating' => $r['rating'] !== null ? (int) $r['rating'] : null,
                'style' => $r['style'] instanceof \BackedEnum ? $r['style']->value : ($r['style'] ?? null),
            ];
        }, $rows);
    }

    /**
     * Recent crossings as a JSON-friendly array shape.
     * Used by both the map (emoji markers — needs lat/lng) and the sidebar feed (needs comment, style).
     * No dedup: same crossings as the homepage list, matching one-to-one with the homepage's `findRecent`.
     *
     * @return list<array{
     *     id:int,
     *     crossedAt:string,
     *     rating:?int,
     *     style:?string,
     *     styleLabel:?string,
     *     comment:?string,
     *     lineSlug:string,
     *     lineName:string,
     *     latitude:string,
     *     longitude:string,
     *     userId:int,
     *     userDisplayName:string,
     *     userEmoji:?string
     * }>
     */
    public function findRecentForJson(?int $limit = null): array
    {
        $rows = $this->buildFeedQuery()
            ->orderBy('c.crossedAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults($limit ?? self::RECENT_LIMIT)
            ->getQuery()
            ->getArrayResult();

        return array_map([$this, 'mapFeedRow'], $rows);
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
     *     lineSlug:string,
     *     lineName:string,
     *     latitude:string,
     *     longitude:string,
     *     userId:int,
     *     userDisplayName:string,
     *     userEmoji:?string
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
                'h.slug AS lineSlug',
                'h.name AS lineName',
                'h.point1Latitude AS latitude',
                'h.point1Longitude AS longitude',
                'u.id AS userId',
                'u.nick AS nick',
                'u.email AS email',
                'u.emoji AS emoji',
            )
            ->join('c.line', 'h')
            ->join('c.user', 'u');
    }

    /**
     * @param array<string,mixed> $r
     * @return array{
     *     id:int, crossedAt:string, rating:?int, style:?string, styleLabel:?string,
     *     comment:?string, lineSlug:string, lineName:string,
     *     latitude:string, longitude:string, userId:int, userDisplayName:string, userEmoji:?string
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
            'lineSlug' => (string) $r['lineSlug'],
            'lineName' => (string) $r['lineName'],
            'latitude' => (string) $r['latitude'],
            'longitude' => (string) $r['longitude'],
            'userId' => (int) $r['userId'],
            'userDisplayName' => $r['nick'] ?? (string) $r['email'],
            'userEmoji' => $r['emoji'] !== null ? (string) $r['emoji'] : null,
        ];
    }
}
