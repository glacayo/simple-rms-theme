<?php
/**
 * Focused production-path harness for issue #26 (homepage SEO targeting).
 *
 * No framework. Proves disabled neutrality, enabled Hero/SEO scope, validation,
 * normalization, stale-intent clearing, reviewer isolation, and landing invariance.
 *
 * Usage: php tests/wizard-home-seo-targeting-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! class_exists( 'WP_Error', false ) ) {
	class WP_Error {
		public $code;
		public $message;
		public $data;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		$str = preg_replace( '/[\r\n\t]+/', ' ', (string) $str );
		$str = preg_replace( '/\s+/', ' ', is_string( $str ) ? $str : '' );

		return trim( strip_tags( is_string( $str ) ? $str : '' ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0 ) {
		return json_encode( $data, $options );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );

		return $text;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		unset( $hook );

		return $value;
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $value ) {
		return (string) $value;
	}
}

require_once dirname( __DIR__ ) . '/inc/wizard/class-ai-content-harness.php';
require_once dirname( __DIR__ ) . '/inc/wizard/class-ai-content-reviewer.php';

use Inc\Wizard\AI_Content_Harness;
use Inc\Wizard\AI_Content_Reviewer;

/**
 * @param mixed $condition
 */
function rms_home_seo_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$harness  = new AI_Content_Harness();
$reviewer = new AI_Content_Reviewer( $harness );
$passed   = 0;
$client   = [ 'company_name' => 'Acme Builders' ];

$disabled_intent = $harness->normalize_home_seo_intent(
	[
		'seo_targeting' => [
			'enabled'            => false,
			'primary_keyword'    => 'stale deck builder',
			'secondary_keywords' => [ 'old secondary' ],
		],
	]
);
rms_home_seo_assert( ! is_wp_error( $disabled_intent ), 'disabled intent should not error' );
rms_home_seo_assert( [ 'enabled' => false ] === $disabled_intent, 'disabled intent must drop stale keywords' );
$disabled_persist = $harness->persist_home_seo_intent( $disabled_intent );
rms_home_seo_assert( [ 'enabled' => false ] === $disabled_persist, 'disabled persist must contain only enabled=false' );
rms_home_seo_assert( ! array_key_exists( 'primary_keyword', $disabled_persist ), 'disabled persist leaked primary_keyword' );
rms_home_seo_assert( ! array_key_exists( 'secondary_keywords', $disabled_persist ), 'disabled persist leaked secondary_keywords' );

$disabled_hero = $harness->get_layer3( 'hero', 1, $client );
$disabled_seo  = $harness->get_layer3( 'seo-content', 1, $client );
foreach ( [ $disabled_hero, $disabled_seo ] as $prompt ) {
	rms_home_seo_assert( false === strpos( $prompt, 'KEYWORD CONTEXT' ), 'disabled prompt leaked landing keyword context' );
	rms_home_seo_assert( false === strpos( $prompt, 'KEYWORD INTENT' ), 'disabled prompt leaked homepage keyword intent' );
	rms_home_seo_assert( false === strpos( $prompt, 'stale deck builder' ), 'disabled prompt leaked stale keyword' );
}
echo "PASS disabled-neutral-no-keyword-context\n";
$passed++;

$enabled = $harness->normalize_home_seo_intent(
	[
		'seo_targeting' => [
			'enabled'            => true,
			'primary_keyword'    => '  deck   builder  ',
			'secondary_keywords' => [ 'custom decks', 'DECK BUILDER', 'composite decking', '', 'Custom Decks' ],
		],
	]
);
rms_home_seo_assert( ! is_wp_error( $enabled ), 'enabled intent should normalize' );
rms_home_seo_assert( 'deck builder' === $enabled['primary_keyword'], 'primary whitespace was not collapsed' );
rms_home_seo_assert( [ 'custom decks', 'composite decking' ] === $enabled['secondary_keywords'], 'secondary dedupe/clamp/order failed' );

