<?php
/**
 * Wizard setup module bootstrap.
 *
 * @package Simple_RMS_Theme
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'RMS_WIZARD_PATH' ) ) {
    define( 'RMS_WIZARD_PATH', \trailingslashit( __DIR__ ) );
}

if ( ! defined( 'RMS_WIZARD_URL' ) ) {
    define( 'RMS_WIZARD_URL', \trailingslashit( \get_template_directory_uri() . '/inc/wizard' ) );
}

spl_autoload_register(
    static function ( string $class_name ): void {
        $prefix = 'Inc\\Wizard\\';

        if ( 0 !== strpos( $class_name, $prefix ) ) {
            return;
        }

        $relative_class = substr( $class_name, strlen( $prefix ) );
        $file_name      = 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';
        $file_path      = RMS_WIZARD_PATH . $file_name;

        if ( is_readable( $file_path ) ) {
            require_once $file_path;
        }
    }
);
