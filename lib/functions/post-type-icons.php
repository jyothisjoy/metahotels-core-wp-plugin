<?php
/**
 * MetaHotels Core - Post Type Icons
 * 
 * This file handles custom icons for all post types in the MetaHotels plugin.
 * Uses WordPress Dashicons for consistent admin interface styling.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the appropriate Dashicon for each post type
 * 
 * @param string $post_type The post type name
 * @return string The Dashicon class name
 */
function metahotels_get_post_type_icon($post_type) {
    $icons = array(
        'hotel' => 'dashicons-building',           // Building icon for hotels
        'hotel_room' => 'dashicons-admin-home',    // Home icon for rooms
        'offer' => 'dashicons-tag',                // Tag icon for offers
        'career' => 'dashicons-businessman',       // Business person icon for careers
        'destination' => 'dashicons-location',     // Location icon for destinations
        'facility' => 'dashicons-admin-tools',     // Tools icon for facilities
        'hotel_surrounding' => 'dashicons-admin-site', // Site icon for surroundings
        'restaurant' => 'dashicons-food',        // Food icon for restaurants
        'meeting' => 'dashicons-groups'            // Groups icon for meetings
    );
    
    return isset($icons[$post_type]) ? $icons[$post_type] : 'dashicons-admin-post';
}

/**
 * Add custom CSS for post type icons
 */
function metahotels_post_type_icons_css() {
    ?>
    <style type="text/css">
        /* Custom styling for post type icons */
        #adminmenu .wp-menu-image.dashicons-building:before {
            color: #46b450;
        }
        #adminmenu .wp-menu-image.dashicons-admin-home:before {
            color: #46b450;
        }
        #adminmenu .wp-menu-image.dashicons-tag:before {
            color: #46b450;
        }
        #adminmenu .wp-menu-image.dashicons-businessman:before {
            color: #46b450;
        }
        #adminmenu .wp-menu-image.dashicons-location:before {
            color: #46b450;
        }
        #adminmenu .wp-menu-image.dashicons-admin-tools:before {
            color: #46b450;
        }
        #adminmenu .wp-menu-image.dashicons-admin-site:before {
            color: #46b450;
        }
        #adminmenu .wp-menu-image.dashicons-food:before {
            color: #46b450;
        }
        #adminmenu .wp-menu-image.dashicons-groups:before {
            color: #46b450;
        }
    </style>
    <?php
}
add_action('admin_head', 'metahotels_post_type_icons_css'); 