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

// Enqueue admin styles
function metahotels_enqueue_admin_scripts($hook) {
    if ('settings_page_metahotels-settings' !== $hook) {
        return;
    }
    wp_enqueue_style('metahotels-admin-style', plugins_url('../assets/admin-style.css', __FILE__), array(), '1.0.0');
}
add_action('admin_enqueue_scripts', 'metahotels_enqueue_admin_scripts');

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
        'destinations' => 'Destinations',
        'restaurants' => 'Restaurants',
        'meetings' => 'Meetings'
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

    // Register video background feature toggle (off by default so its assets
    // are not loaded unless the feature is actually in use).
    //
    // NOTE: This option lives in its own settings group ('metahotels_other_options')
    // rather than 'metahotels_core_options'. Each <form> that posts to options.php
    // only submits the fields it renders, and WordPress resets every *other* option
    // registered in the same group to null. Keeping the "Other Settings" tab in a
    // separate group prevents saving it from wiping the General Settings options
    // (and vice versa).
    register_setting('metahotels_other_options', 'metahotels_enable_video_background', array(
        'type' => 'boolean',
        'default' => false,
        'sanitize_callback' => 'metahotels_sanitize_boolean'
    ));
}
add_action('admin_init', 'metahotels_core_register_settings');

// Sanitize boolean values
if (!function_exists('metahotels_sanitize_boolean')) {
    function metahotels_sanitize_boolean($value) {
        return (bool) $value;
    }
}

// Mark rewrite rules for a single deferred flush per request.
function metahotels_core_schedule_rewrite_flush($old_value = null, $new_value = null) {
    if ($old_value === $new_value) {
        return;
    }
    set_transient('metahotels_core_rewrite_flush_needed', 1, MINUTE_IN_SECONDS * 5);
}

// Flush rewrite rules once after settings updates complete.
function metahotels_core_maybe_flush_rewrite_rules() {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    if (!get_transient('metahotels_core_rewrite_flush_needed')) {
        return;
    }

    delete_transient('metahotels_core_rewrite_flush_needed');
    flush_rewrite_rules();
}

