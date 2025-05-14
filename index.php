<?php

// uncomment this line if you must temporarily take down your site for maintenance
//require '.maintenance.php';

// absolute filesystem path to this web root
define('DS', '/');
define('WWW_DIR', __DIR__);
define('HIGHLINES_DIR', WWW_DIR . DS . 'line' . DS . 'high');

define('CSS_DIR', WWW_DIR . DS . 'css');
define('JS_DIR', WWW_DIR . DS . 'js');

// absolute filesystem path to the application root
define('APP_DIR', WWW_DIR . '/app');
define('TEMPLATE_DIR', APP_DIR . DS . "templates");

// absolute filesystem path to the libraries
define('LIBS_DIR', WWW_DIR . '/libs');

define('VIDEO_DIR', WWW_DIR . '/video');
define('USERS_DIR', WWW_DIR . DS . "uzivatel");

define('HIGHLINE_DIR', WWW_DIR . '/line/high');

define('FLASH_INFO', 'info');

define('DATA_DIR', WWW_DIR . DS . 'data');

require APP_DIR . '/bootstrap.php';

