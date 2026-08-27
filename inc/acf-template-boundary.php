<?php
/**
 * ACF Frontend Template Boundary
 *
 * ACF Pro is a required theme dependency. When its APIs are unavailable
 * (plugin inactive), `template_include` swaps ANY frontend template for
 * the single setup-safe shell before any ACF-dependent partial can fatal.
 * With ACF active, the original template is returned unchanged.
 *
 * @package Simple_RMS_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Whether the ACF Pro APIs the theme requires are available.
 *
 * @return bool
 */
function rms_acf_available(): bool {
    return function_exists( 'get_field' )
        && function_exists( 'get_sub_field' )
        && function_exists( 'have_rows' )
        && function_exists( 'the_row' )
        && function_exists( 'get_row_layout' );
}

/**
 * Whether this request is admin, REST, or CLI and must keep its original template.
 *
 * `template_include` does not run in those contexts, but the bypass is explicit
 * so those surfaces are never masked if the filter is applied manually.
 *
 * @return bool
 */
function rms_acf_boundary_should_bypass(): bool {
    if ( function_exists( 'is_admin' ) && is_admin() ) {
        return true;
    }

    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return true;
    }

    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        return true;
    }

    return false;
}

/**
 * `template_include`: swap to the setup-safe shell when ACF is unavailable.
 * If the safe template is missing/unreadable, return the original rather
 * than introduce another error.
 *
 * @param string $template The template WordPress selected for this request.
 * @return string Template to load.
 */
function rms_template_include_acf_boundary( string $template ): string {
    if ( rms_acf_available() || '' === $template || rms_acf_boundary_should_bypass() ) {
        return $template;
    }

    $safe = trailingslashit( get_template_directory() ) . 'templates/setup-safe.php';
    if ( is_readable( $safe ) ) {
        return $safe;
    }

    return $template;
}
add_filter( 'template_include', 'rms_template_include_acf_boundary' );

/**
 * Unique kebab-case `page_sections` layout names stored on a page.
 *
 * @return string[]
 */
function rms_page_section_layouts( int $post_id ): array {
	if ( $post_id <= 0 ) {
		return [];
	}

	$rows = function_exists( 'get_field' ) ? get_field( 'page_sections', $post_id ) : get_post_meta( $post_id, 'page_sections', true );
	if ( ! is_array( $rows ) ) {
		return [];
	}

	$layouts = [];
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$layout = sanitize_key( (string) ( $row['acf_fc_layout'] ?? '' ) );
		if ( '' === $layout || 1 !== preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $layout ) ) {
			continue;
		}
		$layouts[] = $layout;
	}

	return array_values( array_unique( $layouts ) );
}