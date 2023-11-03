<?php

// Directorio de la instalacion de WordPress
if (!defined('WP_ROOT_PATH'))
    define('WP_ROOT_PATH', realpath(__DIR__ . '/../..')  .  DIRECTORY_SEPARATOR);

if (!defined('ROOT_PATH'))
    define('ROOT_PATH', realpath(__DIR__ . '/..')  .  DIRECTORY_SEPARATOR);

if (!defined('CONFIG_PATH'))
	define('CONFIG_PATH', ROOT_PATH  . 'config' . DIRECTORY_SEPARATOR);

if (!defined('WP_CONTENT_PATH')){
    define('WP_CONTENT_PATH', WP_ROOT_PATH . 'wp-content' . DIRECTORY_SEPARATOR);
}

if (!defined('PLUGINS_PATH')){
    define('PLUGINS_PATH', WP_CONTENT_PATH . 'plugins' . DIRECTORY_SEPARATOR);
}

if (!defined('THEMES_PATH')){
    define('THEMES_PATH', WP_CONTENT_PATH . 'themes' . DIRECTORY_SEPARATOR);
}

if (!defined('LOGS_PATH'))
    define('LOGS_PATH', ROOT_PATH . 'logs'. DIRECTORY_SEPARATOR); 

if (!defined('VENDOR_PATH'))
    define('VENDOR_PATH', ROOT_PATH . 'vendor'. DIRECTORY_SEPARATOR); 

if (!defined('ETC_PATH'))
    define('ETC_PATH', ROOT_PATH . 'etc'. DIRECTORY_SEPARATOR);    

if (!defined('DOWNLOADS_PATH'))
    define('DOWNLOADS_PATH', ROOT_PATH . 'downloads'. DIRECTORY_SEPARATOR);    

