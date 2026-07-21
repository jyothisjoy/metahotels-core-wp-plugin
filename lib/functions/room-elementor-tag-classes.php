<?php
/**
 * Elementor dynamic tag classes for Hotel Room fields.
 *
 * IMPORTANT: This file is only required from inside the
 * 'elementor/dynamic_tags/register' hook (see room-elementor-tags.php), so the
 * \Elementor\ base classes are guaranteed to be loaded before these class
 * declarations are parsed. Never require it unconditionally.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('MetaHotels_Room_Base_Tag') && class_exists('\Elementor\Core\DynamicTags\Tag')) {

    /**
     * Base tag: renders a single Hotel Room meta value.
     */
    abstract class MetaHotels_Room_Base_Tag extends \Elementor\Core\DynamicTags\Tag {

        /**
         * Meta key this tag reads. Defined by each subclass.
         */
        abstract protected function meta_key();

        public function get_group() {
            return 'metahotels_room';
        }

        public function get_categories() {
            return array(
                \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
                \Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY,
            );
        }

        public function get_title() {
            $fields   = function_exists('metahotels_room_fields') ? metahotels_room_fields() : array();
            $meta_key = $this->meta_key();
            return isset($fields[$meta_key]['tag_title']) ? $fields[$meta_key]['tag_title'] : $meta_key;
        }

        public function render() {
            $post_id = get_the_ID();
            if (!$post_id) {
                return;
            }
            echo esc_html(get_post_meta($post_id, $this->meta_key(), true));
        }
    }

    class MetaHotels_Room_Occupancy_Adults_Tag extends MetaHotels_Room_Base_Tag {
        public function get_name() { return 'mh-room-occupancy-adults'; }
        protected function meta_key() { return '_room_occupancy_adults'; }
    }

    class MetaHotels_Room_Occupancy_Kids_Tag extends MetaHotels_Room_Base_Tag {
        public function get_name() { return 'mh-room-occupancy-kids'; }
        protected function meta_key() { return '_room_occupancy_kids'; }
    }

    class MetaHotels_Room_Max_Occupancy_Tag extends MetaHotels_Room_Base_Tag {
        public function get_name() { return 'mh-room-max-occupancy'; }
        protected function meta_key() { return '_room_max_occupancy'; }
    }

    class MetaHotels_Room_Count_Tag extends MetaHotels_Room_Base_Tag {
        public function get_name() { return 'mh-room-count'; }
        protected function meta_key() { return '_room_count'; }
    }

    class MetaHotels_Room_Area_Tag extends MetaHotels_Room_Base_Tag {
        public function get_name() { return 'mh-room-area'; }
        protected function meta_key() { return '_room_area'; }

        // Area is stored as free text (e.g. "25 m²"), so it is text-only.
        public function get_categories() {
            return array(\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY);
        }
    }
}
