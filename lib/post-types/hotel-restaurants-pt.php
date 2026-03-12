<?php
if (!defined('ABSPATH')) {
    exit;
}

// Register the restaurant custom post type
function metahotels_register_restaurant_post_type() {
    // Check if post type is enabled (defaulting to true for now)
    if (!get_option('metahotels_enable_restaurants', true)) {
        return; // Don't register if disabled
    }

    $labels = array(
        'name' => 'Restaurants',
        'singular_name' => 'Restaurant',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Restaurant',
        'edit_item' => 'Edit Restaurant',
        'new_item' => 'New Restaurant',
        'view_item' => 'View Restaurant',
        'search_items' => 'Search Restaurants',
        'not_found' => 'No restaurants found',
        'not_found_in_trash' => 'No restaurants found in trash',
        'menu_name' => 'Restaurants'
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => array(
            'slug' => 'restaurant',
            'with_front' => false
        ),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => false,
        'menu_position' => 6, // Positioning it after Hotels (which was 5)
        'menu_icon' => 'dashicons-food',
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author'),
    );

    register_post_type('restaurant', $args);
}
add_action('init', 'metahotels_register_restaurant_post_type');
