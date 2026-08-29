<?php
/**
 * Shared flexible `page_sections` loop for wizard internal pages.
 *
 * @package Simple_RMS_Theme
 */

defined( 'ABSPATH' ) || exit;

$rms_sections_post_id = 0;
if ( isset( $args ) && is_array( $args ) ) {
	$rms_sections_post_id = \absint( $args['post_id'] ?? 0 );
}
if ( $rms_sections_post_id <= 0 && function_exists( 'get_query_var' ) ) {
	$rms_sections_post_id = \absint( get_query_var( 'rms_page_sections_post_id' ) );
}
$rms_acf_id = $rms_sections_post_id > 0 ? $rms_sections_post_id : false;

$acf_available = function_exists( 'have_rows' )
	&& function_exists( 'the_row' )
	&& function_exists( 'get_row_layout' )
	&& function_exists( 'get_sub_field' );

if ( ! $acf_available ) {
	if ( $rms_sections_post_id > 0 ) {
		echo '<div class="internal-page internal-page--acf-missing">';
		echo '<header class="internal-page__header"><h1 class="internal-page__title">' . esc_html( get_the_title( $rms_sections_post_id ) ) . '</h1></header>';
		echo '</div>';
		return;
	}
	if ( function_exists( 'have_posts' ) && have_posts() ) {
		echo '<main id="main" class="internal-page internal-page--acf-missing">';
		while ( have_posts() ) {
			the_post();
			echo '<article class="internal-page__article">';
			echo '<header class="internal-page__header"><h1 class="internal-page__title">' . esc_html( get_the_title() ) . '</h1></header>';
			echo '<div class="internal-page__content entry-content">';
			if ( function_exists( 'the_content' ) ) {
				the_content();
			}
			echo '</div></article>';
		}
		echo '</main>';
	}
	return;
}

if ( ! have_rows( 'page_sections', $rms_acf_id ) ) {
	return;
}

while ( have_rows( 'page_sections', $rms_acf_id ) ) {
	the_row();
	$layout = get_row_layout();

	// Theme layout ids are kebab-case (`about-us`, `cta-v2`). Reject traversal before locate.
	if ( ! is_string( $layout ) || 1 !== preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $layout ) ) {
		continue;
	}

	$slug = 'templates/' . $layout;
	if ( function_exists( 'locate_template' ) && '' === locate_template( array( $slug . '.php' ), false, false ) ) {
		continue;
	}

	get_template_part( $slug );
}
