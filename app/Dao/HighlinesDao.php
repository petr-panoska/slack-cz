<?php

namespace Slack\Dao;

use Doctrine\ORM\AbstractQuery;
use Slack\EnvironmentManager;
use Slack\Models\Highline;
use Slack\Models\LatLng;

/**
 * Description of HighlinesDao
 *
 * @author Vejvis
 */
class HighlinesDao extends BaseDao
{

  const ENTITY_NAME = "Slack\Models\Highline";

  public function findAllForUser($userId)
  {
    if (is_null($userId)) {
      return [];
    }
    $em = EnvironmentManager::getEntityManager()->getRepository(Highline::class);
    return $em->findBy(['uzivatel' => $userId]);
  }

  public function findAllWithGps()
  {
    $qb = EnvironmentManager::getEntityManager()->getRepository(\Slack\Models\Highline::class)->createQueryBuilder('h')
        ->join('h.point1', 'point1')
        ->select('h, point1');
    return $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);
  }

  /**
   *
   * @param int $id
   * @param string $entityName
   * @return Highline
   */
  public function find($id, $entityName = self::ENTITY_NAME)
  {
    return parent::find($entityName, $id);
  }

  public static function findByBounds(LatLng $northEast, LatLng $southWest)
  {
    $em = EnvironmentManager::getEntityManager();

    $parameters = array("northEastLat" => $northEast->getLat(),
        "northEastLng" => $northEast->getLng(),
        "southWestLat" => $southWest->getLat(),
        "southWestLng" => $southWest->getLng());
    $dql = "SELECT highline FROM " . self::ENTITY_NAME . " highline JOIN highline.point1 gps WHERE";
    $dql .= " gps.lat >= :southWestLat AND gps.lat <= :northEastLat AND gps.lng >= :southWestLng AND gps.lng <= :northEastLng ORDER BY highline.delka ASC";
    return $em->createQuery($dql)
        ->setParameters($parameters)
        ->getResult();
  }
}
