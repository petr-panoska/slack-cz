<?php

namespace Slack;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Nette\Environment;
use Nette\Object;
use Slack\Dao\UserDao;
use Slack\Models\Components\Collection\Collection;
use Slack\Models\User;

/**
 * Description of EnvironmentManager
 *
 * @author Vejvis
 */
class EnvironmentManager extends Object
{
  private $userDao;

  private static $entityManager;

  public function __construct(UserDao $dao)
  {
    $this->userDao = $dao;
  }

  /**
   * @return EntityManager Description
   */
  public static function getEntityManager()
  {
    if (self::$entityManager == null) {
      // do produkce lepší APC cache
      $cache = new ArrayCache;


      $config = new Configuration;
      $config->setMetadataCacheImpl($cache);
      $driverImpl = $config->newDefaultAnnotationDriver(APP_DIR . DS . 'models');
      $config->setMetadataDriverImpl($driverImpl);
      $config->setQueryCacheImpl($cache);
      $config->setProxyDir(WWW_DIR . DS . 'temp' . DS . 'Proxies');
      $config->setProxyNamespace('Slack\Proxies');
      $config->setAutoGenerateProxyClasses(true);

      //todo
      $dbParams = array(
          'driver' => 'pdo_mysql',
          'host' => 'localhost',
          'user' => 'slackcz',
          'password' => 'Kunanesenanuk1',
          'dbname' => 'slackcz',
          'charset' => 'utf8',
          'driverOptions' => array(
              1002 => 'SET NAMES utf8'
          )
      );

      self::$entityManager = EntityManager::create($dbParams, $config);
    }
    return self::$entityManager;
  }

  public function getLoggedUser()
  {
    $user = Environment::getUser();
    if ($user->isLoggedIn()) {
      return $this->userDao->find($user->getId());
    }
    return null;
  }

  /**
   *
   * Gets the http request variable - from POST or GET method
   * (POST has higher priority if both of them are set)
   *
   * @param string $name Name of the variable
   * @param mixed $defaultValue The default value being returned if matchRule check fails or if variable is not set
   * @param string $matchRule Matching rule can be [ "word" | regex ]
   * @return mixed The value of environment variable with given name
   */
  static
  function getRequestVar($name, $defaultValue = null, $matchRule = null)
  {
    $regexp = self::parseMatchRule($matchRule);

    $var = null;
    if (isset($_POST[$name])) {

      /* TODO: do this recurisively for deep arrays */
      if (is_array($_POST[$name])) {
        $var = new Collection();
        foreach ($_POST[$name] as $key => $value) {
          if (is_array($value)) {
            $var->put($key, $value);
          } else {
            $var->put($key, htmlspecialchars($value));
          }
        }
      } else {
        $var = htmlspecialchars($_POST[$name]);
      }
    } else if (isset($_GET[$name])) {

      /* TODO: do this recurisively for deep arrays */
      if (is_array($_GET[$name])) {
        $var = new Collection();
        foreach ($_GET[$name] as $key => $value) {
          $var->put($key, htmlspecialchars($value));
        }
      } else {
        $var = htmlspecialchars($_GET[$name]);
      }
    }

    if (($var != null) && ((($matchRule != null) && (preg_match($regexp, $var) > 0)) || ($matchRule == null))) {
      return $var;
    } else {
      return $defaultValue;
    }
  }

  /**
   * Converts matching rule to regexp
   *
   * @param string $matchRule The input match rule
   * @return string regexp
   */
  private static
  function parseMatchRule($matchRule)
  {
    switch ($matchRule) {
      case "word":
        return "/[A-Za-z0-9_]/";
      default:
        return $matchRule;
    }
  }

}
