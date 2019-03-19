<?php
namespace appfoliosync;
/*
  Plugin Name: Appfolio Sync
  Plugin URI: https://wordpress.org/plugins/appfolio-sync/
  Version: 1.0.1
  Description: Appfolio listing Synchronisation Plugin  | <a href="#">Documentation</a>
  Author: oprone
  Author URI: http://www.oprone.com/
  Text Domain: appfolio-sync
  Domain Path: /languages
  License: MIT
  Documentation : http://www.oprone.com/appfolio-sync/doc
 */

if(!defined('APPFOSYTD')){
    define('APPFOSYTD','appfosytextdomain' );
}

if(!defined('APPFOSYNAME')){
    define('APPFOSYNAME','Appfolio Sync' );
}

if(!defined('APPFOSYPSLUG')){
    define('APPFOSYPSLUG','appfolio-sync' );
}

if(!defined('APPFOSYPERFIX')){
    define('APPFOSYPERFIX','appfosy_' );
}

if(!defined('APPFOSYDIR')){
    define('APPFOSYDIR',plugin_dir_url( __FILE__ ) );
}


if(!defined('APPFOSYDIRPATH')){
    define('APPFOSYDIRPATH',plugin_dir_path( __FILE__ ) );
}


require_once 'classes/clsAppfoSyLoader.php';

-
    /**
     * Initialize plugin
     */


$_appfosync = new clsAppfoSyLoader();