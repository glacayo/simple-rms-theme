<?php
/**
 * Focused smoke harness for issue #23 (ACF-inactive frontend).
 *
 * No framework. Parent process runs isolated child scenarios because
 * `function_exists` / ACF stubs cannot be toggled in one process.
 *
 * Usage: php tests/acf-inactive-frontend-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$scenario = getenv( 'RMS_HARNESS_SCENARIO' );

if ( false === $scenario || '' === $scenario ) {
    $php       = PHP_BINARY;
    $self      = __FILE__;
    $scenarios = array(
        'inactive-swap',
        'active-passthrough',
        'admin-bypass',
        'rest-bypass',
        'cli-bypass',
        'option-helper',
        'option-helper-active',
        'schema-no-get-field',
        'shell-guest',
        'shell-admin',
        'safe-missing',
    );

    $failed = 0;
    foreach ( $scenarios as $name ) {
        $cmd = '"' . $php . '" ' . escapeshellarg( $self );
        putenv( 'RMS_HARNESS_SCENARIO=' . $name );
        $output = array();
        $code   = 0;
        exec( $cmd, $output, $code );
        $text = implode( "\n", $output );
        if ( 0 !== $code ) {
            fwrite( STDERR, "FAIL {$name}\n{$text}\n" );
            $failed++;
            continue;
        }
        echo "PASS {$name}\n";
        if ( '' !== $text ) {
            echo $text . "\n";
        }
    }
    putenv( 'RMS_HARNESS_SCENARIO' );

    if ( $failed > 0 ) {
        fwrite( STDERR, "Harness failed: {$failed} scenario(s).\n" );
        exit( 1 );
    }

    echo "Harness passed: " . count( $scenarios ) . " scenarios.\n";
    exit( 0 );
}

$theme_root = dirname( __DIR__ );

/**
 * @param mixed $condition
 */
function rms_harness_assert( $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, $message . "\n" );
        exit( 1 );
    }
}

function rms_harness_stub_wordpress( array $overrides = array() ): void {
    if ( ! defined( 'ABSPATH' ) ) {
        define( 'ABSPATH', __DIR__ . '/' );
    }

    $defaults = array(
        'is_admin'        => false,
        'can_manage'      => false,
        'status'          => null,
        'nocache'         => false,
        'blogname'        => 'Harness Site',
        'charset'         => 'UTF-8',
        'filters'         => array(),
        'template_dir'    => dirname( __DIR__ ),
        'get_field_calls' => 0,
    );
    $existing = isset( $GLOBALS['rms_harness'] ) && is_array( $GLOBALS['rms_harness'] ) ? $GLOBALS['rms_harness'] : array();
    $GLOBALS['rms_harness'] = array_merge( $defaults, $existing, $overrides );

    if ( ! function_exists( 'add_filter' ) ) {
        function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
            $GLOBALS['rms_harness']['filters'][ $hook ][] = $callback;
            return true;
        }
    }

    if ( ! function_exists( 'add_action' ) ) {
        function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
            return add_filter( $hook, $callback, $priority, $accepted_args );
        }
    }

    if ( ! function_exists( 'trailingslashit' ) ) {
        function trailingslashit( $value ) {
            return rtrim( (string) $value, '/\\' ) . '/';
        }
    }

    if ( ! function_exists( 'get_template_directory' ) ) {
        function get_template_directory() {
            return $GLOBALS['rms_harness']['template_dir'];
        }
    }

    if ( ! function_exists( 'is_admin' ) ) {
        function is_admin() {
            return ! empty( $GLOBALS['rms_harness']['is_admin'] );
        }
    }

    if ( ! function_exists( 'get_bloginfo' ) ) {
        function get_bloginfo( $show = '' ) {
            if ( 'charset' === $show ) {
                return $GLOBALS['rms_harness']['charset'];
            }
            return $GLOBALS['rms_harness']['blogname'];
        }
    }

    if ( ! function_exists( 'bloginfo' ) ) {
        function bloginfo( $show = '' ) {
            echo esc_html( get_bloginfo( $show ) );
        }
    }

    if ( ! function_exists( 'language_attributes' ) ) {
        function language_attributes() {
            echo 'lang="en-US"';
        }
    }

    if ( ! function_exists( 'esc_html' ) ) {
        function esc_html( $text ) {
            return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
        }
    }

    if ( ! function_exists( 'esc_html_e' ) ) {
        function esc_html_e( $text, $domain = '' ) {
            echo esc_html( $text );
        }
    }

    if ( ! function_exists( 'esc_url' ) ) {
        function esc_url( $url ) {
            return filter_var( (string) $url, FILTER_SANITIZE_URL );
        }
    }

    if ( ! function_exists( 'current_user_can' ) ) {
        function current_user_can( $cap ) {
            return ! empty( $GLOBALS['rms_harness']['can_manage'] ) && 'manage_options' === $cap;
        }
    }

    if ( ! function_exists( 'admin_url' ) ) {
        function admin_url( $path = '' ) {
            return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
        }
    }

    if ( ! function_exists( 'nocache_headers' ) ) {
        function nocache_headers() {
            $GLOBALS['rms_harness']['nocache'] = true;
        }
    }

    if ( ! function_exists( 'status_header' ) ) {
        function status_header( $code ) {
            $GLOBALS['rms_harness']['status'] = (int) $code;
        }
    }

    if ( ! function_exists( 'home_url' ) ) {
        function home_url( $path = '' ) {
            return 'https://example.test' . $path;
        }
    }
}

