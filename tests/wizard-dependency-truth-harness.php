<?php
/**
 * Focused production-path harness for issue #22 (dependency truth).
 *
 * No framework. Isolated child scenarios because TGMPA / function stubs
 * cannot be toggled safely in one process.
 *
 * Usage: php tests/wizard-dependency-truth-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$scenario = getenv( 'RMS_HARNESS_SCENARIO' );

if ( false === $scenario || '' === $scenario ) {
	$php       = PHP_BINARY;
	$self      = __FILE__;
	$scenarios = array(
		'diagnose-actions',
		'controller-success',
		'repo-source-resolved',
		'missing-truthful',
		'install-fail',
		'activation-fail',
		'partial-fail',
		'all-active-complete',
		'rerun-already-active',
		'tgmpa-missing',
	);

	$failed = 0;
	foreach ( $scenarios as $name ) {
		$cmd    = '"' . $php . '" ' . escapeshellarg( $self );
		putenv( 'RMS_HARNESS_SCENARIO=' . $name );
		$output = array();
		$code   = 0;
		exec( $cmd, $output, $code );
		$text = implode( "\n", $output );
		if ( 0 !== $code ) {
			fwrite( STDERR, "FAIL {$name}\n{$text}\n" );
			$failed++;
			continue;
		}
		echo "PASS {$name}\n";
		if ( '' !== $text ) {
			echo $text . "\n";
		}
	}
	putenv( 'RMS_HARNESS_SCENARIO' );

	if ( $failed > 0 ) {
		fwrite( STDERR, "Harness failed: {$failed} scenario(s).\n" );
		exit( 1 );
	}

	echo 'Harness passed: ' . count( $scenarios ) . " scenarios.\n";
	exit( 0 );
}

/**
 * @param mixed $condition
 */
function rms_harness_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

function rms_harness_stub_wordpress(): void {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}

	$includes = ABSPATH . 'wp-admin/includes';
	if ( ! is_dir( $includes ) ) {
		mkdir( $includes, 0777, true );
	}
	foreach ( array( 'plugin-install.php', 'class-wp-upgrader.php', 'file.php', 'plugin.php' ) as $stub ) {
		$path = $includes . '/' . $stub;
		if ( ! file_exists( $path ) ) {
			file_put_contents( $path, "<?php\n" );
		}
	}

	$GLOBALS['rms_harness'] = array_merge(
		array(
			'options'         => array(),
			'installed'       => array(),
			'active'          => array(),
			'install_fail'    => array(),
			'source_fail'     => array(),
			'activate_fail'   => array(),
			'install_sources' => array(),
			'plugins'         => array(),
		),
		isset( $GLOBALS['rms_harness'] ) && is_array( $GLOBALS['rms_harness'] ) ? $GLOBALS['rms_harness'] : array()
	);

	if ( ! class_exists( 'WP_Error', false ) ) {
		class WP_Error {
			public $code;
			public $message;
			public $data;

			public function __construct( $code = '', $message = '', $data = '' ) {
				$this->code    = $code;
				$this->message = $message;
				$this->data    = $data;
			}

			public function get_error_code() {
				return $this->code;
			}

			public function get_error_message() {
				return $this->message;
			}
		}
	}

	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $thing ) {
			return $thing instanceof WP_Error;
		}
	}

	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( $key ) {
			return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
		}
	}

	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $value ) {
			return trim( (string) $value );
		}
	}

	if ( ! function_exists( '__' ) ) {
		function __( $text, $domain = '' ) {
			return $text;
		}
	}

	if ( ! function_exists( 'current_time' ) ) {
		function current_time( $type = 'mysql', $gmt = false ) {
			return '2026-08-24 00:00:00';
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( $key, $default = false ) {
			return $GLOBALS['rms_harness']['options'][ $key ] ?? $default;
		}
	}

	if ( ! function_exists( 'update_option' ) ) {
		function update_option( $key, $value, $autoload = true ) {
			$GLOBALS['rms_harness']['options'][ $key ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'delete_option' ) ) {
		function delete_option( $key ) {
			unset( $GLOBALS['rms_harness']['options'][ $key ] );
			return true;
		}
	}

	if ( ! function_exists( 'do_action' ) ) {
		function do_action( $hook ) {
			return null;
		}
	}

	if ( ! function_exists( 'wp_clean_plugins_cache' ) ) {
		function wp_clean_plugins_cache() {
			return true;
		}
	}

	if ( ! function_exists( 'plugins_api' ) ) {
		function plugins_api( $action, $args ) {
			$slug = (string) ( $args['slug'] ?? '' );
			if ( ! empty( $GLOBALS['rms_harness']['source_fail'][ $slug ] ) ) {
				return new WP_Error( 'source', 'Could not resolve source' );
			}

			return (object) array(
				'download_link' => 'https://downloads.wordpress.org/plugin/' . $slug . '.latest-stable.zip',
			);
		}
	}

	if ( ! function_exists( 'activate_plugin' ) ) {
		function activate_plugin( $file ) {
			$slug = explode( '/', (string) $file )[0];
			if ( ! empty( $GLOBALS['rms_harness']['activate_fail'][ $slug ] ) ) {
				return new WP_Error( 'activate', 'Activation failed' );
			}
			$GLOBALS['rms_harness']['active'][ $slug ] = true;
			return null;
		}
	}

	if ( ! class_exists( 'Automatic_Upgrader_Skin', false ) ) {
		class Automatic_Upgrader_Skin {
		}
	}

	if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
		class Plugin_Upgrader {
			public function __construct( $skin = null ) {}

			public function install( $source ) {
				$GLOBALS['rms_harness']['install_sources'][] = (string) $source;
				$slug = basename( (string) parse_url( (string) $source, PHP_URL_PATH ) );
				$slug = preg_replace( '/\.latest-stable\.zip$|\.zip$/', '', $slug );
				if ( '' === $slug || 'repo' === $source ) {
					$slug = 'unknown';
				}
				if ( ! empty( $GLOBALS['rms_harness']['install_fail'][ $slug ] ) || 'repo' === $source ) {
					return false;
				}
				$GLOBALS['rms_harness']['installed'][ $slug ] = true;
				return true;
			}
		}
	}
}

