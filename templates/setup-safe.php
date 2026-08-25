<?php
/**
 * Setup-Safe Frontend Shell
 *
 * Rendered by `rms_template_include_acf_boundary` when ACF Pro is inactive.
 * Self-contained on purpose: header/footer chrome contains demo client facts
 * and would also fire `wp_head` schema. This shell returns HTTP 200 HTML
 * with no client facts and no ACF API calls.
 *
 * Visitors see a plain setup message. Users who can manage options also
 * see a link to the plugins admin screen (capability-checked).
 *
 * @package Simple_RMS_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( function_exists( 'nocache_headers' ) ) {
    nocache_headers();
}

if ( function_exists( 'status_header' ) ) {
    status_header( 200 );
}

$site_name = get_bloginfo( 'name' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html( $site_name ); ?></title>
</head>
<body class="setup-safe">
<main id="main-content" class="setup-safe">
    <section class="setup-safe__section">
        <div class="container">
            <h1 class="setup-safe__title">
                <?php echo esc_html( $site_name ); ?>
            </h1>
            <p class="setup-safe__message">
                <?php
                esc_html_e(
                    'This site is almost ready. A required plugin needs to be activated before the full content can be displayed.',
                    'simple-rms-theme'
                );
                ?>
            </p>
            <?php if ( current_user_can( 'manage_options' ) ) : ?>
                <p class="setup-safe__action">
                    <a class="btn setup-safe__button" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">
                        <?php esc_html_e( 'Activate plugins in the admin dashboard', 'simple-rms-theme' ); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
