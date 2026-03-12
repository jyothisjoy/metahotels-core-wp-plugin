<?php
if (!defined('ABSPATH')) {
    exit;
}

function metahotels_register_hotel_rooms_post_type() {
    // Check if post type is enabled
    if (!get_option('metahotels_enable_hotel_rooms', true)) {
        return; // Don't register if disabled
    }

    $labels = array(
        'name'               => 'Hotel Rooms',
        'singular_name'      => 'Hotel Room',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Hotel Room',
        'edit_item'          => 'Edit Hotel Room',
        'new_item'           => 'New Hotel Room',
        'view_item'          => 'View Hotel Room',
        'search_items'       => 'Search Hotel Rooms',
        'not_found'          => 'No hotel rooms found',
        'not_found_in_trash' => 'No hotel rooms found in trash',
        'parent_item_colon'  => '',
        'menu_name'          => 'Hotel Rooms'
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array( 'slug' => 'room' ),
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_position'       => 5,
        'menu_icon'           => 'dashicons-admin-home',
        'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author' ),
        'taxonomies'          => array( 'hotel_category' )
    );

    register_post_type( 'hotel_room', $args );
}
add_action( 'init', 'metahotels_register_hotel_rooms_post_type' );

function metahotels_register_hotel_category_taxonomy() {
    // Only register taxonomy if the hotel rooms post type is enabled
    if (!get_option('metahotels_enable_hotel_rooms', true)) {
        return;
    }

    $labels = array(
        'name'              => 'Hotel Categories',
        'singular_name'     => 'Hotel Category',
        'search_items'      => 'Search Hotel Categories',
        'all_items'         => 'All Hotel Categories',
        'parent_item'       => 'Parent Hotel Category',
        'parent_item_colon' => 'Parent Hotel Category:',
        'edit_item'         => 'Edit Hotel Category',
        'update_item'       => 'Update Hotel Category',
        'add_new_item'      => 'Add New Hotel Category',
        'new_item_name'     => 'New Hotel Category Name',
        'menu_name'         => 'Hotel Categories',
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'hotel-category' ),
    );

    register_taxonomy( 'hotel_category', 'hotel_room', $args );
}
add_action( 'init', 'metahotels_register_hotel_category_taxonomy' );
