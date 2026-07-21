<?php
if (!defined('ABSPATH')) {
    exit;
}

function metahotels_register_hotel_rooms_post_type() {
    // Check if post type is enabled
    if (!get_option('metahotels_enable_hotel_rooms', true)) {
        return; // Don't register if disabled
    }

    $labels = array(
        'name'               => 'Hotel Rooms',
        'singular_name'      => 'Hotel Room',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Hotel Room',
        'edit_item'          => 'Edit Hotel Room',
        'new_item'           => 'New Hotel Room',
        'view_item'          => 'View Hotel Room',
        'search_items'       => 'Search Hotel Rooms',
        'not_found'          => 'No hotel rooms found',
        'not_found_in_trash' => 'No hotel rooms found in trash',
        'parent_item_colon'  => '',
        'menu_name'          => 'Hotel Rooms'
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array( 'slug' => 'room' ),
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_position'       => 5,
        'menu_icon'           => 'dashicons-admin-home',
        'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author' ),
        'taxonomies'          => array( 'hotel_category' )
    );

    register_post_type( 'hotel_room', $args );
}
add_action( 'init', 'metahotels_register_hotel_rooms_post_type' );

function metahotels_register_hotel_category_taxonomy() {
    // Only register taxonomy if the hotel rooms post type is enabled
    if (!get_option('metahotels_enable_hotel_rooms', true)) {
        return;
    }

    $labels = array(
        'name'              => 'Hotel Categories',
        'singular_name'     => 'Hotel Category',
        'search_items'      => 'Search Hotel Categories',
        'all_items'         => 'All Hotel Categories',
        'parent_item'       => 'Parent Hotel Category',
        'parent_item_colon' => 'Parent Hotel Category:',
        'edit_item'         => 'Edit Hotel Category',
        'update_item'       => 'Update Hotel Category',
        'add_new_item'      => 'Add New Hotel Category',
        'new_item_name'     => 'New Hotel Category Name',
        'menu_name'         => 'Hotel Categories',
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'hotel-category' ),
    );

    register_taxonomy( 'hotel_category', 'hotel_room', $args );
}
add_action( 'init', 'metahotels_register_hotel_category_taxonomy' );

/**
 * Canonical definition of the Hotel Room detail fields.
 *
 * Single source of truth shared by the "Room Details" metabox, the
 * register_post_meta() call, the Elementor dynamic tags, and the
 * "Information" reference tab in the settings page. Keep additions here
 * and every consumer stays in sync.
 *
 * @return array<string,array> Keyed by meta key (leading underscore = protected/hidden).
 */
function metahotels_room_fields() {
    return array(
        '_room_occupancy_adults' => array( 'label' => 'Occupancy Adults',      'input' => 'number', 'type' => 'integer', 'tag_slug' => 'mh-room-occupancy-adults', 'tag_title' => 'Room: Occupancy Adults' ),
        '_room_occupancy_kids'   => array( 'label' => 'Occupancy Kids',        'input' => 'number', 'type' => 'integer', 'tag_slug' => 'mh-room-occupancy-kids',   'tag_title' => 'Room: Occupancy Kids' ),
        '_room_max_occupancy'    => array( 'label' => 'Maximum No. of People', 'input' => 'number', 'type' => 'integer', 'tag_slug' => 'mh-room-max-occupancy',    'tag_title' => 'Room: Max People' ),
        '_room_count'            => array( 'label' => 'No. of Rooms',          'input' => 'number', 'type' => 'integer', 'tag_slug' => 'mh-room-count',            'tag_title' => 'Room: No. of Rooms' ),
        '_room_area'             => array( 'label' => 'Area',                  'input' => 'text',   'type' => 'string',  'tag_slug' => 'mh-room-area',             'tag_title' => 'Room: Area' ),
    );
}

/**
 * The POST field name for a meta key (meta key without the leading underscore).
 */
