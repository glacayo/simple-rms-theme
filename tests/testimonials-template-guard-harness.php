<?php
/**
 * Testimonials empty-state fallback and internal-page shell regression guard.
 *
 * Guards two contracts:
 *  1. Every `pages/*.php` template stays a real executable shell: valid PHP
 *     opener, executes without PHP errors, dispatches content parts, and
 *     calls get_header()/get_footer() exactly once (theme convention for
 *     every page template).
 *  2. The Testimonials template renders `templates/testimonials-v1` when no
 *     `page_sections` rows exist, and header.php requests the matching
 *     `section-testimonials-v1` stylesheet for that empty state before
 *     wp_head(), so markup and CSS cannot drift independently.
 *
 * Usage: php tests/testimonials-template-guard-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}

$GLOBALS['rms_guard_log']         = array();
$GLOBALS['rms_guard_parts']       = array();
$GLOBALS['rms_guard_headers']     = 0;
$GLOBALS['rms_guard_footers']     = 0;
$GLOBALS['rms_guard_rows']        = array();
$GLOBALS['rms_guard_row_index']   = 0;
$GLOBALS['rms_guard_row_started'] = false;
$GLOBALS['rms_guard_sections']    = array();
$GLOBALS['rms_guard_template']    = '';
$GLOBALS['rms_guard_queried_id']  = 0;
$GLOBALS['rms_guard_failures']    = 0;
$GLOBALS['rms_guard_scenarios']   = 0;

if ( ! function_exists( 'esc_html' ) ) { function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $u ) { return (string) $u; } }
if ( ! function_exists( 'wp_kses_post' ) ) { function wp_kses_post( $v ) { return (string) $v; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) ); } }
if ( ! function_exists( 'add_filter' ) ) { function add_filter( $h, $cb, $p = 10, $a = 1 ) { unset( $h, $cb, $p, $a ); return true; } }
if ( ! function_exists( 'get_template_directory' ) ) { function get_template_directory() { return dirname( __DIR__ ); } }
if ( ! function_exists( 'trailingslashit' ) ) { function trailingslashit( $v ) { return rtrim( (string) $v, '/\\' ) . '/'; } }
if ( ! function_exists( 'language_attributes' ) ) { function language_attributes() { echo 'lang="en"'; } }
if ( ! function_exists( 'bloginfo' ) ) { function bloginfo( $show = '' ) { unset( $show ); } }
if ( ! function_exists( 'body_class' ) ) { function body_class( $class = '' ) { unset( $class ); } }
if ( ! function_exists( 'wp_head' ) ) { function wp_head() { $GLOBALS['rms_guard_log'][] = 'wp_head'; } }
if ( ! function_exists( 'wp_enqueue_style' ) ) { function wp_enqueue_style( $h = '', $src = '', $d = array(), $v = false ) { unset( $h, $src, $d, $v ); } }
if ( ! function_exists( 'wp_enqueue_script' ) ) { function wp_enqueue_script( $h = '', $src = '', $d = array(), $v = false, $f = false ) { unset( $h, $src, $d, $v, $f ); } }
if ( ! function_exists( 'is_front_page' ) ) { function is_front_page() { return false; } }
if ( ! function_exists( 'is_home' ) ) { function is_home() { return false; } }
if ( ! function_exists( 'is_single' ) ) { function is_single() { return false; } }
if ( ! function_exists( 'is_page_template' ) ) {
	function is_page_template( $template = '' ) {
		$current = $GLOBALS['rms_guard_template'];
		if ( '' === $template ) {
			return '' !== $current;
		}
		foreach ( is_array( $template ) ? $template : array( $template ) as $candidate ) {
			if ( $current === $candidate ) {
				return true;
			}
		}
		return false;
	}
}
if ( ! function_exists( 'get_queried_object_id' ) ) { function get_queried_object_id() { return (int) $GLOBALS['rms_guard_queried_id']; } }
if ( ! function_exists( 'rms_get_option' ) ) { function rms_get_option( $name, $default = null ) { unset( $name ); return $default; } }
if ( ! function_exists( 'rms_get_footer_version' ) ) { function rms_get_footer_version() { return 'footer-v2'; } }
if ( ! function_exists( 'get_header' ) ) { function get_header( $name = null ) { unset( $name ); $GLOBALS['rms_guard_headers']++; } }
if ( ! function_exists( 'get_footer' ) ) { function get_footer( $name = null ) { unset( $name ); $GLOBALS['rms_guard_footers']++; } }
if ( ! function_exists( 'get_template_part' ) ) {
	function get_template_part( $slug, $name = null, $args = array() ) { unset( $name, $args ); $GLOBALS['rms_guard_parts'][] = (string) $slug; }
}
if ( ! function_exists( 'have_rows' ) ) {
	function have_rows( $selector, $post_id = false ) {
		unset( $post_id );
		if ( 'page_sections' !== $selector ) { return false; }
		return $GLOBALS['rms_guard_row_started'] ? $GLOBALS['rms_guard_row_index'] < count( $GLOBALS['rms_guard_rows'] ) : count( $GLOBALS['rms_guard_rows'] ) > 0;
	}
}
if ( ! function_exists( 'the_row' ) ) { function the_row() { $GLOBALS['rms_guard_row_started'] = true; $GLOBALS['rms_guard_row_index']++; return true; } }
if ( ! function_exists( 'get_row_layout' ) ) { function get_row_layout() { return (string) ( $GLOBALS['rms_guard_rows'][ $GLOBALS['rms_guard_row_index'] - 1 ] ?? '' ); } }
if ( ! function_exists( 'get_sub_field' ) ) { function get_sub_field( $selector = null ) { unset( $selector ); return null; } }
if ( ! function_exists( 'get_field' ) ) {
	function get_field( $selector, $post_id = false, $format_value = true ) {
		unset( $post_id, $format_value );
		return 'page_sections' === $selector ? $GLOBALS['rms_guard_sections'] : null;
	}
}
if ( ! function_exists( 'have_posts' ) ) { function have_posts() { return false; } }
if ( ! function_exists( 'the_post' ) ) { function the_post() {} }
if ( ! function_exists( 'post_class' ) ) { function post_class( $class = '' ) { unset( $class ); } }
if ( ! function_exists( 'get_the_title' ) ) { function get_the_title( $id = 0 ) { unset( $id ); return 'Page'; } }
if ( ! function_exists( 'the_content' ) ) { function the_content() {} }

if ( ! class_exists( 'Vite_Icons_Integration' ) ) {
	class Vite_Icons_Integration {
		public $deferred = array();
		public static function get_instance() {
			if ( ! isset( $GLOBALS['rms_guard_vite'] ) || ! ( $GLOBALS['rms_guard_vite'] instanceof self ) ) {
				$GLOBALS['rms_guard_vite'] = new self();
			}
			return $GLOBALS['rms_guard_vite'];
		}
		public function get_critical_css( $entry, $id = '' ) {
			$GLOBALS['rms_guard_log'][] = 'critical:' . (string) $id;
			return '';
		}
		public function get_asset( $entry ) {
			$GLOBALS['rms_guard_log'][] = 'asset:' . (string) $entry;
			return '';
		}
		public function get_deferred_style( $handle, $entry ): void {
			$GLOBALS['rms_guard_log'][] = 'deferred:' . (string) $handle;
			$this->deferred[]           = array(
				'handle' => (string) $handle,
				'entry'  => (string) $entry,
			);
		}
	}
}

require_once $theme_root . '/inc/acf-template-boundary.php';

function rms_guard_check( $condition, string $message ): void {
	if ( ! $condition ) {
		$GLOBALS['rms_guard_failures']++;
		fwrite( STDERR, 'FAIL ' . $message . "\n" );
		return;
	}
	echo 'PASS ' . $message . "\n";
}

function rms_guard_reset(): void {
	$GLOBALS['rms_guard_log']         = array();
	$GLOBALS['rms_guard_parts']       = array();
	$GLOBALS['rms_guard_headers']     = 0;
	$GLOBALS['rms_guard_footers']     = 0;
	$GLOBALS['rms_guard_rows']        = array();
	$GLOBALS['rms_guard_row_index']   = 0;
	$GLOBALS['rms_guard_row_started'] = false;
	$GLOBALS['rms_guard_sections']    = array();
	$GLOBALS['rms_guard_template']    = '';
	$GLOBALS['rms_guard_queried_id']  = 0;
	if ( isset( $GLOBALS['rms_guard_vite'] ) && $GLOBALS['rms_guard_vite'] instanceof Vite_Icons_Integration ) {
		$GLOBALS['rms_guard_vite']->deferred = array();
	}
}

function rms_guard_set_sections( array $layouts ): void {
	$GLOBALS['rms_guard_rows']     = $layouts;
	$GLOBALS['rms_guard_sections'] = array_map( static function ( $layout ) {
		return array( 'acf_fc_layout' => (string) $layout );
	}, array_values( $layouts ) );
}

function rms_guard_scenario( string $slug, callable $body ): void {
	$before = $GLOBALS['rms_guard_failures'];
	$body();
	$GLOBALS['rms_guard_scenarios']++;
	if ( $before === $GLOBALS['rms_guard_failures'] ) {
		echo "PASS {$slug}\n";
	}
}

function rms_guard_section_styles(): array {
	$styles = array();
	$vite   = $GLOBALS['rms_guard_vite'] ?? null;
	if ( $vite instanceof Vite_Icons_Integration ) {
		foreach ( $vite->deferred as $item ) {
			if ( strpos( (string) $item['entry'], 'src/scss/templates/' ) === 0 ) {
				$styles[] = array(
					'handle' => (string) $item['handle'],
					'entry'  => (string) $item['entry'],
				);
			}
		}
	}
	return $styles;
}

// Scenario 1: every pages/*.php template is a real executable shell.
rms_guard_scenario( 'shell-guard-page-templates', function () use ( $theme_root ) {
	$expected = array( 'about-us.php', 'blog.php', 'contact-us.php', 'landing-page.php', 'projects.php', 'services.php', 'testimonials.php', 'thank-you.php' );
	foreach ( $expected as $page ) {
		rms_guard_check( is_readable( $theme_root . '/pages/' . $page ), 'shell guard: pages/' . $page . ' exists' );
	}
	$discovered = glob( $theme_root . '/pages/*.php' );
	sort( $discovered );
	rms_guard_check( is_array( $discovered ) && count( $discovered ) >= count( $expected ), 'shell guard: pages/*.php discovered' );
	foreach ( $discovered as $path ) {
		$slug   = basename( $path );
		$source = file_get_contents( $path );
		rms_guard_check( is_string( $source ) && 0 === strpos( ltrim( $source ), '<?php' ), 'shell guard: ' . $slug . ' opens with a real PHP tag' );
		rms_guard_reset();
		ob_start();
		include $path;
		$output = (string) ob_get_clean();
		rms_guard_check( 1 === $GLOBALS['rms_guard_headers'], 'shell guard: ' . $slug . ' calls get_header() exactly once' );
		rms_guard_check( 1 === $GLOBALS['rms_guard_footers'], 'shell guard: ' . $slug . ' calls get_footer() exactly once' );
		rms_guard_check( array() !== $GLOBALS['rms_guard_parts'], 'shell guard: ' . $slug . ' dispatches content parts' );
		rms_guard_check( false === stripos( $output, 'Fatal error' ) && false === stripos( $output, 'Warning:' ), 'shell guard: ' . $slug . ' executes without PHP errors' );
	}
} );

// Scenario 2: empty page_sections renders the default Testimonials section.
rms_guard_scenario( 'testimonials-empty-state-renders-default-section', function () use ( $theme_root ) {
	rms_guard_reset();
	$GLOBALS['rms_guard_template']   = 'pages/testimonials.php';
	$GLOBALS['rms_guard_queried_id'] = 31;
	ob_start();
	include $theme_root . '/pages/testimonials.php';
	ob_end_clean();
	rms_guard_check( 1 === $GLOBALS['rms_guard_headers'] && 1 === $GLOBALS['rms_guard_footers'], 'testimonials empty state keeps header/footer chrome' );
	rms_guard_check( in_array( 'templates/testimonials-v1', $GLOBALS['rms_guard_parts'], true ), 'testimonials empty state renders templates/testimonials-v1' );
	rms_guard_check( ! in_array( 'templates/page-sections-loop', $GLOBALS['rms_guard_parts'], true ), 'testimonials empty state skips the flexible loop' );
	rms_guard_check( 1 === count( array_keys( $GLOBALS['rms_guard_parts'], 'templates/testimonials-v1', true ) ), 'testimonials empty state requests the default section exactly once' );
} );

// Scenario 3: stored rows keep the flexible loop and never double-render the fallback.
rms_guard_scenario( 'testimonials-rows-keep-flexible-loop', function () use ( $theme_root ) {
	rms_guard_reset();
	rms_guard_set_sections( array( 'testimonials-v1' ) );
	$GLOBALS['rms_guard_template']   = 'pages/testimonials.php';
	$GLOBALS['rms_guard_queried_id'] = 31;
	ob_start();
	include $theme_root . '/pages/testimonials.php';
	ob_end_clean();
	rms_guard_check( in_array( 'templates/page-sections-loop', $GLOBALS['rms_guard_parts'], true ), 'testimonials rows render through the flexible loop' );
	rms_guard_check( ! in_array( 'templates/testimonials-v1', $GLOBALS['rms_guard_parts'], true ), 'testimonials rows do not request the default section directly' );
} );

// Scenario 4: the default section part itself renders real markup from empty fields.
rms_guard_scenario( 'testimonials-default-part-renders-markup', function () use ( $theme_root ) {
	rms_guard_reset();
	ob_start();
	include $theme_root . '/templates/testimonials-v1.php';
	$markup = (string) ob_get_clean();
	rms_guard_check( false !== strpos( $markup, 'testimonials-v1__headline' ) && false !== strpos( $markup, 'What Our Clients Say' ), 'default testimonials part renders headline markup from empty fields' );
	rms_guard_check( false !== strpos( $markup, 'Maria Johnson' ), 'default testimonials part renders fallback cards from empty fields' );
} );

// Scenario 5: header stylesheet contract for the Testimonials empty state.
rms_guard_scenario( 'testimonials-empty-state-header-stylesheet-contract', function () use ( $theme_root ) {
	$expected_deferred = array(
		array(
			'handle' => 'section-testimonials-v1',
			'entry'  => 'src/scss/templates/testimonials-v1.scss',
		),
	);

	// Empty sections: the default section markup still renders, so its
	// stylesheet must be requested exactly once, before wp_head().
	rms_guard_reset();
	rms_guard_set_sections( array() );
	$GLOBALS['rms_guard_template']   = 'pages/testimonials.php';
	$GLOBALS['rms_guard_queried_id'] = 31;
	ob_start();
	include $theme_root . '/header.php';
	ob_end_clean();
	rms_guard_check( $expected_deferred === rms_guard_section_styles(), 'empty-state header requests exactly section-testimonials-v1' );
	$deferred_index = array_search( 'deferred:section-testimonials-v1', $GLOBALS['rms_guard_log'], true );
	$head_index     = array_search( 'wp_head', $GLOBALS['rms_guard_log'], true );
	rms_guard_check( false !== $deferred_index && false !== $head_index && $deferred_index < $head_index, 'empty-state stylesheet requested before wp_head()' );

	// Rows including the testimonials layout: section-driven request stays single.
	rms_guard_reset();
	rms_guard_set_sections( array( 'testimonials-v1' ) );
	$GLOBALS['rms_guard_template']   = 'pages/testimonials.php';
	$GLOBALS['rms_guard_queried_id'] = 31;
	ob_start();
	include $theme_root . '/header.php';
	ob_end_clean();
	rms_guard_check( $expected_deferred === rms_guard_section_styles(), 'section-driven testimonials-v1 request is not duplicated by the fallback' );

	// Rows without a testimonials layout: no fallback stylesheet request.
	rms_guard_reset();
	rms_guard_set_sections( array( 'about-us', 'cta-v2' ) );
	$GLOBALS['rms_guard_template']   = 'pages/testimonials.php';
	$GLOBALS['rms_guard_queried_id'] = 31;
	ob_start();
	include $theme_root . '/header.php';
	ob_end_clean();
	$styles = rms_guard_section_styles();
	$handles = array_column( $styles, 'handle' );
	rms_guard_check( array( 'section-about-us', 'section-cta-v2' ) === $handles, 'non-testimonials rows request only their own section styles' );

	// Another internal template with rows: section-driven loading unchanged.
	rms_guard_reset();
	rms_guard_set_sections( array( 'about-us' ) );
	$GLOBALS['rms_guard_template']   = 'pages/about-us.php';
	$GLOBALS['rms_guard_queried_id'] = 32;
	ob_start();
	include $theme_root . '/header.php';
	ob_end_clean();
	rms_guard_check( array( array( 'handle' => 'section-about-us', 'entry' => 'src/scss/templates/about-us.scss' ) ) === rms_guard_section_styles(), 'about-us rows keep section-driven stylesheet loading' );

	// Another internal template with empty sections: no Testimonials fallback leak.
	rms_guard_reset();
	rms_guard_set_sections( array() );
	$GLOBALS['rms_guard_template']   = 'pages/about-us.php';
	$GLOBALS['rms_guard_queried_id'] = 32;
	ob_start();
	include $theme_root . '/header.php';
	ob_end_clean();
	$styles = rms_guard_section_styles();
	rms_guard_check( array() === $styles, 'about-us empty state requests no section styles' );
} );

if ( $GLOBALS['rms_guard_failures'] > 0 ) {
	fwrite( STDERR, $GLOBALS['rms_guard_failures'] . " guard check(s) failed.\n" );
	exit( 1 );
}

echo 'Harness passed: ' . $GLOBALS['rms_guard_scenarios'] . " scenarios.\n";