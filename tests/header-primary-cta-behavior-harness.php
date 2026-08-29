<?php
/**
 * Focused behavior harness for issue #56 (header primary CTA getter + render).
 *
 * Proves:
 *   1. `rms_get_header_primary_cta()` returns a normalized url/title/target
 *      contract for configured links (internal page + custom/external URL).
 *   2. Header One and Header Two render the configured URL/title/target and
 *      preserve their existing CTA classes.
 *   3. `_blank` adds `rel="noopener noreferrer"`; target is restricted to
 *      `_self`/`_blank`.
 *   4. Empty/malformed link data falls back to the Contact permalink (then
 *      `home_url('/#contact')`) with label "Get a Free Estimate" and `_self`.
 *   5. url/title/target are escaped.
 *
 * No framework. Single process; `get_field` and the page resolver are stubbed
 * via globals and toggled between scenarios.
 *
 * Usage: php tests/header-primary-cta-behavior-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

require __DIR__ . '/support/header-cta-support.php';

/**
 * Call the primary-CTA getter without fataling when it does not yet exist.
 */
function rms_cta_get(): array {
    if ( function_exists( 'rms_get_header_primary_cta' ) ) {
        return rms_get_header_primary_cta();
    }
    return array( 'url' => '__missing__', 'title' => '__missing__', 'target' => '__missing__' );
}

// ─── Test 2: getter configured with an internal page link ──────────────────

rms_cta_assert(
    function_exists( 'rms_get_header_primary_cta' ),
    'test_getter_defined',
    'rms_get_header_primary_cta() is not defined in inc/acf-theme-options.php'
);

rms_cta_setup(
    array(
        'company_header_primary_cta' => array(
            'url'    => 'https://example.test/free-estimate/',
            'title'  => 'Get Estimate',
            'target' => '_self',
        ),
        'company_phones'       => array(),
        'company_emails'       => array(),
        'company_social_media' => array(),
    ),
    array( 'contact-us' => 42 ),
    array( 42 => 'https://example.test/contact-us/' )
);

$cta = rms_cta_get();
rms_cta_assert( 'https://example.test/free-estimate/' === $cta['url'], 'test_header_one_and_two_render_internal_page_cta (getter url)', 'configured internal page URL not returned' );
rms_cta_assert( 'Get Estimate' === $cta['title'], 'test_header_one_and_two_render_internal_page_cta (getter title)', 'configured title not returned' );
rms_cta_assert( '_self' === $cta['target'], 'test_header_one_and_two_render_internal_page_cta (getter target)', 'configured _self target not returned' );

$h1 = rms_cta_render( 'header-one.php' );
$h2 = rms_cta_render( 'header-two.php' );

rms_cta_assert( false !== strpos( $h1, 'https://example.test/free-estimate/' ), 'test_header_one_and_two_render_internal_page_cta (header-one href)', 'header-one did not render configured internal page URL' );
rms_cta_assert( false !== strpos( $h1, 'Get Estimate' ), 'test_header_one_and_two_render_internal_page_cta (header-one label)', 'header-one did not render configured title' );
rms_cta_assert( false !== strpos( $h2, 'https://example.test/free-estimate/' ), 'test_header_one_and_two_render_internal_page_cta (header-two href)', 'header-two did not render configured internal page URL' );
rms_cta_assert( false !== strpos( $h2, 'Get Estimate' ), 'test_header_one_and_two_render_internal_page_cta (header-two label)', 'header-two did not render configured title' );

// Configured field must win over the contact permalink fallback.
rms_cta_assert( false === strpos( $h1, 'https://example.test/contact-us/' ), 'test_header_one_and_two_render_internal_page_cta (header-one overrides fallback)', 'header-one rendered contact fallback instead of configured URL' );
rms_cta_assert( false === strpos( $h2, 'https://example.test/contact-us/' ), 'test_header_one_and_two_render_internal_page_cta (header-two overrides fallback)', 'header-two rendered contact fallback instead of configured URL' );

// ─── Test 3: custom/external URL ───────────────────────────────────────────

rms_cta_setup(
    array(
        'company_header_primary_cta' => array(
            'url'    => 'https://client.example.com/book',
            'title'  => 'Book Your Service',
            'target' => '_self',
        ),
        'company_phones'       => array(),
        'company_emails'       => array(),
        'company_social_media' => array(),
    ),
    array( 'contact-us' => 42 ),
    array( 42 => 'https://example.test/contact-us/' )
);

$h1 = rms_cta_render( 'header-one.php' );
$h2 = rms_cta_render( 'header-two.php' );

rms_cta_assert( false !== strpos( $h1, 'https://client.example.com/book' ), 'test_header_one_and_two_render_custom_url_cta (header-one)', 'header-one did not render custom external URL' );
rms_cta_assert( false !== strpos( $h2, 'https://client.example.com/book' ), 'test_header_one_and_two_render_custom_url_cta (header-two)', 'header-two did not render custom external URL' );

