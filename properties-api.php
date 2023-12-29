<?php
/*
Plugin Name: Properties API - For Sale
Description: A plugin that exposes a custom REST API endpoint for fetching for sale properties and their metadata.
Version: 1.0
Author: Ryan Pittman
*/

require_once 'api_key_functions.php';
require_once 'admin_pages.php';
require_once 'api_for_sale.php';


// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}


register_activation_hook(__FILE__, 'create_api_keys_table');

add_action('wp_enqueue_scripts', 'properties_api_enqueue_scripts');
add_action('wp_ajax_delete_api_key', 'delete_api_key_callback');
add_action('wp_ajax_nopriv_delete_api_key', 'delete_api_key_callback');