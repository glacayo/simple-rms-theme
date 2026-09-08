<?php
/**
 * Template Name: Testimonials
 *
 * Renders stored wizard `page_sections` rows through the flexible loop.
 * Falls back to the default `testimonials-v1` section when ACF is
 * available but no rows exist.
 *
 * @package Simple_RMS_Theme
 */

get_header();

get_template_part( 'templates/breadcrumb' );

$acf_available = function_exists( 'have_rows' )
	&& function_exists( 'the_row' )
	&& function_exists( 'get_row_layout' )
	&& function_exists( 'get_sub_field' );

if ( $acf_available && ! have_rows( 'page_sections' ) ) {
	get_template_part( 'templates/testimonials-v1' );
} else {
	// Rows exist, or ACF is missing and the loop renders its safe fallback.
	get_template_part( 'templates/page-sections-loop' );
}

get_footer();
