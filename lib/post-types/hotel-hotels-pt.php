<?php
// Register the hotel custom post type
function register_hotel_post_type() {
    $labels = array(
        'name' => 'Hotels',
        'singular_name' => 'Hotel',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Hotel',
        'edit_item' => 'Edit Hotel',
        'new_item' => 'New Hotel',
        'view_item' => 'View Hotel',
        'search_items' => 'Search Hotels',
        'not_found' => 'No hotels found',
        'not_found_in_trash' => 'No hotels found in trash',
        'menu_name' => 'Hotels'
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => array(
            'slug' => 'hotel',
            'with_front' => false,
            'hierarchical' => true
        ),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => true,
        'menu_position' => 5,
        'menu_icon' => 'dashicons-building',
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author', 'page-attributes'),
    );

    register_post_type('hotel', $args);
}
add_action('init', 'register_hotel_post_type');

// Add rewrite rules for hotel subpages
function add_hotel_rewrite_rules() {
    // Rule for child hotel pages: hotel/parent-hotel/child-page/
    add_rewrite_rule(
        'hotel/([^/]+)/([^/]+)/?$',
        'index.php?post_type=hotel&name=$matches[2]',
        'top'
    );
    
    // Rule for deeper nesting: hotel/parent-hotel/child-page/grandchild-page/
    add_rewrite_rule(
        'hotel/([^/]+)/([^/]+)/([^/]+)/?$',
        'index.php?post_type=hotel&name=$matches[3]',
        'top'
    );
    
    // Rule for even deeper nesting: hotel/parent-hotel/child-page/grandchild-page/great-grandchild-page/
    add_rewrite_rule(
        'hotel/([^/]+)/([^/]+)/([^/]+)/([^/]+)/?$',
        'index.php?post_type=hotel&name=$matches[4]',
        'top'
    );
}
add_action('init', 'add_hotel_rewrite_rules', 10);

// Add query vars for hotel hierarchy
function add_hotel_query_vars($vars) {
    $vars[] = 'hotel_parent';
    return $vars;
}
add_filter('query_vars', 'add_hotel_query_vars');

// Handle hotel page requests
function handle_hotel_page_request($wp) {
    // Only handle hotel post type requests
    if (!isset($wp->query_vars['post_type']) || $wp->query_vars['post_type'] !== 'hotel') {
        return;
    }
    
    // If we have a name, try to find the post
    if (isset($wp->query_vars['name'])) {
        $post_name = $wp->query_vars['name'];
        
        // Try to find the post by name
        $post = get_page_by_path($post_name, OBJECT, 'hotel');
        
        if ($post) {
            // Set the post ID in query vars
            $wp->query_vars['p'] = $post->ID;
            unset($wp->query_vars['name']);
        }
    }
}
add_action('parse_request', 'handle_hotel_page_request');

// Fix permalink generation for hierarchical hotel posts
function fix_hotel_permalink($post_link, $post) {
    if ($post->post_type === 'hotel' && $post->post_parent > 0) {
        // Get the parent post
        $parent = get_post($post->post_parent);
        if ($parent) {
            // Build the hierarchical permalink
            $post_link = home_url('/hotel/' . $parent->post_name . '/' . $post->post_name . '/');
        }
    }
    return $post_link;
}
add_filter('post_type_link', 'fix_hotel_permalink', 10, 2);

// Register taxonomies for hotels
function register_hotel_taxonomies() {
    // Hotel Type Taxonomy
    register_taxonomy('hotel_type', 'hotel', array(
        'hierarchical' => true,
        'labels' => array(
            'name' => 'Hotel Types',
            'singular_name' => 'Hotel Type',
            'search_items' => 'Search Hotel Types',
            'all_items' => 'All Hotel Types',
            'parent_item' => 'Parent Hotel Type',
            'parent_item_colon' => 'Parent Hotel Type:',
            'edit_item' => 'Edit Hotel Type',
            'update_item' => 'Update Hotel Type',
            'add_new_item' => 'Add New Hotel Type',
            'new_item_name' => 'New Hotel Type Name',
            'menu_name' => 'Hotel Types'
        ),
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'hotel-type'),
    ));

    // Star Rating Taxonomy
    register_taxonomy('star_rating', 'hotel', array(
        'hierarchical' => true,
        'labels' => array(
            'name' => 'Star Ratings',
            'singular_name' => 'Star Rating',
            'search_items' => 'Search Star Ratings',
            'all_items' => 'All Star Ratings',
            'parent_item' => 'Parent Star Rating',
            'parent_item_colon' => 'Parent Star Rating:',
            'edit_item' => 'Edit Star Rating',
            'update_item' => 'Update Star Rating',
            'add_new_item' => 'Add New Star Rating',
            'new_item_name' => 'New Star Rating Name',
            'menu_name' => 'Star Ratings'
        ),
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'star-rating'),
    ));
}
add_action('init', 'register_hotel_taxonomies');