// Flush rewrite rules when post type enable/disable settings change
add_action('update_option_metahotels_enable_hotels', 'metahotels_core_schedule_rewrite_flush', 10, 2);
add_action('update_option_metahotels_enable_hotel_rooms', 'metahotels_core_schedule_rewrite_flush', 10, 2);
add_action('update_option_metahotels_enable_hotel_surroundings', 'metahotels_core_schedule_rewrite_flush', 10, 2);
add_action('update_option_metahotels_enable_facilities', 'metahotels_core_schedule_rewrite_flush', 10, 2);
add_action('update_option_metahotels_enable_offers', 'metahotels_core_schedule_rewrite_flush', 10, 2);
add_action('update_option_metahotels_enable_careers', 'metahotels_core_schedule_rewrite_flush', 10, 2);
add_action('update_option_metahotels_enable_destinations', 'metahotels_core_schedule_rewrite_flush', 10, 2);
add_action('update_option_metahotels_enable_restaurants', 'metahotels_core_schedule_rewrite_flush', 10, 2);
add_action('update_option_metahotels_enable_meetings', 'metahotels_core_schedule_rewrite_flush', 10, 2);
add_action('shutdown', 'metahotels_core_maybe_flush_rewrite_rules', 999);

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
    
    // Define post types for easier iteration
    $post_types_map = array(
        'hotels' => 'Hotels',
        'hotel_rooms' => 'Hotel Rooms',
        'hotel_surroundings' => 'Hotel Surroundings',
        'facilities' => 'Facilities',
        'offers' => 'Offers',
        'careers' => 'Careers',
        'destinations' => 'Destinations',
        'restaurants' => 'Restaurants',
        'meetings' => 'Meetings'
    );
    ?>
    <div class="wrap metahotels-settings-wrap">
        <h1>MetaHotels Core Settings</h1>
        
        <!-- Navigation Tabs -->
        <div class="metahotels-tabs">
            <button type="button" class="metahotels-tab active" data-tab="general">General Settings</button>
            <button type="button" class="metahotels-tab" data-tab="marketing">Marketing Settings</button>
            <button type="button" class="metahotels-tab" data-tab="other">Other Settings</button>
            <button type="button" class="metahotels-tab" data-tab="information">Information</button>
        </div>

        <!-- General Settings Tab Content -->
        <div id="tab-general" class="metahotels-tab-content active">
            <?php
            // Get post type enable/disable settings
            $post_types_enabled = array(
                'hotels' => get_option('metahotels_enable_hotels', true),
                'hotel_rooms' => get_option('metahotels_enable_hotel_rooms', true),
                'hotel_surroundings' => get_option('metahotels_enable_hotel_surroundings', true),
                'facilities' => get_option('metahotels_enable_facilities', true),
                'offers' => get_option('metahotels_enable_offers', true),
                'careers' => get_option('metahotels_enable_careers', true),
                'destinations' => get_option('metahotels_enable_destinations', true),
                'restaurants' => get_option('metahotels_enable_restaurants', true),
                'meetings' => get_option('metahotels_enable_meetings', true)
            );
            
            // Get comment disabling setting
            $disable_comments = metahotels_comments_disabled();
            ?>
            
            <form method="post" action="options.php">
                <?php settings_fields('metahotels_core_options'); ?>
                
                <div class="metahotels-section">
                    <div class="metahotels-card">
                        <div class="metahotels-card-header">
                            <h3 class="metahotels-card-title">Post Type Management</h3>
                            <p class="metahotels-card-description">Enable or disable post types created by this plugin. Disabling a post type will hide it from the admin menu.</p>
                        </div>
                        <div class="metahotels-card-content">
                            <?php
                                $icons = array(
                                    'hotels'             => 'dashicons-building',
                                    'hotel_rooms'        => 'dashicons-admin-home',
                                    'hotel_surroundings' => 'dashicons-admin-site',
                                    'facilities'         => 'dashicons-admin-tools',
                                    'offers'             => 'dashicons-tag',
                                    'careers'            => 'dashicons-businessman',
                                    'destinations'       => 'dashicons-location',
                                    'restaurants'        => 'dashicons-food',
                                    'meetings'           => 'dashicons-groups',
                                );
                            ?>
                            <div class="metahotels-grid">
                            <?php foreach ($post_types_map as $key => $label):
                                $option_name = 'metahotels_enable_' . $key;
                                $is_enabled  = get_option($option_name, true);
                            ?>
                                <div class="metahotels-switch-wrapper">
                                    <div class="metahotels-switch-label">
                                        <span class="metahotels-switch-title">
                                            <?php if (isset($icons[$key])): ?>
                                                <span class="dashicons <?php echo esc_attr($icons[$key]); ?>" style="font-size: 1.25rem; width: 1.25rem; height: 1.25rem; vertical-align: middle; margin-right: 0.5rem; color: #64748b;"></span>
                                            <?php endif; ?>
                                            <?php echo esc_html($label); ?>
                                        </span>
                                        <span class="metahotels-switch-desc"><?php echo $is_enabled ? esc_html__('Active', 'metahotels-core') : esc_html__('Disabled', 'metahotels-core'); ?></span>
                                    </div>
                                    <label class="metahotels-switch">
                                        <input type="hidden" name="<?php echo esc_attr($option_name); ?>" value="0" />
                                        <input type="checkbox" 
                                               name="<?php echo esc_attr($option_name); ?>" 
                                               value="1" 
                                               <?php checked($is_enabled, true); ?> />
                                        <span class="metahotels-slider"></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="metahotels-card">
                        <div class="metahotels-card-header">
                            <h3 class="metahotels-card-title">Site Comments</h3>
                            <p class="metahotels-card-description">Manage comment settings across the entire site.</p>
                        </div>
                        <div class="metahotels-card-content">
                            <?php $disable_comments = metahotels_comments_disabled(); ?>
                            <div class="metahotels-switch-wrapper">
                                <div class="metahotels-switch-label">
                                    <span class="metahotels-switch-title">Disable All Comments</span>
                                    <span class="metahotels-switch-desc">This will remove comment support from all post types and hide comment UI.</span>
                                </div>
                                <label class="metahotels-switch">
                                    <input type="hidden" name="metahotels_disable_comments" value="0" />
                                    <input type="checkbox" 
                                           name="metahotels_disable_comments" 
                                           value="1" 
                                           <?php checked($disable_comments, true); ?> />
                                    <span class="metahotels-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="notice notice-info" style="margin-top: 20px;">
                <p><strong>Note:</strong> After changing these settings, you may need to refresh your permalink structure by going to <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>">Settings &rarr; Permalinks</a> and clicking "Save Changes".</p>
            </div>
        </div>

        <!-- Marketing Settings Tab Content -->
        <div id="tab-marketing" class="metahotels-tab-content">
             <?php 
            if (function_exists('metahotels_brevo_render_content')) {
                metahotels_brevo_render_content();
            } else {
                echo '<div class="metahotels-card"><div class="metahotels-card-content"><p>Marketing settings are not available.</p></div></div>';
            }
            ?>
        </div>

        <!-- Other Settings Tab Content -->
        <div id="tab-other" class="metahotels-tab-content">
            <form method="post" action="options.php">
                <?php settings_fields('metahotels_other_options'); ?>

                <div class="metahotels-section">
                    <div class="metahotels-card">
                        <div class="metahotels-card-header">
                            <h3 class="metahotels-card-title"><?php esc_html_e('Elementor Enhancements', 'metahotels-core'); ?></h3>
                            <p class="metahotels-card-description"><?php esc_html_e('Optional front-end features. Their assets load only while the feature is enabled.', 'metahotels-core'); ?></p>
                        </div>
                        <div class="metahotels-card-content">
                            <?php $vbg_enabled = (bool) get_option('metahotels_enable_video_background', false); ?>
                            <div class="metahotels-switch-wrapper">
                                <div class="metahotels-switch-label">
                                    <span class="metahotels-switch-title">
                                        <span class="dashicons dashicons-format-video" style="font-size: 1.25rem; width: 1.25rem; height: 1.25rem; vertical-align: middle; margin-right: 0.5rem; color: #64748b;"></span>
                                        <?php esc_html_e('Video Background for Sections', 'metahotels-core'); ?>
                                    </span>
                                    <span class="metahotels-switch-desc"><?php esc_html_e('Adds a video-background option (R2 / YouTube) to Elementor Sections and Containers. When off, its CSS and JS are not loaded.', 'metahotels-core'); ?></span>
                                </div>
                                <label class="metahotels-switch">
                                    <input type="hidden" name="metahotels_enable_video_background" value="0" />
                                    <input type="checkbox"
                                           name="metahotels_enable_video_background"
                                           value="1"
                                           <?php checked($vbg_enabled, true); ?> />
                                    <span class="metahotels-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>

        <!-- Information Tab Content -->
        <div id="tab-information" class="metahotels-tab-content">
            <?php
            if (function_exists('metahotels_information_render_content')) {
                metahotels_information_render_content();
            } else {
                echo '<div class="metahotels-card"><div class="metahotels-card-content"><p>Information is not available.</p></div></div>';
            }
            ?>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Simple tab switching logic
        const settingsWrap = document.querySelector('.metahotels-settings-wrap');
        if (!settingsWrap) {
            return;
        }

        const allowedTabs = ['general', 'marketing', 'other', 'information'];
        const tabs = settingsWrap.querySelectorAll('.metahotels-tab');
        const contents = settingsWrap.querySelectorAll('.metahotels-tab-content');
        
        // Restore active tab from localStorage
        const savedTab = localStorage.getItem('metahotels_active_tab');
        if (savedTab && allowedTabs.includes(savedTab)) {
            const activeTabBtn = settingsWrap.querySelector(`.metahotels-tab[data-tab="${savedTab}"]`);
            if (activeTabBtn) {
                switchTab(savedTab);
            }
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                const tabId = this.getAttribute('data-tab');
                if (!allowedTabs.includes(tabId)) {
                    return;
                }
                switchTab(tabId);
                localStorage.setItem('metahotels_active_tab', tabId);
            });
        });

        function switchTab(tabId) {
            // Deactivate all
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            
            // Activate selected
            const selectedTab = settingsWrap.querySelector(`.metahotels-tab[data-tab="${tabId}"]`);
            const selectedContent = document.getElementById('tab-' + tabId);
            
            if (selectedTab && selectedContent) {
                selectedTab.classList.add('active');
                selectedContent.classList.add('active');
            }
        }
    });
    </script>
    <?php
}

