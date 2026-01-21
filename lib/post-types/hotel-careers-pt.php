<?php
function register_careers_post_type() {
    // Check if post type is enabled
    if (!get_option('metahotels_enable_careers', true)) {
        return; // Don't register if disabled
    }

    $labels = array(
        'name'               => 'Careers',
        'singular_name'      => 'Career',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Career',
        'edit_item'          => 'Edit Career',
        'new_item'           => 'New Career',
        'view_item'          => 'View Career',
        'search_items'       => 'Search Careers',
        'not_found'          => 'No careers found',
        'not_found_in_trash' => 'No careers found in trash',
        'parent_item_colon'  => '',
        'menu_name'          => 'Careers'
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array( 'slug' => 'career' ),
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_position'       => 5,
        'menu_icon'           => 'dashicons-businessman',
        'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author' ),
        'taxonomies'          => array( 'career_category' )
    );

    register_post_type( 'career', $args );
}
add_action( 'init', 'register_careers_post_type' );

function register_career_category_taxonomy() {
    // Only register taxonomy if the careers post type is enabled
    if (!get_option('metahotels_enable_careers', true)) {
        return;
    }

    $labels = array(
        'name'              => 'Career Categories',
        'singular_name'     => 'Career Category',
        'search_items'      => 'Search Career Categories',
        'all_items'         => 'All Career Categories',
        'parent_item'       => 'Parent Career Category',
        'parent_item_colon' => 'Parent Career Category:',
        'edit_item'         => 'Edit Career Category',
        'update_item'       => 'Update Career Category',
        'add_new_item'      => 'Add New Career Category',
        'new_item_name'     => 'New Career Category Name',
        'menu_name'         => 'Career Categories',
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'career-category' ),
    );

    register_taxonomy( 'career_category', 'career', $args );
}
add_action( 'init', 'register_career_category_taxonomy' );