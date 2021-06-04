<?php
/*
Plugin Name: Appilix
Plugin URI: https://www.appilix.com/
Description: Appilix - WordPress App Builder allows you to connect your wordpress website with Appilix App Builder from <a href="https://www.appilix.com/"><strong>www.appilix.com</strong></a> and build a native android app in less than 10 minutes.
Author: appilix
Author URI: https://www.appilix.com
Version: 1.0
License: GPL-2.0+
*/

include 'functions.php';

register_activation_hook(__FILE__, 'appilix_init');
add_action('admin_enqueue_scripts', 'appilix_styles_and_scripts');
add_action("admin_menu", "appilix_add_menu");
add_action( 'admin_init', 'appilix_activation_redirect' );
add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'appilix_action_links' );

add_action( 'rest_api_init', function () {
    register_rest_route( 'appilix/v1', '/connection', array(
        'methods' => 'GET',
        'callback' => 'appilix_check_connection',
    ));
    register_rest_route( 'appilix/v1', '/register_firebase_token', array(
        'methods' => 'GET',
        'callback' => 'appilix_register_firebase_token',
    ));
    register_rest_route( 'appilix/v1', '/categories', array(
        'methods' => 'GET',
        'callback' => 'appilix_get_categories',
    ));
    register_rest_route( 'appilix/v1', '/pages', array(
        'methods' => 'GET',
        'callback' => 'appilix_get_pages',
    ));
    register_rest_route( 'appilix/v1', '/articles', array(
        'methods' => 'GET',
        'callback' => 'appilix_get_articles',
    ));
    register_rest_route( 'appilix/v1', '/layout', array(
        'methods' => 'GET',
        'callback' => 'appilix_get_layout',
    ));
    register_rest_route( 'appilix/v1', '/comments', array(
        'methods' => 'GET',
        'callback' => 'appilix_get_comments',
    ));
    register_rest_route( 'appilix/v1', '/cat_posts', array(
        'methods' => 'GET',
        'callback' => 'appilix_get_category_posts',
    ));
    register_rest_route( 'appilix/v1', '/bookmarks_posts', array(
        'methods' => 'GET',
        'callback' => 'appilix_get_bookmarks_posts',
    ));
    register_rest_route( 'appilix/v1', '/post_comment', array(
        'methods' => 'POST',
        'callback' => 'appilix_post_comment',
    ));
} );


function appilix_index()
{
    include ('views/main-container.php');
}