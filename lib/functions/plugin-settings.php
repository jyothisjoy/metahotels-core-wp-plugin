<?php
// MetaHotels Core Settings Page
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
function metahotels_core_admin_menu() {
    add_submenu_page(
        'options-general.php',
        'MetaHotels Settings',
        'MetaHotels Settings',
        'manage_options',
        'metahotels-settings',
        'metahotels_core_settings_page'
    );
}
add_action('admin_menu', 'metahotels_core_admin_menu');

// Register settings
function metahotels_core_register_settings() {
    // Register post type enable/disable settings (default: all enabled)
    $post_types = array(
        'hotels' => 'Hotels',
        'hotel_rooms' => 'Hotel Rooms',
        'hotel_surroundings' => 'Hotel Surroundings',
        'facilities' => 'Facilities',
        'offers' => 'Offers',
        'careers' => 'Careers',
        'destinations' => 'Destinations'
    );

    foreach ($post_types as $key => $label) {
        register_setting('metahotels_core_options', 'metahotels_enable_' . $key, array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'metahotels_sanitize_boolean'
        ));
    }

    // Register comment disabling setting
    register_setting('metahotels_core_options', 'metahotels_disable_comments', array(
        'type' => 'boolean',
        'default' => false,
        'sanitize_callback' => 'metahotels_sanitize_boolean'
    ));
}
add_action('admin_init', 'metahotels_core_register_settings');

// Sanitize boolean values
function metahotels_sanitize_boolean($value) {
    return (bool) $value;
}

// Flush rewrite rules after settings are saved
function metahotels_core_flush_rewrite_rules() {
    flush_rewrite_rules();
}

// Flush rewrite rules when post type enable/disable settings change
add_action('update_option_metahotels_enable_hotels', 'metahotels_core_flush_rewrite_rules');
add_action('update_option_metahotels_enable_hotel_rooms', 'metahotels_core_flush_rewrite_rules');
add_action('update_option_metahotels_enable_hotel_surroundings', 'metahotels_core_flush_rewrite_rules');
add_action('update_option_metahotels_enable_facilities', 'metahotels_core_flush_rewrite_rules');
add_action('update_option_metahotels_enable_offers', 'metahotels_core_flush_rewrite_rules');
add_action('update_option_metahotels_enable_careers', 'metahotels_core_flush_rewrite_rules');
add_action('update_option_metahotels_enable_destinations', 'metahotels_core_flush_rewrite_rules');

