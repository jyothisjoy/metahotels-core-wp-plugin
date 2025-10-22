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
        'show_in_nav_menus' => true,
        'show_in_admin_bar' => true,
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
        'show_in_rest' => true,
        'rest_base' => 'hotels',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
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

// Add custom admin columns for hotels
function add_hotel_admin_columns($columns) {
    $new_columns = array();
    
    // Add checkbox column
    $new_columns['cb'] = $columns['cb'];
    
    // Add title column
    $new_columns['title'] = $columns['title'];
    
    // Add status column
    $new_columns['hotel_status'] = 'Status';
    
    // Add hotel type column
    $new_columns['hotel_type'] = 'Hotel Type';
    
    // Add star rating column
    $new_columns['star_rating'] = 'Star Rating';
    
    // Add Hotel Manager column
    $new_columns['hotel_manager'] = 'Hotel Manager';
    
    // Add date column
    $new_columns['date'] = $columns['date'];
    
    return $new_columns;
}
add_filter('manage_hotel_posts_columns', 'add_hotel_admin_columns');

// Populate custom admin columns
function populate_hotel_admin_columns($column, $post_id) {
    switch ($column) {
        case 'hotel_status':
            $post = get_post($post_id);
            if ($post->post_status === 'trash') {
                echo '<span style="color: #d63638;">Trashed</span>';
            } elseif ($post->post_status === 'publish') {
                echo '<span style="color: #00a32a;">Published</span>';
            } elseif ($post->post_status === 'draft') {
                echo '<span style="color: #dba617;">Draft</span>';
            } else {
                echo ucfirst($post->post_status);
            }
            break;
            
        case 'hotel_type':
            $types = get_the_terms($post_id, 'hotel_type');
            if ($types && !is_wp_error($types)) {
                $type_names = array();
                foreach ($types as $type) {
                    $type_names[] = $type->name;
                }
                echo implode(', ', $type_names);
            } else {
                echo '—';
            }
            break;
            
        case 'star_rating':
            $ratings = get_the_terms($post_id, 'star_rating');
            if ($ratings && !is_wp_error($ratings)) {
                $rating_names = array();
                foreach ($ratings as $rating) {
                    $rating_names[] = $rating->name;
                }
                echo implode(', ', $rating_names);
            } else {
                echo '—';
            }
            break;
            
        case 'hotel_manager':
            $post = get_post($post_id);
            if (in_array($post->post_status, array('publish', 'draft', 'private'))) {
                $hotel_manager_url = admin_url('edit.php?post_type=hotel&hotel_id=' . $post_id);
                echo '<a href="' . esc_url($hotel_manager_url) . '" class="button button-small" style="background: #0073aa; color: white; border: none; padding: 4px 8px; text-decoration: none; border-radius: 3px;">Hotel Manager</a>';
            } else {
                echo '—';
            }
            break;
    }
}
add_action('manage_hotel_posts_custom_column', 'populate_hotel_admin_columns', 10, 2);

// Add status filter dropdown
function add_hotel_status_filter() {
    global $typenow;
    
    if ($typenow === 'hotel') {
        $current_status = isset($_GET['post_status']) ? $_GET['post_status'] : '';
        $statuses = array(
            '' => 'All Statuses',
            'publish' => 'Published',
            'draft' => 'Draft',
            'trash' => 'Trash'
        );
        
        echo '<select name="post_status">';
        foreach ($statuses as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                $value,
                selected($current_status, $value, false),
                $label
            );
        }
        echo '</select>';
    }
}
add_action('restrict_manage_posts', 'add_hotel_status_filter');

// Handle status filter
function filter_hotels_by_status($query) {
    global $pagenow, $typenow;
    
    if ($pagenow === 'edit.php' && $typenow === 'hotel' && isset($_GET['post_status']) && $_GET['post_status'] !== '') {
        $query->set('post_status', $_GET['post_status']);
    }
}
add_action('pre_get_posts', 'filter_hotels_by_status');

