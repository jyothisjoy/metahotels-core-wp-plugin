<?php
if (!defined('ABSPATH')) {
    exit;
}

// Register the custom post type "Hotel Surroundings"
function metahotels_register_hotel_surroundings_post_type() {
    // Check if post type is enabled
    if (!get_option('metahotels_enable_hotel_surroundings', true)) {
        return; // Don't register if disabled
    }

    $labels = array(
        'name' => 'Hotel Surroundings',
        'singular_name' => 'Hotel Surrounding',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Hotel Surrounding',
        'edit_item' => 'Edit Hotel Surrounding',
        'new_item' => 'New Hotel Surrounding',
        'view_item' => 'View Hotel Surrounding',
        'search_items' => 'Search Hotel Surroundings',
        'not_found' => 'No hotel surroundings found',
        'not_found_in_trash' => 'No hotel surroundings found in trash',
        'parent_item_colon' => 'Parent Hotel Surrounding:',
        'menu_name' => 'Hotel Surroundings',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'rewrite' => array('slug' => 'nearby'),
        'capability_type' => 'post',
        'has_archive' => false,
        'hierarchical' => false,
        'menu_position' => 20,
        'menu_icon' => 'dashicons-admin-site',
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'author')
    );

    register_post_type('hotel_surrounding', $args);
}
add_action('init', 'metahotels_register_hotel_surroundings_post_type');

// Register the taxonomy "Surroundings Category" for "Hotel Surroundings" post type
function metahotels_register_surroundings_category_taxonomy() {
    // Only register taxonomy if the hotel surroundings post type is enabled
    if (!get_option('metahotels_enable_hotel_surroundings', true)) {
        return;
    }

    $labels = array(
        'name' => 'Surroundings Categories',
        'singular_name' => 'Surroundings Category',
        'search_items' => 'Search Surroundings Categories',
        'all_items' => 'All Surroundings Categories',
        'parent_item' => 'Parent Surroundings Category',
        'parent_item_colon' => 'Parent Surroundings Category:',
        'edit_item' => 'Edit Surroundings Category',
        'update_item' => 'Update Surroundings Category',
        'add_new_item' => 'Add New Surroundings Category',
        'new_item_name' => 'New Surroundings Category Name',
        'menu_name' => 'Surroundings Categories',
    );

    $args = array(
        'hierarchical' => true,
        'labels' => $labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'surroundings-category'),
    );

    register_taxonomy('surroundings_category', 'hotel_surrounding', $args);
}
add_action('init', 'metahotels_register_surroundings_category_taxonomy');

// Add custom meta boxes for additional fields
function metahotels_add_hotel_surroundings_meta_boxes() {
    add_meta_box(
        'hotel_surroundings_details',
        'Location Details',
        'metahotels_render_hotel_surroundings_meta_box',
        'hotel_surrounding',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'metahotels_add_hotel_surroundings_meta_boxes');

// Render meta box content
function metahotels_render_hotel_surroundings_meta_box($post) {
    // Add nonce for security
    wp_nonce_field('hotel_surroundings_meta_box', 'hotel_surroundings_meta_box_nonce');

    // Get existing values
    $address = get_post_meta($post->ID, '_surrounding_address', true);
    $distance = get_post_meta($post->ID, '_surrounding_distance', true);
    $travel_options = get_post_meta($post->ID, '_surrounding_travel_options', true);
    $map_link = get_post_meta($post->ID, '_surrounding_map_link', true);
    $opening_hours = get_post_meta($post->ID, '_surrounding_opening_hours', true);
    $phone = get_post_meta($post->ID, '_surrounding_phone', true);
    $website = get_post_meta($post->ID, '_surrounding_website', true);
    $email = get_post_meta($post->ID, '_surrounding_email', true);

    // Output form fields
    ?>
    <p>
        <label for="surrounding_address">Address:</label><br>
        <textarea id="surrounding_address" name="surrounding_address" rows="3" style="width:100%"><?php echo esc_textarea($address); ?></textarea>
    </p>
    <p>
        <label for="surrounding_distance">Distance from Hotel:</label><br>
        <input type="text" id="surrounding_distance" name="surrounding_distance" value="<?php echo esc_attr($distance); ?>" style="width:100%">
    </p>
    <p>
        <label for="surrounding_travel_options">Travel Options:</label><br>
        <textarea id="surrounding_travel_options" name="surrounding_travel_options" rows="3" style="width:100%"><?php echo esc_textarea($travel_options); ?></textarea>
    </p>
    <p>
        <label for="surrounding_map_link">Google Map Link:</label><br>
        <input type="url" id="surrounding_map_link" name="surrounding_map_link" value="<?php echo esc_url($map_link); ?>" style="width:100%">
    </p>
    <p>
        <label for="surrounding_opening_hours">Opening Hours:</label><br>
        <textarea id="surrounding_opening_hours" name="surrounding_opening_hours" rows="3" style="width:100%"><?php echo esc_textarea($opening_hours); ?></textarea>
    </p>
    <p>
        <label for="surrounding_phone">Phone:</label><br>
        <input type="tel" id="surrounding_phone" name="surrounding_phone" value="<?php echo esc_attr($phone); ?>" style="width:100%">
    </p>
    <p>
        <label for="surrounding_website">Website:</label><br>
        <input type="url" id="surrounding_website" name="surrounding_website" value="<?php echo esc_url($website); ?>" style="width:100%">
    </p>
    <p>
        <label for="surrounding_email">Email:</label><br>
        <input type="email" id="surrounding_email" name="surrounding_email" value="<?php echo esc_attr($email); ?>" style="width:100%">
    </p>
    <?php
}

// Save meta box data
function metahotels_save_hotel_surroundings_meta_box($post_id) {
    // Check if nonce is set and valid
    if (!isset($_POST['hotel_surroundings_meta_box_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['hotel_surroundings_meta_box_nonce']));
    if (!wp_verify_nonce($nonce, 'hotel_surroundings_meta_box')) {
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

    // Save the meta box data with field-appropriate sanitisation
    $text_fields = array(
        'surrounding_address'       => '_surrounding_address',
        'surrounding_distance'      => '_surrounding_distance',
        'surrounding_travel_options' => '_surrounding_travel_options',
        'surrounding_opening_hours' => '_surrounding_opening_hours',
        'surrounding_phone'         => '_surrounding_phone',
    );
    $url_fields = array(
        'surrounding_map_link' => '_surrounding_map_link',
        'surrounding_website'  => '_surrounding_website',
    );

    foreach ($text_fields as $field => $meta_key) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $meta_key, sanitize_text_field(wp_unslash($_POST[$field])));
        }
    }

    foreach ($url_fields as $field => $meta_key) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $meta_key, esc_url_raw(wp_unslash($_POST[$field])));
        }
    }

    if (isset($_POST['surrounding_email'])) {
        update_post_meta($post_id, '_surrounding_email', sanitize_email(wp_unslash($_POST['surrounding_email'])));
    }
}
add_action('save_post_hotel_surrounding', 'metahotels_save_hotel_surroundings_meta_box');