function rms_harness_boot_tgmpa( array $plugins ): void {
	if ( ! class_exists( 'TGM_Plugin_Activation', false ) ) {
		class TGM_Plugin_Activation {
			public $plugins = array();
			public static $instance;

			public static function get_instance() {
				return self::$instance;
			}

			public function populate_file_path() {
				foreach ( $this->plugins as $slug => $plugin ) {
					if ( ! empty( $GLOBALS['rms_harness']['installed'][ $slug ] ) ) {
						$this->plugins[ $slug ]['file_path'] = $slug . '/' . $slug . '.php';
					} else {
						$this->plugins[ $slug ]['file_path'] = $slug;
					}
				}
			}

			public function is_plugin_installed( $slug ) {
				return ! empty( $GLOBALS['rms_harness']['installed'][ $slug ] );
			}

			public function is_plugin_active( $slug ) {
				return ! empty( $GLOBALS['rms_harness']['active'][ $slug ] );
			}
		}
	}

	$tgmpa          = new TGM_Plugin_Activation();
	$tgmpa->plugins = $plugins;
	TGM_Plugin_Activation::$instance = $tgmpa;
}

function rms_harness_required_plugins(): array {
	return array(
		'advanced-custom-fields-pro' => array(
			'name'        => 'Advanced Custom Fields PRO',
			'slug'        => 'advanced-custom-fields-pro',
			'required'    => true,
			'source'      => '/theme/inc/plugins/advanced-custom-fields-pro.zip',
			'source_type' => 'bundled',
			'file_path'   => 'advanced-custom-fields-pro/acf.php',
		),
		'classic-editor'             => array(
			'name'        => 'Classic Editor',
			'slug'        => 'classic-editor',
			'required'    => true,
			'source'      => 'repo',
			'source_type' => 'repo',
			'file_path'   => 'classic-editor',
		),
		'contact-form-7'             => array(
			'name'        => 'Contact Form 7',
			'slug'        => 'contact-form-7',
			'required'    => true,
			'source'      => 'repo',
			'source_type' => 'repo',
			'file_path'   => 'contact-form-7',
		),
		'wordpress-seo'              => array(
			'name'        => 'Yoast SEO',
			'slug'        => 'wordpress-seo',
			'required'    => true,
			'source'      => 'repo',
			'source_type' => 'repo',
			'file_path'   => 'wordpress-seo',
		),
	);
}

