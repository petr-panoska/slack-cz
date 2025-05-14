<?php

// uncomment this line if you must temporarily take down your site for maintenance
// require '.maintenance.php';

switch ($_SERVER['REMOTE_ADDR']) {
  case '213.238.175.3':
  case '213.238.175.12':
    die('Nemáte oprávnění k přístupu na stránky');
    break;
}

// absolute filesystem path to this web root
define('DS', '/');
define('WWW_DIR', __DIR__);

define('CSS_DIR', WWW_DIR . DS . 'css');
define('JS_DIR', WWW_DIR . DS . 'js');

// absolute filesystem path to the application root
define('APP_DIR', WWW_DIR . '/../app');

// absolute filesystem path to the libraries
define('LIBS_DIR', WWW_DIR . '/../libs');

define('VIDEO_DIR', WWW_DIR . '/../video');

define('HIGHLINE_DIR', WWW_DIR . '/../line/high');

define('FLASH_INFO', 'info');

define('DATA_DIR', WWW_DIR . DS . 'data');

require_once WWW_DIR . '/../vendor/autoload.php';

// load bootstrap file
require APP_DIR . '/bootstrap.php';