// Add bulk actions for trash and restore
function add_hotel_bulk_actions($bulk_actions) {
    $bulk_actions['hotel_manager'] = 'Open Hotel Manager';
    $bulk_actions['trash_hotels'] = 'Move to Trash';
    $bulk_actions['restore_hotels'] = 'Restore from Trash';
    $bulk_actions['delete_hotels'] = 'Delete Permanently';
    return $bulk_actions;
}
add_filter('bulk_actions-edit-hotel', 'add_hotel_bulk_actions');

// Handle bulk actions
function handle_hotel_bulk_actions($redirect_to, $doaction, $post_ids) {
    if ($doaction === 'hotel_manager') {
        // Redirect to Hotel Manager for the first selected hotel
        if (!empty($post_ids)) {
            $first_hotel_id = $post_ids[0];
            $redirect_to = admin_url('edit.php?post_type=hotel&hotel_id=' . $first_hotel_id);
        }
    } elseif ($doaction === 'trash_hotels') {
        foreach ($post_ids as $post_id) {
            wp_trash_post($post_id);
        }
        $redirect_to = add_query_arg('trashed', count($post_ids), $redirect_to);
    } elseif ($doaction === 'restore_hotels') {
        foreach ($post_ids as $post_id) {
            wp_untrash_post($post_id);
        }
        $redirect_to = add_query_arg('untrashed', count($post_ids), $redirect_to);
    } elseif ($doaction === 'delete_hotels') {
        foreach ($post_ids as $post_id) {
            wp_delete_post($post_id, true);
        }
        $redirect_to = add_query_arg('deleted', count($post_ids), $redirect_to);
    }
    
    return $redirect_to;
}
add_filter('handle_bulk_actions-edit-hotel', 'handle_hotel_bulk_actions', 10, 3);

// Add admin notices for bulk actions
function hotel_bulk_action_admin_notices() {
    if (!empty($_REQUEST['trashed'])) {
        $count = intval($_REQUEST['trashed']);
        printf('<div class="notice notice-success is-dismissible"><p>%d hotel(s) moved to trash.</p></div>', $count);
    }
    
    if (!empty($_REQUEST['untrashed'])) {
        $count = intval($_REQUEST['untrashed']);
        printf('<div class="notice notice-success is-dismissible"><p>%d hotel(s) restored from trash.</p></div>', $count);
    }
    
    if (!empty($_REQUEST['deleted'])) {
        $count = intval($_REQUEST['deleted']);
        printf('<div class="notice notice-success is-dismissible"><p>%d hotel(s) permanently deleted.</p></div>', $count);
    }
}
add_action('admin_notices', 'hotel_bulk_action_admin_notices');

// Add quick edit actions for individual posts
function add_hotel_quick_actions($actions, $post) {
    // Debug: Check if function is being called
    if ($post->post_type === 'hotel') {
        if ($post->post_status === 'trash') {
            $actions['restore'] = sprintf(
                '<a href="%s" aria-label="Restore this hotel from trash">Restore</a>',
                wp_nonce_url(admin_url(sprintf('post.php?post=%d&action=untrash', $post->ID)), 'untrash-post_' . $post->ID)
            );
        } else {
            // Add Hotel Manager action for published/draft hotels
            if (in_array($post->post_status, array('publish', 'draft', 'private'))) {
                $actions['hotel-manager'] = sprintf(
                    '<a href="%s" aria-label="Open Hotel Manager for this hotel" style="color: #0073aa;">Hotel Manager</a>',
                    admin_url('edit.php?post_type=hotel&hotel_id=' . $post->ID)
                );
            }
            
            $actions['trash'] = sprintf(
                '<a href="%s" aria-label="Move this hotel to trash">Trash</a>',
                wp_nonce_url(admin_url(sprintf('post.php?post=%d&action=trash', $post->ID)), 'trash-post_' . $post->ID)
            );
        }
    }
    return $actions;
}
add_filter('post_row_actions', 'add_hotel_quick_actions', 5, 2);

// Add Hotel Manager access notice
function add_hotel_manager_notice() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'edit-hotel') {
        echo '<div class="notice notice-info" style="margin-top: 20px;">';
        echo '<p><strong>Hotel Manager:</strong> Use the "Hotel Manager" links in the row actions below to access the advanced hotel management interface for each hotel.</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'add_hotel_manager_notice');