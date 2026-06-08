<?php

namespace App\Repository;

use App\Entity\Highline;
use App\Entity\HighlineCrossing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Highline>
 */
class HighlineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Highline::class);
    }

    /**
     * Highlines with a first anchor but no second one (point2 null) — these can't draw
     * the line on the map. Triage list for back-filling the missing endpoint, sorted by
     * crossing count DESC (most-crossed = most worth fixing first).
     *
     * @return list<array{highline:Highline, crossingCount:int}>
     */
    public function findMissingSecondPoint(): array
    {
        $rows = $this->createQueryBuilder('h')
            ->select('h AS highline', 'COUNT(c.id) AS crossingCount')
            ->leftJoin(HighlineCrossing::class, 'c', Join::WITH, 'c.highline = h')
            ->where('h.point2Latitude IS NULL OR h.point2Longitude IS NULL')
            ->groupBy('h.id')
            ->orderBy('crossingCount', 'DESC')
            ->addOrderBy('h.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $r): array => [
            'highline' => $r['highline'],
            'crossingCount' => (int) $r['crossingCount'],
        ], $rows);
    }

    /**
     * Returns lightweight rows for the map (no description/HTML payload).
     *
     * @return list<array{id:int,name:string,slug:string,type:string,length:int,height:int,latitude:string,longitude:string,area:?string,region:?string}>
     */
    public function findAllForMap(): array
    {
        return $this->createQueryBuilder('h')
            ->select('h.id, h.name, h.slug, h.type, h.length, h.height, h.latitude, h.longitude, h.area, h.region')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * For the time-travel feature: each highline with the date it should appear on the map
     * (= firstAscentDate, falling back to the earliest known crossing date).
     * Highlines with no first-ascent and no crossings are excluded — nothing to anchor them in time.
     *
     * @return list<array{id:int,name:string,slug:string,type:string,length:int,height:int,latitude:string,longitude:string,area:?string,region:?string,appearanceDate:string}>
     */
    public function findAllForTimeline(): array
    {
        $sql = <<<'SQL'
            SELECT
                h.id,
                h.name,
                h.slug,
                h.type,
                h.length,
                h.height,
                h.latitude::text  AS latitude,
                h.longitude::text AS longitude,
                h.area,
                h.region,
                COALESCE(h.first_ascent_date, MIN(c.crossed_at))::text AS appearance_date
            FROM highline h
            LEFT JOIN highline_crossing c ON c.highline_id = h.id
            GROUP BY h.id
            HAVING COALESCE(h.first_ascent_date, MIN(c.crossed_at)) IS NOT NULL
            ORDER BY appearance_date ASC, h.id ASC
        SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql)->fetchAllAssociative();

        return array_map(static fn(array $r): array => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'slug' => $r['slug'],
            'type' => $r['type'],
            'length' => (int) $r['length'],
            'height' => (int) $r['height'],
            'latitude' => $r['latitude'],
            'longitude' => $r['longitude'],
            'area' => $r['area'],
            'region' => $r['region'],
            'appearanceDate' => substr((string) $r['appearance_date'], 0, 10),
        ], $rows);
    }
}
