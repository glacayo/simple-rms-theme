<?php
/**
 * Focused harness for issue #102: SEO content header alignment.
 *
 * Proves, causally and without a framework:
 *   1. Schema: one added select sub-field seo_header_alignment
 *      (field_rms_seo_header_alignment, "Header Alignment", choices
 *      left|center|right, default left); acf-json byte-synced with the PHP
 *      registration; seo_modifier keeps exactly default|reverse.
 *   2. Template: strict whitelist with left fallback for missing/empty/
 *      invalid/non-string/whitespace; center/right emit exactly their own
 *      .seo-content--align-* modifier across all six default/reverse x
 *      alignment combinations; two instances stay isolated with no class
 *      bleed into text/media/image; headline/aria markup unchanged.
 *   3. SCSS: center centers only the header block; right overlays the right
 *      grid column at >=640px (half of $space-10), re-maths at >=1024px
 *      ($space-16), keeps text-align:left, full width below 640px; gap tokens
 *      match .seo-content__grid so the math tracks the real grid gap.
 *   4. AI: blocked (never fillable/editorial), stripped from generation/
 *      validation/builder/assembler; validate_fields stripping is the rerun
 *      overwrite guard (keyword layout: no canonical store here).
 *
 * Reuses tests/support/header-cta-support.php for the shared WP/ACF/template
 * stubs and rms_cta_assert helpers. get_sub_field, wp_kses_post, wizard, and
 * DOM helpers are declared inline (pattern from tests/area-coverage-*.php).
 * Usage: php tests/seo-content-header-alignment-harness.php
 */

if ( PHP_SAPI !== 'cli' ) { fwrite( STDERR, "CLI only.\n" ); exit( 1 ); }

$theme_root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', $theme_root . '/' ); }

// Shared stubs + rms_cta_* helpers (rms_cta_render includes templates/*.php).
require __DIR__ . '/support/header-cta-support.php';

// ─── Inline stubs not covered by the shared support (wizard stubs follow tests/area-coverage-color-schema-harness.php) ───

if ( ! function_exists( 'get_sub_field' ) ) {
	function get_sub_field( $selector, $format_value = true ) {
		return $GLOBALS['rms_seo_sub'][ $selector ] ?? null;
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ) {
		return (string) $data;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0 ) {
		return json_encode( $data, $options );
	}
}
if ( ! class_exists( 'WP_Error', false ) ) {
	class WP_Error {}
}

// ─── Fixtures + render/parse/token helpers ───────────────────────────────────

/** Render templates/seo-content.php against one repeater sub-row. */
function rms_seo_render( array $overrides = array() ): string {
	$GLOBALS['rms_seo_sub'] = array_merge( array(
		'seo_headline'    => 'Roofing Built to Last',
		'seo_subheadline' => 'A reusable section for ACF integration',
		'seo_text'        => '<p>Dependable workmanship and clear communication.</p>',
		'seo_image'       => 'https://example.test/roof.jpg',
		'seo_modifier'    => 'default',
		'seo_bg_style'    => 'light',
		'seo_bg_image'    => '',
	), $overrides );
	return rms_cta_render( 'seo-content.php' );
}

/** DOMXPath over rendered markup (HTML errors silenced). */
function rms_seo_dom_parse( string $html ): DOMXPath {
	$doc = new DOMDocument(); libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
	libxml_clear_errors();
	return new DOMXPath( $doc );
}

/** XPath predicate matching an element by a single space-separated class token. */
function rms_seo_cls( string $cls ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' $cls ')";
}

/** Attribute of a DOM node ("" for null or non-element nodes). */
function rms_seo_attr( $node, string $name ): string {
	return $node instanceof DOMElement ? (string) $node->getAttribute( $name ) : '';
}

/** Section class attribute ("" unless the render yields exactly one section). */
function rms_seo_section_class( string $html ): string {
	$sections = rms_seo_dom_parse( $html )->query( '//*[' . rms_seo_cls( 'seo-content' ) . ']' );
	return 1 === $sections->length ? rms_seo_attr( $sections->item( 0 ), 'class' ) : '';
}

