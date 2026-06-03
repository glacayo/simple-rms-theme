<?php
/**
 * Abstract AI provider.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Generic AI provider.
 */
class AI_Provider {
	protected $api_key;

	public function __construct( string $api_key = '' ) {
		$this->api_key = $api_key;
	}

	public function get_api_key(): string {
		return $this->api_key;
	}

	/**
	 * Generate content from the provider.
	 *
	 * @param string $model   Model identifier.
	 * @param string $prompt  Prompt text.
	 * @param array  $context Optional context.
	 *
	 * @return array{success:bool,content:string,error?:string}
	 */
	public function generate( string $model, string $prompt, array $context = [] ): array {
		return [
			'success' => false,
			'content' => '',
			'error'   => \__( 'Unknown provider selected.', 'simple-rms-theme' ),
		];
	}

	/**
	 * List available models for the provider.
	 *
	 * @return array<int,array{id:string,label:string}>|\WP_Error
	 */
	public function list_models() {
		return new \WP_Error( 'rms_wizard_unknown_ai_provider', \__( 'Unknown provider selected.', 'simple-rms-theme' ), [ 'status' => 400 ] );
	}

	protected function cache_content( string $content, array $context ): void {
		if ( empty( $context['session_id'] ) || empty( $context['section_key'] ) ) {
			return;
		}

		$key = 'rms_wizard_section_' . \md5( (string) $context['session_id'] . ':' . (string) $context['section_key'] );
		\set_transient( $key, $content, \DAY_IN_SECONDS );
	}
}
