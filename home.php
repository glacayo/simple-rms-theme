<?php
/**
 * Posts index (`page_for_posts`). Chrome comes from the posts page, not the loop post.
 *
 * @package Simple_RMS_Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$posts_page_id = function_exists( 'get_option' ) ? \absint( get_option( 'page_for_posts' ) ) : 0;
if ( $posts_page_id > 0 ) {
	if ( function_exists( 'set_query_var' ) ) {
		set_query_var( 'rms_page_sections_post_id', $posts_page_id );
	}
	get_template_part( 'templates/page-sections-loop', null, array( 'post_id' => $posts_page_id ) );
	if ( function_exists( 'set_query_var' ) ) {
		set_query_var( 'rms_page_sections_post_id', 0 );
	}
}

echo '<main id="main-content" class="blog-index">';
if ( function_exists( 'have_posts' ) && have_posts() ) {
	while ( have_posts() ) {
		the_post();
		echo '<article ';
		post_class();
		echo '>';
		echo '<h2><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h2>';
		echo '<div class="entry-summary">';
		the_excerpt();
		echo '</div></article>';
	}
	if ( function_exists( 'the_posts_pagination' ) ) {
		the_posts_pagination();
	} elseif ( function_exists( 'the_posts_navigation' ) ) {
		the_posts_navigation();
	}
} else {
	echo '<p class="blog-index__empty">' . esc_html__( 'No posts found.', 'simple-rms-theme' ) . '</p>';
}
echo '</main>';

get_footer();
