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

add_action(
	'admin_menu',
	static function (): void {
		add_theme_page(
			__( 'Setup Wizard', 'simple-rms-theme' ),
			__( 'Setup Wizard', 'simple-rms-theme' ),
			'manage_options',
			'rms-setup-wizard',
			'rms_wizard_render_admin_page'
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	static function ( string $hook_suffix ): void {
		if ( 'appearance_page_rms-setup-wizard' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script( 'wp-api-fetch' );
		wp_add_inline_script(
			'wp-api-fetch',
			'window.rmsWizardSettings = ' . wp_json_encode(
				[
					'root'  => esc_url_raw( rest_url( \Inc\Wizard\Rest_Controller::NAMESPACE . '/' ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				]
			) . ';',
			'before'
		);
	}
);

add_action(
	'rest_api_init',
	static function (): void {
		( new Inc\Wizard\Rest_Controller() )->register_routes();
	}
);

/**
 * Render the minimal server-side wizard placeholder.
 *
 * Phase 4 owns the full interactive UI and Vite assets.
 *
 * @return void
 */
function rms_wizard_render_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access the setup wizard.', 'simple-rms-theme' ) );
	}

	$controller = new Inc\Wizard\Step_Controller();
	$state      = $controller->get_resume_state();
	$locked     = ! empty( $state['locked'] );
	?>
	<div class="wrap rms-setup-wizard">
		<h1><?php esc_html_e( 'Setup Wizard', 'simple-rms-theme' ); ?></h1>
		<?php if ( $locked ) : ?>
			<div class="notice notice-success inline">
				<p><?php esc_html_e( 'The setup wizard has already been completed. Define RMS_WIZARD_FORCE as true to run it again in development.', 'simple-rms-theme' ); ?></p>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'The setup wizard backend is ready. The guided admin interface will be added in the next phase.', 'simple-rms-theme' ); ?></p>
			<p>
				<?php esc_html_e( 'Current step:', 'simple-rms-theme' ); ?>
				<strong><?php echo esc_html( (string) $state['current_step'] ); ?></strong>
			</p>
		<?php endif; ?>
	</div>
	<?php
}
