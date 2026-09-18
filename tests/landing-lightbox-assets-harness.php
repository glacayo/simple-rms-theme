<?php
/**
 * Focused regression harness for issue #129 (landing lightbox assets).
 *
 * Contract: the front page, the Projects template, and the SEO Landing
 * template must each request the *same shared* portfolio lightbox assets:
 *   - deferred stylesheet handle `lightbox` -> src/scss/components/lightbox.scss
 *   - footer runtime handle `lightbox-js` -> src/ts/lightbox.ts
 * exactly once per request, with `[]` deps and `null` version.
 *
 * Guards beyond the happy path:
 *   - the enqueued records are identical across all three contexts, so the
 *     landing template cannot silently grow a second modal implementation;
 *   - no duplicate style/script handle is deferred on the landing template,
 *     and `section-portfolio-v1` stays a single request;
 *   - only header.php wires the shared portfolio lightbox runtime, and only
 *     one shared lightbox module exists on disk.
 *
 * No framework. `header.php` is included with WordPress/Vite stubs that record
 * every deferred style and enqueue call. Context (front page / page template)
 * is toggled through globals between scenarios.
 *
 * Usage: php tests/landing-lightbox-assets-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $theme_root . '/' );
}

define( 'RMS_LB_STYLE_HANDLE', 'lightbox' );
define( 'RMS_LB_STYLE_ENTRY', 'src/scss/components/lightbox.scss' );
define( 'RMS_LB_SCRIPT_HANDLE', 'lightbox-js' );
define( 'RMS_LB_SCRIPT_ENTRY', 'src/ts/lightbox.ts' );

$GLOBALS['rms_lb_log']         = array();
$GLOBALS['rms_lb_deferred']    = array();
$GLOBALS['rms_lb_styles']      = array();
$GLOBALS['rms_lb_scripts']     = array();
$GLOBALS['rms_lb_front']       = false;
$GLOBALS['rms_lb_home']        = false;
$GLOBALS['rms_lb_single']      = false;
$GLOBALS['rms_lb_template']    = '';
$GLOBALS['rms_lb_queried_id']  = 0;
$GLOBALS['rms_lb_failures']    = 0;
$GLOBALS['rms_lb_scenarios']   = 0;

// ─── WordPress + Vite stubs ────────────────────────────────────────────────

if ( ! function_exists( 'language_attributes' ) ) { function language_attributes() { echo 'lang="en"'; } }
if ( ! function_exists( 'bloginfo' ) ) { function bloginfo( $show = '' ) { unset( $show ); } }
if ( ! function_exists( 'body_class' ) ) { function body_class( $class = '' ) { unset( $class ); } }
if ( ! function_exists( 'wp_head' ) ) { function wp_head() { $GLOBALS['rms_lb_log'][] = 'wp_head'; } }
if ( ! function_exists( 'add_filter' ) ) { function add_filter( $h, $cb, $p = 10, $a = 1 ) { unset( $h, $cb, $p, $a ); return true; } }
if ( ! function_exists( 'get_template_directory' ) ) { function get_template_directory() { return dirname( __DIR__ ); } }
if ( ! function_exists( 'trailingslashit' ) ) { function trailingslashit( $v ) { return rtrim( (string) $v, '/\\' ) . '/'; } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $u ) { return (string) $u; } }
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ) );
	}
}
if ( ! function_exists( 'is_front_page' ) ) { function is_front_page() { return (bool) $GLOBALS['rms_lb_front']; } }
if ( ! function_exists( 'is_home' ) ) { function is_home() { return (bool) $GLOBALS['rms_lb_home']; } }
if ( ! function_exists( 'is_single' ) ) { function is_single() { return (bool) $GLOBALS['rms_lb_single']; } }
if ( ! function_exists( 'is_page_template' ) ) {
	function is_page_template( $template = '' ) {
		$current = (string) $GLOBALS['rms_lb_template'];
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
if ( ! function_exists( 'get_queried_object_id' ) ) { function get_queried_object_id() { return (int) $GLOBALS['rms_lb_queried_id']; } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $post_id, $key = '', $single = false ) { unset( $post_id, $key, $single ); return ''; } }
if ( ! function_exists( 'rms_get_option' ) ) { function rms_get_option( $name, $default = null ) { unset( $name ); return $default; } }
if ( ! function_exists( 'rms_get_footer_version' ) ) { function rms_get_footer_version() { return 'footer-v2'; } }
if ( ! function_exists( 'get_template_part' ) ) { function get_template_part( $slug, $name = null, $args = array() ) { unset( $slug, $name, $args ); } }
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle = '', $src = '', $deps = array(), $ver = false ) {
		$GLOBALS['rms_lb_styles'][] = array(
			'handle' => (string) $handle,
			'src'    => (string) $src,
			'deps'   => $deps,
			'ver'    => $ver,
		);
		$GLOBALS['rms_lb_log'][] = 'style:' . (string) $handle;
	}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle = '', $src = '', $deps = array(), $ver = false, $footer = false ) {
		$GLOBALS['rms_lb_scripts'][] = array(
			'handle' => (string) $handle,
			'src'    => (string) $src,
			'deps'   => $deps,
			'ver'    => $ver,
			'footer' => (bool) $footer,
		);
		$GLOBALS['rms_lb_log'][] = 'script:' . (string) $handle;
	}
}

if ( ! class_exists( 'Vite_Icons_Integration' ) ) {
	class Vite_Icons_Integration {
		public static function get_instance() {
			if ( ! isset( $GLOBALS['rms_lb_vite'] ) || ! ( $GLOBALS['rms_lb_vite'] instanceof self ) ) {
				$GLOBALS['rms_lb_vite'] = new self();
			}
			return $GLOBALS['rms_lb_vite'];
		}
		public function get_critical_css( $entry, $id = '' ) {
			unset( $entry );
			$GLOBALS['rms_lb_log'][] = 'critical:' . (string) $id;
			return '';
		}
		public function get_asset( $entry ) {
			$GLOBALS['rms_lb_log'][] = 'asset:' . (string) $entry;
			return 'https://example.test/dist/' . (string) $entry;
		}
		public function get_deferred_style( $handle, $entry ): void {
			// Mirror the real method: skip the link when the asset is missing.
			$css = $this->get_asset( $entry );
			if ( ! $css ) {
				return;
			}
			$GLOBALS['rms_lb_deferred'][] = array(
				'handle' => (string) $handle,
				'entry'  => (string) $entry,
			);
			$GLOBALS['rms_lb_log'][] = 'deferred:' . (string) $handle;
		}
	}
}

require_once $theme_root . '/inc/acf-template-boundary.php';

// ─── Harness helpers ───────────────────────────────────────────────────────

function rms_lb_check( $condition, string $message ): void {
	if ( ! $condition ) {
		$GLOBALS['rms_lb_failures']++;
		fwrite( STDERR, 'FAIL ' . $message . "\n" );
		return;
	}
	echo 'PASS ' . $message . "\n";
}

function rms_lb_reset(): void {
	$GLOBALS['rms_lb_log']        = array();
	$GLOBALS['rms_lb_deferred']   = array();
	$GLOBALS['rms_lb_styles']     = array();
	$GLOBALS['rms_lb_scripts']    = array();
	$GLOBALS['rms_lb_front']      = false;
	$GLOBALS['rms_lb_home']       = false;
	$GLOBALS['rms_lb_single']     = false;
	$GLOBALS['rms_lb_template']   = '';
	$GLOBALS['rms_lb_queried_id'] = 0;
}

function rms_lb_run_header( array $context ): void {
	rms_lb_reset();
	$GLOBALS['rms_lb_front']      = (bool) ( $context['front'] ?? false );
	$GLOBALS['rms_lb_home']       = (bool) ( $context['home'] ?? false );
	$GLOBALS['rms_lb_single']     = (bool) ( $context['single'] ?? false );
	$GLOBALS['rms_lb_template']   = (string) ( $context['template'] ?? '' );
	$GLOBALS['rms_lb_queried_id'] = (int) ( $context['queried_id'] ?? 0 );
	ob_start();
	include dirname( __DIR__ ) . '/header.php';
	ob_end_clean();
}

function rms_lb_scenario( string $slug, callable $body ): void {
	$before = $GLOBALS['rms_lb_failures'];
	$body();
	$GLOBALS['rms_lb_scenarios']++;
	if ( $before === $GLOBALS['rms_lb_failures'] ) {
		echo "PASS {$slug}\n";
	}
}

/**
 * The exact shared asset records requested in the current context.
 *
 * @return array{styles: array<int, array<string, mixed>>, scripts: array<int, array<string, mixed>>}
 */
