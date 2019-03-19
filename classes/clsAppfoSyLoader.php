<?php
namespace appfoliosync;
/**
 * User: anushkar
 * Date: 2/25/19
 * Time: 1:15 PM
 */
class clsAppfoSyLoader
{
    function __construct()
    {
        register_activation_hook(APPFOSYDIRPATH.'/appfolio-sync.php', array($this,'appfosy_activation'));
        register_deactivation_hook(APPFOSYDIRPATH.'/appfolio-sync.php', array($this,'appfosy_deactivation'));

        require_once  'clsAppfoSyLogWriter.php';
        require_once 'clsAppfoSyoptions.php';
        require_once 'clsAppfoSyListings.php';

        add_action('wp_enqueue_scripts', array( $this, 'appfosy_scripts' ));
        add_filter('template_include', array( $this, 'get_appfosy_template' ));
        add_filter('wp_enqueue_scripts', array( $this, 'appfosy_scripts' ));


        $options = new clsAppfoSyoptions();
        $listings = new clsAppfoSyListings();

        add_action( 'appfosy_event', array($this,'cron_appfosy_event'), 10, 0 );
    }

    public function cron_appfosy_event() {
        $_appfosyncLogger = new clsAppfoSyLogWriter();
        try{
            $msg = 'Corn event "appfosy_event" has been fired at '.time();
            $_appfosyncLogger->emailNotification();
            $_appfosyncLogger->warning($msg);

        }catch(\Exception $e){

            $_appfosyncLogger->warning($e->getMessage());
        }
    }

    public function appfosy_scripts()
    {
        $imageloadurl = "'" . admin_url('admin-ajax.php') . "?action=getlistingimage&url={{link}}'";
        wp_enqueue_script("mustache", APPFOSYDIR . 'assets/scripts/mustache.js', array(
            'jquery'
        ));
        wp_enqueue_script("underscore", APPFOSYDIR . 'assets/scripts/underscore-min.js', array(
            'jquery'
        ));
        wp_enqueue_script("appfosy-scr", APPFOSYDIR . 'assets/scripts/script.js', array(
            'jquery',
            'mustache',
            'underscore'
        ));
        wp_localize_script('appfosy-scr', 'appfosy_var', array(
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
        wp_enqueue_style('appfosy-style', APPFOSYDIR . 'assets/style/style.css');
    }

    public function appfosy_activation(){
        if (! wp_next_scheduled ( 'appfosy_event' )) {
            $schedu = get_option( APPFOSYPERFIX . 'schedu' );
            if(!isset($schedu) || empty($schedu)) $schedu = 'hourly';
            wp_schedule_event(time(), $schedu, 'appfosy_event');
        }
    }

    public function appfosy_deactivation() {
        wp_clear_scheduled_hook('appfosy_event');
    }

    function get_appfosy_template($archive_template)
    {
        return $archive_template;
        global $post;
        $_appfoposttype = get_option('appfoposttype');
        if ($post->post_type == $_appfoposttype) {
            $archive_template = APPFOSYDIRPATH . '/templates/custlistingcategory.php';
        }
        return $archive_template;
    }


}