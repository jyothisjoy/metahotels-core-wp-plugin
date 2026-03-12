<?php
if (!defined('ABSPATH')) {
    exit;
}

// Add meta boxes for hotel selection in related post types
function metahotels_add_hotel_selection_meta_box() {
    // Add to hotel rooms
    add_meta_box(
        'hotel_selection',
        'Select Hotel',
        'metahotels_render_hotel_selection_meta_box',
        'hotel_room',
        'normal',
        'high'
    );

    // Add to facilities
    add_meta_box(
        'hotel_selection',
        'Select Hotel',
        'metahotels_render_hotel_selection_meta_box',
        'facility',
        'normal',
        'high'
    );

    // Add to surroundings
    add_meta_box(
        'hotel_selection',
        'Select Hotel',
        'metahotels_render_hotel_selection_meta_box',
        'hotel_surrounding',
        'normal',
        'high'
    );

    // Add to offers
    add_meta_box(
        'hotel_selection',
        'Select Hotel',
        'metahotels_render_hotel_selection_meta_box',
        'offer',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'metahotels_add_hotel_selection_meta_box');

function metahotels_render_hotel_selection_meta_box($post) {
    // Add nonce for security
    wp_nonce_field('hotel_selection_meta_box', 'hotel_selection_meta_box_nonce');

    // Get saved hotel ID
    $hotel_id = get_post_meta($post->ID, '_selected_hotel', true);

    // Get all hotels (cached for the duration of this request)
    $hotels = wp_cache_get('metahotels_all_hotels', 'metahotels');
    if ($hotels === false) {
        $hotels = get_posts(array(
            'post_type'      => 'hotel',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ));
        wp_cache_set('metahotels_all_hotels', $hotels, 'metahotels', 300);
    }
    
    echo '<p><label for="selected_hotel"><strong>Select Hotel:</strong></label><br>';
    echo '<select name="selected_hotel" id="selected_hotel" style="width: 100%;">';
    echo '<option value="">Select a Hotel</option>';
    foreach ($hotels as $hotel) {
        echo '<option value="' . esc_attr((string) $hotel->ID) . '" ' . selected((int) $hotel_id, (int) $hotel->ID, false) . '>' . esc_html($hotel->post_title) . '</option>';
    }
    echo '</select></p>';
}

// Save meta box data
function metahotels_save_hotel_selection_meta_box($post_id) {
    // Check if our nonce is set
    if (!isset($_POST['hotel_selection_meta_box_nonce'])) {
        return;
    }

    // Verify that the nonce is valid
    $nonce = sanitize_text_field(wp_unslash($_POST['hotel_selection_meta_box_nonce']));
    if (!wp_verify_nonce($nonce, 'hotel_selection_meta_box')) {
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
        update_post_meta($post_id, '_selected_hotel', absint(wp_unslash($_POST['selected_hotel'])));
    }
}
add_action('save_post_hotel_room', 'metahotels_save_hotel_selection_meta_box');
add_action('save_post_facility', 'metahotels_save_hotel_selection_meta_box');
add_action('save_post_hotel_surrounding', 'metahotels_save_hotel_selection_meta_box');
add_action('save_post_offer', 'metahotels_save_hotel_selection_meta_box');
