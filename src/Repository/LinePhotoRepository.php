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
     * Homepage rotation: random N from the recent window; top up with all-time most-liked if short.
     * @return list<LinePhoto>
     */
    public function findRecentForHomepage(int $limit = 6, int $recentDays = 7): array
    {
        $since = new \DateTimeImmutable("-{$recentDays} days");

        $recentIds = array_map(
            static fn (array $r): int => (int) $r['id'],
            $this->getEntityManager()->getConnection()->fetchAllAssociative(
                'SELECT id FROM line_photo WHERE created_at >= :since ORDER BY RANDOM() LIMIT :lim',
                ['since' => $since->format('Y-m-d H:i:s'), 'lim' => $limit],
                ['since' => \PDO::PARAM_STR, 'lim' => \PDO::PARAM_INT],
            )
        );

        $remaining = $limit - count($recentIds);
        $fillIds = [];
        if ($remaining > 0) {
            $sql = <<<SQL
SELECT p.id
FROM line_photo p
LEFT JOIN line_photo_like l ON l.photo_id = p.id
WHERE (:exclude::int[] IS NULL OR NOT (p.id = ANY(:exclude::int[])))
GROUP BY p.id
ORDER BY COUNT(l.id) DESC, p.created_at DESC
LIMIT :lim
SQL;
            $excludeStr = $recentIds === []
                ? null
                : '{' . implode(',', $recentIds) . '}';
            $fillIds = array_map(
                static fn (array $r): int => (int) $r['id'],
                $this->getEntityManager()->getConnection()->fetchAllAssociative(
                    $sql,
                    ['exclude' => $excludeStr, 'lim' => $remaining],
                    ['exclude' => \PDO::PARAM_STR, 'lim' => \PDO::PARAM_INT],
                )
            );
        }

        $ids = array_merge($recentIds, $fillIds);
        if ($ids === []) {
            return [];
        }

        $photos = $this->createQueryBuilder('p')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($photos as $p) {
            $byId[$p->getId()] = $p;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        return $ordered;
    }
}