$hero_keywords = $harness->home_seo_keywords_for_layout( $enabled, 'hero' );
$seo_keywords  = $harness->home_seo_keywords_for_layout( $enabled, 'seo-content' );
$about_kw      = $harness->home_seo_keywords_for_layout( $enabled, 'about-us' );
$faq_kw        = $harness->home_seo_keywords_for_layout( $enabled, 'faq-v1' );
$trust_kw      = $harness->home_seo_keywords_for_layout( $enabled, 'testimonials-v1' );
$portfolio_kw  = $harness->home_seo_keywords_for_layout( $enabled, 'portfolio-v1' );
rms_home_seo_assert( 'deck builder' === ( $hero_keywords['primary_keyword'] ?? '' ), 'hero did not receive primary' );
rms_home_seo_assert( 'deck builder' === ( $seo_keywords['primary_keyword'] ?? '' ), 'seo-content did not receive primary' );
rms_home_seo_assert( [] === $about_kw && [] === $faq_kw && [] === $trust_kw && [] === $portfolio_kw, 'non-target layouts received keyword directives' );

$hero_prompt  = $harness->get_layer3( 'hero', 1, $client, AI_Content_Harness::PAGE_HOME, $hero_keywords );
$seo_prompt   = $harness->get_layer3( 'seo-content', 1, $client, AI_Content_Harness::PAGE_HOME, $seo_keywords );
$about_prompt = $harness->get_layer3( 'about-us', 1, $client, AI_Content_Harness::PAGE_HOME, $hero_keywords );
foreach ( [ $hero_prompt, $seo_prompt ] as $prompt ) {
	rms_home_seo_assert( false !== strpos( $prompt, 'KEYWORD INTENT (editorial only, this section only)' ), 'targeted prompt missing editorial keyword block' );
	rms_home_seo_assert( false !== strpos( $prompt, 'deck builder' ), 'targeted prompt missing primary keyword' );
	rms_home_seo_assert( false !== strpos( $prompt, 'never evidence' ), 'targeted prompt missing non-evidence rule' );
	rms_home_seo_assert( false !== strpos( $prompt, 'credentials, guarantees, statistics' ), 'targeted prompt missing invention ban' );
	rms_home_seo_assert( false === strpos( $prompt, 'KEYWORD CONTEXT (mandatory for this section only)' ), 'homepage reused landing keyword contract' );
}
rms_home_seo_assert( false === strpos( $about_prompt, 'KEYWORD INTENT' ), 'about-us received keyword intent' );
rms_home_seo_assert( false === strpos( $about_prompt, 'deck builder' ), 'about-us prompt contained the keyword' );
echo "PASS enabled-scopes-only-hero-seo\n";
$passed++;

$missing = $harness->normalize_home_seo_intent(
	[
		'seo_targeting' => [
			'enabled'            => true,
			'primary_keyword'    => '   ',
			'secondary_keywords' => [ 'custom decks' ],
		],
	]
);
rms_home_seo_assert( is_wp_error( $missing ), 'empty primary must reject' );
rms_home_seo_assert( 'rms_wizard_home_seo_primary_required' === $missing->get_error_code(), 'unexpected missing-primary error code' );
echo "PASS missing-primary-rejects\n";
$passed++;

