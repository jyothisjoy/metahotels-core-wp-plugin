<?php
// Register the facility custom post type
function meta_hotels_register_facility_post_type() {
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
        'exclude_from_search' => true,
        'publicly_queryable' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'rewrite' => false,
        'capability_type' => 'post',
        'has_archive' => false,
        'hierarchical' => false,
        'menu_position' => 20,
        'menu_icon' => 'dashicons-admin-tools',
        'supports' => array( 'title', 'thumbnail', 'excerpt', 'author' ),
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