function rms_lb_record(): array {
	$styles = array_values( array_filter(
		$GLOBALS['rms_lb_deferred'],
		static function ( $item ) {
			return RMS_LB_STYLE_ENTRY === $item['entry'];
		}
	) );
	$scripts = array_values( array_filter(
		$GLOBALS['rms_lb_scripts'],
		static function ( $item ) {
			return RMS_LB_SCRIPT_HANDLE === $item['handle'];
		}
	) );
	return array( 'styles' => $styles, 'scripts' => $scripts );
}

/**
 * Recursively collect theme PHP files, excluding tests, dist, and vendor trees.
 *
 * @return string[]
 */
function rms_lb_theme_php_files( string $theme_root ): array {
	$skip = array( 'tests', 'dist', 'node_modules', '.git', '.codegraph', 'vendor', 'odd' );
	$files = array();
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $theme_root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}
		$relative = ltrim( str_replace( $theme_root, '', $file->getPathname() ), '/\\' );
		$top      = strtok( str_replace( '\\', '/', $relative ), '/' );
		if ( in_array( $top, $skip, true ) ) {
			continue;
		}
		$files[] = $relative;
	}
	sort( $files );
	return $files;
}

/**
 * Recursively collect files whose basename contains "lightbox".
 *
 * @return string[]
 */
