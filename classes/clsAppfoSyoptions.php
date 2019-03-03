<?php
/**
 * Created by PhpStorm.
 * User: anushkar
 * Date: 2/25/19
 * Time: 2:06 AM
 */

namespace appfoliosync;


class clsAppfoSyoptions
{
    function __construct()
    {
        // Hook to plugins_loaded
        add_action( 'plugins_loaded', array($this,'plugin_example_init'));
    }

    /**
     * Initialize example plugin
     */
    function plugin_example_init() {

        require_once APPFOSYDIRPATH."ext/sunrise.php";

        // Make plugin available for translation, you can change /languages/ to your .mo-files folder name
        load_plugin_textdomain( APPFOSYTD, false, APPFOSYDIRPATH. '/languages/' );

        // Initialize Sunrise
        $admin = new Sunrise7( array(
            // Sunrise file path
            'file' =>   APPFOSYDIRPATH.'ext/sunrise.php',
            // Plugin slug (should be equal to plugin directory name)
            'slug' => APPFOSYPSLUG,
            // Plugin prefix
            'prefix' => APPFOSYPERFIX,
            // Plugin textdomain
            'textdomain' => APPFOSYTD,
            // Custom CSS assets folder
            'css' => APPFOSYDIR.'assets/style',
            // Custom JS assets folder
            'js' => APPFOSYDIR.'assets/scripts',
        ) );

        // Prepare array with options
        $options = array(

            // Open tab: Regular fields
            array(
                'type' => 'opentab',
                'name' => __( APPFOSYNAME. ' Plugin Settings', APPFOSYTD ),
            ),

            // URL field
            array(
                'id'       => 'listing_url',
                'type'    => 'url',
                'default' => '#',
                'name'    => __( 'Listing URL', APPFOSYTD ),
                'desc'    => __( 'URL to the real estate listing.', APPFOSYTD ),
            ),

            // Text field
            array(
                'id'       => 'listing_posttype',
                'type'    => 'text',
                'default' => 'listing',
                'name'    => __( 'Listing Post Type', APPFOSYTD ),
                'desc'    => __( 'The post type that need to integrate with.', APPFOSYTD ),
            ),





            // Select (dropdown list)
            array(
                'id'       => 'schedu',
                'type'    => 'select',
                'default' => 'hourly',
                'name'    => __( 'Synchronization Schedule ', APPFOSYTD ),
                'desc'    => __( 'How often the synchronization should reoccur.', APPFOSYTD ),
                'options' => array(
                    array(
                        'value' => 'hourly',
                        'label'  => __( 'Hourly', APPFOSYTD ),
                    ),
                    array(
                        'value' => 'twicedaily',
                        'label'  => __( 'Twice Daily', APPFOSYTD ),
                    ),
                    array(
                        'value' => 'daily',
                        'label'  => __( 'Daily', APPFOSYTD ),
                    ),
                ),
            ),

            // Text field
            array(
                'id'       => 'manual_import',
                'type'    => 'button',
                'default' => 'Import',
                'name'    => __( 'Manual import', APPFOSYTD ),
                'desc'    => __( 'Import listings manually.', APPFOSYTD ),
                'callback'=> "btnImportCallback(this)"
            ),



            // Close tab: Regular fields
            array(
                'type' => 'closetab',
            ),

            // open tab: WP real estate plugin related
            array(
                'type' => 'opentab',
                'name' => __( 'Wp Real Estate Plugin Settings', APPFOSYTD ),
            ),

            // Text field
            array(
                'id'       => 'house_catid',
                'type'    => 'text',
                'default' => '0',
                'name'    => __( 'House tag id', APPFOSYTD ),
                'desc'    => __( 'Listing Type Id for Houses in Wp Real Estate Plugin.', APPFOSYTD ),
            ),
            array(
                'id'       => 'unit_catid',
                'type'    => 'text',
                'default' => '0',
                'name'    => __( 'Apartment tag id', APPFOSYTD ),
                'desc'    => __( 'Listing Type Id in Wp Real Estate Plugin.', APPFOSYTD ),
            ),

            // Close tab: WP real estate plugin related
            array(
                'type' => 'closetab',
            ),


            // open tab: WP real estate plugin google map related
            array(
                'type' => 'opentab',
                'name' => __( 'Google API Settings', APPFOSYTD ),
            ),

            // Text field
            array(
                'id'       => 'gapi',
                'type'    => 'text',
                'default' => '0',
                'name'    => __( 'Google Api', APPFOSYTD ),
                'desc'    => __( 'Google API key to validate address while importing listings.', APPFOSYTD ),
            ),


            // Close tab: WP real estate plugin related
            array(
                'type' => 'closetab',
            ),


        );

        // Add top-level menu (like Dashboard -> Comments)
        $admin->add_menu( array(
            // Settings page <title>
            'page_title' => __( APPFOSYNAME. ' Settings', APPFOSYTD ),
            // Menu title, will be shown in left dashboard menu
            'menu_title' => __( APPFOSYNAME, APPFOSYTD ),
            // Minimal user capability to access this page
            'capability' => 'manage_options',
            // Unique page slug
            'slug' => APPFOSYPSLUG,
            // Add here your custom icon url, or use [dashicons](https://developer.wordpress.org/resource/dashicons/)
            // 'icon_url' => admin_url( 'images/wp-logo.png' ),
            'icon_url' => 'dashicons-admin-multisite',
            // Menu position from 80 to <infinity>, you can use decimals
            'position' => '91.1',
            // Array with options available on this page
            'options' => $options,
        ) );


    }
}