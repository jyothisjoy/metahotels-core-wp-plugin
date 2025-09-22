<?php
// Add meta boxes for hotel selection in related post types
function add_hotel_selection_meta_box() {
    // Add to hotel rooms
    add_meta_box(
        'hotel_selection',
        'Select Hotel',
        'render_hotel_selection_meta_box',
        'hotel_room',
        'normal',
        'high'
    );

    // Add to facilities
    add_meta_box(
        'hotel_selection',
        'Select Hotel',
        'render_hotel_selection_meta_box',
        'facility',
        'normal',
        'high'
    );

    // Add to surroundings
    add_meta_box(
        'hotel_selection',
        'Select Hotel',
        'render_hotel_selection_meta_box',
        'hotel_surrounding',
        'normal',
        'high'
    );

    // Add to offers
    add_meta_box(
        'hotel_selection',
        'Select Hotel',
        'render_hotel_selection_meta_box',
        'offer',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_hotel_selection_meta_box');

function render_hotel_selection_meta_box($post) {
    // Add nonce for security
    wp_nonce_field('hotel_selection_meta_box', 'hotel_selection_meta_box_nonce');

    // Get saved hotel ID
    $hotel_id = get_post_meta($post->ID, '_selected_hotel', true);

    // Get all hotels
    $hotels = get_posts(array(
        'post_type' => 'hotel',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    
    echo '<p><label for="selected_hotel"><strong>Select Hotel:</strong></label><br>';
    echo '<select name="selected_hotel" id="selected_hotel" style="width: 100%;">';
    echo '<option value="">Select a Hotel</option>';
    foreach ($hotels as $hotel) {
        $selected = ($hotel_id == $hotel->ID) ? 'selected' : '';
        echo '<option value="' . $hotel->ID . '" ' . $selected . '>' . $hotel->post_title . '</option>';
    }
    echo '</select></p>';
}

// Save meta box data
function save_hotel_selection_meta_box($post_id) {
    // Check if our nonce is set
    if (!isset($_POST['hotel_selection_meta_box_nonce'])) {
        return;
    }

    // Verify that the nonce is valid
    if (!wp_verify_nonce($_POST['hotel_selection_meta_box_nonce'], 'hotel_selection_meta_box')) {
        return;
    }

    // If this is an autosave, our form has not been submitted, so we don't want to do anything
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user's permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save the hotel selection
    if (isset($_POST['selected_hotel'])) {
        update_post_meta($post_id, '_selected_hotel', sanitize_text_field($_POST['selected_hotel']));
    }
}
add_action('save_post', 'save_hotel_selection_meta_box');