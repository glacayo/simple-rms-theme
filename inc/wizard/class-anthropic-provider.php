<?php
/**
 * Anthropic provider implementation.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Anthropic hosted AI provider.
 *
 * Wire contract:
 *  - Generate: POST https://api.anthropic.com/v1/messages with `x-api-key`,
 *    `anthropic-version: 2023-06-01`, `content-type: application/json`, a top-level
 *    `system` only when non-empty, `model`, `max_tokens` (default 2048), and a single
 *    user `messages[]` entry. No streaming.
 *  - List/validate: GET https://api.anthropic.com/v1/models with the same auth headers.
 *
 * A successful `list_models()` response is the explicit credential validation
 * per the wizard-ai-providers spec (Provider Setup Gating). No `validate()`
 * method is added in v1. API keys and sensitive headers are never logged.
 */
class Anthropic_Provider extends AI_Provider {
	public const LIST_ENDPOINT     = 'https://api.anthropic.com/v1/models';
	public const GENERATE_ENDPOINT = 'https://api.anthropic.com/v1/messages';
	public const API_VERSION       = '2023-06-01';
	public const DEFAULT_MAX_TOKENS = 2048;

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
				'error'   => \__( 'Anthropic API key is missing.', 'simple-rms-theme' ),
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

		$payload = [
			'model'      => $model,
			'max_tokens' => self::DEFAULT_MAX_TOKENS,
			'messages'   => $messages,
		];

		if ( '' !== $system ) {
			$payload['system'] = $system;
		}

		$body = \wp_json_encode( $payload );

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
				'error'   => \sprintf( \__( 'Anthropic returned HTTP %d.', 'simple-rms-theme' ), $code ),
			];
		}

		$raw_body = (string) \wp_remote_retrieve_body( $response );
		$data     = \json_decode( $raw_body, true );
		$content  = $this->extract_text( $data );

		if ( '' === $content ) {
			return [
				'success' => false,
				'content' => '',
				'error'   => \__( 'Anthropic returned empty content.', 'simple-rms-theme' ),
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
			return new \WP_Error( 'rms_wizard_missing_anthropic_key', \__( 'Anthropic API key is missing.', 'simple-rms-theme' ), [ 'status' => 400 ] );
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
				'rms_wizard_anthropic_models_failed',
				\sprintf( \__( 'Anthropic model list failed with HTTP %d.', 'simple-rms-theme' ), $code ),
				[ 'status' => $code ]
			);
		}

		$body   = (string) \wp_remote_retrieve_body( $response );
		$data   = \json_decode( $body, true );
		$models = [];

		if ( ! \is_array( $data ) || ! isset( $data['data'] ) || ! \is_array( $data['data'] ) ) {
			return new \WP_Error( 'rms_wizard_anthropic_models_invalid', \__( 'Anthropic returned an unexpected model list response.', 'simple-rms-theme' ), [ 'status' => 502 ] );
		}

		foreach ( $data['data'] as $model ) {
			if ( ! \is_array( $model ) || ! isset( $model['id'] ) || ! \is_string( $model['id'] ) ) {
				continue;
			}

			$id = \sanitize_text_field( $model['id'] );

			if ( isset( $model['display_name'] ) && \is_string( $model['display_name'] ) ) {
				$label = \sanitize_text_field( $model['display_name'] );
			} else {
				$label = $id;
			}

			$models[] = [
				'id'    => $id,
				'label' => $label,
			];
		}

		if ( [] === $models ) {
			return new \WP_Error( 'rms_wizard_anthropic_models_empty', \__( 'Anthropic returned no models for the supplied credential.', 'simple-rms-theme' ), [ 'status' => 502 ] );
		}

		return $models;
	}

	/**
	 * Build request headers. The x-api-key header is never logged.
	 */
	private function headers(): array {
		return [
			'Content-Type'      => 'application/json',
			'x-api-key'         => $this->api_key,
			'anthropic-version' => self::API_VERSION,
		];
	}

	/**
	 * Extract the first text block from the Anthropic `content[]` response.
	 *
	 * @param mixed $data Decoded JSON response.
	 */
	private function extract_text( $data ): string {
		if ( ! \is_array( $data ) || ! isset( $data['content'] ) || ! \is_array( $data['content'] ) ) {
			return '';
		}

		foreach ( $data['content'] as $block ) {
			if ( ! \is_array( $block ) ) {
				continue;
			}

			if ( isset( $block['text'] ) && \is_string( $block['text'] ) ) {
				$trimmed = \trim( $block['text'] );
				if ( '' !== $trimmed ) {
					return $trimmed;
				}
			}
		}

		return '';
	}
}