<?php
/**
 * OpenRouter provider implementation.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * OpenRouter hosted AI provider.
 *
 * Wire contract:
 *  - Generate: POST https://openrouter.ai/api/v1/chat/completions with
 *    `Authorization: Bearer <key>`, `Content-Type: application/json`, the
 *    default OpenAI-shaped body (`model`, `messages` with an optional system
 *    entry followed by the user entry, `stream: false`), and the default
 *    OpenRouter attribution headers `HTTP-Referer` (site URL) and `X-Title`
 *    (site title or theme name). No fallback/auto-routing in v1.
 *  - List/validate: GET https://openrouter.ai/api/v1/models with Bearer auth
 *    and the same attribution headers; maps `data[].id` and `data[].name`
 *    (falling back to the id) to `{id,label}`.
 *
 * A successful `list_models()` response is the explicit credential validation
 * per the wizard-ai-providers spec (Provider Setup Gating). No `validate()`
 * method is added in v1. API keys and the full Authorization header are never
 * logged. Curated/manual model lists never count as successful validation.
 */
class OpenRouter_Provider extends AI_Provider {
	public const LIST_ENDPOINT     = 'https://openrouter.ai/api/v1/models';
	public const GENERATE_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

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
				'error'   => \__( 'OpenRouter API key is missing.', 'simple-rms-theme' ),
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
				'error'   => \sprintf( \__( 'OpenRouter returned HTTP %d.', 'simple-rms-theme' ), $code ),
			];
		}

		$raw_body = (string) \wp_remote_retrieve_body( $response );
		$data     = \json_decode( $raw_body, true );
		$content  = '';

		// OpenRouter uses the OpenAI-compatible `choices[0].message.content` shape.
		if ( \is_array( $data ) && isset( $data['choices'][0]['message']['content'] ) && \is_string( $data['choices'][0]['message']['content'] ) ) {
			$content = \trim( $data['choices'][0]['message']['content'] );
		}

		if ( '' === $content ) {
			return [
				'success' => false,
				'content' => '',
				'error'   => \__( 'OpenRouter returned empty content.', 'simple-rms-theme' ),
			];
		}

		$this->cache_content( $content, $context );

		return [ 'success' => true, 'content' => $content ];
	}

	/**
	 * List available models via the live OpenRouter models endpoint.
	 *
	 * A successful response is the explicit credential validation per the
	 * wizard-ai-providers spec. Curated/manual lists never validate.
	 *
	 * @return array<int,array{id:string,label:string}>|\WP_Error
	 */
	public function list_models() {
		if ( '' === $this->api_key ) {
			return new \WP_Error( 'rms_wizard_missing_openrouter_key', \__( 'OpenRouter API key is missing.', 'simple-rms-theme' ), [ 'status' => 400 ] );
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
				'rms_wizard_openrouter_models_failed',
				\sprintf( \__( 'OpenRouter model list failed with HTTP %d.', 'simple-rms-theme' ), $code ),
				[ 'status' => $code ]
			);
		}

		$body   = (string) \wp_remote_retrieve_body( $response );
		$data   = \json_decode( $body, true );
		$models = [];

		if ( ! \is_array( $data ) || ! isset( $data['data'] ) || ! \is_array( $data['data'] ) ) {
			return new \WP_Error( 'rms_wizard_openrouter_models_invalid', \__( 'OpenRouter returned an unexpected model list response.', 'simple-rms-theme' ), [ 'status' => 502 ] );
		}

		foreach ( $data['data'] as $model ) {
			if ( ! \is_array( $model ) || ! isset( $model['id'] ) || ! \is_string( $model['id'] ) ) {
				continue;
			}

			$id = \sanitize_text_field( $model['id'] );

			if ( '' === $id ) {
				continue;
			}

			// OpenRouter exposes `name` on each model entry; fall back to the id.
			if ( isset( $model['name'] ) && \is_string( $model['name'] ) ) {
				$label = \sanitize_text_field( $model['name'] );
			} else {
				$label = $id;
			}

			if ( '' === $label ) {
				$label = $id;
			}

			$models[] = [
				'id'    => $id,
				'label' => $label,
			];
		}

		if ( [] === $models ) {
			return new \WP_Error( 'rms_wizard_openrouter_models_empty', \__( 'OpenRouter returned no models for the supplied credential.', 'simple-rms-theme' ), [ 'status' => 502 ] );
		}

		return $models;
	}

	/**
	 * Build request headers including the default OpenRouter attribution
	 * headers (`HTTP-Referer`, `X-Title`). The Authorization header and API
	 * key are never logged.
	 */
	private function headers(): array {
		$headers = [ 'Content-Type' => 'application/json' ];

		if ( '' !== $this->api_key ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		// OpenRouter attribution headers. Defaults only in v1 (no admin override).
		$referer = $this->default_referer();
		$title   = $this->default_title();

		if ( '' !== $referer ) {
			$headers['HTTP-Referer'] = $referer;
		}

		if ( '' !== $title ) {
			$headers['X-Title'] = $title;
		}

		return $headers;
	}

	/**
	 * Default `HTTP-Referer` value: the site home URL. Returns an empty string
	 * when the URL cannot be resolved (e.g. during non-HTTP bootstrap), so the
	 * header is simply omitted rather than sent blank.
	 */
	private function default_referer(): string {
		if ( ! \function_exists( 'home_url' ) ) {
			return '';
		}

		$url = \home_url();

		if ( ! \is_string( $url ) || '' === $url ) {
			return '';
		}

		return $url;
	}

	/**
	 * Default `X-Title` value: the site title, falling back to the theme name
	 * ("Simple RMS Theme"). Never logged at error level.
	 */
	private function default_title(): string {
		if ( \function_exists( 'get_bloginfo' ) ) {
			$title = \get_bloginfo( 'name' );

			if ( \is_string( $title ) && '' !== $title ) {
				return $title;
			}
		}

		return \__( 'Simple RMS Theme', 'simple-rms-theme' );
	}
}