// ─── Test 4: _blank target adds safe rel ───────────────────────────────────

rms_cta_setup(
    array(
        'company_header_primary_cta' => array(
            'url'    => 'https://client.example.com/book',
            'title'  => 'Book Now',
            'target' => '_blank',
        ),
        'company_phones'       => array(),
        'company_emails'       => array(),
        'company_social_media' => array(),
    ),
    array(),
    array()
);

$h1 = rms_cta_render( 'header-one.php' );
$h2 = rms_cta_render( 'header-two.php' );

rms_cta_assert( false !== strpos( $h1, 'target="_blank"' ), 'test_header_cta_blank_target_adds_noopener_noreferrer (header-one target)', 'header-one missing target="_blank"' );
rms_cta_assert( false !== strpos( $h1, 'rel="noopener noreferrer"' ), 'test_header_cta_blank_target_adds_noopener_noreferrer (header-one rel)', 'header-one missing rel="noopener noreferrer" for _blank' );
rms_cta_assert( false !== strpos( $h2, 'target="_blank"' ), 'test_header_cta_blank_target_adds_noopener_noreferrer (header-two target)', 'header-two missing target="_blank"' );
rms_cta_assert( false !== strpos( $h2, 'rel="noopener noreferrer"' ), 'test_header_cta_blank_target_adds_noopener_noreferrer (header-two rel)', 'header-two missing rel="noopener noreferrer" for _blank' );

// `_self` (and any non-blank) must NOT add the blank-target rel.
rms_cta_setup(
    array(
        'company_header_primary_cta' => array(
            'url'    => 'https://example.test/estimate',
            'title'  => 'Get Estimate',
            'target' => '_self',
        ),
        'company_phones'       => array(),
        'company_emails'       => array(),
        'company_social_media' => array(),
    ),
    array(),
    array()
);
$h1_self = rms_cta_render( 'header-one.php' );
rms_cta_assert( false === strpos( $h1_self, 'rel="noopener noreferrer"' ), 'test_header_cta_blank_target_adds_noopener_noreferrer (self no rel)', 'header-one added rel for _self target' );
rms_cta_assert( false !== strpos( $h1_self, 'target="_self"' ), 'test_header_cta_blank_target_adds_noopener_noreferrer (self target)', 'header-one missing target="_self"' );

// ─── Test 5: empty/malformed link data falls back safely ───────────────────

// 5a. Field key absent entirely.
rms_cta_setup(
    array(
        'company_phones'       => array(),
        'company_emails'       => array(),
        'company_social_media' => array(),
    ),
    array( 'contact-us' => 42 ),
    array( 42 => 'https://example.test/contact-us/' )
);
$cta = rms_cta_get();
rms_cta_assert( 'https://example.test/contact-us/' === $cta['url'], 'test_header_cta_empty_uses_contact_permalink_or_hash_fallback (getter contact permalink)', 'empty field did not resolve contact permalink' );
rms_cta_assert( 'Get a Free Estimate' === $cta['title'], 'test_header_cta_empty_uses_contact_permalink_or_hash_fallback (getter default label)', 'empty field did not use default label' );
rms_cta_assert( '_self' === $cta['target'], 'test_header_cta_empty_uses_contact_permalink_or_hash_fallback (getter self target)', 'empty field did not default to _self' );

$h1 = rms_cta_render( 'header-one.php' );
$h2 = rms_cta_render( 'header-two.php' );
rms_cta_assert( false !== strpos( $h1, 'https://example.test/contact-us/' ), 'test_header_cta_empty_uses_contact_permalink_or_hash_fallback (header-one permalink)', 'header-one empty CTA did not use contact permalink' );
rms_cta_assert( false !== strpos( $h1, 'Get a Free Estimate' ), 'test_header_cta_empty_uses_contact_permalink_or_hash_fallback (header-one label)', 'header-one empty CTA did not use default label' );
rms_cta_assert( false !== strpos( $h2, 'Get a Free Estimate' ), 'test_header_cta_empty_uses_contact_permalink_or_hash_fallback (header-two label)', 'header-two empty CTA did not use default label' );
rms_cta_assert( false === strpos( $h1, 'href="#"' ) && false === strpos( $h2, 'href="#"' ), 'test_header_cta_empty_uses_contact_permalink_or_hash_fallback (no dead href)', 'empty CTA produced dead href="#"' );

