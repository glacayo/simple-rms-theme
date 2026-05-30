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

		if ( class_exists( 'Vite_Icons_Integration' ) ) {
			$vite = Vite_Icons_Integration::get_instance();

			$wizard_css = $vite->get_asset( 'src/scss/admin/wizard.scss' );
			if ( $wizard_css ) {
				wp_enqueue_style( 'rms-wizard-admin', $wizard_css, [], null );
			}

			if ( file_exists( get_template_directory() . '/hot' ) ) {
				wp_enqueue_script( 'vite-client', 'http://localhost:3000/@vite/client', [], null, false );
			}

			$wizard_js = $vite->get_asset( 'src/ts/admin/wizard.ts' );
			if ( $wizard_js ) {
				wp_enqueue_script( 'rms-wizard-admin', $wizard_js, [], null, true );
			}
		}

		wp_add_inline_script(
			wp_script_is( 'rms-wizard-admin', 'enqueued' ) ? 'rms-wizard-admin' : 'wp-api-fetch',
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
	$steps      = [
		'dependencies'     => __( 'Dependencies', 'simple-rms-theme' ),
		'acf-import'       => __( 'ACF Import', 'simple-rms-theme' ),
		'client-data'      => __( 'Client Data', 'simple-rms-theme' ),
		'ai-generation'    => __( 'AI Generation', 'simple-rms-theme' ),
		'content-creation' => __( 'Content Creation', 'simple-rms-theme' ),
	];
	$descriptions = [
		'dependencies'     => __( 'Check and install the required WordPress plugins before continuing.', 'simple-rms-theme' ),
		'acf-import'       => __( 'Import ACF JSON field groups from the theme acf-json directory.', 'simple-rms-theme' ),
		'client-data'      => __( 'Save contractor business information into the theme options.', 'simple-rms-theme' ),
		'ai-generation'    => __( 'Generate one content section through the configured provider endpoint.', 'simple-rms-theme' ),
		'content-creation' => __( 'Create pages from prepared JSON content and write related metadata.', 'simple-rms-theme' ),
	];
	?>
	<div
		class="wrap rms-setup-wizard"
		data-rms-wizard
		data-rms-wizard-root="<?php echo esc_url( rest_url( \Inc\Wizard\Rest_Controller::NAMESPACE . '/' ) ); ?>"
		data-rms-wizard-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
	>
		<h1><?php esc_html_e( 'Setup Wizard', 'simple-rms-theme' ); ?></h1>
		<?php if ( $locked ) : ?>
			<div class="notice notice-success inline">
				<p><?php esc_html_e( 'The setup wizard has already been completed. Define RMS_WIZARD_FORCE as true to run it again in development.', 'simple-rms-theme' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="rms-wizard-shell">
			<aside class="rms-wizard-sidebar" aria-label="<?php esc_attr_e( 'Wizard progress', 'simple-rms-theme' ); ?>">
				<div class="rms-wizard-progress">
					<div class="rms-wizard-progress__meta">
						<strong><?php esc_html_e( 'Setup progress', 'simple-rms-theme' ); ?></strong>
						<span data-wizard-progress-text><?php esc_html_e( 'Loading progress...', 'simple-rms-theme' ); ?></span>
					</div>
					<div class="rms-wizard-progress__track">
						<div class="rms-wizard-progress__bar" data-wizard-progress-bar role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
					</div>
				</div>

				<ol class="rms-wizard-steps">
					<?php $index = 1; ?>
					<?php foreach ( $steps as $slug => $label ) : ?>
						<?php $status = (string) ( $state['step_status'][ $slug ] ?? 'pending' ); ?>
						<li>
							<button type="button" class="rms-wizard-step-nav is-<?php echo esc_attr( $status ); ?>" data-wizard-step-nav="<?php echo esc_attr( $slug ); ?>">
								<span class="rms-wizard-step-nav__index"><?php echo esc_html( (string) $index ); ?></span>
								<span>
									<span class="rms-wizard-step-nav__title"><?php echo esc_html( $label ); ?></span>
									<span class="rms-wizard-step-nav__status" data-wizard-step-status><?php echo esc_html( $status ); ?></span>
								</span>
							</button>
						</li>
						<?php ++$index; ?>
					<?php endforeach; ?>
				</ol>
			</aside>

			<main class="rms-wizard-panel">
				<div class="rms-wizard-notice" data-wizard-notice hidden></div>

				<?php foreach ( $steps as $slug => $label ) : ?>
					<section class="rms-wizard-step-panel" data-wizard-step-panel="<?php echo esc_attr( $slug ); ?>" <?php echo (string) $state['current_step'] === $slug ? '' : 'hidden'; ?>>
						<header class="rms-wizard-step-panel__header">
							<h2><?php echo esc_html( $label ); ?></h2>
							<p><?php echo esc_html( $descriptions[ $slug ] ); ?></p>
						</header>

						<?php if ( 'client-data' === $slug ) : ?>
							<form class="rms-wizard-fields">
								<div class="rms-wizard-field">
									<label for="rms-wizard-company-name"><?php esc_html_e( 'Company name', 'simple-rms-theme' ); ?></label>
									<input id="rms-wizard-company-name" type="text" name="company_name" autocomplete="organization">
								</div>
								<div class="rms-wizard-field">
									<label for="rms-wizard-phone"><?php esc_html_e( 'Primary phone', 'simple-rms-theme' ); ?></label>
									<input id="rms-wizard-phone" type="tel" name="primary_phone" autocomplete="tel">
								</div>
								<div class="rms-wizard-field">
									<label for="rms-wizard-email"><?php esc_html_e( 'Primary email', 'simple-rms-theme' ); ?></label>
									<input id="rms-wizard-email" type="email" name="primary_email" autocomplete="email">
								</div>
								<div class="rms-wizard-field">
									<label for="rms-wizard-service-area"><?php esc_html_e( 'Service area', 'simple-rms-theme' ); ?></label>
									<textarea id="rms-wizard-service-area" name="service_area"></textarea>
								</div>
							</form>
						<?php elseif ( 'ai-generation' === $slug ) : ?>
							<form class="rms-wizard-fields">
								<div class="rms-wizard-field">
									<label for="rms-wizard-ai-endpoint"><?php esc_html_e( 'Provider endpoint', 'simple-rms-theme' ); ?></label>
									<input id="rms-wizard-ai-endpoint" type="url" name="endpoint" placeholder="https://api.example.com/v1/generate">
								</div>
								<div class="rms-wizard-field">
									<label for="rms-wizard-ai-key"><?php esc_html_e( 'API key', 'simple-rms-theme' ); ?></label>
									<input id="rms-wizard-ai-key" type="password" name="api_key" autocomplete="off">
								</div>
								<div class="rms-wizard-field">
									<label for="rms-wizard-ai-model"><?php esc_html_e( 'Model', 'simple-rms-theme' ); ?></label>
									<input id="rms-wizard-ai-model" type="text" name="model">
								</div>
								<div class="rms-wizard-field">
									<label for="rms-wizard-ai-prompt"><?php esc_html_e( 'Prompt', 'simple-rms-theme' ); ?></label>
									<textarea id="rms-wizard-ai-prompt" name="prompt"></textarea>
								</div>
							</form>
						<?php elseif ( 'content-creation' === $slug ) : ?>
							<form class="rms-wizard-fields">
								<div class="rms-wizard-field">
									<label for="rms-wizard-pages-json"><?php esc_html_e( 'Pages JSON', 'simple-rms-theme' ); ?></label>
									<textarea id="rms-wizard-pages-json" name="pages" placeholder='[{"title":"Home","content":"..."}]'></textarea>
								</div>
							</form>
						<?php endif; ?>

						<div class="rms-wizard-actions">
							<button type="button" class="button button-primary" data-wizard-run-step="<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Run step', 'simple-rms-theme' ); ?></button>
							<button type="button" class="button" data-wizard-retry-step="<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Retry', 'simple-rms-theme' ); ?></button>
							<span class="rms-wizard-action-status" data-wizard-action-status></span>
						</div>

						<pre class="rms-wizard-step-result" data-wizard-step-result hidden></pre>
					</section>
				<?php endforeach; ?>

				<div class="rms-wizard-actions">
					<button type="button" class="button" data-wizard-refresh><?php esc_html_e( 'Refresh state', 'simple-rms-theme' ); ?></button>
					<button type="button" class="button button-primary" data-wizard-complete><?php esc_html_e( 'Complete wizard', 'simple-rms-theme' ); ?></button>
				</div>

				<section class="rms-wizard-log" aria-label="<?php esc_attr_e( 'Wizard log', 'simple-rms-theme' ); ?>">
					<h2><?php esc_html_e( 'Recent log entries', 'simple-rms-theme' ); ?></h2>
					<ol class="rms-wizard-log__list" data-wizard-logs>
						<li class="rms-wizard-log__item"><?php esc_html_e( 'Loading log entries...', 'simple-rms-theme' ); ?></li>
					</ol>
				</section>
			</main>
		</div>
	</div>
	<?php
}