function rms_lb_lightbox_sources( string $theme_root, string $extension ): array {
	$found = array();
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $theme_root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() || $extension !== strtolower( $file->getExtension() ) ) {
			continue;
		}
		$path = str_replace( '\\', '/', $file->getPathname() );
		if ( false !== strpos( $path, '/node_modules/' ) || false !== strpos( $path, '/dist/' ) ) {
			continue;
		}
		if ( false !== stripos( $file->getBasename( '.' . $extension ), 'lightbox' ) ) {
			$found[] = basename( $path );
		}
	}
	sort( $found );
	return array_values( array_unique( $found ) );
}

// ─── Scenario 1: shared lightbox assets in all three contexts ──────────────

rms_lb_scenario( 'shared-lightbox-assets-per-context', function () {
	$contexts = array(
		'front-page'        => array( 'front' => true ),
		'projects-template' => array( 'template' => 'pages/projects.php' ),
		'landing-template'  => array( 'template' => 'pages/landing-page.php' ),
	);
	$records = array();

	foreach ( $contexts as $name => $context ) {
		rms_lb_run_header( $context );
		$record = rms_lb_record();

		rms_lb_check( 1 === count( $record['styles'] ), $name . ' requests the shared lightbox stylesheet exactly once' );
		rms_lb_check(
			isset( $record['styles'][0] ) && RMS_LB_STYLE_HANDLE === $record['styles'][0]['handle'],
			$name . ' uses the shared style handle ' . RMS_LB_STYLE_HANDLE
		);
		rms_lb_check(
			isset( $record['styles'][0] ) && RMS_LB_STYLE_ENTRY === $record['styles'][0]['entry'],
			$name . ' requests the shared lightbox stylesheet entry ' . RMS_LB_STYLE_ENTRY
		);

		rms_lb_check( 1 === count( $record['scripts'] ), $name . ' enqueues the shared lightbox runtime exactly once' );
		$script = $record['scripts'][0] ?? array();
		rms_lb_check( RMS_LB_SCRIPT_HANDLE === ( $script['handle'] ?? '' ), $name . ' uses the shared script handle ' . RMS_LB_SCRIPT_HANDLE );
		rms_lb_check( false !== strpos( (string) ( $script['src'] ?? '' ), RMS_LB_SCRIPT_ENTRY ), $name . ' enqueues the shared runtime entry ' . RMS_LB_SCRIPT_ENTRY );
		rms_lb_check( array() === ( $script['deps'] ?? null ), $name . ' enqueues the lightbox runtime with no dependencies' );
		rms_lb_check( array_key_exists( 'ver', $script ) && null === $script['ver'], $name . ' enqueues the lightbox runtime with a null version' );
		rms_lb_check( true === ( $script['footer'] ?? false ), $name . ' enqueues the lightbox runtime in the footer' );

		$records[ $name ] = $record;
	}

	// A landing-specific modal/handle would break this identity.
	rms_lb_check(
		$records['front-page'] === $records['projects-template'],
		'projects template enqueues the identical shared lightbox records as the front page'
	);
	rms_lb_check(
		$records['front-page'] === $records['landing-template'],
		'landing template enqueues the identical shared lightbox records as the front page'
	);

	// The shared stylesheet must be requested before wp_head().
	rms_lb_run_header( array( 'template' => 'pages/landing-page.php' ) );
	$deferred_index = array_search( 'deferred:' . RMS_LB_STYLE_HANDLE, $GLOBALS['rms_lb_log'], true );
	$head_index     = array_search( 'wp_head', $GLOBALS['rms_lb_log'], true );
	rms_lb_check(
		false !== $deferred_index && false !== $head_index && $deferred_index < $head_index,
		'landing template requests the lightbox stylesheet before wp_head()'
	);
} );

