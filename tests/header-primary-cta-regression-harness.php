<?php
/**
 * Focused regression harness for issue #56 (header primary CTA locality + no
 * collateral change to other header variants or existing contact wiring).
 *
 * Proves:
 *   1. Header Three is unchanged and has no CTA.
 *   2. Flexible-content section CTAs stay local (no read of the global field).
 *   3. Existing #55 contact wiring (phone/social/address/email) remains intact.
 *
 * No framework. Single process; `get_field` and the page resolver are stubbed
 * via globals and toggled between scenarios.
 *
 * Usage: php tests/header-primary-cta-regression-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

require __DIR__ . '/support/header-cta-support.php';

// ─── Test 7: header three unchanged / no CTA ───────────────────────────────

$h3 = rms_cta_render( 'header-three.php' );
rms_cta_assert( false === strpos( $h3, 'cta-btn' ), 'test_header_three_has_no_cta (no cta button)', 'header-three rendered a CTA button' );
rms_cta_assert( false === strpos( $h3, 'rms-header-v3__cta' ), 'test_header_three_has_no_cta (no v3 cta class)', 'header-three rendered a CTA class' );

// ─── Test 8: flexible-content CTAs stay local (static) ─────────────────────

$templates_dir = $theme_root . '/templates';
$consumers = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $templates_dir, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
    if ( 'php' !== $file->getExtension() ) {
        continue;
    }
    $content = file_get_contents( $file->getPathname() );
    if ( false !== strpos( $content, 'rms_get_header_primary_cta' ) || false !== strpos( $content, 'company_header_primary_cta' ) ) {
        $consumers[] = $file->getFilename();
    }
}
sort( $consumers );
rms_cta_assert(
    array( 'header-one.php', 'header-two.php' ) === $consumers,
    'test_flexible_content_ctas_remain_local (only header-one/two consume)',
    'unexpected consumers of the global primary CTA: ' . implode( ', ', $consumers )
);

// ─── Test 9: existing contact wiring remains intact ────────────────────────

rms_cta_setup(
    array(
        'company_header_primary_cta' => array(
            'url'    => 'https://example.test/estimate',
            'title'  => 'Get Estimate',
            'target' => '_self',
        ),
        'company_phones'       => array( array( 'phone_number' => '(407) 555-0100' ) ),
        'company_emails'       => array( array( 'email_address' => 'contact@example.com' ) ),
        'company_social_media' => array(
            array(
                'social_is_active' => true,
                'social_url'       => 'https://facebook.com/raven',
                'social_platform'  => 'facebook',
                'social_label'     => 'Facebook',
            ),
        ),
        'company_address_line_1' => '1 Test Way',
        'company_city'           => 'Testville',
        'company_state'          => 'TX',
        'company_postal_code'    => '75001',
    ),
    array( 'contact-us' => 42 ),
    array( 42 => 'https://example.test/contact-us/' )
);

$h1 = rms_cta_render( 'header-one.php' );
$h2 = rms_cta_render( 'header-two.php' );

rms_cta_assert( false !== strpos( $h1, 'tel:4075550100' ), 'test_existing_contact_wiring_remains_intact (header-one phone)', 'header-one phone wiring regressed' );
rms_cta_assert( false !== strpos( $h1, 'https://facebook.com/raven' ), 'test_existing_contact_wiring_remains_intact (header-one social)', 'header-one social wiring regressed' );
rms_cta_assert( false !== strpos( $h1, '1 Test Way, Testville, TX, 75001' ), 'test_existing_contact_wiring_remains_intact (header-one address)', 'header-one address wiring regressed' );
rms_cta_assert( false !== strpos( $h2, 'mailto:contact@example.com' ), 'test_existing_contact_wiring_remains_intact (header-two email)', 'header-two email wiring regressed' );
rms_cta_assert( false !== strpos( $h2, 'https://facebook.com/raven' ), 'test_existing_contact_wiring_remains_intact (header-two social)', 'header-two social wiring regressed' );

// ─── Summary ───────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "Regression harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Regression harness passed: locality + existing contact wiring intact.\n";
exit( 0 );