$normalized = $harness->normalize_home_seo_keywords(
	[
		'primary_keyword'    => "\tdeck\tbuilder\n",
		'secondary_keywords' => [
			'  one  ',
			'Two',
			'two',
			'',
			'THREE',
			'three',
			'four',
			'five',
			'six',
			'seven',
			'eight',
			'nine',
			'ten',
			'eleven',
			'twelve',
		],
	]
);
rms_home_seo_assert( 'deck builder' === $normalized['primary_keyword'], 'primary collapse failed' );
rms_home_seo_assert(
	[ 'one', 'Two', 'THREE', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten' ] === $normalized['subkeywords'],
	'secondary normalize/dedupe/clamp/order failed: ' . implode( '|', $normalized['subkeywords'] )
);
echo "PASS normalize-dedupe-clamp\n";
$passed++;

$rerun_disabled = $harness->normalize_home_seo_intent(
	[
		'seo_targeting' => [
			'enabled'         => 0,
			'primary_keyword' => 'changed later',
		],
	]
);
$rerun_persist = $harness->persist_home_seo_intent( $rerun_disabled );
$rerun_keywords = $harness->home_seo_keywords_for_layout(
	array_merge( [ 'primary_keyword' => 'stale deck builder' ], $rerun_persist ),
	'hero'
);
$rerun_prompt = $harness->get_layer3( 'hero', 1, $client, AI_Content_Harness::PAGE_HOME, $rerun_keywords );
rms_home_seo_assert( [] === $rerun_keywords, 'disabled rerun still produced keyword payload' );
rms_home_seo_assert( false === strpos( $rerun_prompt, 'stale deck builder' ), 'disabled rerun kept stale keyword in prompt' );
rms_home_seo_assert( false === strpos( $rerun_prompt, 'changed later' ), 'disabled rerun leaked submitted keyword' );
echo "PASS rerun-disabled-clears-stale-intent\n";
$passed++;

$changed = $harness->normalize_home_seo_intent(
	[
		'seo_targeting' => [
			'enabled'         => true,
			'primary_keyword' => 'composite decking contractor',
		],
	]
);
$changed_keywords = $harness->home_seo_keywords_for_layout( $changed, 'hero' );
$changed_prompt   = $harness->get_layer3( 'hero', 1, $client, AI_Content_Harness::PAGE_HOME, $changed_keywords );
rms_home_seo_assert( false !== strpos( $changed_prompt, 'composite decking contractor' ), 'changed keyword was not used' );
rms_home_seo_assert( false === strpos( $changed_prompt, 'deck builder' ), 'old keyword remained after change' );
echo "PASS changed-keyword-used\n";
$passed++;

$priors = [
	[ 'layout' => 'hero', 'payload' => [ 'hero_title' => 'deck builder' ] ],
	[ 'layout' => 'seo-content', 'payload' => [ 'seo_headline' => 'deck builder' ] ],
	[ 'layout' => 'about-us', 'payload' => [ 'about_headline' => 'Our story' ] ],
	[ 'layout' => 'faq-v1', 'payload' => [ 'faq_v1_headline' => 'Questions' ] ],
];
$neutral = $harness->filter_neutral_priors( $priors );
rms_home_seo_assert( 2 === count( $neutral ), 'neutral prior filter count is wrong' );
rms_home_seo_assert( 'about-us' === $neutral[0]['layout'] && 'faq-v1' === $neutral[1]['layout'], 'neutral priors kept keyword layouts' );
rms_home_seo_assert( ! $harness->is_reusable_layout( 'hero' ), 'hero must stay non-canonical' );
rms_home_seo_assert( ! $harness->is_reusable_layout( 'seo-content' ), 'seo-content must stay non-canonical' );
rms_home_seo_assert( $harness->is_reusable_layout( 'about-us' ), 'about-us should remain reusable' );
echo "PASS canonical-prior-context-clean\n";
$passed++;

$json_prompt = new ReflectionMethod( AI_Content_Reviewer::class, 'json_prompt' );
$json_prompt->setAccessible( true );
$scoped = $json_prompt->invoke(
	$reviewer,
	'Review this section and diagnose failures before any rewrite.',
	'hero',
	[ 'hero_title' => 'Reliable decks' ],
	[ [ 'layout' => 'about-us', 'payload' => [ 'about_headline' => 'Our story' ] ] ],
	[
		'client_context' => $client,
		'keyword_intent' => $hero_keywords,
	],
	[]
);
$unscoped = $json_prompt->invoke(
	$reviewer,
	'Review this section and diagnose failures before any rewrite.',
	'about-us',
	[ 'about_headline' => 'Our story' ],
	$neutral,
	[ 'client_context' => $client ],
	[]
);
$scoped_data   = json_decode( $scoped, true );
$unscoped_data = json_decode( $unscoped, true );
rms_home_seo_assert( is_array( $scoped_data ) && isset( $scoped_data['declared_keyword_intent'] ), 'reviewer missing scoped keyword intent' );
rms_home_seo_assert( 'deck builder' === $scoped_data['declared_keyword_intent']['primary_keyword'], 'reviewer scoped primary mismatch' );
rms_home_seo_assert( false !== strpos( (string) $scoped_data['declared_keyword_intent']['role'], 'Not evidence' ), 'reviewer intent role missing non-evidence language' );
rms_home_seo_assert( ! isset( $unscoped_data['declared_keyword_intent'] ), 'reviewer leaked keyword intent into a neutral section' );
echo "PASS reviewer-context-scoped\n";
$passed++;

$landing_keywords = [
	'primary_keyword' => 'kitchen remodel near me',
	'subkeywords'     => [ 'Kitchen Remodel Near Me', 'cabinets', 'Kitchen Remodel Near Me' ],
];
$landing_normalized = $harness->normalize_keywords( $landing_keywords );
rms_home_seo_assert( 'kitchen remodel near me' === $landing_normalized['primary_keyword'], 'landing primary changed' );
rms_home_seo_assert(
	[ 'Kitchen Remodel Near Me', 'cabinets', 'Kitchen Remodel Near Me' ] === $landing_normalized['subkeywords'],
	'landing normalize_keywords behavior changed'
);
$landing_hero = $harness->get_layer3( 'hero', 1, $client, AI_Content_Harness::PAGE_LANDING, $landing_keywords );
rms_home_seo_assert( false !== strpos( $landing_hero, 'KEYWORD CONTEXT (mandatory for this section only)' ), 'landing keyword contract changed' );
rms_home_seo_assert( false !== strpos( $landing_hero, '- Primary keyword: kitchen remodel near me' ), 'landing prompt does not resolve the primary keyword value' );
rms_home_seo_assert( false !== strpos( $landing_hero, '- Subkeywords: Kitchen Remodel Near Me, cabinets, Kitchen Remodel Near Me' ), 'landing prompt does not resolve the subkeyword values' );
rms_home_seo_assert( false === strpos( $landing_hero, '{{primary_keyword}}' ) && false === strpos( $landing_hero, '{{subkeywords}}' ), 'landing prompt leaked literal keyword tokens' );
rms_home_seo_assert( false === strpos( $landing_hero, 'KEYWORD INTENT (editorial only' ), 'landing prompt picked up homepage contract' );
rms_home_seo_assert( false === strpos( $landing_hero, 'never evidence' ), 'landing prompt picked up homepage evidence language' );

$home_source     = file_get_contents( dirname( __DIR__ ) . '/inc/wizard/class-step-home-page-builder.php' );
$landing_source  = file_get_contents( dirname( __DIR__ ) . '/inc/wizard/class-step-landing-page-builder.php' );
$reviewer_source = file_get_contents( dirname( __DIR__ ) . '/inc/wizard/class-ai-content-reviewer.php' );
rms_home_seo_assert( is_string( $home_source ) && false !== strpos( $home_source, 'normalize_home_seo_intent' ), 'home builder is not wired to homepage SEO intent' );
rms_home_seo_assert( is_string( $home_source ) && false !== strpos( $home_source, 'home_seo_keywords_for_layout' ), 'home builder does not scope keywords by layout' );
rms_home_seo_assert( is_string( $home_source ) && false !== strpos( $home_source, 'filter_neutral_priors' ), 'home builder does not isolate neutral priors' );
rms_home_seo_assert( is_string( $landing_source ) && false !== strpos( $landing_source, 'normalize_keywords' ), 'landing builder lost normalize_keywords' );
rms_home_seo_assert( is_string( $landing_source ) && false === strpos( $landing_source, 'normalize_home_seo_intent' ), 'landing builder now depends on homepage SEO intent' );
rms_home_seo_assert( is_string( $reviewer_source ) && false !== strpos( $reviewer_source, 'declared_keyword_intent' ), 'reviewer missing declared keyword intent helper' );
echo "PASS landing-behavior-unchanged\n";
$passed++;

echo 'Harness passed: ' . $passed . " scenarios.\n";
exit( 0 );
