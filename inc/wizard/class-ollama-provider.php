<?php
/**
 * Ollama Cloud provider implementation.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Ollama Cloud AI provider.
 */
class Ollama_Provider extends AI_Provider {
	public const LIST_ENDPOINT     = 'https://ollama.com/api/tags';
	public const GENERATE_ENDPOINT = 'https://ollama.com/api/chat';

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
				'error'   => \__( 'Ollama Cloud API key is missing.', 'simple-rms-theme' ),
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
				'error'   => \sprintf( \__( 'Ollama Cloud returned HTTP %d.', 'simple-rms-theme' ), $code ),
			];
		}

		$raw_body = (string) \wp_remote_retrieve_body( $response );
		$data     = \json_decode( $raw_body, true );
		$content  = '';

		if ( \is_array( $data ) && isset( $data['message']['content'] ) && \is_string( $data['message']['content'] ) ) {
			$content = \trim( $data['message']['content'] );
		} elseif ( \is_array( $data ) && isset( $data['response'] ) && \is_string( $data['response'] ) ) {
			$content = \trim( $data['response'] );
		} else {
			$content = \trim( $raw_body );
		}

		if ( '' === $content ) {
			return [
				'success' => false,
				'content' => '',
				'error'   => \__( 'Ollama Cloud returned empty content.', 'simple-rms-theme' ),
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
			return new \WP_Error( 'rms_wizard_missing_ollama_key', \__( 'Ollama Cloud API key is missing.', 'simple-rms-theme' ), [ 'status' => 400 ] );
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
				'rms_wizard_ollama_models_failed',
				\sprintf( \__( 'Ollama Cloud model list failed with HTTP %d.', 'simple-rms-theme' ), $code ),
				[ 'status' => $code ]
			);
		}

		$body     = (string) \wp_remote_retrieve_body( $response );
		$data     = \json_decode( $body, true );
		$models   = [];

		if ( ! \is_array( $data ) || ! isset( $data['models'] ) || ! \is_array( $data['models'] ) ) {
			return new \WP_Error( 'rms_wizard_ollama_models_invalid', \__( 'Ollama Cloud returned an unexpected model list response.', 'simple-rms-theme' ), [ 'status' => 502 ] );
		}

		foreach ( $data['models'] as $model ) {
			if ( ! \is_array( $model ) || ! isset( $model['name'] ) ) {
				continue;
			}

			$name  = \sanitize_text_field( $model['name'] );
			$label = isset( $model['label'] ) && \is_string( $model['label'] ) ? \sanitize_text_field( $model['label'] ) : $name;

			$models[] = [
				'id'    => $name,
				'label' => $label,
			];
		}

		return $models;
	}

	private function headers(): array {
		$headers = [ 'Content-Type' => 'application/json' ];

		if ( '' !== $this->api_key ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		return $headers;
	}

}