// Settings page HTML
function metahotels_core_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Show success message if settings were just saved
    if (isset($_GET['settings-updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
    }

    // Get post type enable/disable settings
    $post_types_enabled = array(
        'hotels' => get_option('metahotels_enable_hotels', true),
        'hotel_rooms' => get_option('metahotels_enable_hotel_rooms', true),
        'hotel_surroundings' => get_option('metahotels_enable_hotel_surroundings', true),
        'facilities' => get_option('metahotels_enable_facilities', true),
        'offers' => get_option('metahotels_enable_offers', true),
        'careers' => get_option('metahotels_enable_careers', true),
        'destinations' => get_option('metahotels_enable_destinations', true)
    );
    
    // Get comment disabling setting
    $disable_comments = get_option('metahotels_disable_comments', false);
    
    ?>
    <div class="wrap">
        <h1>MetaHotels Core Settings</h1>
        
        <form method="post" action="options.php">
            <?php settings_fields('metahotels_core_options'); ?>
            
            <h2>Post Type Management</h2>
            <p>Enable or disable post types created by this plugin. Disabling a post type will hide it from the admin menu, but existing posts will remain in the database.</p>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="metahotels_enable_hotels">Enable Hotels</label>
                        </th>
                        <td>
                            <input type="hidden" name="metahotels_enable_hotels" value="0" />
                            <input type="checkbox" 
                                   id="metahotels_enable_hotels" 
                                   name="metahotels_enable_hotels" 
                                   value="1" 
                                   <?php checked($post_types_enabled['hotels'], true); ?> />
                            <p class="description">Show the Hotels post type in the admin menu.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="metahotels_enable_hotel_rooms">Enable Hotel Rooms</label>
                        </th>
                        <td>
                            <input type="hidden" name="metahotels_enable_hotel_rooms" value="0" />
                            <input type="checkbox" 
                                   id="metahotels_enable_hotel_rooms" 
                                   name="metahotels_enable_hotel_rooms" 
                                   value="1" 
                                   <?php checked($post_types_enabled['hotel_rooms'], true); ?> />
                            <p class="description">Show the Hotel Rooms post type in the admin menu.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="metahotels_enable_hotel_surroundings">Enable Hotel Surroundings</label>
                        </th>
                        <td>
                            <input type="hidden" name="metahotels_enable_hotel_surroundings" value="0" />
                            <input type="checkbox" 
                                   id="metahotels_enable_hotel_surroundings" 
                                   name="metahotels_enable_hotel_surroundings" 
                                   value="1" 
                                   <?php checked($post_types_enabled['hotel_surroundings'], true); ?> />
                            <p class="description">Show the Hotel Surroundings post type in the admin menu.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="metahotels_enable_facilities">Enable Facilities</label>
                        </th>
                        <td>
                            <input type="hidden" name="metahotels_enable_facilities" value="0" />
                            <input type="checkbox" 
                                   id="metahotels_enable_facilities" 
                                   name="metahotels_enable_facilities" 
                                   value="1" 
                                   <?php checked($post_types_enabled['facilities'], true); ?> />
                            <p class="description">Show the Facilities post type in the admin menu.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="metahotels_enable_offers">Enable Offers</label>
                        </th>
                        <td>
                            <input type="hidden" name="metahotels_enable_offers" value="0" />
                            <input type="checkbox" 
                                   id="metahotels_enable_offers" 
                                   name="metahotels_enable_offers" 
                                   value="1" 
                                   <?php checked($post_types_enabled['offers'], true); ?> />
                            <p class="description">Show the Offers post type in the admin menu.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="metahotels_enable_careers">Enable Careers</label>
                        </th>
                        <td>
                            <input type="hidden" name="metahotels_enable_careers" value="0" />
                            <input type="checkbox" 
                                   id="metahotels_enable_careers" 
                                   name="metahotels_enable_careers" 
                                   value="1" 
                                   <?php checked($post_types_enabled['careers'], true); ?> />
                            <p class="description">Show the Careers post type in the admin menu.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="metahotels_enable_destinations">Enable Destinations</label>
                        </th>
                        <td>
                            <input type="hidden" name="metahotels_enable_destinations" value="0" />
                            <input type="checkbox" 
                                   id="metahotels_enable_destinations" 
                                   name="metahotels_enable_destinations" 
                                   value="1" 
                                   <?php checked($post_types_enabled['destinations'], true); ?> />
                            <p class="description">Show the Destinations post type in the admin menu.</p>
                        </td>
                    </tr>
                </tbody>
            
            <h2>Site Comments</h2>
            <p>Disable comments across the entire site. This will remove comment support from all post types, close comments on existing posts, and hide comment-related UI elements.</p>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="metahotels_disable_comments">Disable Comments</label>
                        </th>
                        <td>
                            <input type="hidden" name="metahotels_disable_comments" value="0" />
                            <input type="checkbox" 
                                   id="metahotels_disable_comments" 
                                   name="metahotels_disable_comments" 
                                   value="1" 
                                   <?php checked($disable_comments, true); ?> />
                            <p class="description">Disable comments site-wide. This will remove comment functionality from all post types and hide comment-related features in the admin.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <div class="notice notice-info" style="margin-top: 20px;">
            <p><strong>Note:</strong> After changing these settings, you may need to refresh your permalink structure by going to <a href="<?php echo admin_url('options-permalink.php'); ?>">Settings → Permalinks</a> and clicking "Save Changes".</p>
        </div>
    </div>
    <?php
}

// ============================================
// Comment Disabling Functionality
// ============================================

// Remove comment support from all post types
function metahotels_remove_comment_support() {
    if (get_option('metahotels_disable_comments', false)) {
        $post_types = get_post_types(array('public' => true), 'names');
        foreach ($post_types as $post_type) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
}
add_action('init', 'metahotels_remove_comment_support', 100);

// Close comments on the frontend
function metahotels_close_comments($open, $post_id) {
    if (get_option('metahotels_disable_comments', false)) {
        return false;
    }
    return $open;
}
add_filter('comments_open', 'metahotels_close_comments', 20, 2);
add_filter('pings_open', 'metahotels_close_comments', 20, 2);

// Close comments on new posts by default
function metahotels_close_new_posts_comments($post_id, $post) {
    // Check if the option is enabled
    if (!get_option('metahotels_disable_comments', false)) {
        return;
    }

    // Skip if it's a revision or autosave
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }

    // Check if comments are already closed to avoid unnecessary updates
    if ($post->comment_status !== 'closed' || $post->ping_status !== 'closed') {
        global $wpdb;
        
        // Use direct database update to avoid triggering hooks (infinite loop protection)
        $wpdb->update(
            $wpdb->posts,
            array(
                'comment_status' => 'closed',
                'ping_status'    => 'closed'
            ),
            array('ID' => $post_id),
            array('%s', '%s'),
            array('%d')
        );
        
        // Clear post cache so the change is reflected immediately
        clean_post_cache($post_id);
    }
}
add_action('wp_insert_post', 'metahotels_close_new_posts_comments', 10, 2);

// Set default comment status for new posts
function metahotels_default_comment_status($status, $post_type) {
    if (get_option('metahotels_disable_comments', false)) {
        return 'closed';
    }
    return $status;
}
add_filter('get_default_comment_status', 'metahotels_default_comment_status', 10, 2);

// Disable comment feeds
function metahotels_disable_comment_feeds() {
    if (get_option('metahotels_disable_comments', false)) {
        $feed = get_query_var('feed');
        if (in_array($feed, array('comments-rss2', 'comments-rss', 'comments-atom'))) {
            wp_die(__('Comments are disabled.', 'metahotels-core'), '', array('response' => 403));
        }
    }
}
add_action('template_redirect', 'metahotels_disable_comment_feeds', 9);

// Hide comment-related admin menu items
function metahotels_hide_comments_admin_menu() {
    if (get_option('metahotels_disable_comments', false)) {
        remove_menu_page('edit-comments.php');
        remove_submenu_page('options-general.php', 'options-discussion.php');
    }
}
add_action('admin_menu', 'metahotels_hide_comments_admin_menu', 999);

// Remove comment metabox from post edit screen
function metahotels_remove_comments_meta_box() {
    if (get_option('metahotels_disable_comments', false)) {
        $post_types = get_post_types();
        foreach ($post_types as $post_type) {
            remove_meta_box('commentstatusdiv', $post_type, 'normal');
            remove_meta_box('commentsdiv', $post_type, 'normal');
            remove_meta_box('trackbacksdiv', $post_type, 'normal');
        }
    }
}
add_action('admin_init', 'metahotels_remove_comments_meta_box');

// Remove comment column from posts list
function metahotels_remove_comments_column($columns) {
    if (get_option('metahotels_disable_comments', false)) {
        unset($columns['comments']);
    }
    return $columns;
}
add_filter('manage_posts_columns', 'metahotels_remove_comments_column');
add_filter('manage_pages_columns', 'metahotels_remove_comments_column');

// Remove comment-related dashboard widgets
function metahotels_remove_comment_dashboard_widgets() {
    if (get_option('metahotels_disable_comments', false)) {
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    }
}
add_action('wp_dashboard_setup', 'metahotels_remove_comment_dashboard_widgets');

// Remove comment-related admin bar items
function metahotels_remove_comments_admin_bar($wp_admin_bar) {
    if (get_option('metahotels_disable_comments', false)) {
        $wp_admin_bar->remove_node('comments');
    }
}
add_action('admin_bar_menu', 'metahotels_remove_comments_admin_bar', 999);

// Disable comment REST API endpoints
function metahotels_disable_comments_rest_api($endpoints) {
    if (get_option('metahotels_disable_comments', false)) {
        if (isset($endpoints['/wp/v2/comments'])) {
            unset($endpoints['/wp/v2/comments']);
        }
        if (isset($endpoints['/wp/v2/comments/(?P<id>[\d]+)'])) {
            unset($endpoints['/wp/v2/comments/(?P<id>[\d]+)']);
        }
    }
    return $endpoints;
}
add_filter('rest_endpoints', 'metahotels_disable_comments_rest_api');

