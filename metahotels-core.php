<?php
/*
Plugin Name: MetaHotels - Core
Plugin URI: https://www.jyothisjoy.com
Description: The core engine for MetaHotels. Manages custom post types (Hotels, Rooms, Offers, etc.) and integrates seamlessly with Brevo for email and WhatsApp marketing. Features include smart country detection and reCAPTCHA v3 security.
Version: 2.9.6
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
require_once plugin_dir_path(__FILE__) . 'lib/post-types/hotel-facilities-pt.php';
require_once plugin_dir_path(__FILE__) . 'lib/post-types/hotel-rooms-pt.php';
require_once plugin_dir_path(__FILE__) . 'lib/post-types/hotel-surroundings-pt.php';
require_once plugin_dir_path(__FILE__) . 'lib/post-types/hotel-offers-pt.php';
require_once plugin_dir_path(__FILE__) . 'lib/post-types/hotel-careers-pt.php';
require_once plugin_dir_path(__FILE__) . 'lib/post-types/hotel-destinations-pt.php';
require_once plugin_dir_path(__FILE__) . 'lib/post-types/hotel-hotels-pt.php';
require_once plugin_dir_path(__FILE__) . 'lib/post-types/hotel-restaurants-pt.php';
require_once plugin_dir_path(__FILE__) . 'lib/post-types/hotel-meetings-pt.php';

// Function Includes
require_once plugin_dir_path(__FILE__) . 'lib/functions/metaboxes.php';
require_once plugin_dir_path(__FILE__) . 'lib/functions/post-type-icons.php';
require_once plugin_dir_path(__FILE__) . 'lib/functions/brevo-settings.php';
require_once plugin_dir_path(__FILE__) . 'lib/functions/plugin-settings.php';
require_once plugin_dir_path(__FILE__) . 'lib/functions/room-elementor-tags.php';
require_once plugin_dir_path(__FILE__) . 'lib/functions/vbg-elementor.php';

// Plugin Update Checker
$puc_path = plugin_dir_path(__FILE__) . 'lib/plugin-update-checker/plugin-update-checker.php';
if (file_exists($puc_path)) {
    require $puc_path;
    YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/jyothisjoy/metahotels-core-wp-plugin',
        __FILE__,
        'metahotels-core'
    );
}

// Shortcode Includes
require_once plugin_dir_path(__FILE__) . 'lib/shortcodes/brevo-form-shortcode.php';

// Flush rewrite rules on plugin activation
register_activation_hook(__FILE__, 'metahotels_flush_rewrite_rules');

function metahotels_flush_rewrite_rules() {
    // Register all plugin post types/taxonomies before flushing.
    $registrars = array(
        'metahotels_register_hotel_post_type',
        'metahotels_register_hotel_taxonomies',
        'metahotels_register_hotel_rooms_post_type',
        'metahotels_register_hotel_category_taxonomy',
        'metahotels_register_hotel_surroundings_post_type',
        'metahotels_register_surroundings_category_taxonomy',
        'metahotels_register_facility_post_type',
        'metahotels_register_offers_post_type',
        'metahotels_register_offer_taxonomy',
        'metahotels_register_careers_post_type',
        'metahotels_register_career_category_taxonomy',
        'metahotels_register_destination_post_type',
        'metahotels_register_destination_taxonomies',
        'metahotels_register_restaurant_post_type',
        'metahotels_register_meeting_post_type',
    );

    foreach ($registrars as $callback) {
        if (function_exists($callback)) {
            call_user_func($callback);
        }
    }

    flush_rewrite_rules();
}
