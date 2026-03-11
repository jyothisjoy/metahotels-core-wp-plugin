<?php
/**
 * Plugin Name: Offers Post Type
 * Description: Registers a custom 'offer' post type with hierarchical categories and two meta fields (Timings and Menu URL).
 * Version:     1.0
 * Author:      Jyothis Joy
 * Text Domain: metahotels
 */

/**
 * Register the 'offer' custom post type.
 */
function create_offers_post_type() {
    // Check if post type is enabled
    if (!get_option('metahotels_enable_offers', true)) {
        return; // Don't register if disabled
    }

    $labels = array(
        'name'                  => _x('Offers', 'Post Type General Name', 'metahotels'),
        'singular_name'         => _x('Offer', 'Post Type Singular Name', 'metahotels'),
        'menu_name'             => __('Offers', 'metahotels'),
        'name_admin_bar'        => __('Offer', 'metahotels'),
        'archives'              => __('Offer Archives', 'metahotels'),
        'attributes'            => __('Offer Attributes', 'metahotels'),
        'parent_item_colon'     => __('Parent Offer:', 'metahotels'),
        'all_items'             => __('All Offers', 'metahotels'),
        'add_new_item'          => __('Add New Offer', 'metahotels'),
        'add_new'               => __('Add New', 'metahotels'),
        'new_item'              => __('New Offer', 'metahotels'),
        'edit_item'             => __('Edit Offer', 'metahotels'),
        'update_item'           => __('Update Offer', 'metahotels'),
        'view_item'             => __('View Offer', 'metahotels'),
        'view_items'            => __('View Offers', 'metahotels'),
        'search_items'          => __('Search Offer', 'metahotels'),
        'not_found'             => __('Not found', 'metahotels'),
        'not_found_in_trash'    => __('Not found in Trash', 'metahotels'),
        'featured_image'        => __('Featured Image', 'metahotels'),
        'set_featured_image'    => __('Set featured image', 'metahotels'),
        'remove_featured_image' => __('Remove featured image', 'metahotels'),
        'use_featured_image'    => __('Use as featured image', 'metahotels'),
        'insert_into_item'      => __('Insert into offer', 'metahotels'),
        'uploaded_to_this_item' => __('Uploaded to this offer', 'metahotels'),
        'items_list'            => __('Offers list', 'metahotels'),
        'items_list_navigation' => __('Offers list navigation', 'metahotels'),
        'filter_items_list'     => __('Filter offers list', 'metahotels'),
    );

    $args = array(
        'label'                 => __('Offer', 'metahotels'),
        'description'           => __('Post Type Description', 'metahotels'),
        'labels'                => $labels,
        'supports'              => array('title', 'editor', 'excerpt', 'author', 'thumbnail', 'revisions', 'custom-fields'),
        'taxonomies'            => array('offer_category'),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-tag',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
    );
    register_post_type('offer', $args);
}
add_action('init', 'create_offers_post_type', 1);

/**
 * Register the 'offer_category' taxonomy for Offers.
 */
function create_offer_taxonomy() {
    // Only register taxonomy if the offers post type is enabled
    if (!get_option('metahotels_enable_offers', true)) {
        return;
    }

    $labels = array(
        'name'              => _x('Offer Categories', 'taxonomy general name', 'metahotels'),
        'singular_name'     => _x('Offer Category', 'taxonomy singular name', 'metahotels'),
        'search_items'      => __('Search Offer Categories', 'metahotels'),
        'all_items'         => __('All Offer Categories', 'metahotels'),
        'parent_item'       => __('Parent Offer Category', 'metahotels'),
        'parent_item_colon' => __('Parent Offer Category:', 'metahotels'),
        'edit_item'         => __('Edit Offer Category', 'metahotels'),
        'update_item'       => __('Update Offer Category', 'metahotels'),
        'add_new_item'      => __('Add New Offer Category', 'metahotels'),
        'new_item_name'     => __('New Offer Category Name', 'metahotels'),
        'menu_name'         => __('Offer Category', 'metahotels'),
    );
    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'offer-category'),
    );
    register_taxonomy('offer_category', array('offer'), $args);
}
add_action('init', 'create_offer_taxonomy', 0);

/**
 * Add meta box for Timings and Menu URL.
 */
