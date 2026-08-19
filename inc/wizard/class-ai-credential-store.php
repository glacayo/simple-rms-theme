<?php
/**
 * Encrypted AI credential storage.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Store per-provider API keys using reversible encryption.
 */
class AI_Credential_Store {
	public const OPTION_PREFIX = 'rms_wizard_ai_credential_';

	/**
	 * Derive a 32-byte encryption key.
	 */
	public static function derive_key(): string {
		if ( \defined( 'RMS_WIZARD_ENCRYPTION_KEY' ) && '' !== \constant( 'RMS_WIZARD_ENCRYPTION_KEY' ) ) {
			return \hash( 'sha256', \constant( 'RMS_WIZARD_ENCRYPTION_KEY' ), true );
		}

		$constants = [
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		];

		$parts = [];
		foreach ( $constants as $constant ) {
			if ( \defined( $constant ) ) {
				$parts[] = \constant( $constant );
			}
		}

		$combined = \implode( '', \array_filter( $parts, 'is_string' ) );

		if ( '' === $combined ) {
			return \hash( 'sha256', \wp_salt( 'auth' ) . \wp_salt( 'secure_auth' ), true );
		}

		return \hash( 'sha256', $combined, true );
	}

	/**
	 * Encrypt a plaintext string.
	 *
	 * @param string $plain Plaintext API key.
	 *
	 * @return string Base64-encoded ciphertext.
	 * @throws \RuntimeException When encryption fails.
	 */
	public static function encrypt( string $plain ): string {
		$key = self::derive_key();

		if ( \function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = \random_bytes( \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$box   = \sodium_crypto_secretbox( $plain, $nonce, $key );

			return 'sodium:' . \base64_encode( $nonce . $box );
		}

		if ( \function_exists( 'openssl_encrypt' ) ) {
			$iv   = \random_bytes( 12 );
			$tag  = '';
			$data = \openssl_encrypt( $plain, 'AES-256-GCM', $key, \OPENSSL_RAW_DATA, $iv, $tag );

			if ( false === $data ) {
				throw new \RuntimeException( \__( 'Encryption failed.', 'simple-rms-theme' ) );
			}

			return 'openssl:' . \base64_encode( $iv . $tag . $data );
		}

		throw new \RuntimeException( \__( 'No encryption extension available.', 'simple-rms-theme' ) );
	}

	/**
	 * Decrypt a previously encrypted string.
	 *
	 * @param string $cipher Base64-encoded ciphertext.
	 *
	 * @return string Plaintext or empty string on failure.
	 */
	public static function decrypt( string $cipher ): string {
		if ( '' === $cipher ) {
			return '';
		}

		$method  = '';
		$payload = $cipher;

		if ( false !== \strpos( $cipher, ':' ) ) {
			[ $method, $payload ] = \explode( ':', $cipher, 2 );
		}

		$raw = \base64_decode( $payload, true );

		if ( false === $raw ) {
			return '';
		}

		$key = self::derive_key();

		if ( ( '' === $method || 'sodium' === $method ) && \function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$nonce_len = \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
			if ( \strlen( $raw ) < $nonce_len ) {
				return '';
			}

			$nonce = \substr( $raw, 0, $nonce_len );
			$box   = \substr( $raw, $nonce_len );
			$plain = \sodium_crypto_secretbox_open( $box, $nonce, $key );

			return false === $plain ? '' : $plain;
		}

		if ( ( '' === $method || 'openssl' === $method ) && \function_exists( 'openssl_decrypt' ) ) {
			$iv   = \substr( $raw, 0, 12 );
			$tag  = \substr( $raw, 12, 16 );
			$data = \substr( $raw, 28 );

			$plain = \openssl_decrypt( $data, 'AES-256-GCM', $key, \OPENSSL_RAW_DATA, $iv, $tag );

			return false === $plain ? '' : $plain;
		}

		return '';
	}

	/**
	 * Save a provider API key (deletes if empty).
	 *
	 * @param string $provider Provider slug.
	 * @param string $api_key  API key.
	 *
	 * @return bool
	 */
	public static function save( string $provider, string $api_key ): bool {
		$option = self::OPTION_PREFIX . \sanitize_key( $provider );
		$api_key = self::normalize_api_key( $api_key );

		if ( '' === $api_key ) {
			return \delete_option( $option );
		}

		return \update_option( $option, self::encrypt( $api_key ), false );
	}

	/**
	 * Get a provider API key.
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return string
	 */
	public static function get( string $provider ): string {
		$encrypted = \get_option( self::OPTION_PREFIX . \sanitize_key( $provider ), '' );

		if ( '' === $encrypted ) {
			return '';
		}

		return self::decrypt( $encrypted );
	}

	/**
	 * Whether a provider has a stored key.
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return bool
	 */
	public static function has( string $provider ): bool {
		return '' !== \get_option( self::OPTION_PREFIX . \sanitize_key( $provider ), '' );
	}

	/**
	 * Return a masked representation of the stored key status.
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return string
	 */
	public static function mask_status( string $provider ): string {
		return self::has( $provider ) ? \__( 'Saved (masked)', 'simple-rms-theme' ) : \__( 'No key saved', 'simple-rms-theme' );
	}

	/**
	 * Return stored credential metadata safe for UI responses.
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return array{has_key:bool,status:string}
	 */
	public static function status( string $provider ): array {
		return [
			'has_key' => self::has( $provider ),
			'status'  => self::mask_status( $provider ),
		];
	}

	/**
	 * Normalize API keys without mangling valid token characters.
	 */
	public static function normalize_api_key( string $api_key ): string {
		$api_key = \trim( $api_key );
		$api_key = (string) \preg_replace( '/[\r\n\0]+/', '', $api_key );

		return $api_key;
	}
}
