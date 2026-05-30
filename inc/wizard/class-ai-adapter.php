<?php
/**
 * Wizard AI HTTP adapter.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Calls AI provider APIs through the WordPress HTTP API with retry/backoff.
 */
class AI_Adapter {
	private const MAX_ATTEMPTS = 3;

	private $logger;
	private $endpoint;
	private $api_key;
	private $model;

	public function __construct( string $endpoint, string $api_key, string $model = '', ?Logger $logger = null ) {
		$this->endpoint = $endpoint;
		$this->api_key  = $api_key;
		$this->model    = $model;
		$this->logger   = $logger ?? new Logger();
	}

	/**
	 * Generate content from the configured provider.
	 *
	 * @param string $prompt  Prompt text.
	 * @param array  $context Request context and optional session/section cache keys.
	 *
	 * @return array<string,mixed>
	 */
	public function generate( string $prompt, array $context = [] ): array {
		$body = [
			'prompt'  => $prompt,
			'context' => $context,
		];

		if ( '' !== $this->model ) {
			$body['model'] = $this->model;
		}

		$last_error = 'Unknown provider error.';

		for ( $attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++ ) {
			$response = \wp_remote_request(
				$this->endpoint,
				[
					'method'  => 'POST',
					'timeout' => 45,
					'headers' => $this->headers(),
					'body'    => \wp_json_encode( $body ),
				]
			);

			if ( \is_wp_error( $response ) ) {
				$last_error = $response->get_error_message();
			} else {
				$code    = (int) \wp_remote_retrieve_response_code( $response );
				$content = $this->extract_content( (string) \wp_remote_retrieve_body( $response ) );

				if ( $code >= 200 && $code < 300 && '' !== $content ) {
					$this->cache_content( $content, $context );

					return [ 'success' => true, 'content' => $content, 'error' => null, 'attempts' => $attempt ];
				}

				$last_error = sprintf( 'Provider returned HTTP %d.', $code );
			}

			$this->logger->log( 'warning', 'AI generation attempt failed.', [ 'attempt' => $attempt, 'error' => $last_error ] );

			if ( $attempt < self::MAX_ATTEMPTS ) {
				sleep( 2 ** ( $attempt - 1 ) );
			}
		}

		$this->logger->log( 'error', 'AI generation failed after retry limit.', [ 'error' => $last_error ] );

		return [ 'success' => false, 'content' => '', 'error' => $last_error, 'attempts' => self::MAX_ATTEMPTS ];
	}

	private function headers(): array {
		$headers = [ 'Content-Type' => 'application/json' ];

		if ( '' !== $this->api_key ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		return $headers;
	}

	private function extract_content( string $body ): string {
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return trim( $body );
		}

		$paths = [
			[ 'content' ],
			[ 'text' ],
			[ 'choices', 0, 'message', 'content' ],
			[ 'candidates', 0, 'content', 'parts', 0, 'text' ],
			[ 'content', 0, 'text' ],
		];

		foreach ( $paths as $path ) {
			$value = $data;

			foreach ( $path as $segment ) {
				if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
					$value = null;
					break;
				}

				$value = $value[ $segment ];
			}

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		return '';
	}

	private function cache_content( string $content, array $context ): void {
		if ( empty( $context['session_id'] ) || empty( $context['section_key'] ) ) {
			return;
		}

		$key = 'rms_wizard_section_' . md5( (string) $context['session_id'] . ':' . (string) $context['section_key'] );
		\set_transient( $key, $content, \DAY_IN_SECONDS );
	}
}
