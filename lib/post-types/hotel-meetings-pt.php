<?php
// Register the meetings custom post type
function register_meeting_post_type() {
    // Check if post type is enabled (defaulting to true for now)
    if (!get_option('metahotels_enable_meetings', true)) {
        return; // Don't register if disabled
    }

    $labels = array(
        'name' => 'Meetings',
        'singular_name' => 'Meeting',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Meeting',
        'edit_item' => 'Edit Meeting',
        'new_item' => 'New Meeting',
        'view_item' => 'View Meeting',
        'search_items' => 'Search Meetings',
        'not_found' => 'No meetings found',
        'not_found_in_trash' => 'No meetings found in trash',
        'menu_name' => 'Meetings'
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => array(
            'slug' => 'meeting',
            'with_front' => false
        ),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => false,
        'menu_position' => 7, // Positioning it after Restaurants (which was 6)
        'menu_icon' => 'dashicons-groups',
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author'),
    );

    register_post_type('meeting', $args);
}
add_action('init', 'register_meeting_post_type');
