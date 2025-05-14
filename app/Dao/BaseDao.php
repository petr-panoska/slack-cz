<?php

namespace Slack\Dao;

use Exception;
use Slack\EnvironmentManager;

/**
 * Description of HighlinesDao
 *
 * @author Vejvis
 */
class BaseDao
{
  const SORT_ASC = "ASC";
  const SORT_DESC = "DESC";

  public static function search($entityName, $attributs, $term, $sortColumn = null, $dir = null)
  {
    if (!is_array($attributs)) {
      return null;
    }

    /*
     * Create search sql
     */
    $columns = array();
    foreach ($attributs as $attribut) {
      $columns[] = "e." . $attribut . " LIKE :term";
    }
    $sql = "SELECT e FROM " . $entityName . " e WHERE " . implode(" OR ", $columns);
    if ($sortColumn != null && $dir != null) {
      $sql .= " ORDER BY e." . $sortColumn . " " . $dir;
    }

    return self::findByQuery($sql, array("term" => $term));
  }

  public static function findByQuery($dql, $parameters = array())
  {
    return EnvironmentManager::getEntityManager()->createQuery($dql)->setParameters($parameters)->getResult();
  }

  public static function persist($entity)
  {
    $em = EnvironmentManager::getEntityManager();
    $em->getConnection()->beginTransaction();
    try {
      $em->persist($entity);
      $em->flush();
      $em->getConnection()->commit();
    } catch (Exception $e) {
      $em->getConnection()->rollback();
      throw $e;
    }
  }

  protected function find($entityName, $id)
  {
    return EnvironmentManager::getEntityManager()->find($entityName, $id);
  }

  public static function findAll($entityName)
  {
    return EnvironmentManager::getEntityManager()->getRepository($entityName)->findAll();
  }

  public static function remove($entityName, $id)
  {
    $object = self::find($entityName, $id);
    $em = EnvironmentManager::getEntityManager();
    $em->remove($object);
    $em->flush();
  }

  public function update($object)
  {
    $em = EnvironmentManager::getEntityManager();
    $em->merge($object);
    $em->flush();
  }
}
