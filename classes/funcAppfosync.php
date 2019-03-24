<?php
namespace appfoliosync;
function fetch_remote_file($url, $post_data=array())
{
    $post_body = '';
    if(!empty($post_data))
    {
        foreach($post_data as $key => $val)
        {
            $post_body .= '&'.urlencode($key).'='.urlencode($val);
        }
        $post_body = ltrim($post_body, '&');
    }

        $response = wp_remote_get( $url,$post_body );
        return wp_remote_retrieve_body( $response );


}