function metahotels_room_field_name( $meta_key ) {
    return ltrim( $meta_key, '_' );
}

// Register the "Room Details" meta box.
function metahotels_add_hotel_room_meta_boxes() {
    add_meta_box(
        'hotel_room_details',
        'Room Details',
        'metahotels_room_details_meta_box_html',
        'hotel_room',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'metahotels_add_hotel_room_meta_boxes');

// Render the "Room Details" meta box content.
function metahotels_room_details_meta_box_html($post) {
    // Add nonce for security
    wp_nonce_field('hotel_room_meta_box', 'hotel_room_meta_box_nonce');

    foreach (metahotels_room_fields() as $meta_key => $def) {
        $field_name = metahotels_room_field_name($meta_key);
        $value      = get_post_meta($post->ID, $meta_key, true);
        ?>
        <p>
            <label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($def['label']); ?>:</label><br>
            <?php if ('number' === $def['input']): ?>
                <input type="number" min="0" step="1" id="<?php echo esc_attr($field_name); ?>" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($value); ?>" style="width:100%">
            <?php else: ?>
                <input type="text" id="<?php echo esc_attr($field_name); ?>" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($value); ?>" style="width:100%">
            <?php endif; ?>
        </p>
        <?php
    }
}

// Save the "Room Details" meta box data.
function metahotels_save_room_details_meta_box($post_id) {
    // Check if nonce is set and valid
    if (!isset($_POST['hotel_room_meta_box_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['hotel_room_meta_box_nonce']));
    if (!wp_verify_nonce($nonce, 'hotel_room_meta_box')) {
        return;
    }

    // Check if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save each field with type-appropriate sanitisation.
    foreach (metahotels_room_fields() as $meta_key => $def) {
        $field_name = metahotels_room_field_name($meta_key);
        if (!isset($_POST[$field_name])) {
            continue;
        }

        if ('number' === $def['input']) {
            update_post_meta($post_id, $meta_key, absint(wp_unslash($_POST[$field_name])));
        } else {
            update_post_meta($post_id, $meta_key, sanitize_text_field(wp_unslash($_POST[$field_name])));
        }
    }
}
add_action('save_post_hotel_room', 'metahotels_save_room_details_meta_box');

// Register the room meta so it is typed, sanitised and available via REST.
function metahotels_register_room_meta() {
    foreach (metahotels_room_fields() as $meta_key => $def) {
        register_post_meta('hotel_room', $meta_key, array(
            'type'              => $def['type'],
            'single'            => true,
            'sanitize_callback' => ('number' === $def['input']) ? 'absint' : 'sanitize_text_field',
            'show_in_rest'      => true,
            'auth_callback'     => '__return_true',
        ));
    }
}
add_action('init', 'metahotels_register_room_meta');

/**
 * Render the read-only "Information" tab content on the settings page.
 *
 * Documents each Room field, its meta key and the matching Elementor
 * dynamic tag so template builders have a reference. Called by
 * metahotels_core_settings_page() when the function exists.
 */
function metahotels_information_render_content() {
    ?>
    <div class="metahotels-card">
        <div class="metahotels-card-header">
            <h3 class="metahotels-card-title">Hotel Room Fields</h3>
            <p class="metahotels-card-description">Reference for the custom fields on the Hotel Room post type. In Elementor, these are available as dynamic tags under the <strong>&ldquo;Hotel Room&rdquo;</strong> group (click the dynamic/database icon on any widget control).</p>
        </div>
        <div class="metahotels-card-content">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col">Field</th>
                        <th scope="col">Meta key</th>
                        <th scope="col">Elementor dynamic tag</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (metahotels_room_fields() as $meta_key => $def): ?>
                        <tr>
                            <td><?php echo esc_html($def['label']); ?></td>
                            <td><code><?php echo esc_html($meta_key); ?></code></td>
                            <td><?php echo esc_html($def['tag_title']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
