<?php
/**
 * Focused harness for issue #103 (Services V2 section CTA spacing).
 * Proves `.services-v2__cta` is centered with `$space-12` top margin,
 * matching Services V1, without changing template markup or other CTAs.
 * Usage: php tests/services-v2-cta-harness.php
 */

if ( PHP_SAPI !== 'cli' ) { fwrite( STDERR, "CLI only.\n" ); exit( 1 ); }
$theme_root = dirname( __DIR__ );
$failures   = 0;

function rms_s2_assert( bool $condition, string $name, string $message ): void {
	global $failures;
	if ( $condition ) { fwrite( STDOUT, "PASS {$name}\n" ); }
	else { $failures++; fwrite( STDERR, "FAIL {$name}: {$message}\n" ); }
}

$v2_scss = (string) file_get_contents( $theme_root . '/src/scss/templates/services-v2.scss' );
$v1_scss = (string) file_get_contents( $theme_root . '/src/scss/templates/services-v1.scss' );
$v2_php  = (string) file_get_contents( $theme_root . '/templates/services-v2.php' );

rms_s2_assert(
	(bool) preg_match( '/<div class="services-v2__cta">\s*<a href="[^"]+" class="btn services-v2__cta-btn">/', $v2_php ),
	'test_services_v2_template_keeps_cta_wrapper',
	'templates/services-v2.php must keep the existing CTA wrapper and global button class'
);

rms_s2_assert(
	(bool) preg_match( '/\.services-v1__cta\s*\{\s*text-align:\s*center;\s*margin-top:\s*\$space-12;\s*\}/', $v1_scss ),
	'test_services_v1_cta_unchanged',
	'services-v1 CTA spacing must remain the canonical reference'
);

rms_s2_assert(
	(bool) preg_match( '/\.services-v2__cta\s*\{\s*text-align:\s*center;\s*margin-top:\s*\$space-12;\s*\}/', $v2_scss ),
	'test_services_v2_cta_matches_v1_spacing',
	'.services-v2__cta must set text-align:center and margin-top:$space-12'
);

rms_s2_assert(
	! preg_match( '/^\s*\.btn\b/m', $v2_scss ) && ! preg_match( '/^\s*\.services-v2__cta-btn\b/m', $v2_scss ),
	'test_services_v2_does_not_override_global_button',
	'services-v2.scss must not add button-level overrides'
);

if ( $failures > 0 ) { fwrite( STDERR, "\n{$failures} services-v2 CTA check(s) failed.\n" ); exit( 1 ); }
fwrite( STDOUT, "\nAll services-v2 CTA checks passed.\n" );
exit( 0 );
