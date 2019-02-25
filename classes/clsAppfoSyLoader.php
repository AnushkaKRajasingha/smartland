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
        add_action('wp_enqueue_scripts', array( $this, 'appfosy_scripts' ));
        add_filter('template_include', array( $this, 'get_appfosy_template' ));
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
            'ajaxurl' => admin_url('admin-ajax.php'),
            'listingitem' => '<div class="listing-item" data-timestamp="{{timestamp}}"> <div class="listing-image" style="background-image: url(' . $imageloadurl . ');"> </div> <div class="listing-content"> <h3 class="listing-title"><a href="{{link}}" class="listing-title">{{title}}</a></h3> <div class="listing-content"> <span class="pub-date">Date : {{date}} | From : {{from}}</span> <div class="item-desc">{{description}}</div> </div> <a href="{{link}}" class="listing-more">read more</a> </div> </div>'
        ));
        wp_enqueue_style('appfosy-style', APPFOSYDIR . 'assets/style/style.css');
    }

    function get_appfosy_template($archive_template)
    {
        global $post;
        $_appfoposttype = get_option('appfoposttype');
        if ($post->post_type == $_appfoposttype) {
            $archive_template = APPFOSYDIRPATH . '/templates/custlistingcategory.php';
        }
        return $archive_template;
    }

}