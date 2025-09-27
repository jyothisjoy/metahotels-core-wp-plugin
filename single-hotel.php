<?php
/**
 * Template for displaying single hotel posts
 * 
 * @package MetaHotels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header(); ?>

<div id="primary" class="content-area">
	<main id="main" class="site-main">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<header class="entry-header">
					<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
				</header>

				<div class="entry-content">
					<?php
					the_content();

					wp_link_pages( array(
						'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'metahotels-core' ),
						'after'  => '</div>',
					) );
					?>
				</div>

				<?php
				// Display hotel subpages if any
				$subpages = get_pages( array(
					'child_of' => get_the_ID(),
					'post_type' => 'hotel',
					'sort_column' => 'menu_order'
				) );

				if ( $subpages ) {
					echo '<div class="hotel-subpages">';
					echo '<h2>' . esc_html__( 'Hotel Pages', 'metahotels-core' ) . '</h2>';
					echo '<ul>';
					foreach ( $subpages as $subpage ) {
						echo '<li><a href="' . esc_url( get_permalink( $subpage->ID ) ) . '">' . esc_html( $subpage->post_title ) . '</a></li>';
					}
					echo '</ul>';
					echo '</div>';
				}
				?>
			</article>
			<?php
		endwhile;
		?>
	</main>
</div>

<?php get_footer(); ?>