<?php

require_once 'vendor/autoload.php';

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

$paths = array("app/models");
$isDevMode = false;

$configurator = new \Nette\Config\Configurator();
$configurator->setTempDirectory(__DIR__ . '/temp');
$configurator->createRobotLoader()
    ->addDirectory(__DIR__ . '/app')
    ->register();
$configurator->addConfig(__DIR__ . '/app/config/config.neon');
$container = $configurator->createContainer();

$dbParams = array(
    'driver' => $container->getParameters()['database']['driver'],
    'user' => $container->getParameters()['database']['user'],
    'password' => $container->getParameters()['database']['password'],
    'dbname' => $container->getParameters()['database']['dbname'],
);

$config = Setup::createAnnotationMetadataConfiguration($paths, $isDevMode);
$entityManager = EntityManager::create($dbParams, $config);
$platform = $entityManager->getConnection()->getDatabasePlatform();
$platform->registerDoctrineTypeMapping('enum', 'string');

return ConsoleRunner::createHelperSet($entityManager);