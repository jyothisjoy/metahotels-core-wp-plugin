<?php
/*
Plugin Name: MetaHotels - Core
Plugin URI: https://www.jyothisjoy.com
Description: MetaHotels - Core is a powerful WordPress plugin designed to showcase the various details of hotels. Includes a [countdown_timer_25h] shortcode that displays a live 25-hour countdown timer, resetting every 25 hours from a fixed reference point. Use this shortcode in posts, pages, or templates to show a dynamic countdown for special offers, events, or other time-based features. Also includes Brevo integration for email marketing and WhatsApp communication.
Version: 2.7
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
include ('lib/functions/hotel-manager.php');

// Shortcut Includes
include ('lib/shortcodes/rooms-shortcode.php');
include ('lib/shortcodes/countdown-timer-shortcode.php');
include ('lib/shortcodes/brevo-form-shortcode.php');

// Enhanced conflict resolution for scripts
add_action('wp_enqueue_scripts', 'metahotels_handle_script_conflicts', 1);

function metahotels_handle_script_conflicts() {
    // Ensure jQuery is loaded before our scripts
    wp_enqueue_script('jquery');
    
    // Note: Script loading is now handled in the shortcode file to prevent duplicates
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