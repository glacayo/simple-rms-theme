<?php
/**
 * TGM Plugin Activation configuration.
 *
 * @package Simple_RMS_Theme
 */

// Include the TGM_Plugin_Activation class.
if ( ! class_exists( 'TGM_Plugin_Activation' ) ) {
    require_once get_template_directory() . '/inc/tgmpa/class-tgm-plugin-activation.php';
}

/**
 * Register the required plugins.
 *
 * @return void
 */
function simple_rms_theme_register_required_plugins() {
    $plugins = array(
        // Advanced Custom Fields PRO
        array(
            'name'     => 'Advanced Custom Fields PRO',
            'slug'     => 'advanced-custom-fields-pro',
            'source'   => get_template_directory() . '/inc/plugins/advanced-custom-fields-pro.zip',
            'required' => true,
        ),
        // Classic Editor
        array(
            'name'     => 'Classic Editor',
            'slug'     => 'classic-editor',
            'required' => true,
        ),
        // Yoast SEO
        array(
            'name'     => 'Yoast SEO',
            'slug'     => 'wordpress-seo',
            'required' => false,
        ),
        // Contact Form 7
        array(
            'name'     => 'Contact Form 7',
            'slug'     => 'contact-form-7',
            'required' => false,
        ),
        // WP Fastest Cache
        array(
            'name'     => 'WP Fastest Cache',
            'slug'     => 'wp-fastest-cache',
            'required' => false,
        ),
        // Wordfence Security
        array(
            'name'     => 'Wordfence Security',
            'slug'     => 'wordfence',
            'required' => false,
        ),
    );

    $config = array(
        'id'           => 'simple-rms-theme',
        'default_path' => '',
        'menu'         => 'tgmpa-install-plugins',
        'has_notices'  => true,
        'dismissable'  => true,
        'dismiss_msg'  => '',
        'is_automatic' => false,
        'message'      => '',
    );

    tgmpa( $plugins, $config );
}
add_action( 'tgmpa_register', 'simple_rms_theme_register_required_plugins' );
