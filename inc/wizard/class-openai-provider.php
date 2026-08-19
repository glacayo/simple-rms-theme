<?php
/**
 * OpenAI provider implementation.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * OpenAI hosted AI provider.
 *
 * Wire contract:
 *  - Generate: POST https://api.openai.com/v1/chat/completions with
 *    `Authorization: Bearer <key>`, `model`, `messages` (system when non-empty
 *    plus user), and `stream: false`.
 *  - List/validate: GET https://api.openai.com/v1/models with Bearer auth.
 *
 * A successful `list_models()` response is the explicit credential validation
 * per the wizard-ai-providers spec (Provider Setup Gating). No `validate()`
 * method is added in v1. API keys and the full Authorization header are never
 * logged.
 */
class OpenAI_Provider extends AI_Provider {
	public const LIST_ENDPOINT     = 'https://api.openai.com/v1/models';
	public const GENERATE_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

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
				'error'   => \__( 'OpenAI API key is missing.', 'simple-rms-theme' ),
			];
		}

		if ( '' === $model ) {
			return [
				'success' => false,
				'content' => '',
				'error'   => \__( 'Model is required.', 'simple-rms-theme' ),
			];
		}

		$messages = [
			[
				'role'    => 'user',
				'content' => $prompt,
			],
		];

		$system = \trim( $system );

		if ( '' !== $system ) {
			$messages = [
				[
					'role'    => 'system',
					'content' => $system,
				],
				[
					'role'    => 'user',
					'content' => $prompt,
				],
			];
		}

		$body = \wp_json_encode(
			[
				'model'    => $model,
				'messages' => $messages,
				'stream'   => false,
			]
		);

		if ( false === $body ) {
			return [
				'success' => false,
				'content' => '',
				'error'   => \__( 'Failed to encode request body.', 'simple-rms-theme' ),
			];
		}

		$response = \wp_remote_request(
			self::GENERATE_ENDPOINT,
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
				'error'   => \sprintf( \__( 'OpenAI returned HTTP %d.', 'simple-rms-theme' ), $code ),
			];
		}

		$raw_body = (string) \wp_remote_retrieve_body( $response );
		$data     = \json_decode( $raw_body, true );
		$content  = '';

		if ( \is_array( $data ) && isset( $data['choices'][0]['message']['content'] ) && \is_string( $data['choices'][0]['message']['content'] ) ) {
			$content = \trim( $data['choices'][0]['message']['content'] );
		}

		if ( '' === $content ) {
			return [
				'success' => false,
				'content' => '',
				'error'   => \__( 'OpenAI returned empty content.', 'simple-rms-theme' ),
			];
		}

		$this->cache_content( $content, $context );

		return [ 'success' => true, 'content' => $content ];
	}

	/**
	 * @return array<int,array{id:string,label:string}>|\WP_Error
	 */
	public function list_models() {
		if ( '' === $this->api_key ) {
			return new \WP_Error( 'rms_wizard_missing_openai_key', \__( 'OpenAI API key is missing.', 'simple-rms-theme' ), [ 'status' => 400 ] );
		}

		$response = \wp_remote_get(
			self::LIST_ENDPOINT,
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
				'rms_wizard_openai_models_failed',
				\sprintf( \__( 'OpenAI model list failed with HTTP %d.', 'simple-rms-theme' ), $code ),
				[ 'status' => $code ]
			);
		}

		$body     = (string) \wp_remote_retrieve_body( $response );
		$data     = \json_decode( $body, true );
		$models   = [];

		if ( ! \is_array( $data ) || ! isset( $data['data'] ) || ! \is_array( $data['data'] ) ) {
			return new \WP_Error( 'rms_wizard_openai_models_invalid', \__( 'OpenAI returned an unexpected model list response.', 'simple-rms-theme' ), [ 'status' => 502 ] );
		}

		foreach ( $data['data'] as $model ) {
			if ( ! \is_array( $model ) || ! isset( $model['id'] ) ) {
				continue;
			}

			$id    = \sanitize_text_field( $model['id'] );
			$label = isset( $model['label'] ) && \is_string( $model['label'] ) ? \sanitize_text_field( $model['label'] ) : $id;

			$models[] = [
				'id'    => $id,
				'label' => $label,
			];
		}

		if ( [] === $models ) {
			return new \WP_Error( 'rms_wizard_openai_models_empty', \__( 'OpenAI returned no models for the supplied credential.', 'simple-rms-theme' ), [ 'status' => 502 ] );
		}

		return $models;
	}

	/**
	 * Build request headers. The Authorization header is never logged.
	 */
	private function headers(): array {
		$headers = [ 'Content-Type' => 'application/json' ];

		if ( '' !== $this->api_key ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		return $headers;
	}
}