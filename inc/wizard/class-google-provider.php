<?php
/**
 * Google Gemini provider implementation.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Google Gemini hosted AI provider.
 *
 * Wire contract:
 *  - Generate: POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key=<key>
 *    with `Content-Type: application/json`, `systemInstruction.parts[0].text` only
 *    when the system prompt is non-empty, and `contents` with one user message/parts text.
 *    No streaming.
 *  - List/validate: GET https://generativelanguage.googleapis.com/v1beta/models?key=<key>
 *
 * The API key is a query parameter. URLs are built with `add_query_arg()`; the
 * full URL and API key are never logged. Only the host is recorded on errors.
 *
 * A successful `list_models()` response is the explicit credential validation
 * per the wizard-ai-providers spec (Provider Setup Gating). No `validate()`
 * method is added in v1. Curated/manual model lists never count as successful
 * validation.
 */
class Google_Provider extends AI_Provider {
	public const API_HOST   = 'https://generativelanguage.googleapis.com';
	public const LIST_PATH  = '/v1beta/models';
	public const GENERATE_PATH_TEMPLATE = '/v1beta/models/%s:generateContent';

	public function __construct( string $api_key = '' ) {
		parent::__construct( $api_key );
	}

	/**
	 * @return array{success:bool,content:string,error?:string}
	 */
	public function generate( string $model, string $prompt, array $context = [], string $system = '' ): array {
		if ( '' === $this->api_key ) {
			return [
				'success' => false,
				'content' => '',
				'error'   => \__( 'Google Gemini API key is missing.', 'simple-rms-theme' ),
			];
		}

		if ( '' === $model ) {
			return [
				'success' => false,
				'content' => '',
				'error'   => \__( 'Model is required.', 'simple-rms-theme' ),
			];
		}

		$system = \trim( $system );

		$payload = [
			'contents' => [
				[
					'role'  => 'user',
					'parts' => [
						[ 'text' => $prompt ],
					],
				],
			],
		];

		if ( '' !== $system ) {
			$payload['systemInstruction'] = [
				'parts' => [
					[ 'text' => $system ],
				],
			];
		}

		$body = \wp_json_encode( $payload );

		if ( false === $body ) {
			return [
				'success' => false,
				'content' => '',
				'error'   => \__( 'Failed to encode request body.', 'simple-rms-theme' ),
			];
		}

		$model_segment  = $this->normalize_model_id( $model );
		$generate_path  = \sprintf( self::GENERATE_PATH_TEMPLATE, $model_segment );
		$generate_url   = \add_query_arg( 'key', $this->api_key, self::API_HOST . $generate_path );

		$response = \wp_remote_request(
			$generate_url,
			[
				'method'  => 'POST',
				'timeout' => 45,
				'headers' => $this->headers(),
				'body'    => $body,
			]
		);

		if ( \is_wp_error( $response ) ) {
			return [
				'success' => false,
				'content' => '',
				'error'   => $response->get_error_message(),
			];
		}

		$code = (int) \wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			return [
				'success' => false,
				'content' => '',
				'error'   => \sprintf( \__( 'Google Gemini returned HTTP %d.', 'simple-rms-theme' ), $code ),
			];
		}

		$raw_body = (string) \wp_remote_retrieve_body( $response );
		$data     = \json_decode( $raw_body, true );
		$content  = $this->extract_text( $data );

		if ( '' === $content ) {
			return [
				'success' => false,
				'content' => '',
				'error'   => \__( 'Google Gemini returned empty content.', 'simple-rms-theme' ),
			];
		}

		$this->cache_content( $content, $context );