/** Require every token in $must inside $haystack and none in $must_not. */
function rms_seo_expect_tokens( string $haystack, array $must, array $must_not, string $id, string $detail ): void {
	$ok = true;
	foreach ( $must as $token ) { $ok = $ok && false !== strpos( $haystack, $token ); }
	foreach ( $must_not as $token ) { $ok = $ok && false === strpos( $haystack, $token ); }
	rms_cta_assert( $ok, $id, $detail );
}

/**
 * Render one sub-row and require its single section class to carry exactly the
 * expected tokens; collapses whitelist, combination, and isolation checks.
 */
function rms_seo_expect_section( array $overrides, array $must, array $must_not, string $id, string $detail ): void {
	rms_seo_expect_tokens( rms_seo_section_class( rms_seo_render( $overrides ) ),
		array_merge( array( 'seo-content' ), $must ), $must_not, $id, $detail );
}

/** Named sub-field of layout_seo_content; null unless exactly one matches. */
function rms_seo_layout_field( array $group, string $name ): ?array {
	$subs = (array) ( $group['fields'][0]['layouts']['layout_seo_content']['sub_fields'] ?? array() );
	$hits = array_values( array_filter( $subs, function ( $field ) use ( $name ) {
		return is_array( $field ) && ( $field['name'] ?? '' ) === $name;
	} ) );
	return 1 === count( $hits ) ? $hits[0] : null;
}

/** Brace-balanced "{...}" block for the first $selector occurrence followed by whitespace + "{" (comments naming the selector are ignored). */
function rms_seo_scss_block( string $scss, string $selector ): string {
	return preg_match( '/' . preg_quote( $selector, '/' ) . '[ \t\r\n]*(?<block>\{(?:[^{}]|(?&block))*\})/', $scss, $m )
		? $m[0] : '';
}

/** Segment of $text after $start up to $end ("" when either is absent). */
function rms_seo_between( string $text, string $start, ?string $end ): string {
	$from = strpos( $text, $start ); if ( false === $from ) { return ''; }
	$from += strlen( $start );
	$to   = null === $end ? strlen( $text ) : strpos( $text, $end, $from );
	return false === $to ? substr( $text, $from ) : substr( $text, $from, $to - $from );
}

// ─── 1. Schema: PHP registration, JSON parity, seo_modifier unchanged ────────

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
	function acf_add_local_field_group( array $field_group ): void {
		$GLOBALS['rms_captured_group'] = $field_group;
	}
}

$GLOBALS['rms_captured_group'] = null;
require_once $theme_root . '/inc/acf-flexible-content.php';
rms_register_acf_page_sections();
$php_group = is_array( $GLOBALS['rms_captured_group'] ?? null ) ? $GLOBALS['rms_captured_group'] : array();
rms_cta_assert( ( $php_group['key'] ?? '' ) === 'group_rms_page_sections', 'test_php_registration_captured', 'the page sections field group must be captured from the PHP registration' );
$php_alignment = rms_seo_layout_field( $php_group, 'seo_header_alignment' );
rms_cta_assert( is_array( $php_alignment ), 'test_php_alignment_field_present',
	'layout_seo_content must expose exactly one sub-field named seo_header_alignment' );

// Field contract as a table: key/label/type/default plus exact left|center|right choices.
$contract_ok = is_array( $php_alignment ) && array_keys( (array) ( $php_alignment['choices'] ?? array() ) ) === array( 'left', 'center', 'right' );
foreach ( array( 'key' => 'field_rms_seo_header_alignment', 'label' => 'Header Alignment', 'type' => 'select', 'default_value' => 'left' ) as $key => $expected ) {
	$contract_ok = $contract_ok && $expected === ( $php_alignment[ $key ] ?? null );
}
rms_cta_assert( $contract_ok, 'test_php_alignment_field_contract',
	'the field must be a select with key field_rms_seo_header_alignment, label "Header Alignment", choices left|center|right, default left' );

$php_modifier = rms_seo_layout_field( $php_group, 'seo_modifier' );
rms_cta_assert( is_array( $php_modifier ) && array_keys( (array) ( $php_modifier['choices'] ?? array() ) ) === array( 'default', 'reverse' ),
	'test_php_modifier_stays_column_order_only', 'seo_modifier must keep exactly the default|reverse choices (column order only)' );

