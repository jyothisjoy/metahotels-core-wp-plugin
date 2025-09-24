<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hotel Manager Admin Page
 * Provides a comprehensive interface for managing hotel inner pages
 */

// Replace the default Hotels listing with Hotel Manager
add_action('load-edit.php', 'mh_replace_hotels_listing');

function mh_replace_hotels_listing() {
    // Only replace for hotel post type
    if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'hotel') {
        return;
    }
    
    // Don't replace if we're on a submenu page
    if (isset($_GET['page'])) {
        return;
    }
    
    // Check if hotel post type exists
    if (!post_type_exists('hotel')) {
        return;
    }
    
    // Replace the default listing with our Hotel Manager
    add_action('admin_enqueue_scripts', 'mh_hotel_manager_scripts');
    add_action('admin_footer', 'mh_inject_hotel_manager_html');
}

// Enqueue scripts and styles for Hotel Manager
add_action('admin_enqueue_scripts', 'mh_hotel_manager_scripts');

function mh_hotel_manager_scripts($hook) {
    // Load on hotel post type pages
    if ($hook !== 'edit.php' || !isset($_GET['post_type']) || $_GET['post_type'] !== 'hotel') {
        return;
    }
    
    // Don't load on submenu pages
    if (isset($_GET['page'])) {
        return;
    }
    
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
    
    wp_enqueue_script(
        'mh-hotel-manager',
        plugin_dir_url(dirname(dirname(__FILE__))) . 'lib/assets/hotel-manager.js',
        array('jquery', 'jquery-ui-sortable'),
        '1.0.0',
        true
    );
    
    wp_enqueue_style(
        'mh-hotel-manager',
        plugin_dir_url(dirname(dirname(__FILE__))) . 'lib/assets/hotel-manager.css',
        array(),
        '1.0.0'
    );
    
    // Localize script with AJAX URL and nonce
    wp_localize_script('mh-hotel-manager', 'mh_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mh_manager'),
        'strings' => array(
            'confirm_delete' => __('Are you sure you want to delete this page?', 'metahotels-core'),
            'confirm_duplicate' => __('Are you sure you want to duplicate this page?', 'metahotels-core'),
            'loading' => __('Loading...', 'metahotels-core'),
            'error' => __('An error occurred. Please try again.', 'metahotels-core'),
            'success' => __('Operation completed successfully.', 'metahotels-core'),
        )
    ));
}

// Inject Hotel Manager HTML into the main Hotels page
function mh_inject_hotel_manager_html() {
    $selected_hotel = isset($_GET['hotel_id']) ? intval($_GET['hotel_id']) : 0;
    $hotels = mh_get_top_level_hotels();
    $pinned_hotels = get_user_meta(get_current_user_id(), 'mh_pinned_hotels', true);
    $pinned_hotels = is_array($pinned_hotels) ? $pinned_hotels : array();
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Hide the default WordPress listing
        $('.wp-list-table').hide();
        $('.tablenav').hide();
        $('.subsubsub').hide();
        
        // Add our Hotel Manager interface
        var hotelManagerHTML = <?php echo json_encode(mh_get_hotel_manager_html($selected_hotel, $hotels, $pinned_hotels)); ?>;
        
        // Insert after the page title
        $('.wp-heading-inline').after('<div class="mh-hotel-manager-wrapper">' + hotelManagerHTML + '</div>');
        
        // Initialize the Hotel Manager
        if (typeof initializeHotelManager === 'function') {
            initializeHotelManager();
        }
    });
    </script>
    <?php
}