		return [ 'success' => true, 'content' => $content ];
	}

	/**
	 * List usable generative models via the live Google Gemini models endpoint.
	 *
	 * A successful response is the explicit credential validation per the
	 * wizard-ai-providers spec. Curated/manual lists never validate.
	 *
	 * @return array<int,array{id:string,label:string}>|\WP_Error
	 */
	public function list_models() {
		if ( '' === $this->api_key ) {
			return new \WP_Error( 'rms_wizard_missing_google_key', \__( 'Google Gemini API key is missing.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		$list_url = \add_query_arg( 'key', $this->api_key, self::API_HOST . self::LIST_PATH );

		$response = \wp_remote_get(
			$list_url,
			[
				'timeout' => 20,
				'headers' => $this->headers(),
			]
		);

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) \wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'rms_wizard_google_models_failed',
				\sprintf( \__( 'Google Gemini model list failed with HTTP %d.', 'simple-rms-theme' ), $code ),
				[ 'status' => $code ]
			);
		}

		$body   = (string) \wp_remote_retrieve_body( $response );
		$data   = \json_decode( $body, true );
		$models = [];

		if ( ! \is_array( $data ) || ! isset( $data['models'] ) || ! \is_array( $data['models'] ) ) {
			return new \WP_Error( 'rms_wizard_google_models_invalid', \__( 'Google Gemini returned an unexpected model list response.', 'simple-rms-theme' ), [ 'status' => 502 ] );
		}

		foreach ( $data['models'] as $model ) {
			if ( ! \is_array( $model ) || ! isset( $model['name'] ) || ! \is_string( $model['name'] ) ) {
				continue;
			}

			// Skip models that cannot generate content (e.g. embedding/text-only models).
			if ( ! $this->supports_generate_content( $model ) ) {
				continue;
			}

			$id = $this->normalize_model_id( \sanitize_text_field( $model['name'] ) );

			if ( '' === $id ) {
				continue;
			}

			$label = isset( $model['displayName'] ) && \is_string( $model['displayName'] )
				? \sanitize_text_field( $model['displayName'] )
				: $id;

			$models[] = [
				'id'    => $id,
				'label' => $label,
			];
		}

		if ( [] === $models ) {
			return new \WP_Error( 'rms_wizard_google_models_empty', \__( 'Google Gemini returned no usable generative models for the supplied credential.', 'simple-rms-theme' ), [ 'status' => 502 ] );
		}

		return $models;
	}

	/**
	 * Build request headers. No API key is placed in headers for Google Gemini;
	 * the key travels in the query string. Only the host is ever logged.
	 */
	private function headers(): array {
		return [ 'Content-Type' => 'application/json' ];
	}

	/**
	 * Strip a leading `models/` prefix from a Gemini model name so the segment
	 * passed to `:generateContent` is a valid bare model id.
	 *
	 * @param string $model_id Raw model name (e.g. `models/gemini-...`).
	 */
	private function normalize_model_id( string $model_id ): string {
		$model_id = \trim( $model_id );

		if ( '' === $model_id ) {
			return '';
		}

		if ( 0 === \strpos( $model_id, 'models/' ) ) {
			$model_id = \substr( $model_id, 7 );
		}

		return $model_id;
	}

	/**
	 * Determine whether a Google Gemini model entry supports generateContent.
	 *
	 * Gemini's models endpoint exposes `supportedGenerationMethods` per model.
	 * We keep only entries that include `generateContent`.
	 *
	 * @param array $model Single model entry from the models list response.
	 */
	private function supports_generate_content( array $model ): bool {
		if ( ! isset( $model['supportedGenerationMethods'] ) || ! \is_array( $model['supportedGenerationMethods'] ) ) {
			return false;
		}

		foreach ( $model['supportedGenerationMethods'] as $method ) {
			if ( \is_string( $method ) && 'generateContent' === $method ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract assistant text from the Gemini `candidates[0].content.parts[].text` shape.
	 *
	 * Walks `candidates[].content.parts[]` and returns the first non-empty text
	 * fragment found. Falls back to concatenating all non-empty text fragments
	 * if no single non-empty fragment is found in the first candidate.
	 *
	 * @param mixed $data Decoded JSON response.
	 */
	private function extract_text( $data ): string {
		if ( ! \is_array( $data ) || ! isset( $data['candidates'] ) || ! \is_array( $data['candidates'] ) ) {
			return '';
		}

		foreach ( $data['candidates'] as $candidate ) {
			if ( ! \is_array( $candidate ) || ! isset( $candidate['content']['parts'] ) || ! \is_array( $candidate['content']['parts'] ) ) {
				continue;
			}

			// First non-empty text fragment wins (mirrors the first-text contract
			// used by the other providers).
			foreach ( $candidate['content']['parts'] as $part ) {
				if ( ! \is_array( $part ) ) {
					continue;
				}

				if ( isset( $part['text'] ) && \is_string( $part['text'] ) ) {
					$trimmed = \trim( $part['text'] );
					if ( '' !== $trimmed ) {
						return $trimmed;
					}
				}
			}
		}

		return '';
	}
}