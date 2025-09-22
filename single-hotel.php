<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

get_header();

// Get the current hotel post
$hotel = get_post();

// Check if this is a subpage
$is_subpage = !empty($wp_query->query_vars['page']);

if ($is_subpage) {
    // This is a subpage (e.g., about-us)
    $subpage_slug = $wp_query->query_vars['page'];
    
    // Get the subpage content
    $subpage = get_page_by_path($subpage_slug, OBJECT, 'hotel');
    
    if ($subpage) {
        // Display subpage content
        ?>
        <div id="primary" <?php astra_primary_class(); ?>>
            <?php astra_primary_content_top(); ?>
            
            <article id="post-<?php echo $subpage->ID; ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <h1 class="entry-title"><?php echo $subpage->post_title; ?></h1>
                </header>

                <div class="entry-content">
                    <?php echo apply_filters('the_content', $subpage->post_content); ?>
                </div>
            </article>

            <?php astra_primary_content_bottom(); ?>
        </div>
        <?php
    } else {
        // Subpage not found
        ?>
        <div id="primary" <?php astra_primary_class(); ?>>
            <?php astra_primary_content_top(); ?>
            
            <article class="error-404 not-found">
                <header class="page-header">
                    <h1 class="page-title"><?php esc_html_e('Page Not Found', 'astra'); ?></h1>
                </header>

                <div class="page-content">
                    <p><?php esc_html_e('The requested page could not be found.', 'astra'); ?></p>
                </div>
            </article>

            <?php astra_primary_content_bottom(); ?>
        </div>
        <?php
    }
} else {
    // This is the main hotel page
    ?>
    <div id="primary" <?php astra_primary_class(); ?>>
        <?php astra_primary_content_top(); ?>
        
        <article id="post-<?php echo $hotel->ID; ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <h1 class="entry-title"><?php echo $hotel->post_title; ?></h1>
            </header>

            <div class="entry-content">
                <?php echo apply_filters('the_content', $hotel->post_content); ?>
            </div>

            <?php
            // Display subpages if any
            $subpages = get_pages(array(
                'child_of' => $hotel->ID,
                'post_type' => 'hotel',
                'sort_column' => 'menu_order'
            ));

            if ($subpages) {
                echo '<div class="hotel-subpages">';
                echo '<h2>' . __('Hotel Pages', 'astra') . '</h2>';
                echo '<ul>';
                foreach ($subpages as $subpage) {
                    echo '<li><a href="' . get_permalink($subpage->ID) . '">' . $subpage->post_title . '</a></li>';
                }
                echo '</ul>';
                echo '</div>';
            }
            ?>
        </article>

        <?php astra_primary_content_bottom(); ?>
    </div>
    <?php
}

get_footer(); 