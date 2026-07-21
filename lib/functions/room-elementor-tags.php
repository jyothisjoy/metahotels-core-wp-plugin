<?php
/**
 * Registers Hotel Room custom fields as Elementor dynamic tags.
 *
 * Adds a "Hotel Room" dynamic-tag group so each room field (Occupancy Adults,
 * Kids, Max People, No. of Rooms, Area) is selectable by name in Elementor's
 * dynamic-content picker / Loop Grid — no manual meta-key typing.
 *
 * Safe when Elementor is not active: the hook simply never fires, and the tag
 * class file (which extends \Elementor\ base classes) is only required here.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager
 */
function metahotels_register_room_dynamic_tags($dynamic_tags_manager) {
    // Group all room tags together in the dynamic picker.
    $dynamic_tags_manager->register_group('metahotels_room', array(
        'title' => 'Hotel Room',
    ));

    // Elementor base classes are loaded at this point — safe to declare tag classes.
    require_once plugin_dir_path(__FILE__) . 'room-elementor-tag-classes.php';

    if (!class_exists('MetaHotels_Room_Base_Tag')) {
        return;
    }

    $tags = array(
        'MetaHotels_Room_Occupancy_Adults_Tag',
        'MetaHotels_Room_Occupancy_Kids_Tag',
        'MetaHotels_Room_Max_Occupancy_Tag',
        'MetaHotels_Room_Count_Tag',
        'MetaHotels_Room_Area_Tag',
    );

    foreach ($tags as $tag_class) {
        $dynamic_tags_manager->register(new $tag_class());
    }
}
add_action('elementor/dynamic_tags/register', 'metahotels_register_room_dynamic_tags');
