<?php
/**
 * Focused contract harness for issue #156 (mobile submenus never open).
 *
 * Proves, without a framework:
 *   1. All six nav walkers emit well-formed nesting: every <li> closes in
 *      end_el, submenus sit inside their parent <li>, no stray </li>.
 *   2. The three mobile walkers render a real toggle button for parents:
 *      placeholder parents (`#` or empty URL) become the button itself,
 *      parents with a URL keep their link plus an icon-only toggle. Each
 *      toggle starts collapsed and controls its submenu by id. No mobile link
 *      points to `#` and mobile walkers no longer emit aria-haspopup.
 *   3. Desktop walkers keep their aria-haspopup dropdown links.
 *   4. The three menu scripts bind to those toggles, reset open submenus on
 *      close, and skip collapsed submenu links in their focus traps; header
 *      one no longer caps submenus at a fixed max-height.
 *
 * Usage: php tests/mobile-nav-walkers-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );
$failures   = 0;

function rms_mn_assert( bool $condition, string $name, string $detail ): void {
    global $failures;
    if ( $condition ) {
        fwrite( STDOUT, "PASS {$name}\n" );
        return;
    }
    $failures++;
    fwrite( STDERR, "FAIL {$name}: {$detail}\n" );
}

// ─── Minimal WordPress stubs ───────────────────────────────────────────────

function __( $text, $domain = 'default' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $url ) { return esc_attr( trim( (string) $url ) ); }
function add_action( ...$args ) { return true; }
function add_filter( ...$args ) { return true; }
function add_theme_support( ...$args ) { return true; }
function add_image_size( ...$args ) { return true; }
function register_nav_menus( ...$args ) { return true; }
class Walker_Nav_Menu {}

require $theme_root . '/inc/setup.php';

/**
 * Drive a walker in the same call order as Walker::display_element().
 */
function rms_mn_walk( $walker, array $items, int $depth = 0 ): string {
    $out = '';
    foreach ( $items as $item ) {
        $children = $item->children ?? array();
        $walker->start_el( $out, $item, $depth );
        if ( $children ) {
            $walker->start_lvl( $out, $depth );
            $out .= rms_mn_walk( $walker, $children, $depth + 1 );
            $walker->end_lvl( $out, $depth );
        }
        $walker->end_el( $out, $item, $depth );
    }
    return $out;
}

function rms_mn_item( int $id, string $title, string $url, array $children = array() ): object {
    return (object) array(
        'ID'       => $id,
        'title'    => $title,
        'url'      => $url,
        'classes'  => $children ? array( 'menu-item', 'menu-item-has-children' ) : array( 'menu-item' ),
        'children' => $children,
    );
}

$tree = array(
    rms_mn_item( 1, 'Home', 'https://example.test/' ),
    rms_mn_item( 2, 'Our Services', '#', array(
        rms_mn_item( 3, 'Roofing', 'https://example.test/roofing/' ),
        rms_mn_item( 4, 'Siding', 'https://example.test/siding/', array(
            rms_mn_item( 5, 'Vinyl', 'https://example.test/vinyl/' ),
        ) ),
    ) ),
    rms_mn_item( 6, 'About', 'https://example.test/about/', array(
        rms_mn_item( 7, 'Team', 'https://example.test/team/' ),
    ) ),
    rms_mn_item( 8, 'Areas', '', array(
        rms_mn_item( 9, 'Dallas', 'https://example.test/dallas/' ),
    ) ),
);

function rms_mn_parse( string $html ): ?DOMXPath {
    $doc = new DOMDocument();
    $ok  = @$doc->loadXML( '<ul>' . $html . '</ul>' );
    return $ok ? new DOMXPath( $doc ) : null;
}

function rms_mn_li_by_title( DOMXPath $xp, string $title ): ?DOMElement {
    $nodes = $xp->query( "//li[(a|button|button/span)[normalize-space(.)='{$title}']]" );
    return $nodes && $nodes->length ? $nodes->item( 0 ) : null;
}

// ─── 1. Well-formed nesting for all six walkers ────────────────────────────

$walkers = array(
    'primary'    => 'RMS_Walker_Nav_Primary',
    'mobile'     => 'RMS_Walker_Nav_Mobile',
    'v2-desktop' => 'RMS_Walker_Nav_V2_Desktop',
    'v2-mobile'  => 'RMS_Walker_Nav_V2_Mobile',
    'v3-desktop' => 'RMS_Walker_Nav_V3_Desktop',
    'v3-mobile'  => 'RMS_Walker_Nav_V3_Mobile',
);