function rms_harness_define_acf_stubs(): void {
    if ( ! function_exists( 'get_field' ) ) {
        function get_field( $selector, $post_id = false, $format_value = true ) {
            $GLOBALS['rms_harness']['get_field_calls']++;
            return 'acf-active-value';
        }
    }
    if ( ! function_exists( 'get_sub_field' ) ) {
        function get_sub_field( $selector, $format_value = true ) {
            return null;
        }
    }
    if ( ! function_exists( 'have_rows' ) ) {
        function have_rows( $selector, $post_id = false ) {
            return false;
        }
    }
    if ( ! function_exists( 'the_row' ) ) {
        function the_row() {
            return false;
        }
    }
    if ( ! function_exists( 'get_row_layout' ) ) {
        function get_row_layout() {
            return '';
        }
    }
}

switch ( $scenario ) {
    case 'inactive-swap':
        rms_harness_stub_wordpress();
        require $theme_root . '/inc/acf-template-boundary.php';
        rms_harness_assert( function_exists( 'rms_acf_available' ), 'missing rms_acf_available' );
        rms_harness_assert( false === rms_acf_available(), 'ACF should be unavailable' );

        $paths = array(
            $theme_root . '/front-page.php',
            $theme_root . '/index.php',
            $theme_root . '/pages/contact-us.php',
            $theme_root . '/templates/flexible-content.php',
            $theme_root . '/templates/footer-v2.php',
        );
        $safe = $theme_root . '/templates/setup-safe.php';
        foreach ( $paths as $path ) {
            $result = rms_template_include_acf_boundary( $path );
            rms_harness_assert( $safe === $result, 'expected setup-safe for ' . $path . ' got ' . $result );
        }
        break;

    case 'active-passthrough':
        rms_harness_stub_wordpress();
        rms_harness_define_acf_stubs();
        require $theme_root . '/inc/acf-template-boundary.php';
        rms_harness_assert( true === rms_acf_available(), 'ACF should be available' );
        $original = $theme_root . '/front-page.php';
        $result   = rms_template_include_acf_boundary( $original );
        rms_harness_assert( $original === $result, 'ACF-active path must keep original template' );
        break;

    case 'admin-bypass':
        rms_harness_stub_wordpress( array( 'is_admin' => true ) );
        require $theme_root . '/inc/acf-template-boundary.php';
        $original = $theme_root . '/front-page.php';
        $result   = rms_template_include_acf_boundary( $original );
        rms_harness_assert( $original === $result, 'admin must bypass setup-safe swap' );
        break;

    case 'rest-bypass':
        define( 'REST_REQUEST', true );
        rms_harness_stub_wordpress();
        require $theme_root . '/inc/acf-template-boundary.php';
        $original = $theme_root . '/front-page.php';
        $result   = rms_template_include_acf_boundary( $original );
        rms_harness_assert( $original === $result, 'REST must bypass setup-safe swap' );
        break;

    case 'cli-bypass':
        define( 'WP_CLI', true );
        rms_harness_stub_wordpress();
        require $theme_root . '/inc/acf-template-boundary.php';
        $original = $theme_root . '/front-page.php';
        $result   = rms_template_include_acf_boundary( $original );
        rms_harness_assert( $original === $result, 'CLI must bypass setup-safe swap' );
        break;

    case 'option-helper':
        rms_harness_stub_wordpress();
        require $theme_root . '/inc/acf-theme-options.php';
        rms_harness_assert( false === function_exists( 'get_field' ), 'get_field must stay undefined' );
        $value = rms_get_option( 'company_payment_methods', 'SAFE_DEFAULT' );
        rms_harness_assert( 'SAFE_DEFAULT' === $value, 'rms_get_option must return default without get_field' );
        $empty = rms_get_option( 'company_phones' );
        rms_harness_assert( null === $empty, 'rms_get_option must return null default without get_field' );
        break;

    case 'option-helper-active':
        rms_harness_stub_wordpress();
        rms_harness_define_acf_stubs();
        require $theme_root . '/inc/acf-theme-options.php';
        $value = rms_get_option( 'company_payment_methods', 'SAFE_DEFAULT' );
        rms_harness_assert( 'acf-active-value' === $value, 'ACF-active rms_get_option must call get_field' );
        rms_harness_assert( $GLOBALS['rms_harness']['get_field_calls'] > 0, 'get_field was not called on the active path' );
        break;

    case 'schema-no-get-field':
        rms_harness_stub_wordpress();
        require $theme_root . '/inc/acf-theme-options.php';
        require $theme_root . '/inc/schema.php';
        rms_harness_assert( false === function_exists( 'get_field' ), 'get_field must stay undefined' );
        $schema = rms_schema_local_business();
        rms_harness_assert( is_array( $schema ), 'schema helper must return an array without get_field' );
        ob_start();
        rms_schema_header();
        $out = ob_get_clean();
        rms_harness_assert( '' === $out, 'schema header must emit nothing when ACF is inactive' );
        break;

    case 'shell-guest':
        rms_harness_stub_wordpress();
        ob_start();
        require $theme_root . '/templates/setup-safe.php';
        $html = ob_get_clean();
        rms_harness_assert( 200 === $GLOBALS['rms_harness']['status'], 'setup-safe must set HTTP 200' );
        rms_harness_assert( true === $GLOBALS['rms_harness']['nocache'], 'setup-safe must send nocache headers' );
        rms_harness_assert( false !== stripos( $html, '<!DOCTYPE html>' ), 'missing doctype' );
        rms_harness_assert( false !== stripos( $html, '<main' ), 'missing main landmark' );
        rms_harness_assert( false !== stripos( $html, '<h1' ), 'missing h1' );
        rms_harness_assert( false !== stripos( $html, 'almost ready' ), 'missing setup copy' );
        rms_harness_assert( false === stripos( $html, 'plugins.php' ), 'guest must not see plugins link' );
        rms_harness_assert( false === stripos( $html, '414' ), 'must not leak demo phone' );
        rms_harness_assert( false === stripos( $html, 'Milwaukee' ), 'must not leak demo city' );
        rms_harness_assert( false === stripos( $html, 'get_field' ), 'must not mention get_field' );
        rms_harness_assert( false === function_exists( 'get_field' ), 'shell must not define get_field' );
        break;

    case 'safe-missing':
        $temp = sys_get_temp_dir() . '/rms-acf-harness-' . getmypid();
        if ( ! is_dir( $temp ) && ! mkdir( $temp ) && ! is_dir( $temp ) ) {
            fwrite( STDERR, "Unable to create temp dir\n" );
            exit( 1 );
        }
        rms_harness_stub_wordpress( array( 'template_dir' => $temp ) );
        require $theme_root . '/inc/acf-template-boundary.php';
        $original = $theme_root . '/front-page.php';
        $result   = rms_template_include_acf_boundary( $original );
        rms_harness_assert( $original === $result, 'missing setup-safe must return original template' );
        @rmdir( $temp );
        break;

    case 'shell-admin':
        rms_harness_stub_wordpress( array( 'can_manage' => true ) );
        ob_start();
        require $theme_root . '/templates/setup-safe.php';
        $html = ob_get_clean();
        rms_harness_assert( false !== stripos( $html, 'plugins.php' ), 'capable admin must see plugins link' );
        rms_harness_assert( false === stripos( $html, 'advanced-custom-fields' ), 'must not leak plugin slug' );
        break;

    default:
        fwrite( STDERR, "Unknown scenario: {$scenario}\n" );
        exit( 1 );
}

exit( 0 );
