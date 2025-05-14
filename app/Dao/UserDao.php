<?php

namespace Slack\Dao;

use Slack\EnvironmentManager;
use Slack\Models\User;

class UserDao
{
  const ENTITY_NAME = "Slack\Models\User";

  public function find($id)
  {
    return EnvironmentManager::getEntityManager()->getRepository(User::class)->find($id);
  }

  public function findAll()
  {
    return EnvironmentManager::getEntityManager()->getRepository(User::class)->findAll();
  }

  public function findEnabled()
  {
    return EnvironmentManager::getEntityManager()->getRepository(User::class)
        ->findBy(['enabled' => true], ['nick' => 'ASC']);
  }

  public function existUserByNick($nick)
  {
    $em = EnvironmentManager::getEntityManager();
    $user = $em->getRepository(self::ENTITY_NAME)->findOneBy(array("nick" => $nick));
    return $user == null ? false : true;
  }

  /**
   * @param $email
   * @return array|mixed|null
   */
  public function findByEmail($email)
  {
    $users = EnvironmentManager::getEntityManager()->getRepository(self::ENTITY_NAME)->findBy(['email' => $email]);
    if (count($users) > 1) {
      return $users;
    } elseif (count($users) === 1) {
      return $users[0];
    } else {
      return null;
    }
  }

  /**
   * @param $nick
   * @return object|null
   */
  public function findByNick($nick)
  {
    return EnvironmentManager::getEntityManager()->getRepository(self::ENTITY_NAME)->findOneBy(['nick' => $nick]);
  }
}