$rendered = array();
foreach ( $walkers as $key => $class ) {
    $html             = rms_mn_walk( new $class(), $tree );
    $rendered[ $key ] = $html;
    $xp               = rms_mn_parse( $html );

    rms_mn_assert( null !== $xp, "{$key}-well-formed", 'walker output must parse as well-formed markup' );
    rms_mn_assert(
        substr_count( $html, '<li' ) === substr_count( $html, '</li>' ),
        "{$key}-li-balanced",
        'every <li> must have exactly one </li>'
    );
    rms_mn_assert(
        ! preg_match( '#<ul[^>]*>\s*</li>#', $html ),
        "{$key}-no-stray-li",
        'no </li> may follow a submenu <ul> opening tag'
    );
    if ( ! $xp ) {
        continue;
    }

    $roofing = rms_mn_li_by_title( $xp, 'Roofing' );
    $vinyl   = rms_mn_li_by_title( $xp, 'Vinyl' );
    rms_mn_assert(
        $roofing && $roofing->parentNode->parentNode instanceof DOMElement
            && rms_mn_li_by_title( $xp, 'Our Services' ) === $roofing->parentNode->parentNode,
        "{$key}-child-nested",
        'child items must live inside their parent <li>'
    );
    rms_mn_assert(
        $vinyl && rms_mn_li_by_title( $xp, 'Siding' ) === $vinyl->parentNode->parentNode,
        "{$key}-grandchild-nested",
        'third-level items must live inside their parent <li>'
    );
    rms_mn_assert(
        4 === $xp->query( '/ul/li' )->length,
        "{$key}-top-level-count",
        'top level must keep exactly the four root items'
    );
}

// ─── 2. Mobile toggle markup ───────────────────────────────────────────────

$mobile = array(
    'mobile'    => 'rms-header',
    'v2-mobile' => 'rms-header-v2',
    'v3-mobile' => 'rms-header-v3',
);

foreach ( $mobile as $key => $block ) {
    $html   = $rendered[ $key ];
    $xp     = rms_mn_parse( $html );
    $toggle = "{$block}__mobile-nav-toggle";
    if ( ! $xp ) {
        continue;
    }

    rms_mn_assert( false === strpos( $html, 'aria-haspopup' ), "{$key}-no-haspopup", 'mobile walkers must not emit aria-haspopup' );
    rms_mn_assert( 0 === $xp->query( "//a[@href='#' or @href='']" )->length, "{$key}-no-empty-links", 'no mobile link may point to # or an empty URL' );

    $buttons = $xp->query( "//button[contains(concat(' ', @class, ' '), ' {$toggle} ')]" );
    rms_mn_assert( 4 === $buttons->length, "{$key}-toggle-count", "expected one {$toggle} per parent (4), got {$buttons->length}" );

    foreach ( $buttons as $button ) {
        $li        = $button->parentNode;
        $controls  = $button->getAttribute( 'aria-controls' );
        $submenu   = $controls !== '' ? $xp->query( "//ul[@id='{$controls}']" )->item( 0 ) : null;
        $label     = trim( $button->textContent ) !== '' ? trim( $button->textContent ) : $button->getAttribute( 'aria-label' );
        rms_mn_assert(
            'button' === $button->getAttribute( 'type' ) && 'false' === $button->getAttribute( 'aria-expanded' ),
            "{$key}-toggle-collapsed-{$label}",
            'toggle must be type=button and start with aria-expanded=false'
        );
        rms_mn_assert(
            $submenu instanceof DOMElement && $submenu->parentNode === $li,
            "{$key}-toggle-controls-{$label}",
            'aria-controls must point to the submenu <ul> inside the same <li>'
        );
    }

    $services = rms_mn_li_by_title( $xp, 'Our Services' );
    rms_mn_assert(
        $services && 0 === $xp->query( './a', $services )->length
            && 'Our Services' === trim( (string) $xp->query( "./button[contains(@class, '{$toggle}')]", $services )->item( 0 )?->textContent ),
        "{$key}-placeholder-is-button",
        'a # parent must render as the toggle button carrying its title, with no link'
    );

    $areas = rms_mn_li_by_title( $xp, 'Areas' );
    rms_mn_assert(
        $areas && 0 === $xp->query( './a', $areas )->length,
        "{$key}-empty-url-is-button",
        'an empty-URL parent must render as the toggle button, with no link'
    );

    $about = rms_mn_li_by_title( $xp, 'About' );
    $about_button = $about ? $xp->query( "./button[contains(@class, '{$toggle}')]", $about )->item( 0 ) : null;
    rms_mn_assert(
        $about && 'https://example.test/about/' === $xp->query( './a', $about )->item( 0 )?->getAttribute( 'href' )
            && $about_button && '' === trim( $about_button->textContent )
            && '' !== $about_button->getAttribute( 'aria-label' ),
        "{$key}-url-parent-link-plus-icon",
        'a parent with a URL keeps its link and gets an icon-only toggle with an aria-label'
    );

    $home = rms_mn_li_by_title( $xp, 'Home' );
    rms_mn_assert(
        $home && 0 === $xp->query( './button', $home )->length,
        "{$key}-leaf-no-toggle",
        'items without children must not render a toggle'
    );
}

