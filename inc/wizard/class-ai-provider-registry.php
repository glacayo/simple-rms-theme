<?php
/**
 * AI provider registry.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Resolve and register AI provider implementations.
 */
class AI_Provider_Registry {
	/**
	 * Providers available for selection in the IA Generation UI / REST config.
	 *
	 * Anthropic is implemented (`make_provider( 'anthropic' )` + class file) but
	 * intentionally omitted here until a real API key can be smoke-tested.
	 * Anthropic billing currently requires a minimum deposit and the payment
	 * processor is failing for the maintainer — re-add the label entry when ready.
	 *
	 * @return array<int,array{slug:string,label:string}>
	 */
	public static function list_providers(): array {
		return \apply_filters(
			'rms_wizard_ai_providers',
			[
				[
					'slug'  => 'ollama-cloud',
					'label' => \__( 'Ollama Cloud', 'simple-rms-theme' ),
				],
				[
					'slug'  => 'openai',
					'label' => \__( 'OpenAI', 'simple-rms-theme' ),
				],
				[
					'slug'  => 'google',
					'label' => \__( 'Google Gemini', 'simple-rms-theme' ),
				],
				[
					'slug'  => 'openrouter',
					'label' => \__( 'OpenRouter', 'simple-rms-theme' ),
				],
			]
		);
	}

	public static function default_provider(): string {
		$providers = self::list_providers();

		return (string) ( $providers[0]['slug'] ?? 'ollama-cloud' );
	}

	public static function provider_exists( string $provider ): bool {
		foreach ( self::list_providers() as $registered ) {
			if ( ( $registered['slug'] ?? '' ) === $provider ) {
				return true;
			}
		}

		return false;
	}

	public static function get_provider_label( string $provider ): string {
		foreach ( self::list_providers() as $p ) {
			if ( $p['slug'] === $provider ) {
				return $p['label'];
			}
		}

		return $provider;
	}

	public static function make_provider( string $provider, string $api_key = '' ): AI_Provider {
		$provider = \sanitize_key( $provider );

		if ( '' === $api_key ) {
			$api_key = AI_Credential_Store::get( $provider );
		}

		if ( 'ollama-cloud' === $provider ) {
			return new Ollama_Provider( $api_key );
		}

		if ( 'openai' === $provider ) {
			return new OpenAI_Provider( $api_key );
		}

		if ( 'anthropic' === $provider ) {
			return new Anthropic_Provider( $api_key );
		}

		if ( 'google' === $provider ) {
			return new Google_Provider( $api_key );
		}

		if ( 'openrouter' === $provider ) {
			return new OpenRouter_Provider( $api_key );
		}

		return new AI_Provider( $api_key );
	}
}