// 5b. No Contact page: fall back to home_url('/#contact'); never a bare `#`.
rms_cta_setup(
    array(
        'company_phones'       => array(),
        'company_emails'       => array(),
        'company_social_media' => array(),
    ),
    array(),
    array()
);
$h1 = rms_cta_render( 'header-one.php' );
$h2 = rms_cta_render( 'header-two.php' );
rms_cta_assert( false !== strpos( $h1, 'https://example.test/#contact' ), 'test_header_cta_empty_uses_contact_permalink_or_hash_fallback (header-one hash fallback)', 'header-one did not fall back to home_url(/#contact)' );
rms_cta_assert( false !== strpos( $h2, 'https://example.test/#contact' ), 'test_header_cta_empty_uses_contact_permalink_or_hash_fallback (header-two hash fallback)', 'header-two did not fall back to home_url(/#contact)' );
rms_cta_assert( false === strpos( $h1, 'href="#"' ) && false === strpos( $h2, 'href="#"' ), 'test_header_cta_empty_uses_contact_permalink_or_hash_fallback (hash fallback no dead href)', 'hash fallback still produced bare href="#"' );

// 5c. Malformed link data: non-array, empty array, empty url, bare '#' url.
foreach ( array( 'garbage', 42, null, array(), array( 'url' => '', 'title' => 'X', 'target' => '_blank' ), array( 'url' => '#', 'title' => 'X', 'target' => '_blank' ) ) as $malformed ) {
    rms_cta_setup(
        array_merge(
            array( 'company_header_primary_cta' => $malformed ),
            array( 'company_phones' => array(), 'company_emails' => array(), 'company_social_media' => array() )
        ),
        array( 'contact-us' => 42 ),
        array( 42 => 'https://example.test/contact-us/' )
    );
    $cta = rms_cta_get();
    $ok = ( 'https://example.test/contact-us/' === $cta['url'] )
        && ( 'Get a Free Estimate' === $cta['title'] )
        && ( '_self' === $cta['target'] );
    rms_cta_assert( $ok, 'test_header_cta_empty_uses_contact_permalink_or_hash_fallback (malformed ' . gettype( $malformed ) . ')', 'malformed link data did not fall back safely' );
}

// 5d. Partial link data: url present but title/target missing.
rms_cta_setup(
    array(
        'company_header_primary_cta' => array( 'url' => 'https://example.test/only-url' ),
        'company_phones'       => array(),
        'company_emails'       => array(),
        'company_social_media' => array(),
    ),
    array(),
    array()
);
$cta = rms_cta_get();
rms_cta_assert( 'https://example.test/only-url' === $cta['url'], 'test_getter_handles_malformed_link_data (url kept)', 'partial link lost its URL' );
rms_cta_assert( 'Get a Free Estimate' === $cta['title'], 'test_getter_handles_malformed_link_data (title defaulted)', 'partial link did not default its title' );
rms_cta_assert( '_self' === $cta['target'], 'test_getter_handles_malformed_link_data (target defaulted)', 'partial link did not default its target' );

// 5e. Invalid target values are restricted to _self.
foreach ( array( '_top', 'foo', '', null, array( 'nested' ) ) as $bad_target ) {
    rms_cta_setup(
        array(
            'company_header_primary_cta' => array( 'url' => 'https://example.test/estimate', 'title' => 'Get Estimate', 'target' => $bad_target ),
            'company_phones'       => array(),
            'company_emails'       => array(),
            'company_social_media' => array(),
        ),
        array(),
        array()
    );
    $cta = rms_cta_get();
    rms_cta_assert( '_self' === $cta['target'], 'test_getter_handles_malformed_link_data (target restricted ' . gettype( $bad_target ) . ')', 'invalid target was not restricted to _self' );
}

// ─── Test 6: url/title/target escaping ─────────────────────────────────────

rms_cta_setup(
    array(
        'company_header_primary_cta' => array(
            'url'    => 'javascript:alert(1)',
            'title'  => '<script>alert("x")</script> & "quotes"',
            'target' => '_blank',
        ),
        'company_phones'       => array(),
        'company_emails'       => array(),
        'company_social_media' => array(),
    ),
    array(),
    array()
);
$h1 = rms_cta_render( 'header-one.php' );
rms_cta_assert( false === strpos( $h1, 'javascript:' ), 'test_header_cta_escapes_url_title_target (url protocol stripped)', 'header-one emitted a javascript: href' );
rms_cta_assert( false === strpos( $h1, '<script>alert' ), 'test_header_cta_escapes_url_title_target (no raw script)', 'header-one emitted unescaped <script> in title' );
rms_cta_assert( false !== strpos( $h1, '&lt;script&gt;' ), 'test_header_cta_escapes_url_title_target (title escaped)', 'header-one did not escape <script> in title' );
rms_cta_assert( false !== strpos( $h1, '&quot;quotes&quot;' ), 'test_header_cta_escapes_url_title_target (quotes escaped)', 'header-one did not escape double quotes in title' );

// ─── Summary ───────────────────────────────────────────────────────────────

if ( $failures > 0 ) {
    fwrite( STDERR, "Behavior harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Behavior harness passed: getter + render behavior for header primary CTA.\n";
exit( 0 );
