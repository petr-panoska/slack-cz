<?php

namespace App\Repository;

use App\Entity\LineCrossing;
use App\Entity\LonglineCrossing;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * One row per active user for the public diary directory (`/denicky`):
     * nick + name, highline & longline crossing counts, and last activity date
     * (newest of either crossing type). Already sorted for the no-JS render:
     * last activity DESC (users with no activity last), nick A→Z as tiebreak.
     *
     * @return list<array{
     *     id:int,
     *     nick:?string,
     *     firstName:?string,
     *     lastName:?string,
     *     highlineCount:int,
     *     longlineCount:int,
     *     lastActivity:?\DateTimeImmutable
     * }>
     */
    public function findDiaryRows(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select(
                'u.id AS id',
                'u.nick AS nick',
                'u.firstName AS firstName',
                'u.lastName AS lastName',
                'COUNT(DISTINCT hc.id) AS highlineCount',
                'COUNT(DISTINCT lc.id) AS longlineCount',
                'MAX(hc.crossedAt) AS lastLine',
                'MAX(lc.crossedAt) AS lastLongline',
            )
            // Two collection LEFT JOINs fan out the rows — COUNT(DISTINCT) keeps the
            // counts honest, MAX is idempotent over the duplicates.
            ->leftJoin(LineCrossing::class, 'hc', Join::WITH, 'hc.user = u')
            ->leftJoin(LonglineCrossing::class, 'lc', Join::WITH, 'lc.user = u')
            ->where('u.isActive = true')
            ->groupBy('u.id') // u.id is PK → Postgres allows selecting nick/names without grouping them
            ->getQuery()
            ->getArrayResult();

        // Aggregate MAX(crossedAt) may hydrate as string or DateTimeImmutable.
        $toDate = static fn (mixed $v): ?\DateTimeImmutable => $v instanceof \DateTimeImmutable
            ? $v
            : (is_string($v) && $v !== '' ? new \DateTimeImmutable($v) : null);

        $rows = array_map(static function (array $r) use ($toDate): array {
            $last = null;
            foreach ([$toDate($r['lastLine']), $toDate($r['lastLongline'])] as $d) {
                if ($d !== null && ($last === null || $d > $last)) {
                    $last = $d;
                }
            }

            return [
                'id' => (int) $r['id'],
                'nick' => $r['nick'],
                'firstName' => $r['firstName'],
                'lastName' => $r['lastName'],
                'highlineCount' => (int) $r['highlineCount'],
                'longlineCount' => (int) $r['longlineCount'],
                'lastActivity' => $last,
            ];
        }, $rows);

        // Default order for the server render (JS upgrades sorting after load):
        // most recently active first, no-activity users last, nick A→Z as tiebreak.
        usort($rows, static function (array $a, array $b): int {
            if ($a['lastActivity'] != $b['lastActivity']) {
                if ($a['lastActivity'] === null) {
                    return 1;
                }
                if ($b['lastActivity'] === null) {
                    return -1;
                }

                return $b['lastActivity'] <=> $a['lastActivity'];
            }

            return strcoll(
                mb_strtolower($a['nick'] ?? ''),
                mb_strtolower($b['nick'] ?? ''),
            );
        });

        return $rows;
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
