<?php

add_action('admin_menu', 'properties_api_plugin_setup_menu');

// Add in the menu item and submenus
function properties_api_plugin_setup_menu() {
    add_menu_page('Properties API', 'Properties API', 'manage_options', 'properties-api-create', 'api_key_generator_create_page', 'dashicons-admin-generic', 6);
    add_submenu_page('properties-api-create', 'Create New API Keys', 'Create New API Keys', 'manage_options', 'properties-api-create', 'api_key_generator_create_page');
    add_submenu_page(
		'properties-api-create', // Parent slug
		'Existing API Keys', // Page title
		'Existing API Keys', // Menu title
		'manage_options', // Capability
		'properties-api-existing', // Menu slug
		'api_key_generator_existing_page'); // Callback function
	
	add_submenu_page(
        'properties-api-create', // Parent slug
        'Property Filter', // Page title
        'Property Filter', // Menu title
        'manage_options', // Capability
        'property-filter', // Menu slug
        'property_filter_page' // Callback function
    );
	
	
}

// Register the activation hook for adding in the api key table
register_activation_hook(__FILE__, 'api_keys_plugin_activate');

function api_keys_plugin_activate() {
// Create the API keys table
create_api_keys_table();
}

add_action('admin_enqueue_scripts', 'properties_api_enqueue_scripts');