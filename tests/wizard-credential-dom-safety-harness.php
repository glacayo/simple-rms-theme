<?php
/**
 * Focused production-path harness for issue #25 (credential DOM / response safety).
 *
 * Sentinel-only. Never uses a real API key.
 *
 * Usage: php tests/wizard-credential-dom-safety-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

const RMS_HARNESS_SENTINEL = 'rms-sentinel-not-a-real-key-22f25';

/**
 * @param mixed $condition
 */
function rms_harness_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

function rms_harness_contains_sentinel( $value ): bool {
	return false !== strpos( wp_json_encode( $value ), RMS_HARNESS_SENTINEL );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['rms_harness'] = array(
	'options' => array(),
);

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
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}
if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'harness-salt-' . $scheme;
	}
}

$root = dirname( __DIR__ );
require_once $root . '/inc/wizard/class-logger.php';
require_once $root . '/inc/wizard/class-ai-credential-store.php';

$passed = 0;

$logger = new Inc\Wizard\Logger();
$entry  = $logger->log(
	'info',
	'Provider test completed.',
	array(
		'provider' => 'openai',
		'api_key'  => RMS_HARNESS_SENTINEL,
		'nested'   => array( 'authorization' => RMS_HARNESS_SENTINEL ),
	)
);
rms_harness_assert( ! rms_harness_contains_sentinel( $entry ), 'log entry leaked sentinel' );
rms_harness_assert( ! rms_harness_contains_sentinel( $logger->all() ), 'stored logs leaked sentinel' );
rms_harness_assert( '[redacted]' === ( $entry['context']['api_key'] ?? null ), 'api_key context was not redacted' );
rms_harness_assert( '[redacted]' === ( $entry['context']['nested']['authorization'] ?? null ), 'nested authorization was not redacted' );
echo "PASS logger-redacts-sentinel\n";
$passed++;

$option = Inc\Wizard\AI_Credential_Store::OPTION_PREFIX . 'openai';
$GLOBALS['rms_harness']['options'][ $option ] = 'openssl:dGVzdC1jaXBoZXJ0ZXh0LW5vdC1hLXNlbnRpbmVs';
$status = Inc\Wizard\AI_Credential_Store::status( 'openai' );
rms_harness_assert( true === $status['has_key'], 'saved key must report has_key true' );
rms_harness_assert( ! rms_harness_contains_sentinel( $status ), 'status payload leaked sentinel' );
rms_harness_assert( ! array_key_exists( 'api_key', $status ), 'status must not expose api_key field' );

$models_response = array(
	'success'    => true,
	'provider'   => 'openai',
	'models'     => array( array( 'id' => 'gpt-test', 'label' => 'GPT Test' ) ),
	'credential' => $status,
);
$save_response = array(
	'success' => true,
	'step'    => 'ia-generation',
	'result'  => array(
		'ai_config' => array(
			'provider'        => 'openai',
			'model'           => 'gpt-test',
			'credential'      => $status,
			'has_credentials' => true,
		),
	),
);
rms_harness_assert( ! rms_harness_contains_sentinel( $models_response ), 'models response leaked sentinel' );
rms_harness_assert( ! rms_harness_contains_sentinel( $save_response ), 'save response leaked sentinel' );
echo "PASS responses-masked\n";
$passed++;

$markup = (string) file_get_contents( $root . '/inc/wizard/wizard-init.php' );
rms_harness_assert( false !== strpos( $markup, 'name="api_key"' ), 'IA form is missing the api_key input' );
rms_harness_assert( false === strpos( $markup, 'data-api-key' ), 'IA form must not expose a data-api-key attribute' );
	rms_harness_assert( 1 !== preg_match( '/name="api_key"[^>]*value="/', $markup ), 'IA form must not hydrate a value attribute' );
rms_harness_assert( false !== strpos( $markup, 'Leave blank to use the saved encrypted key' ), 'IA form is missing the saved-key placeholder' );
echo "PASS markup-has-no-secret-value\n";
$passed++;

echo "Harness passed: {$passed} scenarios.\n";
exit( 0 );
