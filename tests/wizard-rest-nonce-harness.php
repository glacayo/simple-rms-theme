<?php
/**
 * REST nonce rejection and acceptance through Rest_Controller.
 *
 * Usage: php tests/wizard-rest-nonce-harness.php
 */
if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

require_once __DIR__ . '/wizard-internal-page-activation-bootstrap.php';

if ( ! class_exists( 'WP_REST_Response', false ) ) {
	class WP_REST_Response {
		public $data;
		public $status;
		public function __construct( $data = null, $status = 200 ) {
			$this->data = $data;
			$this->status = $status;
		}
	}
}
if ( ! class_exists( 'WP_REST_Server', false ) ) {
	class WP_REST_Server {
		public const READABLE = 'GET';
		public const CREATABLE = 'POST';
	}
}
if ( ! class_exists( 'WP_REST_Request', false ) ) {
	class WP_REST_Request {
		private $headers = array();
		public function get_header( $name ) {
			return $this->headers[ strtolower( (string) $name ) ] ?? '';
		}
		public function set_header( $name, $value ) {
			$this->headers[ strtolower( (string) $name ) ] = (string) $value;
		}
	}
}

$GLOBALS['_nonces'] = array( 'valid-nonce' => 'wp_rest' );
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action ) {
		return isset( $GLOBALS['_nonces'][ (string) $nonce ] ) && $action === $GLOBALS['_nonces'][ (string) $nonce ];
	}
}

require_once dirname( __DIR__ ) . '/inc/wizard/class-rest-controller.php';

use Inc\Wizard\Rest_Controller;

$passed = 0;
$rest = new Rest_Controller();
$missing = new WP_REST_Request();
rms_ipa_assert( false === $rest->rest_nonce_is_valid( $missing ), 'missing nonce rejected' );
rms_ipa_assert( false === $rest->permission_callback( $missing ), 'permission denies missing nonce' );
$bad = new WP_REST_Request();
$bad->set_header( 'X-WP-Nonce', 'forged' );
rms_ipa_assert( false === $rest->rest_nonce_is_valid( $bad ), 'invalid nonce rejected' );
$ok = new WP_REST_Request();
$ok->set_header( 'X-WP-Nonce', 'valid-nonce' );
rms_ipa_assert( true === $rest->rest_nonce_is_valid( $ok ), 'valid nonce accepted via wp_verify_nonce' );
rms_ipa_assert( true === $rest->permission_callback( $ok ), 'permission allows valid nonce' );
$GLOBALS['_can'] = false;
rms_ipa_assert( false === $rest->permission_callback( $ok ), 'capability still required with valid nonce' );
echo "PASS valid-rest-nonce-admin-discovery\n";
++$passed;
echo 'Harness passed: ' . $passed . " scenarios.\n";