// ─── Scenario 2: non-portfolio contexts stay unchanged ─────────────────────

rms_lb_scenario( 'non-portfolio-contexts-request-no-lightbox', function () {
	$contexts = array(
		'about-us-template'  => array( 'template' => 'pages/about-us.php' ),
		'contact-us-template' => array( 'template' => 'pages/contact-us.php' ),
		'thank-you-template' => array( 'template' => 'pages/thank-you.php' ),
		'single-post'        => array( 'single' => true ),
	);

	foreach ( $contexts as $name => $context ) {
		rms_lb_run_header( $context );
		$record = rms_lb_record();
		rms_lb_check( array() === $record['styles'], $name . ' requests no shared lightbox stylesheet' );
		rms_lb_check( array() === $record['scripts'], $name . ' enqueues no shared lightbox runtime' );
	}
} );

// ── Scenario 3: landing template adds no duplicate or second-modal enqueue ──

rms_lb_scenario( 'landing-template-no-duplicate-enqueues', function () {
	rms_lb_run_header( array( 'template' => 'pages/landing-page.php' ) );

	$style_handles  = array_column( $GLOBALS['rms_lb_deferred'], 'handle' );
	$script_handles = array_column( $GLOBALS['rms_lb_scripts'], 'handle' );

	rms_lb_check(
		count( $style_handles ) === count( array_unique( $style_handles ) ),
		'landing template defers no duplicate style handle'
	);
	rms_lb_check(
		count( $script_handles ) === count( array_unique( $script_handles ) ),
		'landing template enqueues no duplicate script handle'
	);
	rms_lb_check(
		1 === count( array_keys( $style_handles, 'section-portfolio-v1', true ) ),
		'landing template keeps a single section-portfolio-v1 stylesheet request'
	);

	// The lightbox runtime must not arrive through a landing-only handle.
	$foreign = preg_grep( '/lightbox|modal/i', $script_handles );
	rms_lb_check(
		array( RMS_LB_SCRIPT_HANDLE ) === array_values( $foreign ),
		'landing template exposes no modal runtime handle other than ' . RMS_LB_SCRIPT_HANDLE
	);
} );

// ─── Scenario 4: source contract — one shared implementation, three wirings ─

rms_lb_scenario( 'shared-lightbox-source-contract', function () use ( $theme_root ) {
	$header = (string) file_get_contents( $theme_root . '/header.php' );

	rms_lb_check(
		3 === substr_count( $header, "'" . RMS_LB_STYLE_ENTRY . "'" ),
		'header.php wires the shared lightbox stylesheet in exactly three contexts'
	);
	rms_lb_check(
		3 === substr_count( $header, "'" . RMS_LB_SCRIPT_ENTRY . "'" ),
		'header.php wires the shared lightbox runtime in exactly three contexts'
	);
	rms_lb_check(
		3 === substr_count( $header, "'" . RMS_LB_SCRIPT_HANDLE . "'" ),
		'header.php registers the shared lightbox runtime handle in exactly three contexts'
	);

	// No other PHP file may wire the shared portfolio lightbox runtime.
	$owners = array();
	foreach ( rms_lb_theme_php_files( $theme_root ) as $relative ) {
		$source = (string) file_get_contents( $theme_root . '/' . $relative );
		if ( false !== strpos( $source, RMS_LB_SCRIPT_ENTRY ) ) {
			$owners[] = str_replace( '\\', '/', $relative );
		}
	}
	rms_lb_check( array( 'header.php' ) === $owners, 'only header.php wires the shared portfolio lightbox runtime' );

	// A second modal implementation would add another lightbox module.
	rms_lb_check(
		array( 'lightbox.ts' ) === rms_lb_lightbox_sources( $theme_root, 'ts' ),
		'exactly one shared lightbox runtime module exists under src/ts'
	);
	rms_lb_check(
		array( 'lightbox.scss' ) === rms_lb_lightbox_sources( $theme_root, 'scss' ),
		'exactly one shared lightbox stylesheet module exists under src/scss'
	);
} );

// ── Summary ───────────────────────────────────────────────────────────────

if ( $GLOBALS['rms_lb_failures'] > 0 ) {
	fwrite( STDERR, $GLOBALS['rms_lb_failures'] . " guard check(s) failed.\n" );
	exit( 1 );
}

echo 'Harness passed: ' . $GLOBALS['rms_lb_scenarios'] . " scenarios.\n";
exit( 0 );