// ─── 3. Desktop walkers keep dropdown semantics ────────────────────────────

foreach ( array( 'primary', 'v2-desktop', 'v3-desktop' ) as $key ) {
    rms_mn_assert(
        4 === substr_count( $rendered[ $key ], 'aria-haspopup="true"' ),
        "{$key}-keeps-haspopup",
        'desktop parents must keep aria-haspopup links'
    );
    rms_mn_assert( false === strpos( $rendered[ $key ], '__mobile-nav-toggle' ), "{$key}-no-mobile-toggle", 'desktop walkers must not render mobile toggles' );
}

// ─── 4. Script and style contracts ─────────────────────────────────────────

$scripts = array(
    'one'   => array( 'rms-header', 'src/ts/header-one-menu.ts' ),
    'two'   => array( 'rms-header-v2', 'src/ts/header-two-menu.ts' ),
    'three' => array( 'rms-header-v3', 'src/ts/header-three-menu.ts' ),
);

foreach ( $scripts as $key => list( $block, $path ) ) {
    $ts = (string) file_get_contents( $theme_root . '/' . $path );
    rms_mn_assert( false !== strpos( $ts, "'.{$block}__mobile-nav-toggle'" ), "ts-{$key}-binds-toggle", "{$path} must bind to .{$block}__mobile-nav-toggle" );
    rms_mn_assert( false === strpos( $ts, "closest('a')" ), "ts-{$key}-no-anchor-bailout", "{$path} must not ignore clicks coming from an <a>" );
    rms_mn_assert( false === strpos( $ts, 'aria-haspopup' ), "ts-{$key}-no-haspopup", "{$path} must not depend on aria-haspopup links" );
    rms_mn_assert( false !== strpos( $ts, 'isReachable' ), "ts-{$key}-trap-skips-collapsed", "{$path} focus trap must skip collapsed submenu links" );
}

foreach ( array( 'two' => 'rms-header-v2', 'three' => 'rms-header-v3' ) as $key => $block ) {
    $ts = (string) file_get_contents( $theme_root . "/src/ts/header-{$key}-menu.ts" );
    rms_mn_assert(
        false !== strpos( $ts, "'.{$block}__mobile-nav-item--has-submenu.' + CLASS_OPEN" ),
        "ts-{$key}-close-resets-li",
        'closeMenu must reset the parent <li>, which carries the open state'
    );
}

$one_ts   = (string) file_get_contents( $theme_root . '/src/ts/header-one-menu.ts' );
$one_scss = (string) file_get_contents( $theme_root . '/src/scss/layout/header-one/_mobile-menu.scss' );
rms_mn_assert( false !== strpos( $one_ts, 'scrollHeight' ), 'ts-one-measures-height', 'header one must open submenus to their real content height' );
rms_mn_assert( false === strpos( $one_scss, 'max-height: 500px' ), 'scss-one-no-fixed-cap', 'header one must not cap submenus at 500px' );
rms_mn_assert( false !== strpos( $one_scss, 'visibility: hidden' ), 'scss-one-collapsed-hidden', 'collapsed header one submenus must be visibility: hidden' );

foreach ( array( 'two' => 'rms-header-v2', 'three' => 'rms-header-v3' ) as $key => $block ) {
    $scss = (string) file_get_contents( $theme_root . "/src/scss/layout/header-{$key}.scss" );
    rms_mn_assert( false !== strpos( $scss, "{$block}__mobile-nav-toggle--icon" ), "scss-{$key}-icon-toggle", 'icon-only toggle must be styled' );
}

if ( $failures > 0 ) {
    fwrite( STDERR, "\n{$failures} failure(s).\n" );
    exit( 1 );
}

fwrite( STDOUT, "\nAll mobile nav walker assertions passed.\n" );
exit( 0 );