$json_raw   = (string) file_get_contents( $theme_root . '/acf-json/group_rms_page_sections.json' );
$json_group = json_decode( $json_raw, true );
rms_cta_assert( is_array( $json_group ) && ( $json_group['key'] ?? '' ) === 'group_rms_page_sections', 'test_json_group_parses',
	'acf-json/group_rms_page_sections.json must decode to the page sections field group' );
rms_cta_assert( is_array( $json_group ) && is_array( $json_alignment = rms_seo_layout_field( $json_group, 'seo_header_alignment' ) ) && $json_alignment === $php_alignment,
	'test_json_alignment_matches_php', 'the JSON seo_header_alignment field must match the PHP registration exactly (PHP/JSON parity)' );

// Byte-level sync: re-encode the captured group exactly as scripts/generate-acf-json.php does, normalizing line endings so a CRLF checkout does not false-negative.
$php_group['modified'] = is_array( $json_group ) ? ( $json_group['modified'] ?? 0 ) : 0;
rms_cta_assert( json_encode( $php_group, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" === str_replace( "\r\n", "\n", $json_raw ),
	'test_json_sync_exact', 're-running scripts/generate-acf-json.php must reproduce the committed JSON byte-for-byte (line-ending agnostic)' );

// ─── 2. Template: whitelist, fallbacks, six combinations, isolation ──────────

rms_cta_assert( '' !== rms_seo_section_class( rms_seo_render( array( 'seo_header_alignment' => 'left' ) ) ),
	'test_template_renders_section', 'the seo-content template must render one section under the harness stubs' );

// Strict whitelist: only exact left|center|right pass through; every other input falls back to left.
foreach ( array(
	'left_explicit' => array( 'seo_header_alignment' => 'left' ),
	'missing'       => array(),
	'empty'         => array( 'seo_header_alignment' => '' ),
	'invalid'       => array( 'seo_header_alignment' => 'bogus' ),
	'non_string'    => array( 'seo_header_alignment' => array( 'center' ) ),
	'whitespace'    => array( 'seo_header_alignment' => ' center ' ),
) as $label => $overrides ) {
	rms_seo_expect_section( $overrides, array( 'seo-content--bg-light' ), array( 'seo-content--align-' ),
		'test_alignment_falls_back_left_' . $label, "$label must fall back to left: no seo-content--align-* modifier, base classes intact" );
}

rms_seo_expect_section( array( 'seo_header_alignment' => 'center' ), array( 'seo-content--align-center' ), array( 'seo-content--align-right' ),
	'test_center_emits_align_center', 'alignment center must emit .seo-content--align-center and no right modifier' );
rms_seo_expect_section( array( 'seo_header_alignment' => 'right' ), array( 'seo-content--align-right' ), array( 'seo-content--align-center' ),
	'test_right_emits_align_right', 'alignment right must emit .seo-content--align-right and no center modifier' );

// Alignment stays independent of seo_modifier (column order) in all six combinations.
$align_expect = array(
	'left'   => array( array(), array( 'seo-content--align-center', 'seo-content--align-right' ) ),
	'center' => array( array( 'seo-content--align-center' ), array( 'seo-content--align-right' ) ),
	'right'  => array( array( 'seo-content--align-right' ), array( 'seo-content--align-center' ) ),
);
foreach ( array( 'default', 'reverse' ) as $modifier ) {
	foreach ( $align_expect as $alignment => list( $must, $must_not ) ) {
		$overrides = array( 'seo_modifier' => $modifier, 'seo_header_alignment' => $alignment );
		rms_seo_expect_section( $overrides, 'reverse' === $modifier ? array( 'seo-content--reverse' ) : array(),
			'reverse' !== $modifier ? array( 'seo-content--reverse' ) : array(),
			"test_combo_{$modifier}_{$alignment}_column_order", "modifier $modifier must map exactly to .seo-content--reverse (column order only)" );
		rms_seo_expect_section( $overrides, $must, $must_not, "test_combo_{$modifier}_{$alignment}_alignment",
			"alignment $alignment with modifier $modifier must emit exactly its own alignment modifier" );
	}
}

// Two consecutive instances keep their own alignment modifiers.
$both = rms_seo_dom_parse( rms_seo_render( array( 'seo_header_alignment' => 'center' ) ) . rms_seo_render( array( 'seo_header_alignment' => 'right', 'seo_modifier' => 'reverse' ) ) )
	->query( '//*[' . rms_seo_cls( 'seo-content' ) . ']' );
rms_cta_assert( 2 === $both->length, 'test_two_instances_render',
	'two consecutive section instances must render two .seo-content sections' );
foreach ( array(
	array( 'a', 0, 'seo-content--align-center', 'seo-content--align-right', 'A (center)' ),
	array( 'b', 1, 'seo-content--align-right', 'seo-content--align-center', 'B (reverse+right)' ),
) as list( $key, $index, $must, $must_not, $name ) ) {
	rms_cta_assert( false !== strpos( rms_seo_attr( $both->item( $index ), 'class' ), $must ) && false === strpos( rms_seo_attr( $both->item( $index ), 'class' ), $must_not ),
		"test_instance_{$key}_keeps_own_alignment", "instance $name must carry only its own alignment modifier" );
}

// Alignment modifiers appear only on the section; inner blocks and headline/aria markup stay untouched.
foreach ( array( 'center', 'right' ) as $label ) {
	$xpath = rms_seo_dom_parse( rms_seo_render( array( 'seo_header_alignment' => $label ) ) );
	rms_cta_assert( 1 === $xpath->query( '//*[contains(@class, "seo-content--align-' . $label . '")]' )->length,
		'test_' . $label . '_modifier_only_on_section', 'the alignment modifier must appear on the section element only (no bleed into header/text/media)' );
	foreach ( array( 'seo-content__text', 'seo-content__media', 'seo-content__image', 'seo-content__header' ) as $block ) {
		rms_cta_assert( 1 === ( $nodes = $xpath->query( '//*[' . rms_seo_cls( $block ) . ']' ) )->length && false === strpos( rms_seo_attr( $nodes->item( 0 ), 'class' ), 'align' ),
			'test_' . $label . '_' . str_replace( '-', '_', $block ) . '_untouched',
			"$block must render exactly once with no alignment class under alignment $label" );
	}
	rms_cta_assert( 1 === $xpath->query( '//h2[' . rms_seo_cls( 'seo-content__headline' ) . ']' )->length && 'seo-content-heading' === rms_seo_attr( $xpath->query( '//h2[' . rms_seo_cls( 'seo-content__headline' ) . ']' )->item( 0 ), 'id' ),
		'test_' . $label . '_headline_markup_unchanged', 'the headline must remain h2.seo-content__headline#seo-content-heading' );
	rms_cta_assert( 'seo-content-heading' === rms_seo_attr( $xpath->query( '//*[' . rms_seo_cls( 'seo-content' ) . ']' )->item( 0 ), 'aria-labelledby' ),
		'test_' . $label . '_aria_labelledby_unchanged', 'the section aria-labelledby target must remain unchanged' );
}

// ─── 3. SCSS contract: center scoping + right column math tracks the gap ─────

$scss         = (string) file_get_contents( $theme_root . '/src/scss/templates/seo-content.scss' );
$align_center = rms_seo_scss_block( $scss, '.seo-content--align-center' );
$align_right  = rms_seo_scss_block( $scss, '.seo-content--align-right' );

rms_seo_expect_tokens( $align_center, array( 'margin-inline: auto', 'text-align: center' ), array(),
	'test_scss_center_centers_header', '.seo-content--align-center must center the header block (margin-inline auto) and center its text' );
rms_seo_expect_tokens( $align_center, array(), array( '__text', '__media' ),
	'test_scss_center_does_not_touch_text_or_media', 'the center block must not reference .seo-content__text or .seo-content__media' );
rms_seo_expect_tokens( $align_right, array( '.seo-content--align-right' ), array(),
	'test_scss_right_block_exists', '.seo-content--align-right must exist in the template SCSS' );

// >=640px spans the column with half of the $space-10 gap; >=1024px re-maths with $space-16.
rms_seo_expect_tokens( rms_seo_between( $align_right, '@include breakpoint($breakpoint-sm)', '@include breakpoint($breakpoint-lg)' ),
	array( 'calc(50% + #{$space-10} / 2)', 'calc(50% - #{$space-10} / 2)' ), array(),
	'test_scss_right_sm_uses_half_gap', 'at >=640px the right header must start at 50% + half the $space-10 grid gap and span the column width' );
rms_seo_expect_tokens( rms_seo_between( $align_right, '@include breakpoint($breakpoint-lg)', null ), array( 'calc(50% + #{$space-16} / 2)', 'calc(50% - #{$space-16} / 2)' ), array(),
	'test_scss_right_lg_uses_desktop_gap', 'at >=1024px the right header must re-math with the $space-16 desktop gap' );

rms_cta_assert( false !== ( $bp = strpos( $align_right, '@include breakpoint(' ) ) && false === strpos( substr( $align_right, 0, $bp ), 'calc(50%' ),
	'test_scss_right_mobile_full_width', 'below 640px the right header must stay full width: no margin/width calc before the first breakpoint' );
rms_seo_expect_tokens( $align_right, array( 'text-align: left' ), array(),
	'test_scss_right_keeps_text_left', 'the right alignment must keep headline/subheadline text-align:left' );
rms_seo_expect_tokens( rms_seo_scss_block( $scss, '.seo-content__grid' ), array( 'gap: $space-10', 'gap: $space-16' ), array(),
	'test_scss_grid_gap_tokens_match_math', '.seo-content__grid must keep the $space-10 / $space-16 gap tokens the right-alignment math uses' );

// ─── 4. AI contract: blocked, never fillable, stripped, never invented ───────

require_once $theme_root . '/inc/wizard/class-ai-content-harness.php';
require_once $theme_root . '/inc/wizard/class-flexible-content-layouts.php';

$harness = new \Inc\Wizard\AI_Content_Harness();
rms_cta_assert( in_array( 'seo_header_alignment', $harness->get_blocked_fields( 'seo-content' ), true ), 'test_ai_blocked_includes_alignment',
	'seo_header_alignment must be listed in the AI blocked fields for seo-content' );
rms_cta_assert( ! in_array( 'seo_header_alignment', $harness->get_fillable_fields( 'seo-content' ), true ), 'test_ai_fillable_excludes_alignment',
	'seo_header_alignment must not be an AI fillable field' );
rms_cta_assert( ! in_array( 'seo_header_alignment', array_keys( (array) ( \Inc\Wizard\AI_Content_Harness::get_editorial_rules( 'seo-content' )['fields'] ?? array() ) ), true ),
	'test_ai_editorial_excludes_alignment', 'seo_header_alignment must not appear in the AI editorial rules' );
rms_cta_assert( false !== strpos( $harness->get_layer3( 'seo-content', 1, array( 'company_name' => 'Acme Builders' ) ), 'seo_header_alignment' ),
	'test_ai_layer3_lists_alignment_blocked', 'the seo-content layer-3 prompt must list seo_header_alignment among the blocked JSON keys' );

// Rerun overwrite guard: validate_fields strips a smuggled alignment key, so AI reruns can never overwrite the saved value.
$validated = $harness->validate_fields( 'seo-content', array( 'seo_headline' => 'Kept', 'seo_header_alignment' => 'right' ) );
rms_cta_assert( is_array( $validated ) && ! array_key_exists( 'seo_header_alignment', $validated ) && 'Kept' === ( $validated['seo_headline'] ?? '' ),
	'test_ai_validate_strips_alignment', 'validate_fields must strip seo_header_alignment from an AI payload while keeping fillable copy' );

rms_cta_assert( ! array_key_exists( 'seo_header_alignment', ( new \Inc\Wizard\Flexible_Content_Layouts() )->build_generic_section( 'seo-content', array( 'company_name' => 'Acme Builders' ), array() ) ),
	'test_generic_builder_omits_alignment', 'the generic builder must not emit seo_header_alignment' );

require_once $theme_root . '/inc/wizard/class-section-assembler.php';
rms_cta_assert( ! array_key_exists( 'seo_header_alignment', ( new \Inc\Wizard\Section_Assembler( new \Inc\Wizard\AI_Content_Harness() ) )->section_data( 'seo-content', array( 'company_name' => 'Acme Builders' ), array(), 4 ) ),
	'test_assembler_omits_alignment', 'the section assembler must not write seo_header_alignment' );

// ─── Summary ─────────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
	fwrite( STDERR, "SEO content header alignment harness failed: {$failures} check(s).\n" );
	exit( 1 );
}

echo "SEO content header alignment harness passed: schema parity, whitelist fallbacks, six combinations, instance isolation, gap-tracking right-column math, and AI blocked-field contract.\n";
exit( 0 );