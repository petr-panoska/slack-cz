<?php

use Nette\Application\Routers\Route;
use Nette\Config\Configurator;

require 'vendor/autoload.php';

// Configure application
$configurator = new Configurator;

// Enable Nette Debugger for error visualisation & logging
//$configurator->setDebugMode(array("109.81.210.144"));
$configurator->enableDebugger(__DIR__ . '/../log');

// Enable RobotLoader - this will load all classes automatically
$configurator->setTempDirectory(__DIR__ . '/../temp');
$configurator->createRobotLoader()
    ->addDirectory(APP_DIR)
    ->register();

// Create Dependency Injection container from config.neon file
$configurator->addConfig(__DIR__ . '/config/config.neon');
$container = $configurator->createContainer();

// Setup router
$container->router[] = new Route('index.php', 'Homepage:default', Route::ONE_WAY);
$container->router[] = new Route('<presenter>/<action>[/<id>]', 'Homepage:default');

// Configure and run the application!
$container->application->run();