// ============================================
// Comment Disabling Functionality
// ============================================

/**
 * Single cached read of the disable-comments option for this request.
 * Avoids 8+ repeated get_option() calls across hooks.
 */
function metahotels_comments_disabled() {
    static $disabled = null;
    if ($disabled === null) {
        $disabled = (bool) get_option('metahotels_disable_comments', false);
    }
    return $disabled;
}

// Remove comment support from all post types
function metahotels_remove_comment_support() {
    if (metahotels_comments_disabled()) {
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
    if (metahotels_comments_disabled()) {
        return false;
    }
    return $open;
}
add_filter('comments_open', 'metahotels_close_comments', 20, 2);
add_filter('pings_open', 'metahotels_close_comments', 20, 2);

// Close comments on new posts by default
function metahotels_close_new_posts_comments($post_id, $post) {
    // Check if the option is enabled
    if (!metahotels_comments_disabled()) {
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
    if (metahotels_comments_disabled()) {
        return 'closed';
    }
    return $status;
}
add_filter('get_default_comment_status', 'metahotels_default_comment_status', 10, 2);

// Disable comment feeds
function metahotels_disable_comment_feeds() {
    if (metahotels_comments_disabled()) {
        $feed = get_query_var('feed');
        if (in_array($feed, array('comments-rss2', 'comments-rss', 'comments-atom'), true)) {
            wp_die(__('Comments are disabled.', 'metahotels-core'), '', array('response' => 403));
        }
    }
}
add_action('template_redirect', 'metahotels_disable_comment_feeds', 9);

// Hide comment-related admin menu items
function metahotels_hide_comments_admin_menu() {
    if (metahotels_comments_disabled()) {
        remove_menu_page('edit-comments.php');
        remove_submenu_page('options-general.php', 'options-discussion.php');
    }
}
add_action('admin_menu', 'metahotels_hide_comments_admin_menu', 999);

// Remove comment metabox from post edit screen
function metahotels_remove_comments_meta_box() {
    if (metahotels_comments_disabled()) {
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
    if (metahotels_comments_disabled()) {
        unset($columns['comments']);
    }
    return $columns;
}
add_filter('manage_posts_columns', 'metahotels_remove_comments_column');
add_filter('manage_pages_columns', 'metahotels_remove_comments_column');

// Remove comment-related dashboard widgets
function metahotels_remove_comment_dashboard_widgets() {
    if (metahotels_comments_disabled()) {
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    }
}
add_action('wp_dashboard_setup', 'metahotels_remove_comment_dashboard_widgets');

// Remove comment-related admin bar items
function metahotels_remove_comments_admin_bar($wp_admin_bar) {
    if (metahotels_comments_disabled()) {
        $wp_admin_bar->remove_node('comments');
    }
}
add_action('admin_bar_menu', 'metahotels_remove_comments_admin_bar', 999);

// Disable comment REST API endpoints
function metahotels_disable_comments_rest_api($endpoints) {
    if (metahotels_comments_disabled()) {
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

