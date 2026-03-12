<?php
if (!defined('ABSPATH')) {
    exit;
}

// Register the destination custom post type
function metahotels_register_destination_post_type() {
    // Check if post type is enabled
    if (!get_option('metahotels_enable_destinations', true)) {
        return; // Don't register if disabled
    }

    $labels = array(
        'name' => 'Destinations',
        'singular_name' => 'Destination',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Destination',
        'edit_item' => 'Edit Destination',
        'new_item' => 'New Destination',
        'view_item' => 'View Destination',
        'search_items' => 'Search Destinations',
        'not_found' => 'No destinations found',
        'not_found_in_trash' => 'No destinations found in trash',
        'menu_name' => 'Destinations'
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'destination'),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => true,
        'menu_position' => 4,
        'menu_icon' => 'dashicons-location',
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author'),
    );

    register_post_type('destination', $args);
}
add_action('init', 'metahotels_register_destination_post_type');

// Register taxonomies for destinations
function metahotels_register_destination_taxonomies() {
    // Only register taxonomies if the destinations post type is enabled
    if (!get_option('metahotels_enable_destinations', true)) {
        return;
    }

    // Region Taxonomy
    register_taxonomy('region', 'destination', array(
        'hierarchical' => true,
        'labels' => array(
            'name' => 'Regions',
            'singular_name' => 'Region',
            'search_items' => 'Search Regions',
            'all_items' => 'All Regions',
            'parent_item' => 'Parent Region',
            'parent_item_colon' => 'Parent Region:',
            'edit_item' => 'Edit Region',
            'update_item' => 'Update Region',
            'add_new_item' => 'Add New Region',
            'new_item_name' => 'New Region Name',
            'menu_name' => 'Regions'
        ),
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'region'),
    ));

    // Country Taxonomy
    register_taxonomy('country', array('destination', 'hotel'), array(
        'hierarchical' => true,
        'labels' => array(
            'name' => 'Countries',
            'singular_name' => 'Country',
            'search_items' => 'Search Countries',
            'all_items' => 'All Countries',
            'parent_item' => 'Parent Country',
            'parent_item_colon' => 'Parent Country:',
            'edit_item' => 'Edit Country',
            'update_item' => 'Update Country',
            'add_new_item' => 'Add New Country',
            'new_item_name' => 'New Country Name',
            'menu_name' => 'Countries'
        ),
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'country'),
    ));
}
add_action('init', 'metahotels_register_destination_taxonomies'); 