// Get Hotel Manager HTML
function mh_get_hotel_manager_html($selected_hotel, $hotels, $pinned_hotels) {
    ob_start();
    ?>
    <div class="mh-hotel-manager">
        <!-- Top Toolbar -->
        <div class="mh-toolbar">
            <div class="mh-hotel-selector">
                <label for="hotel-select"><?php _e('Select Hotel:', 'metahotels-core'); ?></label>
                <div class="mh-hotel-selector-wrapper">
                    <select id="hotel-select" class="mh-select2">
                        <option value=""><?php _e('Choose a hotel...', 'metahotels-core'); ?></option>
                        <?php foreach ($hotels as $hotel): ?>
                            <option value="<?php echo esc_attr($hotel->ID); ?>" <?php selected($selected_hotel, $hotel->ID); ?>>
                                <?php echo esc_html($hotel->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($selected_hotel): ?>
                        <button type="button" id="pin-hotel-btn" class="button" data-hotel-id="<?php echo esc_attr($selected_hotel); ?>">
                            <span class="dashicons dashicons-star-empty"></span> <?php _e('Pin Hotel', 'metahotels-core'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mh-pinned-hotels">
                <label><?php _e('Pinned Hotels:', 'metahotels-core'); ?></label>
                <div class="mh-pinned-list">
                    <?php if (!empty($pinned_hotels)): ?>
                        <?php foreach ($pinned_hotels as $pinned_id): ?>
                            <?php $hotel = get_post($pinned_id); ?>
                            <?php if ($hotel): ?>
                                <span class="mh-pinned-item" data-hotel-id="<?php echo esc_attr($pinned_id); ?>">
                                    <a href="?post_type=hotel&hotel_id=<?php echo esc_attr($pinned_id); ?>">
                                        <?php echo esc_html($hotel->post_title); ?>
                                    </a>
                                    <button type="button" class="mh-unpin" data-hotel-id="<?php echo esc_attr($pinned_id); ?>">×</button>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="mh-no-pinned"><?php _e('No pinned hotels', 'metahotels-core'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mh-actions">
                <button type="button" id="add-inner-page" class="button button-primary" disabled>
                    <?php _e('Add Inner Page', 'metahotels-core'); ?>
                </button>
                <button type="button" id="duplicate-page" class="button" disabled>
                    <?php _e('Duplicate Page', 'metahotels-core'); ?>
                </button>
                <button type="button" id="duplicate-hotel" class="button" disabled>
                    <?php _e('Duplicate Hotel', 'metahotels-core'); ?>
                </button>
                <button type="button" id="seed-defaults" class="button" disabled>
                    <?php _e('Seed Defaults', 'metahotels-core'); ?>
                </button>
                <button type="button" id="expand-all" class="button">
                    <?php _e('Expand All', 'metahotels-core'); ?>
                </button>
                <button type="button" id="collapse-all" class="button">
                    <?php _e('Collapse All', 'metahotels-core'); ?>
                </button>
                <button type="button" id="refresh-tree" class="button">
                    <?php _e('Refresh', 'metahotels-core'); ?>
                </button>
            </div>
        </div>
        
        <!-- Tree Panel -->
        <div class="mh-tree-panel">
            <div id="mh-tree-container">
                <div class="mh-loading" style="display: none;">
                    <p><?php _e('Loading hotel pages...', 'metahotels-core'); ?></p>
                </div>
                <div id="mh-tree" class="mh-tree">
                    <?php if ($selected_hotel): ?>
                        <?php echo mh_render_hotel_tree($selected_hotel); ?>
                    <?php else: ?>
                        <p class="mh-no-hotel"><?php _e('Please select a hotel to view its pages.', 'metahotels-core'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Inline Rename Modal -->
    <div id="mh-rename-modal" class="mh-modal" style="display: none;">
        <div class="mh-modal-content">
            <h3><?php _e('Rename Page', 'metahotels-core'); ?></h3>
            <form id="mh-rename-form">
                <input type="hidden" id="rename-post-id" name="post_id" value="">
                <p>
                    <label for="rename-title"><?php _e('Title:', 'metahotels-core'); ?></label>
                    <input type="text" id="rename-title" name="title" class="widefat" required>
                </p>
                <p>
                    <label for="slug-mode"><?php _e('Slug Mode:', 'metahotels-core'); ?></label>
                    <select id="slug-mode" name="slug_mode">
                        <option value="keep"><?php _e('Keep current slug', 'metahotels-core'); ?></option>
                        <option value="sync"><?php _e('Sync slug to title', 'metahotels-core'); ?></option>
                        <option value="custom"><?php _e('Custom slug', 'metahotels-core'); ?></option>
                    </select>
                </p>
                <p id="custom-slug-field" style="display: none;">
                    <label for="custom-slug"><?php _e('Custom Slug:', 'metahotels-core'); ?></label>
                    <input type="text" id="custom-slug" name="custom_slug" class="widefat">
                </p>
                <p class="mh-modal-actions">
                    <button type="submit" class="button button-primary"><?php _e('Save', 'metahotels-core'); ?></button>
                    <button type="button" class="button mh-cancel"><?php _e('Cancel', 'metahotels-core'); ?></button>
                </p>
            </form>
        </div>
    </div>
    
    <!-- Add Page Modal -->
    <div id="mh-add-modal" class="mh-modal" style="display: none;">
        <div class="mh-modal-content">
            <h3><?php _e('Add Inner Page', 'metahotels-core'); ?></h3>
            <form id="mh-add-form">
                <input type="hidden" id="add-parent-id" name="parent_id" value="">
                <p>
                    <label for="add-title"><?php _e('Title:', 'metahotels-core'); ?></label>
                    <input type="text" id="add-title" name="title" class="widefat" required>
                </p>
                <p>
                    <label for="add-slug"><?php _e('Slug (optional):', 'metahotels-core'); ?></label>
                    <input type="text" id="add-slug" name="slug" class="widefat">
                </p>
                <p>
                    <label for="add-status"><?php _e('Status:', 'metahotels-core'); ?></label>
                    <select id="add-status" name="status">
                        <option value="draft"><?php _e('Draft', 'metahotels-core'); ?></option>
                        <option value="publish"><?php _e('Published', 'metahotels-core'); ?></option>
                    </select>
                </p>
                <p class="mh-modal-actions">
                    <button type="submit" class="button button-primary"><?php _e('Add Page', 'metahotels-core'); ?></button>
                    <button type="button" class="button mh-cancel"><?php _e('Cancel', 'metahotels-core'); ?></button>
                </p>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Main Hotel Manager page (kept for backward compatibility)
function mh_hotel_manager_page() {
    $selected_hotel = isset($_GET['hotel_id']) ? intval($_GET['hotel_id']) : 0;
    $hotels = mh_get_top_level_hotels();
    $pinned_hotels = get_user_meta(get_current_user_id(), 'mh_pinned_hotels', true);
    $pinned_hotels = is_array($pinned_hotels) ? $pinned_hotels : array();
    
    ?>
    <div class="wrap">
        <h1><?php _e('Hotel Manager', 'metahotels-core'); ?></h1>
        
        <div class="mh-hotel-manager">
            <!-- Top Toolbar -->
            <div class="mh-toolbar">
                <div class="mh-hotel-selector">
                    <label for="hotel-select"><?php _e('Select Hotel:', 'metahotels-core'); ?></label>
                    <div class="mh-hotel-selector-wrapper">
                        <select id="hotel-select" class="mh-select2">
                            <option value=""><?php _e('Choose a hotel...', 'metahotels-core'); ?></option>
                            <?php foreach ($hotels as $hotel): ?>
                                <option value="<?php echo esc_attr($hotel->ID); ?>" <?php selected($selected_hotel, $hotel->ID); ?>>
                                    <?php echo esc_html($hotel->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($selected_hotel): ?>
                            <button type="button" id="pin-hotel-btn" class="button" data-hotel-id="<?php echo esc_attr($selected_hotel); ?>">
                                <span class="dashicons dashicons-star-empty"></span> <?php _e('Pin Hotel', 'metahotels-core'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mh-pinned-hotels">
                    <label><?php _e('Pinned Hotels:', 'metahotels-core'); ?></label>
                    <div class="mh-pinned-list">
                        <?php if (!empty($pinned_hotels)): ?>
                            <?php foreach ($pinned_hotels as $pinned_id): ?>
                                <?php $hotel = get_post($pinned_id); ?>
                                <?php if ($hotel): ?>
                                    <span class="mh-pinned-item" data-hotel-id="<?php echo esc_attr($pinned_id); ?>">
                                        <a href="?post_type=hotel&page=hotel-manager&hotel_id=<?php echo esc_attr($pinned_id); ?>">
                                            <?php echo esc_html($hotel->post_title); ?>
                                        </a>
                                        <button type="button" class="mh-unpin" data-hotel-id="<?php echo esc_attr($pinned_id); ?>">×</button>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="mh-no-pinned"><?php _e('No pinned hotels', 'metahotels-core'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mh-actions">
                    <button type="button" id="add-inner-page" class="button button-primary" disabled>
                        <?php _e('Add Inner Page', 'metahotels-core'); ?>
                    </button>
                    <button type="button" id="duplicate-page" class="button" disabled>
                        <?php _e('Duplicate', 'metahotels-core'); ?>
                    </button>
                    <button type="button" id="seed-defaults" class="button" disabled>
                        <?php _e('Seed Defaults', 'metahotels-core'); ?>
                    </button>
                    <button type="button" id="expand-all" class="button">
                        <?php _e('Expand All', 'metahotels-core'); ?>
                    </button>
                    <button type="button" id="collapse-all" class="button">
                        <?php _e('Collapse All', 'metahotels-core'); ?>
                    </button>
                    <button type="button" id="refresh-tree" class="button">
                        <?php _e('Refresh', 'metahotels-core'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Tree Panel -->
            <div class="mh-tree-panel">
                <div id="mh-tree-container">
                    <div class="mh-loading" style="display: none;">
                        <p><?php _e('Loading hotel pages...', 'metahotels-core'); ?></p>
                    </div>
                    <div id="mh-tree" class="mh-tree">
                        <?php if ($selected_hotel): ?>
                            <?php echo mh_render_hotel_tree($selected_hotel); ?>
                        <?php else: ?>
                            <p class="mh-no-hotel"><?php _e('Please select a hotel to view its pages.', 'metahotels-core'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Inline Rename Modal -->
    <div id="mh-rename-modal" class="mh-modal" style="display: none;">
        <div class="mh-modal-content">
            <h3><?php _e('Rename Page', 'metahotels-core'); ?></h3>
            <form id="mh-rename-form">
                <input type="hidden" id="rename-post-id" name="post_id" value="">
                <p>
                    <label for="rename-title"><?php _e('Title:', 'metahotels-core'); ?></label>
                    <input type="text" id="rename-title" name="title" class="widefat" required>
                </p>
                <p>
                    <label for="slug-mode"><?php _e('Slug Mode:', 'metahotels-core'); ?></label>
                    <select id="slug-mode" name="slug_mode">
                        <option value="keep"><?php _e('Keep current slug', 'metahotels-core'); ?></option>
                        <option value="sync"><?php _e('Sync slug to title', 'metahotels-core'); ?></option>
                        <option value="custom"><?php _e('Custom slug', 'metahotels-core'); ?></option>
                    </select>
                </p>
                <p id="custom-slug-field" style="display: none;">
                    <label for="custom-slug"><?php _e('Custom Slug:', 'metahotels-core'); ?></label>
                    <input type="text" id="custom-slug" name="custom_slug" class="widefat">
                </p>
                <p class="mh-modal-actions">
                    <button type="submit" class="button button-primary"><?php _e('Save', 'metahotels-core'); ?></button>
                    <button type="button" class="button mh-cancel"><?php _e('Cancel', 'metahotels-core'); ?></button>
                </p>
            </form>
        </div>
    </div>
    
    <!-- Add Page Modal -->
    <div id="mh-add-modal" class="mh-modal" style="display: none;">
        <div class="mh-modal-content">
            <h3><?php _e('Add Inner Page', 'metahotels-core'); ?></h3>
            <form id="mh-add-form">
                <input type="hidden" id="add-parent-id" name="parent_id" value="">
                <p>
                    <label for="add-title"><?php _e('Title:', 'metahotels-core'); ?></label>
                    <input type="text" id="add-title" name="title" class="widefat" required>
                </p>
                <p>
                    <label for="add-slug"><?php _e('Slug (optional):', 'metahotels-core'); ?></label>
                    <input type="text" id="add-slug" name="slug" class="widefat">
                </p>
                <p>
                    <label for="add-status"><?php _e('Status:', 'metahotels-core'); ?></label>
                    <select id="add-status" name="status">
                        <option value="draft"><?php _e('Draft', 'metahotels-core'); ?></option>
                        <option value="publish"><?php _e('Published', 'metahotels-core'); ?></option>
                    </select>
                </p>
                <p class="mh-modal-actions">
                    <button type="submit" class="button button-primary"><?php _e('Add Page', 'metahotels-core'); ?></button>
                    <button type="button" class="button mh-cancel"><?php _e('Cancel', 'metahotels-core'); ?></button>
                </p>
            </form>
        </div>
    </div>
    <?php
}

// Get top-level hotels
function mh_get_top_level_hotels() {
    $args = array(
        'post_type' => 'hotel',
        'post_status' => array('publish', 'draft', 'private'),
        'posts_per_page' => -1,
        'post_parent' => 0,
        'orderby' => 'title',
        'order' => 'ASC'
    );
    
    return get_posts($args);
}

// Render hotel tree
function mh_render_hotel_tree($hotel_id, $parent_id = 0, $level = 0) {
    $output = '';
    
    // For the root level, first show the main hotel page, then its children
    if ($level === 0) {
        // Get the main hotel post
        $hotel = get_post($hotel_id);
        if (!$hotel) {
            return '<p class="mh-no-hotel">Hotel not found.</p>';
        }
        
        // Check if hotel has children
        $has_children = get_posts(array(
            'post_type' => 'hotel',
            'post_status' => array('publish', 'draft', 'private'),
            'posts_per_page' => 1,
            'post_parent' => $hotel_id
        ));
        
        $status_class = 'status-' . $hotel->post_status;
        $has_children_class = !empty($has_children) ? 'has-children' : '';
        
        $output .= '<ul class="mh-tree-level mh-level-0">';
        $output .= '<li class="mh-tree-node ' . $status_class . ' ' . $has_children_class . '" data-post-id="' . $hotel->ID . '">';
        $output .= '<div class="mh-node-content">';
        
        // Drag handle
        $output .= '<span class="mh-drag-handle dashicons dashicons-menu"></span>';
        
        // Expand/collapse button
        if (!empty($has_children)) {
            $output .= '<button type="button" class="mh-expand-toggle expanded dashicons dashicons-arrow-right-alt2"></button>';
        } else {
            $output .= '<span class="mh-expand-placeholder"></span>';
        }
        
        // Title and status
        $output .= '<span class="mh-node-title">' . esc_html($hotel->post_title) . ' <em>(Hotel Landing)</em></span>';
        $output .= '<span class="mh-status-badge status-' . $hotel->post_status . '">' . ucfirst($hotel->post_status) . '</span>';
        
        // Quick links
        $output .= '<div class="mh-quick-links">';
        $output .= '<a href="' . get_edit_post_link($hotel->ID) . '" class="mh-quick-link" title="' . __('Edit', 'metahotels-core') . '">';
        $output .= '<span class="dashicons dashicons-edit"></span></a>';
        
        $output .= '<a href="' . get_permalink($hotel->ID) . '" class="mh-quick-link" title="' . __('View', 'metahotels-core') . '" target="_blank">';
        $output .= '<span class="dashicons dashicons-external"></span></a>';
        
        // Elementor edit link if available
        if (class_exists('\Elementor\Plugin')) {
            $elementor_edit_url = \Elementor\Plugin::$instance->documents->get($hotel->ID)->get_edit_url();
            if ($elementor_edit_url) {
                $output .= '<a href="' . esc_url($elementor_edit_url) . '" class="mh-quick-link" title="' . __('Edit with Elementor', 'metahotels-core') . '">';
                $output .= '<span class="dashicons dashicons-admin-customizer"></span></a>';
            }
        }
        
        $output .= '</div>';
        
        // Actions dropdown
        $output .= '<div class="mh-actions-dropdown">';
        $output .= '<button type="button" class="mh-actions-toggle dashicons dashicons-ellipsis"></button>';
        $output .= '<div class="mh-actions-menu">';
        $output .= '<a href="#" class="mh-action" data-action="rename" data-post-id="' . $hotel->ID . '">' . __('Rename', 'metahotels-core') . '</a>';
        $output .= '<a href="#" class="mh-action" data-action="duplicate" data-post-id="' . $hotel->ID . '">' . __('Duplicate', 'metahotels-core') . '</a>';
        $output .= '<a href="#" class="mh-action" data-action="move" data-post-id="' . $hotel->ID . '">' . __('Move', 'metahotels-core') . '</a>';
        $output .= '<a href="#" class="mh-action mh-action-danger" data-action="delete" data-post-id="' . $hotel->ID . '">' . __('Delete', 'metahotels-core') . '</a>';
        $output .= '</div>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        // Recursively render children of the main hotel
        $output .= mh_render_hotel_tree($hotel_id, $hotel_id, $level + 1);
        
        $output .= '</li>';
        $output .= '</ul>';
        
        return $output;
    }
    
    // For deeper levels, show children of the given parent
    $children = get_posts(array(
        'post_type' => 'hotel',
        'post_status' => array('publish', 'draft', 'private'),
        'posts_per_page' => -1,
        'post_parent' => $parent_id,
        'orderby' => 'menu_order',
        'order' => 'ASC'
    ));
    
    if (empty($children)) {
        return '';
    }
    
    $output .= '<ul class="mh-tree-level mh-level-' . $level . '">';
    
    foreach ($children as $child) {
        $has_children = get_posts(array(
            'post_type' => 'hotel',
            'post_status' => array('publish', 'draft', 'private'),
            'posts_per_page' => 1,
            'post_parent' => $child->ID
        ));
        
        $status_class = 'status-' . $child->post_status;
        $has_children_class = !empty($has_children) ? 'has-children' : '';
        
        $output .= '<li class="mh-tree-node ' . $status_class . ' ' . $has_children_class . '" data-post-id="' . $child->ID . '">';
        $output .= '<div class="mh-node-content">';
        
        // Drag handle
        $output .= '<span class="mh-drag-handle dashicons dashicons-menu"></span>';
        
        // Expand/collapse button
        if (!empty($has_children)) {
            $output .= '<button type="button" class="mh-expand-toggle expanded dashicons dashicons-arrow-right-alt2"></button>';
        } else {
            $output .= '<span class="mh-expand-placeholder"></span>';
        }
        
        // Title and status
        $output .= '<span class="mh-node-title">' . esc_html($child->post_title) . '</span>';
        $output .= '<span class="mh-status-badge status-' . $child->post_status . '">' . ucfirst($child->post_status) . '</span>';
        
        // Quick links
        $output .= '<div class="mh-quick-links">';
        $output .= '<a href="' . get_edit_post_link($child->ID) . '" class="mh-quick-link" title="' . __('Edit', 'metahotels-core') . '">';
        $output .= '<span class="dashicons dashicons-edit"></span></a>';
        
        $output .= '<a href="' . get_permalink($child->ID) . '" class="mh-quick-link" title="' . __('View', 'metahotels-core') . '" target="_blank">';
        $output .= '<span class="dashicons dashicons-external"></span></a>';
        
        // Elementor edit link if available
        if (class_exists('\Elementor\Plugin')) {
            $elementor_edit_url = \Elementor\Plugin::$instance->documents->get($child->ID)->get_edit_url();
            if ($elementor_edit_url) {
                $output .= '<a href="' . esc_url($elementor_edit_url) . '" class="mh-quick-link" title="' . __('Edit with Elementor', 'metahotels-core') . '">';
                $output .= '<span class="dashicons dashicons-admin-customizer"></span></a>';
            }
        }
        
        $output .= '</div>';
        
        // Actions dropdown
        $output .= '<div class="mh-actions-dropdown">';
        $output .= '<button type="button" class="mh-actions-toggle dashicons dashicons-ellipsis"></button>';
        $output .= '<div class="mh-actions-menu">';
        $output .= '<a href="#" class="mh-action" data-action="rename" data-post-id="' . $child->ID . '">' . __('Rename', 'metahotels-core') . '</a>';
        $output .= '<a href="#" class="mh-action" data-action="duplicate" data-post-id="' . $child->ID . '">' . __('Duplicate', 'metahotels-core') . '</a>';
        $output .= '<a href="#" class="mh-action" data-action="move" data-post-id="' . $child->ID . '">' . __('Move', 'metahotels-core') . '</a>';
        $output .= '<a href="#" class="mh-action mh-action-danger" data-action="delete" data-post-id="' . $child->ID . '">' . __('Delete', 'metahotels-core') . '</a>';
        $output .= '</div>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        // Recursively render children
        $output .= mh_render_hotel_tree($hotel_id, $child->ID, $level + 1);
        
        $output .= '</li>';
    }
    
    $output .= '</ul>';
    
    return $output;
}

// AJAX Handlers
add_action('wp_ajax_mh_get_hotels', 'mh_ajax_get_hotels');
add_action('wp_ajax_mh_get_tree', 'mh_ajax_get_tree');
add_action('wp_ajax_mh_add_child', 'mh_ajax_add_child');
add_action('wp_ajax_mh_rename_post', 'mh_ajax_rename_post');
add_action('wp_ajax_mh_duplicate_post', 'mh_ajax_duplicate_post');
add_action('wp_ajax_mh_reorder_siblings', 'mh_ajax_reorder_siblings');
add_action('wp_ajax_mh_move_post', 'mh_ajax_move_post');
add_action('wp_ajax_mh_seed_defaults', 'mh_ajax_seed_defaults');
add_action('wp_ajax_mh_pin_toggle', 'mh_ajax_pin_toggle');
add_action('wp_ajax_mh_delete_post', 'mh_ajax_delete_post');
add_action('wp_ajax_mh_duplicate_hotel', 'mh_ajax_duplicate_hotel');

// AJAX: Get hotels
function mh_ajax_get_hotels() {
    check_ajax_referer('mh_manager', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_die(__('Insufficient permissions.', 'metahotels-core'));
    }
    
    $search = sanitize_text_field($_POST['search'] ?? '');
    $hotels = mh_get_top_level_hotels();
    
    $results = array();
    foreach ($hotels as $hotel) {
        if (empty($search) || stripos($hotel->post_title, $search) !== false) {
            $child_count = get_posts(array(
                'post_type' => 'hotel',
                'post_status' => array('publish', 'draft', 'private'),
                'posts_per_page' => -1,
                'post_parent' => $hotel->ID,
                'fields' => 'ids'
            ));
            
            $results[] = array(
                'id' => $hotel->ID,
                'title' => $hotel->post_title,
                'slug' => $hotel->post_name,
                'status' => $hotel->post_status,
                'child_count' => count($child_count)
            );
        }
    }
    
    wp_send_json_success($results);
}

// AJAX: Get tree
function mh_ajax_get_tree() {
    check_ajax_referer('mh_manager', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_die(__('Insufficient permissions.', 'metahotels-core'));
    }
    
    $hotel_id = intval($_POST['hotel_id']);
    if (!$hotel_id) {
        wp_send_json_error(__('Invalid hotel ID.', 'metahotels-core'));
    }
    
    $tree_html = mh_render_hotel_tree($hotel_id);
    wp_send_json_success(array('html' => $tree_html));
}

// AJAX: Add child
function mh_ajax_add_child() {
    check_ajax_referer('mh_manager', 'nonce');
    
    $parent_id = intval($_POST['parent_id']);
    $title = sanitize_text_field($_POST['title']);
    $slug = sanitize_title($_POST['slug'] ?? '');
    $status = sanitize_text_field($_POST['status'] ?? 'draft');
    
    if (!$parent_id || !$title) {
        wp_send_json_error(__('Parent ID and title are required.', 'metahotels-core'));
    }
    
    if (!current_user_can('edit_post', $parent_id)) {
        wp_die(__('Insufficient permissions.', 'metahotels-core'));
    }
    
    $post_data = array(
        'post_title' => $title,
        'post_name' => $slug ?: sanitize_title($title),
        'post_status' => $status,
        'post_type' => 'hotel',
        'post_parent' => $parent_id,
        'post_author' => get_current_user_id()
    );
    
    $post_id = wp_insert_post($post_data);
    
    if (is_wp_error($post_id)) {
        wp_send_json_error($post_id->get_error_message());
    }
    
    wp_send_json_success(array(
        'post_id' => $post_id,
        'message' => __('Page created successfully.', 'metahotels-core')
    ));
}

// AJAX: Rename post
function mh_ajax_rename_post() {
    check_ajax_referer('mh_manager', 'nonce');
    
    $post_id = intval($_POST['post_id']);
    $new_title = sanitize_text_field($_POST['new_title']);
    $slug_mode = sanitize_text_field($_POST['slug_mode'] ?? 'keep');
    $custom_slug = sanitize_title($_POST['custom_slug'] ?? '');
    
    if (!$post_id || !$new_title) {
        wp_send_json_error(__('Post ID and title are required.', 'metahotels-core'));
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        wp_die(__('Insufficient permissions.', 'metahotels-core'));
    }
    
    $post_data = array(
        'ID' => $post_id,
        'post_title' => $new_title
    );
    
    // Handle slug based on mode
    switch ($slug_mode) {
        case 'sync':
            $post_data['post_name'] = sanitize_title($new_title);
            break;
        case 'custom':
            if ($custom_slug) {
                $post_data['post_name'] = $custom_slug;
            }
            break;
        // 'keep' - don't change the slug
    }
    
    $result = wp_update_post($post_data);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    
    wp_send_json_success(array(
        'message' => __('Page renamed successfully.', 'metahotels-core')
    ));
}

// AJAX: Duplicate post
function mh_ajax_duplicate_post() {
    check_ajax_referer('mh_manager', 'nonce');
    
    $post_id = intval($_POST['post_id']);
    $deep = (bool) ($_POST['deep'] ?? false);
    
    if (!$post_id) {
        wp_send_json_error(__('Post ID is required.', 'metahotels-core'));
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        wp_die(__('Insufficient permissions.', 'metahotels-core'));
    }
    
    $original_post = get_post($post_id);
    if (!$original_post) {
        wp_send_json_error(__('Post not found.', 'metahotels-core'));
    }
    
    // Create duplicate
    $new_post_data = array(
        'post_title' => $original_post->post_title . ' - Copy',
        'post_name' => $original_post->post_name . '-copy',
        'post_content' => $original_post->post_content,
        'post_excerpt' => $original_post->post_excerpt,
        'post_status' => 'draft',
        'post_type' => $original_post->post_type,
        'post_parent' => $original_post->post_parent,
        'post_author' => get_current_user_id()
    );
    
    $new_post_id = wp_insert_post($new_post_data);
    
    if (is_wp_error($new_post_id)) {
        wp_send_json_error($new_post_id->get_error_message());
    }
    
    // Copy meta data
    $meta_data = get_post_meta($post_id);
    foreach ($meta_data as $key => $values) {
        foreach ($values as $value) {
            add_post_meta($new_post_id, $key, maybe_unserialize($value));
        }
    }
    
    // Copy featured image
    $thumbnail_id = get_post_thumbnail_id($post_id);
    if ($thumbnail_id) {
        set_post_thumbnail($new_post_id, $thumbnail_id);
    }
    
    wp_send_json_success(array(
        'post_id' => $new_post_id,
        'message' => __('Page duplicated successfully.', 'metahotels-core')
    ));
}

// AJAX: Reorder siblings
function mh_ajax_reorder_siblings() {
    check_ajax_referer('mh_manager', 'nonce');
    
    $parent_id = intval($_POST['parent_id']);
    $ordered_ids = array_map('intval', $_POST['ordered_ids'] ?? array());
    
    if (!$parent_id || empty($ordered_ids)) {
        wp_send_json_error(__('Parent ID and ordered IDs are required.', 'metahotels-core'));
    }
    
    if (!current_user_can('edit_post', $parent_id)) {
        wp_die(__('Insufficient permissions.', 'metahotels-core'));
    }
    
    // Verify all IDs are children of the parent
    foreach ($ordered_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_parent != $parent_id) {
            wp_send_json_error(__('Invalid post ID in reorder list.', 'metahotels-core'));
        }
    }
    
    // Update menu_order
    foreach ($ordered_ids as $index => $post_id) {
        wp_update_post(array(
            'ID' => $post_id,
            'menu_order' => $index + 1
        ));
    }
    
    wp_send_json_success(array(
        'message' => __('Order updated successfully.', 'metahotels-core')
    ));
}

// AJAX: Move post
function mh_ajax_move_post() {
    check_ajax_referer('mh_manager', 'nonce');
    
    $post_id = intval($_POST['post_id']);
    $new_parent_id = intval($_POST['new_parent_id']);
    
    if (!$post_id || !$new_parent_id) {
        wp_send_json_error(__('Post ID and new parent ID are required.', 'metahotels-core'));
    }
    
    if (!current_user_can('edit_post', $post_id) || !current_user_can('edit_post', $new_parent_id)) {
        wp_die(__('Insufficient permissions.', 'metahotels-core'));
    }
    
    // Check for circular reference
    if ($post_id == $new_parent_id) {
        wp_send_json_error(__('Cannot move post to itself.', 'metahotels-core'));
    }
    
    // Check if new parent is a descendant of the post being moved
    $descendants = get_posts(array(
        'post_type' => 'hotel',
        'post_status' => array('publish', 'draft', 'private'),
        'posts_per_page' => -1,
        'post_parent' => $post_id,
        'fields' => 'ids'
    ));
    
    if (in_array($new_parent_id, $descendants)) {
        wp_send_json_error(__('Cannot move post to its own descendant.', 'metahotels-core'));
    }
    
    $result = wp_update_post(array(
        'ID' => $post_id,
        'post_parent' => $new_parent_id,
        'menu_order' => 0 // Move to end
    ));
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    
    wp_send_json_success(array(
        'message' => __('Post moved successfully.', 'metahotels-core')
    ));
}

// AJAX: Seed defaults
function mh_ajax_seed_defaults() {
    check_ajax_referer('mh_manager', 'nonce');
    
    $hotel_id = intval($_POST['hotel_id']);
    
    if (!$hotel_id) {
        wp_send_json_error(__('Hotel ID is required.', 'metahotels-core'));
    }
    
    if (!current_user_can('edit_post', $hotel_id)) {
        wp_die(__('Insufficient permissions.', 'metahotels-core'));
    }
    
    $default_pages = array(
        'about' => 'About',
        'dining' => 'Dining',
        'rooms' => 'Rooms',
        'offers' => 'Offers',
        'gallery' => 'Gallery',
        'contact' => 'Contact'
    );
    
    $created_pages = array();
    
    foreach ($default_pages as $slug => $title) {
        // Check if page already exists
        $existing = get_posts(array(
            'post_type' => 'hotel',
            'post_status' => array('publish', 'draft', 'private'),
            'posts_per_page' => 1,
            'post_parent' => $hotel_id,
            'name' => $slug
        ));
        
        if (empty($existing)) {
            $post_data = array(
                'post_title' => $title,
                'post_name' => $slug,
                'post_status' => 'draft',
                'post_type' => 'hotel',
                'post_parent' => $hotel_id,
                'post_author' => get_current_user_id()
            );
            
            $post_id = wp_insert_post($post_data);
            if (!is_wp_error($post_id)) {
                $created_pages[] = $post_id;
            }
        }
    }
    
    wp_send_json_success(array(
        'created_count' => count($created_pages),
        'message' => sprintf(__('%d default pages created.', 'metahotels-core'), count($created_pages))
    ));
}

// AJAX: Pin toggle
function mh_ajax_pin_toggle() {
    check_ajax_referer('mh_manager', 'nonce');
    
    $hotel_id = intval($_POST['hotel_id']);
    $operation = sanitize_text_field($_POST['op'] ?? 'add');
    
    if (!$hotel_id) {
        wp_send_json_error(__('Hotel ID is required.', 'metahotels-core'));
    }
    
    $pinned_hotels = get_user_meta(get_current_user_id(), 'mh_pinned_hotels', true);
    $pinned_hotels = is_array($pinned_hotels) ? $pinned_hotels : array();
    
    if ($operation === 'add') {
        if (!in_array($hotel_id, $pinned_hotels)) {
            $pinned_hotels[] = $hotel_id;
        }
    } else {
        $pinned_hotels = array_diff($pinned_hotels, array($hotel_id));
    }
    
    update_user_meta(get_current_user_id(), 'mh_pinned_hotels', $pinned_hotels);
    
    wp_send_json_success(array(
        'pinned' => $operation === 'add',
        'message' => $operation === 'add' ? __('Hotel pinned.', 'metahotels-core') : __('Hotel unpinned.', 'metahotels-core')
    ));
}

// AJAX: Delete post
function mh_ajax_delete_post() {
    check_ajax_referer('mh_manager', 'nonce');
    
    $post_id = intval($_POST['post_id']);
    
    if (!$post_id) {
        wp_send_json_error(__('Post ID is required.', 'metahotels-core'));
    }
    
    if (!current_user_can('delete_post', $post_id)) {
        wp_die(__('Insufficient permissions.', 'metahotels-core'));
    }
    
    $result = wp_trash_post($post_id);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    
    wp_send_json_success(array(
        'message' => __('Page moved to trash.', 'metahotels-core')
    ));
}

// AJAX: Duplicate entire hotel
function mh_ajax_duplicate_hotel() {
    check_ajax_referer('mh_manager', 'nonce');
    
    $hotel_id = intval($_POST['hotel_id']);
    
    if (!$hotel_id) {
        wp_send_json_error(__('Hotel ID is required.', 'metahotels-core'));
    }
    
    if (!current_user_can('edit_post', $hotel_id)) {
        wp_die(__('Insufficient permissions.', 'metahotels-core'));
    }
    
    $original_hotel = get_post($hotel_id);
    if (!$original_hotel) {
        wp_send_json_error(__('Hotel not found.', 'metahotels-core'));
    }
    
    // Check if a duplicate already exists to prevent multiple copies
    $existing_copy = get_posts(array(
        'post_type' => 'hotel',
        'post_status' => array('publish', 'draft', 'private'),
        'posts_per_page' => 1,
        'post_parent' => 0,
        'name' => $original_hotel->post_name . '-copy'
    ));
    
    if (!empty($existing_copy)) {
        wp_send_json_error(__('A copy of this hotel already exists.', 'metahotels-core'));
    }
    
    // Create the main hotel duplicate
    $new_hotel_data = array(
        'post_title' => $original_hotel->post_title . ' - Copy',
        'post_name' => $original_hotel->post_name . '-copy',
        'post_content' => $original_hotel->post_content,
        'post_excerpt' => $original_hotel->post_excerpt,
        'post_status' => 'draft',
        'post_type' => $original_hotel->post_type,
        'post_parent' => 0, // Top-level hotel
        'post_author' => get_current_user_id(),
        'menu_order' => $original_hotel->menu_order
    );
    
    $new_hotel_id = wp_insert_post($new_hotel_data);
    
    if (is_wp_error($new_hotel_id)) {
        wp_send_json_error($new_hotel_id->get_error_message());
    }
    
    // Copy meta data for the main hotel
    mh_copy_post_meta($hotel_id, $new_hotel_id);
    
    // Copy featured image
    $thumbnail_id = get_post_thumbnail_id($hotel_id);
    if ($thumbnail_id) {
        set_post_thumbnail($new_hotel_id, $thumbnail_id);
    }
    
    // Copy taxonomies
    mh_copy_post_taxonomies($hotel_id, $new_hotel_id);
    
    // Duplicate all child pages recursively
    $duplicated_pages = mh_duplicate_hotel_children($hotel_id, $new_hotel_id);
    
    wp_send_json_success(array(
        'hotel_id' => $new_hotel_id,
        'duplicated_pages' => $duplicated_pages,
        'message' => sprintf(__('Hotel duplicated successfully with %d pages.', 'metahotels-core'), $duplicated_pages + 1)
    ));
}

// Helper function to copy post meta data
function mh_copy_post_meta($source_id, $target_id) {
    $meta_data = get_post_meta($source_id);
    foreach ($meta_data as $key => $values) {
        foreach ($values as $value) {
            add_post_meta($target_id, $key, maybe_unserialize($value));
        }
    }
}

// Helper function to copy post taxonomies
function mh_copy_post_taxonomies($source_id, $target_id) {
    $taxonomies = get_object_taxonomies(get_post_type($source_id));
    foreach ($taxonomies as $taxonomy) {
        $terms = wp_get_object_terms($source_id, $taxonomy);
        if (!is_wp_error($terms) && !empty($terms)) {
            $term_ids = array();
            foreach ($terms as $term) {
                $term_ids[] = $term->term_id;
            }
            wp_set_object_terms($target_id, $term_ids, $taxonomy);
        }
    }
}

// Recursive function to duplicate hotel children
function mh_duplicate_hotel_children($parent_id, $new_parent_id) {
    $children = get_posts(array(
        'post_type' => 'hotel',
        'post_status' => array('publish', 'draft', 'private'),
        'posts_per_page' => -1,
        'post_parent' => $parent_id,
        'orderby' => 'menu_order',
        'order' => 'ASC'
    ));
    
    $duplicated_count = 0;
    
    foreach ($children as $child) {
        // Create child duplicate
        $new_child_data = array(
            'post_title' => $child->post_title,
            'post_name' => $child->post_name . '-copy',
            'post_content' => $child->post_content,
            'post_excerpt' => $child->post_excerpt,
            'post_status' => 'draft',
            'post_type' => $child->post_type,
            'post_parent' => $new_parent_id,
            'post_author' => get_current_user_id(),
            'menu_order' => $child->menu_order
        );
        
        $new_child_id = wp_insert_post($new_child_data);
        
        if (!is_wp_error($new_child_id)) {
            // Copy meta data
            mh_copy_post_meta($child->ID, $new_child_id);
            
            // Copy featured image
            $thumbnail_id = get_post_thumbnail_id($child->ID);
            if ($thumbnail_id) {
                set_post_thumbnail($new_child_id, $thumbnail_id);
            }
            
            // Copy taxonomies
            mh_copy_post_taxonomies($child->ID, $new_child_id);
            
            $duplicated_count++;
            
            // Recursively duplicate grandchildren
            $duplicated_count += mh_duplicate_hotel_children($child->ID, $new_child_id);
        }
    }
    
    return $duplicated_count;
}
