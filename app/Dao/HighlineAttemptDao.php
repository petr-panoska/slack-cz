<?php

namespace Slack\Dao;

use Slack\EnvironmentManager;
use Slack\Models\HighlineAttempt;

class HighlineAttemptDao
{
  public function findAllForUser($userId)
  {
    if (is_null($userId)) {
      return [];
    }
    return EnvironmentManager::getEntityManager()->getRepository(HighlineAttempt::class)->createQueryBuilder('ha')
        ->where('ha.uzivatel = :userId')->setParameter('userId', $userId)
        ->getQuery()->getResult();
  }
}