function rms_harness_load_production(): void {
	$root = dirname( __DIR__ );
	require_once $root . '/inc/wizard/class-logger.php';
	require_once $root . '/inc/wizard/class-state-manager.php';
	require_once $root . '/inc/wizard/class-step-dependencies.php';
	require_once $root . '/inc/wizard/class-step-controller.php';
}

function rms_harness_service(): Inc\Wizard\Step_Dependencies {
	return new Inc\Wizard\Step_Dependencies( new Inc\Wizard\Logger(), new Inc\Wizard\State_Manager() );
}

switch ( $scenario ) {
	case 'diagnose-actions':
		rms_harness_stub_wordpress();
		rms_harness_load_production();
		rms_harness_assert( 'already_active' === Inc\Wizard\Step_Dependencies::diagnose_plugin_action( true, true, true, true ), 'already active mismatch' );
		rms_harness_assert( 'activated' === Inc\Wizard\Step_Dependencies::diagnose_plugin_action( true, false, true, true ), 'activated mismatch' );
		rms_harness_assert( 'installed' === Inc\Wizard\Step_Dependencies::diagnose_plugin_action( false, false, true, true ), 'installed mismatch' );
		rms_harness_assert( 'activation_failed' === Inc\Wizard\Step_Dependencies::diagnose_plugin_action( false, false, true, false ), 'fresh install then inactive must be activation_failed' );
		rms_harness_assert( 'activation_failed' === Inc\Wizard\Step_Dependencies::diagnose_plugin_action( true, false, true, false ), 'existing inactive must be activation_failed' );
		rms_harness_assert( 'install_failed' === Inc\Wizard\Step_Dependencies::diagnose_plugin_action( false, false, false, false ), 'missing must be install_failed' );
		rms_harness_assert( 'not_installed' === Inc\Wizard\Step_Dependencies::diagnose_plugin_action( true, false, false, false ), 'vanished plugin must be not_installed' );
		rms_harness_assert( Inc\Wizard\Step_Dependencies::is_repo_source( 'repo', 'repo' ), 'repo sentinel must resolve' );
		rms_harness_assert( Inc\Wizard\Step_Dependencies::is_repo_source( '', '' ), 'empty source must resolve as repo' );
		rms_harness_assert( ! Inc\Wizard\Step_Dependencies::is_repo_source( '/theme/plugin.zip', 'bundled' ), 'bundled source must not resolve as repo' );
		break;

	case 'controller-success':
		rms_harness_stub_wordpress();
		rms_harness_load_production();
		rms_harness_assert( true === Inc\Wizard\Step_Controller::response_success_from_status( '', false ), 'pseudo-step must succeed' );
		rms_harness_assert( true === Inc\Wizard\Step_Controller::response_success_from_status( 'complete', true ), 'complete must succeed' );
		rms_harness_assert( true === Inc\Wizard\Step_Controller::response_success_from_status( 'running', true ), '#27 running must stay success:true' );
		rms_harness_assert( false === Inc\Wizard\Step_Controller::response_success_from_status( 'failed', true ), 'failed must not succeed' );
		rms_harness_assert( false === Inc\Wizard\Step_Controller::response_success_from_status( 'pending', true ), 'pending must not succeed' );
		rms_harness_assert( false === Inc\Wizard\Step_Controller::response_success_from_status( '', true ), 'unknown must not succeed' );
		break;

	case 'repo-source-resolved':
		rms_harness_stub_wordpress();
		$GLOBALS['rms_harness']['installed'] = array();
		$GLOBALS['rms_harness']['active']    = array();
		rms_harness_boot_tgmpa( rms_harness_required_plugins() );
		rms_harness_load_production();
		rms_harness_service()->install_missing();
		rms_harness_assert( ! in_array( 'repo', $GLOBALS['rms_harness']['install_sources'], true ), 'upgrader received literal repo' );
		foreach ( $GLOBALS['rms_harness']['install_sources'] as $source ) {
			rms_harness_assert( false === strpos( (string) $source, 'repo' ) || false !== strpos( (string) $source, 'http' ), 'source is not a resolved URL: ' . $source );
		}
		break;

	case 'missing-truthful':
		rms_harness_stub_wordpress();
		$GLOBALS['rms_harness']['installed']    = array( 'advanced-custom-fields-pro' => true );
		$GLOBALS['rms_harness']['active']       = array();
		$GLOBALS['rms_harness']['install_fail'] = array(
			'classic-editor' => true,
			'contact-form-7' => true,
			'wordpress-seo'  => true,
		);
		$GLOBALS['rms_harness']['activate_fail'] = array( 'advanced-custom-fields-pro' => true );
		rms_harness_boot_tgmpa( rms_harness_required_plugins() );
		rms_harness_load_production();
		$result = rms_harness_service()->install_missing();
		$state  = ( new Inc\Wizard\State_Manager() )->get_state();
		rms_harness_assert( ! empty( $result['advanced-custom-fields-pro']['installed'] ), 'ACF must stay installed' );
		rms_harness_assert( empty( $result['advanced-custom-fields-pro']['active'] ), 'ACF must not be reported active' );
		rms_harness_assert( 'activation_failed' === $result['advanced-custom-fields-pro']['action'], 'ACF action must be activation_failed' );
		foreach ( array( 'classic-editor', 'contact-form-7', 'wordpress-seo' ) as $slug ) {
			rms_harness_assert( empty( $result[ $slug ]['installed'] ), $slug . ' must not be installed' );
			rms_harness_assert( empty( $result[ $slug ]['active'] ), $slug . ' must not be active' );
			rms_harness_assert( 'install_failed' === $result[ $slug ]['action'], $slug . ' action must be install_failed' );
		}
		rms_harness_assert( 'failed' === ( $state['step_status']['dependencies'] ?? '' ), 'step must stay failed' );
		rms_harness_assert( false === Inc\Wizard\Step_Controller::response_success_from_status( 'failed', true ), 'controller must not report success' );
		break;

	case 'install-fail':
		rms_harness_stub_wordpress();
		$GLOBALS['rms_harness']['source_fail'] = array( 'classic-editor' => true );
		rms_harness_boot_tgmpa( rms_harness_required_plugins() );
		rms_harness_load_production();
		$result = rms_harness_service()->install_missing();
		rms_harness_assert( empty( $result['classic-editor']['installed'] ), 'unresolved source must not mark installed' );
		rms_harness_assert( empty( $result['classic-editor']['active'] ), 'unresolved source must not mark active' );
		rms_harness_assert( 'install_failed' === $result['classic-editor']['action'], 'unresolved source must be install_failed' );
		break;

	case 'activation-fail':
		rms_harness_stub_wordpress();
		$GLOBALS['rms_harness']['installed']     = array( 'classic-editor' => true );
		$GLOBALS['rms_harness']['activate_fail'] = array( 'classic-editor' => true );
		rms_harness_boot_tgmpa( rms_harness_required_plugins() );
		rms_harness_load_production();
		$result = rms_harness_service()->install_missing();
		rms_harness_assert( ! empty( $result['classic-editor']['installed'] ), 'activation fail must keep installed true' );
		rms_harness_assert( empty( $result['classic-editor']['active'] ), 'activation fail must keep active false' );
		rms_harness_assert( 'activation_failed' === $result['classic-editor']['action'], 'must diagnose activation_failed' );
		break;

	case 'partial-fail':
		rms_harness_stub_wordpress();
		$GLOBALS['rms_harness']['installed'] = array(
			'advanced-custom-fields-pro' => true,
			'classic-editor'             => true,
			'contact-form-7'             => true,
		);
		$GLOBALS['rms_harness']['active'] = array(
			'advanced-custom-fields-pro' => true,
			'classic-editor'             => true,
			'contact-form-7'             => true,
		);
		$GLOBALS['rms_harness']['install_fail'] = array( 'wordpress-seo' => true );
		rms_harness_boot_tgmpa( rms_harness_required_plugins() );
		rms_harness_load_production();
		$result = rms_harness_service()->install_missing();
		$state  = ( new Inc\Wizard\State_Manager() )->get_state();
		$active = 0;
		foreach ( $result as $plugin ) {
			if ( ! empty( $plugin['active'] ) ) {
				$active++;
			}
		}
		rms_harness_assert( 3 === $active, 'expected 3 of 4 active, got ' . $active );
		rms_harness_assert( empty( $result['wordpress-seo']['active'] ), 'Yoast must remain inactive' );
		rms_harness_assert( 'failed' === ( $state['step_status']['dependencies'] ?? '' ), 'partial fail must stay failed' );
		break;

	case 'all-active-complete':
		rms_harness_stub_wordpress();
		rms_harness_boot_tgmpa( rms_harness_required_plugins() );
		rms_harness_load_production();
		$result = rms_harness_service()->install_missing();
		$state  = ( new Inc\Wizard\State_Manager() )->get_state();
		foreach ( $result as $slug => $plugin ) {
			rms_harness_assert( ! empty( $plugin['installed'] ), $slug . ' must be installed' );
			rms_harness_assert( ! empty( $plugin['active'] ), $slug . ' must be active' );
		}
		rms_harness_assert( 'complete' === ( $state['step_status']['dependencies'] ?? '' ), 'all active must complete' );
		rms_harness_assert( true === Inc\Wizard\Step_Controller::response_success_from_status( 'complete', true ), 'complete must be success' );
		break;

	case 'rerun-already-active':
		rms_harness_stub_wordpress();
		$GLOBALS['rms_harness']['installed'] = array(
			'advanced-custom-fields-pro' => true,
			'classic-editor'             => true,
			'contact-form-7'             => true,
			'wordpress-seo'              => true,
		);
		$GLOBALS['rms_harness']['active'] = $GLOBALS['rms_harness']['installed'];
		rms_harness_boot_tgmpa( rms_harness_required_plugins() );
		rms_harness_load_production();
		$result = rms_harness_service()->install_missing();
		$state  = ( new Inc\Wizard\State_Manager() )->get_state();
		foreach ( $result as $slug => $plugin ) {
			rms_harness_assert( 'already_active' === $plugin['action'], $slug . ' must be already_active' );
			rms_harness_assert( ! empty( $plugin['active'] ), $slug . ' must stay active' );
		}
		rms_harness_assert( array() === $GLOBALS['rms_harness']['install_sources'], 'rerun must not reinstall' );
		rms_harness_assert( 'complete' === ( $state['step_status']['dependencies'] ?? '' ), 'rerun must complete' );
		$logs = ( new Inc\Wizard\Logger() )->all();
		$found_already = false;
		foreach ( $logs as $entry ) {
			if ( 'Dependency already active.' === ( $entry['message'] ?? '' ) ) {
				$found_already = true;
				break;
			}
		}
		rms_harness_assert( $found_already, 'log must distinguish already-active' );
		break;

	case 'tgmpa-missing':
		rms_harness_stub_wordpress();
		rms_harness_load_production();
		$result = rms_harness_service()->install_missing();
		$state  = ( new Inc\Wizard\State_Manager() )->get_state();
		rms_harness_assert( array() === $result, 'missing TGMPA must return empty evidence' );
		rms_harness_assert( 'failed' === ( $state['step_status']['dependencies'] ?? '' ), 'missing TGMPA must fail the step' );
		break;

	default:
		fwrite( STDERR, "Unknown scenario {$scenario}\n" );
		exit( 1 );
}

exit( 0 );
