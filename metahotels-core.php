<?php
/*
Plugin Name: MetaHotels - Core
Plugin URI: https://www.jyothisjoy.com
Description: The core engine for MetaHotels. Manages custom post types (Hotels, Rooms, Offers, etc.) and integrates seamlessly with Brevo for email and WhatsApp marketing. Features include smart country detection and reCAPTCHA v3 security.
Version: 2.9.1
Author: Jyothis Joy
Author URI: https://www.jyothisjoy.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Calling Custom Post Type File set
include ('lib/post-types/hotel-facilities-pt.php');
include ('lib/post-types/hotel-rooms-pt.php');
include ('lib/post-types/hotel-surroundings-pt.php');
include ('lib/post-types/hotel-offers-pt.php');
include ('lib/post-types/hotel-careers-pt.php');
include ('lib/post-types/hotel-destinations-pt.php');
include ('lib/post-types/hotel-hotels-pt.php');

// Function Includes
include ('lib/functions/metaboxes.php');
include ('lib/functions/post-type-icons.php');
include ('lib/functions/brevo-settings.php');
include ('lib/functions/plugin-settings.php');

// Plugin Update Checker
$puc_path = plugin_dir_path(__FILE__) . 'lib/plugin-update-checker/plugin-update-checker.php';
if (file_exists($puc_path)) {
    require $puc_path;
    $myUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/jyothisjoy/metahotels-core-wp-plugin',
        __FILE__,
        'metahotels-core'
    );
}



// Shortcut Includes
include ('lib/shortcodes/brevo-form-shortcode.php');

// Enhanced conflict resolution for scripts
add_action('wp_enqueue_scripts', 'metahotels_handle_script_conflicts', 1);

function metahotels_handle_script_conflicts() {
    // Ensure jQuery is loaded before our scripts
    wp_enqueue_script('jquery');
}

// Flush rewrite rules on plugin activation
register_activation_hook(__FILE__, 'metahotels_flush_rewrite_rules');

function metahotels_flush_rewrite_rules() {
    // Register post types first
    register_hotel_post_type();
    register_hotel_taxonomies();
    
    // Then flush rewrite rules
    flush_rewrite_rules();
}