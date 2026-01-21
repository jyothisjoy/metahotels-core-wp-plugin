<?php
// Register the facility custom post type
function meta_hotels_register_facility_post_type() {
    // Check if post type is enabled
    if (!get_option('metahotels_enable_facilities', true)) {
        return; // Don't register if disabled
    }

    $labels = array(
        'name' => 'Facilities',
        'singular_name' => 'Facility',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Facility',
        'edit_item' => 'Edit Facility',
        'new_item' => 'New Facility',
        'view_item' => 'View Facility',
        'search_items' => 'Search Facilities',
        'not_found' => 'No facilities found',
        'not_found_in_trash' => 'No facilities found in trash',
        'parent_item_colon' => 'Parent Facility:',
        'menu_name' => 'Facilities'
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => array(
            'slug' => 'facility',
            'with_front' => false
        ),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => true,
        'menu_position' => 5,
        'menu_icon' => 'dashicons-building',
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author', 'page-attributes'),
    );
    
    register_post_type( 'facility', $args );

    // Add a custom taxonomy called "Hotel" for the "Facility" post type
    register_taxonomy(
        'hotel_facility',
        'facility',
        array(
            'label' => 'Facility In Hotel',
            'rewrite' => array( 'slug' => 'hotel-facility' ),
            'hierarchical' => true,
        )
    );
}
add_action( 'init', 'meta_hotels_register_facility_post_type' );