function add_offer_meta_box() {
    add_meta_box(
        'offer_meta_box',      // Unique ID
        'Offer Details',       // Box title
        'offer_meta_box_html', // Callback
        'offer'                // Post type
    );
}
add_action('add_meta_boxes', 'add_offer_meta_box');

/**
 * Output Timings and Menu URL fields.
 */
function offer_meta_box_html($post) {
    wp_nonce_field('offer_meta_box', 'offer_meta_box_nonce');
    $timings = get_post_meta($post->ID, '_timings_meta_key', true);
    $menu_url = get_post_meta($post->ID, '_menu_url_meta_key', true);
    $offer_url = get_post_meta($post->ID, '_offer_url_meta_key', true);
    $offer_terms = get_post_meta($post->ID, '_offer_terms_meta_key', true);
    $offer_includes = get_post_meta($post->ID, '_offer_includes_meta_key', true);
    $offer_excludes = get_post_meta($post->ID, '_offer_excludes_meta_key', true);
    ?>
    <p>
        <label for="timings_field"><strong>Offer Timings:</strong></label><br>
        <input type="text"
               id="timings_field"
               name="timings_field"
               value="<?php echo esc_attr($timings); ?>"
               style="width:100%;" />
    </p>
    <p>
        <label for="menu_url_field"><strong>Menu URL:</strong></label><br>
        <input type="url"
               id="menu_url_field"
               name="menu_url_field"
               value="<?php echo esc_attr($menu_url); ?>"
               placeholder="https://example.com/menu"
               style="width:100%;" />
    </p>
    <p>
        <label for="offer_url_field"><strong>Offer URL:</strong></label><br>
        <input type="url"
               id="offer_url_field"
               name="offer_url_field"
               value="<?php echo esc_attr($offer_url); ?>"
               placeholder="https://example.com/offer"
               style="width:100%;" />
    </p>
    <p>
        <label for="offer_terms_field"><strong>Offer Terms:</strong></label><br>
        <?php
        wp_editor(
            $offer_terms,
            'offer_terms_field',
            array(
                'textarea_name' => 'offer_terms_field',
                'media_buttons' => false,
                'textarea_rows' => 5,
                'teeny' => true,
                'quicktags' => true
            )
        );
        ?>
    </p>
    <p>
        <label for="offer_includes_field"><strong>Offer Includes:</strong></label><br>
        <?php
        wp_editor(
            $offer_includes,
            'offer_includes_field',
            array(
                'textarea_name' => 'offer_includes_field',
                'media_buttons' => false,
                'textarea_rows' => 5,
                'teeny' => true,
                'quicktags' => true
            )
        );
        ?>
    </p>
    <p>
        <label for="offer_excludes_field"><strong>Offer Excludes:</strong></label><br>
        <?php
        wp_editor(
            $offer_excludes,
            'offer_excludes_field',
            array(
                'textarea_name' => 'offer_excludes_field',
                'media_buttons' => false,
                'textarea_rows' => 5,
                'teeny' => true,
                'quicktags' => true
            )
        );
        ?>
    </p>
    <?php
}

/**
 * Save Timings and Menu URL meta values.
 */
function save_offer_meta_box($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!isset($_POST['offer_meta_box_nonce']) || !wp_verify_nonce($_POST['offer_meta_box_nonce'], 'offer_meta_box')) return;

    if (isset($_POST['timings_field'])) {
        update_post_meta($post_id, '_timings_meta_key', sanitize_text_field($_POST['timings_field']));
    }
    if (isset($_POST['menu_url_field'])) {
        update_post_meta($post_id, '_menu_url_meta_key', esc_url_raw($_POST['menu_url_field']));
    }
    if (isset($_POST['offer_url_field'])) {
        update_post_meta($post_id, '_offer_url_meta_key', esc_url_raw($_POST['offer_url_field']));
    }
    if (isset($_POST['offer_terms_field'])) {
        update_post_meta($post_id, '_offer_terms_meta_key', wp_kses_post($_POST['offer_terms_field']));
    }
    if (isset($_POST['offer_includes_field'])) {
        update_post_meta($post_id, '_offer_includes_meta_key', wp_kses_post($_POST['offer_includes_field']));
    }
    if (isset($_POST['offer_excludes_field'])) {
        update_post_meta($post_id, '_offer_excludes_meta_key', wp_kses_post($_POST['offer_excludes_field']));
    }
}
add_action('save_post', 'save_offer_meta_box');