<?php
/**
 * The template for displaying all single offers
 *
 * @package Astra
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

get_header(); ?>

<div id="primary" <?php astra_primary_class(); ?>>
    <main id="main" class="site-main">

        <?php
        while ( have_posts() ) :
            the_post();

        endwhile;
        ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>