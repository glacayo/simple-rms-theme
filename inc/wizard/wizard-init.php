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
		wp_enqueue_media();

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

// Unlock admin-post is inactive until CONTROLLED_UNLOCK_ENABLED flips (Phase 2 task 2.6).
// Relock stays registered so stale unlock markers can still be cleared.
if ( Inc\Wizard\Wizard_Unlock_Controller::is_controlled_unlock_enabled() ) {
	add_action( 'admin_post_' . Inc\Wizard\Wizard_Unlock_Controller::UNLOCK_ACTION, 'rms_wizard_handle_unlock_action' );
}
add_action( 'admin_post_' . Inc\Wizard\Wizard_Unlock_Controller::RELOCK_ACTION, 'rms_wizard_handle_relock_action' );

// Always-loaded Ads landing robots + sitemap protections (front-end + sitemaps).
add_filter( 'wp_robots', 'rms_wizard_ads_landing_wp_robots' );
add_filter( 'wp_sitemaps_posts_query_args', 'rms_wizard_exclude_ads_landings_from_wp_sitemap', 10, 2 );
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', 'rms_wizard_exclude_ads_landings_from_yoast_sitemap' );
add_filter( 'wpseo_sitemap_entry', 'rms_wizard_filter_yoast_sitemap_entry', 10, 3 );

/**
 * Force noindex for Ads landings on the landing template (second protection layer).
 *
 * Requires ALL of: is_page(), valid queried ID, template pages/landing-page.php,
 * and rms_landing_type === ads. SEO landings on the same template are untouched.
 *
 * @param array<string,bool|string> $robots Robots directives.
 *
 * @return array<string,bool|string>
 */
function rms_wizard_ads_landing_wp_robots( array $robots ): array {
	if ( ! is_page() ) {
		return $robots;
	}

	$post_id = (int) get_queried_object_id();

	if ( $post_id <= 0 ) {
		return $robots;
	}

	$template = (string) get_page_template_slug( $post_id );

	if ( 'pages/landing-page.php' !== $template ) {
		return $robots;
	}

	$landing_type = sanitize_key( (string) get_post_meta( $post_id, 'rms_landing_type', true ) );

	if ( 'ads' !== $landing_type ) {
		return $robots;
	}

	$robots['noindex']  = true;
	$robots['nofollow'] = true;

	return $robots;
}

/**
 * Exclude Ads landings from the core WordPress XML sitemap query.
 *
 * @param array<string,mixed> $args      Query args.
 * @param string              $post_type Post type.
 *
 * @return array<string,mixed>
 */
function rms_wizard_exclude_ads_landings_from_wp_sitemap( array $args, string $post_type ): array {
	if ( 'page' !== $post_type ) {
		return $args;
	}

	$meta_query = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : [];

	$meta_query[] = [
		'relation' => 'OR',
		[
			'key'     => 'rms_landing_type',
			'compare' => 'NOT EXISTS',
		],
		[
			'key'     => 'rms_landing_type',
			'value'   => 'ads',
			'compare' => '!=',
		],
	];

	$args['meta_query'] = $meta_query;

	return $args;
}

/**
 * Collect Ads landing page IDs for Yoast sitemap exclusion.
 *
 * @return array<int,int>
 */
function rms_wizard_ads_landing_page_ids(): array {
	static $ids = null;

	if ( null !== $ids ) {
		return $ids;
	}

	$query = new WP_Query(
		[
			'post_type'              => 'page',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_key'               => 'rms_landing_type',
			'meta_value'             => 'ads',
		]
	);

	$ids = array_map( 'absint', is_array( $query->posts ) ? $query->posts : [] );

	return $ids;
}

/**
 * Exclude Ads landings from Yoast sitemaps when the API is available.
 *
 * @param array<int,int> $excluded_ids Existing excluded post IDs.
 *
 * @return array<int,int>
 */
function rms_wizard_exclude_ads_landings_from_yoast_sitemap( $excluded_ids ): array {
	$excluded_ids = is_array( $excluded_ids ) ? $excluded_ids : [];
	$ads_ids      = rms_wizard_ads_landing_page_ids();

	if ( [] === $ads_ids ) {
		return $excluded_ids;
	}

	return array_values( array_unique( array_merge( array_map( 'absint', $excluded_ids ), $ads_ids ) ) );
}

/**
 * Defense-in-depth: drop Ads landings from Yoast sitemap entries.
 *
 * @param array<string,mixed>|false $url    Sitemap entry.
 * @param string                    $type   Object type.
 * @param object|null               $object Post-like object.
 *
 * @return array<string,mixed>|false
 */
function rms_wizard_filter_yoast_sitemap_entry( $url, $type, $object ) {
	if ( false === $url || ! is_object( $object ) || empty( $object->ID ) ) {
		return $url;
	}

	if ( 'post' !== $type && 'page' !== $type ) {
		// Yoast uses 'post' for pages in some versions; also check post_type.
		if ( empty( $object->post_type ) || 'page' !== $object->post_type ) {
			return $url;
		}
	}

	if ( 'ads' === sanitize_key( (string) get_post_meta( (int) $object->ID, 'rms_landing_type', true ) ) ) {
		return false;
	}

	return $url;
}

/**
 * Handle controlled unlock form posts from the Setup Wizard admin notice.
 *
 * Registered only while controlled unlock is enabled. REST still routes unlock
 * through Step_Controller → Wizard_Unlock_Controller::unlock(), which returns
 * 503 while the feature is disabled.
 *
 * @return void
 */
function rms_wizard_handle_unlock_action(): void {
	if ( ! Inc\Wizard\Wizard_Unlock_Controller::is_controlled_unlock_enabled() ) {
		wp_die( esc_html__( 'Controlled unlock is not available until landing page protection is enabled.', 'simple-rms-theme' ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to unlock the setup wizard.', 'simple-rms-theme' ) );
	}

	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ) : '';

	if ( ! Inc\Wizard\Wizard_Unlock_Controller::verify_admin_nonce( $nonce ) ) {
		wp_die( esc_html__( 'Invalid unlock request. Please try again.', 'simple-rms-theme' ) );
	}

	$result = ( new Inc\Wizard\Wizard_Unlock_Controller() )->unlock();

	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ) );
	}

	wp_safe_redirect(
		add_query_arg(
			[
				'page'                => 'rms-setup-wizard',
				'rms_wizard_unlocked' => '1',
			],
			admin_url( 'themes.php' )
		)
	);
	exit;
}

/**
 * Handle controlled re-lock form posts from the Setup Wizard admin notice.
 *
 * @return void
 */
function rms_wizard_handle_relock_action(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to re-lock the setup wizard.', 'simple-rms-theme' ) );
	}

	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_wpnonce'] ) ) : '';

	if ( ! Inc\Wizard\Wizard_Unlock_Controller::verify_admin_nonce( $nonce ) ) {
		wp_die( esc_html__( 'Invalid re-lock request. Please try again.', 'simple-rms-theme' ) );
	}

	$result = ( new Inc\Wizard\Wizard_Unlock_Controller() )->relock();

	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ) );
	}

	wp_safe_redirect(
		add_query_arg(
			[
				'page'              => 'rms-setup-wizard',
				'rms_wizard_relocked' => '1',
			],
			admin_url( 'themes.php' )
		)
	);
	exit;
}

/**
 * Render the setup wizard admin UI.
 *
 * @return void
 */
function rms_wizard_render_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access the setup wizard.', 'simple-rms-theme' ) );
	}

	$controller         = new Inc\Wizard\Step_Controller();
	$state              = $controller->get_resume_state();
	// Trust resume-state flags from Step_Controller (single source of truth).
	$locked             = ! empty( $state['locked'] );
	$completed_flag     = ! empty( $state['completed_flag'] );
	$is_unlocked        = ! empty( $state['unlocked'] );
	$has_unlock_marker  = ! empty( $state['has_unlock_marker'] );
	$force_unlocked     = ! empty( $state['force_unlocked'] );
	$unlock_ui_enabled  = ! empty( $state['controlled_unlock_ui'] );
	$steps              = [
		'dependencies'         => __( 'Dependencies', 'simple-rms-theme' ),
		'acf-import'           => __( 'ACF Import', 'simple-rms-theme' ),
		'client-data'          => __( 'Client Data', 'simple-rms-theme' ),
		'generate-pages'       => __( 'Generate Pages', 'simple-rms-theme' ),
		'menu-setup'           => __( 'Menu Setup', 'simple-rms-theme' ),
		'ia-generation'        => __( 'IA Generation', 'simple-rms-theme' ),
		'home-page-builder'    => __( 'Home Page Builder', 'simple-rms-theme' ),
		'landing-page-builder' => __( 'Landing Page Builder', 'simple-rms-theme' ),
	];
	$step_slugs = array_keys( $steps );
	$descriptions = [
		'dependencies'         => __( 'Check and install the required WordPress plugins before continuing.', 'simple-rms-theme' ),
		'acf-import'           => __( 'Import ACF JSON field groups from the theme acf-json directory.', 'simple-rms-theme' ),
		'client-data'          => __( 'Save contractor business information into the theme options.', 'simple-rms-theme' ),
		'generate-pages'       => __( 'Add the site pages to create, then assign the Home and optional Blog roles.', 'simple-rms-theme' ),
		'menu-setup'           => __( 'Choose generated pages for the primary and mobile menus.', 'simple-rms-theme' ),
		'ia-generation'        => __( 'Configure the AI provider, model, and encrypted credentials for later content generation.', 'simple-rms-theme' ),
		'home-page-builder'    => __( 'Choose Home page sections and build them from the saved client data.', 'simple-rms-theme' ),
		'landing-page-builder' => __( 'Create SEO and Ads landing pages with keywords, reusable sections, and noindex controls.', 'simple-rms-theme' ),
	];
	?>
	<div
		class="wrap rms-setup-wizard is-hydrating"
		data-rms-wizard
		aria-busy="true"
		data-rms-wizard-root="<?php echo esc_url( rest_url( \Inc\Wizard\Rest_Controller::NAMESPACE . '/' ) ); ?>"
		data-rms-wizard-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
	>
		<h1><?php esc_html_e( 'Setup Wizard', 'simple-rms-theme' ); ?></h1>
		<?php if ( $unlock_ui_enabled && ! empty( $_GET['rms_wizard_unlocked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Setup wizard unlocked for editing. Completion state was preserved.', 'simple-rms-theme' ); ?></p>
			</div>
		<?php endif; ?>
		<?php /* Relock success can happen for stale-marker cleanup while unlock UI is still disabled. */ ?>
		<?php if ( ! empty( $_GET['rms_wizard_relocked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Setup wizard re-locked. The site is read-only again.', 'simple-rms-theme' ); ?></p>
			</div>
		<?php endif; ?>
		<?php if ( $force_unlocked ) : ?>
			<div class="notice notice-info inline rms-wizard-controlled-unlock" data-wizard-controlled-unlock="force">
				<p>
					<?php esc_html_e( 'The setup wizard is force-unlocked via RMS_WIZARD_FORCE. Completion state is ignored for editing in this environment.', 'simple-rms-theme' ); ?>
				</p>
			</div>
		<?php elseif ( $completed_flag && $has_unlock_marker ) : ?>
			<?php /* Relock clears markers even when controlled unlock is disabled (stale cleanup). */ ?>
			<div class="notice notice-warning inline rms-wizard-controlled-unlock" data-wizard-controlled-unlock="relock">
				<p>
					<?php
					echo esc_html(
						$is_unlocked
							? __( 'The setup wizard is unlocked for editing. Completion state is preserved. Re-lock when you are finished to restore read-only mode.', 'simple-rms-theme' )
							: __( 'A leftover unlock marker was found while controlled unlock is disabled. Re-lock to clear it and keep the wizard read-only.', 'simple-rms-theme' )
					);
					?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rms-wizard-controlled-unlock__form">
					<input type="hidden" name="action" value="<?php echo esc_attr( Inc\Wizard\Wizard_Unlock_Controller::RELOCK_ACTION ); ?>">
					<?php wp_nonce_field( Inc\Wizard\Wizard_Unlock_Controller::NONCE_ACTION ); ?>
					<?php submit_button( __( 'Re-lock wizard', 'simple-rms-theme' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>
		<?php elseif ( $unlock_ui_enabled && ( $locked || ( $completed_flag && ! $is_unlocked ) ) ) : ?>
			<?php /* Unlock form is gated; admin-post handler is registered only when enabled. */ ?>
			<div class="notice notice-success inline rms-wizard-controlled-unlock" data-wizard-controlled-unlock="unlock">
				<p>
					<?php esc_html_e( 'The setup wizard has already been completed and is read-only. Unlock it to make non-destructive edits without resetting completion state.', 'simple-rms-theme' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rms-wizard-controlled-unlock__form">
					<input type="hidden" name="action" value="<?php echo esc_attr( Inc\Wizard\Wizard_Unlock_Controller::UNLOCK_ACTION ); ?>">
					<?php wp_nonce_field( Inc\Wizard\Wizard_Unlock_Controller::NONCE_ACTION ); ?>
					<?php submit_button( __( 'Unlock wizard for editing', 'simple-rms-theme' ), 'primary', 'submit', false ); ?>
				</form>
				<p class="description">
					<?php esc_html_e( 'Developers may also define RMS_WIZARD_FORCE as true to bypass the lock in local environments.', 'simple-rms-theme' ); ?>
				</p>
			</div>
		<?php elseif ( $locked || ( $completed_flag && ! $is_unlocked ) ) : ?>
			<div class="notice notice-success inline rms-wizard-controlled-unlock" data-wizard-controlled-unlock="readonly">
				<p>
					<?php esc_html_e( 'The setup wizard has already been completed and is read-only.', 'simple-rms-theme' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Developers may define RMS_WIZARD_FORCE as true to bypass the lock in local environments.', 'simple-rms-theme' ); ?>
				</p>
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

				<div class="rms-wizard-sidebar-skeleton" aria-hidden="true">
					<div class="rms-wizard-sidebar-skeleton__title"></div>
					<div class="rms-wizard-sidebar-skeleton__track"></div>
					<?php for ( $skeleton_index = 0; $skeleton_index < count( $steps ); $skeleton_index++ ) : ?>
						<div class="rms-wizard-sidebar-skeleton__step">
							<span></span>
							<strong></strong>
						</div>
					<?php endfor; ?>
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

				<div class="rms-wizard-hydrating-overlay" data-wizard-hydrating-overlay aria-hidden="true">
					<div class="rms-wizard-skeleton">
						<div class="rms-wizard-skeleton__header"></div>
						<div class="rms-wizard-skeleton__line"></div>
						<div class="rms-wizard-skeleton__line rms-wizard-skeleton__line--short"></div>
						<div class="rms-wizard-skeleton__line"></div>
						<div class="rms-wizard-skeleton__actions"></div>
					</div>
					<p class="rms-wizard-hydrating-overlay__text">Loading wizard state...</p>
				</div>

				<?php foreach ( $steps as $slug => $label ) : ?>
					<?php
					$next_step_index = array_search( $slug, $step_slugs, true ) + 1;
					$next_step_slug  = $step_slugs[ $next_step_index ] ?? '';
					?>
					<section class="rms-wizard-step-panel" data-wizard-step-panel="<?php echo esc_attr( $slug ); ?>" <?php echo (string) $state['current_step'] === $slug ? '' : 'hidden'; ?>>
						<header class="rms-wizard-step-panel__header">
							<h2><?php echo esc_html( $label ); ?></h2>
							<p><?php echo esc_html( $descriptions[ $slug ] ); ?></p>
						</header>

						<?php if ( 'client-data' === $slug ) : ?>
							<?php rms_wizard_render_client_data_form(); ?>
						<?php elseif ( 'generate-pages' === $slug ) : ?>
							<?php rms_wizard_render_generate_pages_form(); ?>
						<?php elseif ( 'menu-setup' === $slug ) : ?>
							<?php rms_wizard_render_menu_setup_form(); ?>
						<?php elseif ( 'ia-generation' === $slug ) : ?>
							<?php rms_wizard_render_ia_generation_form(); ?>
						<?php elseif ( 'home-page-builder' === $slug ) : ?>
							<?php rms_wizard_render_home_page_builder_form(); ?>
						<?php elseif ( 'landing-page-builder' === $slug ) : ?>
							<?php rms_wizard_render_landing_page_builder_form(); ?>
						<?php endif; ?>

						<div class="rms-wizard-actions rms-wizard-step-actions">
							<button type="button" class="button button-primary" data-wizard-run-step="<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Run step', 'simple-rms-theme' ); ?></button>
							<button type="button" class="button" data-wizard-retry-step="<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Retry', 'simple-rms-theme' ); ?></button>
							<?php if ( '' !== $next_step_slug ) : ?>
								<button type="button" class="button button-secondary rms-wizard-next-step" data-wizard-next-step="<?php echo esc_attr( $slug ); ?>" data-wizard-next-target="<?php echo esc_attr( $next_step_slug ); ?>" disabled><?php esc_html_e( 'Next step', 'simple-rms-theme' ); ?></button>
							<?php endif; ?>
							<span class="rms-wizard-action-status" data-wizard-action-status></span>
						</div>

						<pre class="rms-wizard-step-result" data-wizard-step-result hidden></pre>
					</section>
				<?php endforeach; ?>

				<div class="rms-wizard-actions rms-wizard-global-actions">
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

		<?php rms_wizard_render_confirmation_modal(); ?>
	</div>
	<?php
}

/**
 * Render the guided Generate Pages step form.
 *
 * @return void
 */
function rms_wizard_render_generate_pages_form(): void {
	$pages = rms_wizard_page_generation_choices();
	$common_pages = [];

	foreach ( $pages as $slug => $page ) {
		$common_pages[] = [
			'slug'        => $slug,
			'title'       => $page['title'],
			'description' => $page['description'],
			'role'        => 'home' === $slug || 'blog' === $slug ? $slug : '',
			'type'        => $slug,
		];
	}
	?>
	<form class="rms-wizard-fields rms-wizard-guided-form" data-wizard-generate-pages-form>
		<div class="rms-wizard-guided-panel">
			<p class="rms-wizard-fields__intro">
				<?php esc_html_e( 'Create the pages this wizard should generate. Use the common pages shortcut as a starting point, or add custom pages one at a time. After pages are generated, only these pages remain in the active page set.', 'simple-rms-theme' ); ?>
			</p>

			<div class="rms-wizard-page-quick-actions">
				<button type="button" class="button" data-wizard-add-common-pages><?php esc_html_e( 'Add common pages', 'simple-rms-theme' ); ?></button>
				<button type="button" class="button button-secondary" data-wizard-add-page><?php esc_html_e( 'Add Page', 'simple-rms-theme' ); ?></button>
				<p class="rms-wizard-field__instructions"><?php esc_html_e( 'Common pages are only a shortcut. You can rename, remove, or add any page before running the step.', 'simple-rms-theme' ); ?></p>
			</div>

			<script type="application/json" data-wizard-common-pages><?php echo wp_json_encode( $common_pages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>

			<div class="rms-wizard-page-builder" data-wizard-custom-pages>
				<div class="rms-wizard-page-builder__header" aria-hidden="true">
					<span><?php esc_html_e( 'Page title', 'simple-rms-theme' ); ?></span>
					<span><?php esc_html_e( 'Slug', 'simple-rms-theme' ); ?></span>
					<span><?php esc_html_e( 'Role', 'simple-rms-theme' ); ?></span>
					<span><?php esc_html_e( 'Action', 'simple-rms-theme' ); ?></span>
				</div>
				<div class="rms-wizard-page-rows" role="list" data-wizard-page-rows></div>
				<p class="rms-wizard-page-builder__empty" data-wizard-page-empty><?php esc_html_e( 'No pages added yet. Add a custom page or use the common pages shortcut.', 'simple-rms-theme' ); ?></p>
			</div>

			<label class="rms-wizard-no-blog-option">
				<input type="radio" name="blog_slug" value="" data-wizard-page-no-blog checked>
				<span><?php esc_html_e( 'Do not assign a Blog page', 'simple-rms-theme' ); ?></span>
			</label>

			<template data-wizard-page-row-template>
				<article class="rms-wizard-page-row" role="listitem" data-wizard-page-row data-wizard-page-type="">
					<div class="rms-wizard-field rms-wizard-page-row__title">
						<label for="rms-wizard-page-title-__INDEX__"><?php esc_html_e( 'Page title', 'simple-rms-theme' ); ?></label>
						<input id="rms-wizard-page-title-__INDEX__" type="text" name="pages[__INDEX__][title]" data-wizard-page-title placeholder="<?php esc_attr_e( 'Services', 'simple-rms-theme' ); ?>">
					</div>
					<div class="rms-wizard-field rms-wizard-page-row__slug">
						<label for="rms-wizard-page-slug-__INDEX__"><?php esc_html_e( 'Slug', 'simple-rms-theme' ); ?></label>
						<input id="rms-wizard-page-slug-__INDEX__" type="text" name="pages[__INDEX__][slug]" data-wizard-page-slug data-wizard-slug-auto="1" placeholder="<?php esc_attr_e( 'services', 'simple-rms-theme' ); ?>">
					</div>
					<div class="rms-wizard-page-row__roles" aria-label="<?php esc_attr_e( 'Page roles', 'simple-rms-theme' ); ?>">
						<label>
							<input type="radio" name="home_slug" value="" data-wizard-page-home>
							<span><?php esc_html_e( 'Home', 'simple-rms-theme' ); ?></span>
						</label>
						<label>
							<input type="radio" name="blog_slug" value="" data-wizard-page-blog>
							<span><?php esc_html_e( 'Blog', 'simple-rms-theme' ); ?></span>
						</label>
					</div>
					<button type="button" class="button-link-delete rms-wizard-page-row__remove" data-wizard-remove-page><?php esc_html_e( 'Remove', 'simple-rms-theme' ); ?></button>
				</article>
			</template>
		</div>

		<?php
		rms_wizard_render_destructive_confirmation(
			'generate-pages',
			__( 'Existing pages not in your selection will be permanently deleted. This cannot be undone.', 'simple-rms-theme' ),
			__( 'I understand the wizard will delete or replace existing pages that are not selected here.', 'simple-rms-theme' )
		);
		?>
	</form>
	<?php
}

/**
 * Render the guided Menu Setup step form.
 *
 * @return void
 */
function rms_wizard_render_menu_setup_form(): void {
	?>
	<form class="rms-wizard-fields rms-wizard-guided-form" data-wizard-menu-form>
		<div class="rms-wizard-guided-panel">
			<p class="rms-wizard-fields__intro">
				<?php esc_html_e( 'Menus are built from Generate Pages results plus menu-eligible SEO landings. Ads landings are excluded automatically. Refresh the state after generating pages or landings if this list is empty.', 'simple-rms-theme' ); ?>
			</p>

			<div class="notice notice-warning inline rms-wizard-menu-empty" data-wizard-menu-empty hidden>
				<p><?php esc_html_e( 'No pages found. Please complete the Generate Pages step first', 'simple-rms-theme' ); ?></p>
			</div>

			<div class="rms-wizard-menu-builder" data-wizard-menu-builder>
				<fieldset class="rms-wizard-menu-column">
					<legend><?php esc_html_e( 'Primary menu', 'simple-rms-theme' ); ?></legend>
					<p><?php esc_html_e( 'Select at least one generated page for the primary navigation.', 'simple-rms-theme' ); ?></p>
					<div class="rms-wizard-menu-list" data-wizard-menu-list="primary"></div>
				</fieldset>

				<fieldset class="rms-wizard-menu-column">
					<legend><?php esc_html_e( 'Mobile menu', 'simple-rms-theme' ); ?></legend>
					<p><?php esc_html_e( 'Leave empty to reuse the primary menu for the mobile location.', 'simple-rms-theme' ); ?></p>
					<div class="rms-wizard-menu-list" data-wizard-menu-list="mobile"></div>
				</fieldset>
			</div>
		</div>

		<?php
		rms_wizard_render_destructive_confirmation(
			'menu-setup',
			__( 'Existing menus and location assignments will be removed and replaced. This cannot be undone.', 'simple-rms-theme' ),
			__( 'I understand the wizard will delete existing menus and replace theme location assignments.', 'simple-rms-theme' )
		);
		?>
	</form>
	<?php
}

/**
 * Render the IA Generation configuration form.
 *
 * @return void
 */
function rms_wizard_render_ia_generation_form(): void {
	$ai_providers        = Inc\Wizard\AI_Provider_Registry::list_providers();
	$default_ai_provider = Inc\Wizard\AI_Provider_Registry::default_provider();
	?>
	<form class="rms-wizard-fields rms-wizard-guided-form" data-wizard-ia-generation-form>
		<p class="rms-wizard-fields__intro">
			<?php esc_html_e( 'Save the provider, model, and encrypted credentials the Home Page Builder will use for section copy.', 'simple-rms-theme' ); ?>
		</p>

		<div class="rms-wizard-field">
			<label for="rms-wizard-ai-provider"><?php esc_html_e( 'Provider', 'simple-rms-theme' ); ?></label>
			<select id="rms-wizard-ai-provider" name="provider" data-wizard-ai-provider>
				<?php foreach ( $ai_providers as $provider ) : ?>
					<option value="<?php echo esc_attr( $provider['slug'] ); ?>" <?php selected( $default_ai_provider, $provider['slug'] ); ?>><?php echo esc_html( $provider['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="rms-wizard-field">
			<label for="rms-wizard-ai-key"><?php esc_html_e( 'API key', 'simple-rms-theme' ); ?></label>
			<input id="rms-wizard-ai-key" type="password" name="api_key" autocomplete="off" placeholder="<?php esc_attr_e( 'Leave blank to use the saved encrypted key', 'simple-rms-theme' ); ?>">
			<p class="rms-wizard-field__instructions" data-wizard-ai-credential-status><?php echo esc_html( Inc\Wizard\AI_Credential_Store::mask_status( $default_ai_provider ) ); ?></p>
		</div>

		<div class="rms-wizard-field">
			<label for="rms-wizard-ai-model"><?php esc_html_e( 'Model', 'simple-rms-theme' ); ?></label>
			<div class="rms-wizard-ai-model-picker">
				<select id="rms-wizard-ai-model" name="model" data-wizard-ai-model>
					<option value=""><?php esc_html_e( 'Load models from the selected provider', 'simple-rms-theme' ); ?></option>
				</select>
				<button type="button" class="button" data-wizard-ai-load-models><?php esc_html_e( 'Test / Load models', 'simple-rms-theme' ); ?></button>
			</div>
			<p class="rms-wizard-field__instructions" data-wizard-ai-model-status><?php esc_html_e( 'The API key is encrypted after a successful provider check.', 'simple-rms-theme' ); ?></p>
		</div>

		<div class="rms-wizard-field">
			<label for="rms-wizard-ai-model-manual"><?php esc_html_e( 'Manual model name', 'simple-rms-theme' ); ?></label>
			<input id="rms-wizard-ai-model-manual" type="text" name="model_manual" data-wizard-ai-model-manual placeholder="<?php esc_attr_e( 'Enter a model manually if loading models fails', 'simple-rms-theme' ); ?>">
			<p class="rms-wizard-field__instructions"><?php esc_html_e( 'This field is used only when no model is selected from the loaded list.', 'simple-rms-theme' ); ?></p>
		</div>
	</form>
	<?php
}

/**
 * Render the guided Home Page Builder form.
 *
 * @return void
 */
function rms_wizard_render_home_page_builder_form(): void {
	$sections        = rms_wizard_home_section_choices();
	$common_sections = rms_wizard_home_common_section_choices();
	$section_options = array_values(
		array_map(
			static function ( array $section ): array {
				$layout = (string) $section['name'];

				return [
					'layout'             => $layout,
					'label'              => $section['label'],
					'description'        => $section['description'],
					'has_repeaters'      => rms_wizard_home_section_has_repeaters( $section ),
					'has_fillable_fields'=> rms_wizard_home_section_has_fillable_fields( $layout ),
					'default_item_count' => rms_wizard_home_section_default_item_count( $layout ),
				];
			},
			$sections
		)
	);
	$common_options  = array_values(
		array_map(
			static function ( array $section ): array {
				$layout = (string) $section['name'];

				return [
					'layout'             => $layout,
					'label'              => $section['label'],
					'description'        => $section['description'],
					'has_repeaters'      => rms_wizard_home_section_has_repeaters( $section ),
					'has_fillable_fields'=> rms_wizard_home_section_has_fillable_fields( $layout ),
					'default_item_count' => rms_wizard_home_section_default_item_count( $layout ),
				];
			},
			$common_sections
		)
	);
	?>
	<form class="rms-wizard-fields rms-wizard-guided-form" data-wizard-home-page-builder-form>
		<div class="rms-wizard-guided-panel">
			<p class="rms-wizard-fields__intro">
				<?php esc_html_e( 'Add Home page sections from the available ACF Flexible Content layouts. The wizard will use saved Client Data and the IA Generation configuration to draft the section copy.', 'simple-rms-theme' ); ?>
			</p>

			<fieldset class="rms-wizard-home-seo-targeting">
				<legend><?php esc_html_e( 'Homepage SEO targeting', 'simple-rms-theme' ); ?></legend>
				<label class="rms-wizard-home-seo-targeting__toggle" for="rms-wizard-home-seo-enabled">
					<input id="rms-wizard-home-seo-enabled" type="checkbox" value="1" data-wizard-home-seo-enabled aria-controls="rms-wizard-home-seo-fields" aria-expanded="false">
					<span><?php esc_html_e( 'Target this homepage for a search query', 'simple-rms-theme' ); ?></span>
				</label>
				<p class="rms-wizard-field__instructions" id="rms-wizard-home-seo-help">
					<?php esc_html_e( 'Optional. Keywords are editorial intent for the Hero and SEO Content sections only. They do not write Yoast fields, change rankings, or authorize invented services, locations, credentials, guarantees, or statistics.', 'simple-rms-theme' ); ?>
				</p>
				<div id="rms-wizard-home-seo-fields" class="rms-wizard-home-seo-targeting__fields" data-wizard-home-seo-fields hidden>
					<div class="rms-wizard-field">
						<label for="rms-wizard-home-seo-primary"><?php esc_html_e( 'Primary keyword', 'simple-rms-theme' ); ?></label>
						<input id="rms-wizard-home-seo-primary" type="text" name="seo_targeting[primary_keyword]" data-wizard-home-seo-primary disabled aria-required="false" aria-invalid="false" aria-describedby="rms-wizard-home-seo-help" placeholder="<?php esc_attr_e( 'deck builder near me', 'simple-rms-theme' ); ?>">
						<p id="rms-wizard-home-seo-primary-error" class="rms-wizard-home-seo-targeting__error" data-wizard-home-seo-primary-error hidden role="alert"></p>
					</div>
					<div class="rms-wizard-field">
						<label for="rms-wizard-home-seo-secondary"><?php esc_html_e( 'Secondary keywords (comma-separated, optional, max 10)', 'simple-rms-theme' ); ?></label>
						<input id="rms-wizard-home-seo-secondary" type="text" name="seo_targeting[secondary_keywords]" data-wizard-home-seo-secondary disabled aria-describedby="rms-wizard-home-seo-help rms-wizard-home-seo-secondary-notice" placeholder="<?php esc_attr_e( 'custom decks, composite decking', 'simple-rms-theme' ); ?>">
						<p id="rms-wizard-home-seo-secondary-notice" class="rms-wizard-home-seo-targeting__notice" data-wizard-home-seo-secondary-notice hidden role="status" aria-live="polite"></p>
					</div>
				</div>
			</fieldset>

			<div class="notice notice-warning inline rms-wizard-home-harness-warning" data-wizard-home-harness-warning hidden>
				<p><?php esc_html_e( 'Home Page Builder requires saved Client Data before AI content can be generated.', 'simple-rms-theme' ); ?></p>
			</div>

			<script type="application/json" data-wizard-home-sections><?php echo wp_json_encode( $section_options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
			<script type="application/json" data-wizard-common-home-sections><?php echo wp_json_encode( $common_options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>

			<div class="rms-wizard-home-section-picker">
				<div class="rms-wizard-field">
					<label for="rms-wizard-home-section-layout"><?php esc_html_e( 'Section layout', 'simple-rms-theme' ); ?></label>
					<select id="rms-wizard-home-section-layout" data-wizard-home-section-select>
						<?php foreach ( $sections as $layout => $section ) : ?>
						<option value="<?php echo esc_attr( $layout ); ?>" data-label="<?php echo esc_attr( $section['label'] ); ?>" data-description="<?php echo esc_attr( $section['description'] ); ?>" data-has-repeaters="<?php echo rms_wizard_home_section_has_repeaters( $section ) ? '1' : '0'; ?>" data-has-fillable-fields="<?php echo rms_wizard_home_section_has_fillable_fields( $layout ) ? '1' : '0'; ?>" data-default-item-count="<?php echo esc_attr( (string) rms_wizard_home_section_default_item_count( (string) $layout ) ); ?>">
							<?php echo esc_html( sprintf( '%1$s (%2$s)', $section['label'], $layout ) ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="button" class="button" data-wizard-add-home-section><?php esc_html_e( 'Add Section', 'simple-rms-theme' ); ?></button>
			</div>

			<?php if ( [] !== $common_sections ) : ?>
				<div class="rms-wizard-home-section-quick-actions">
					<button type="button" class="button" data-wizard-add-common-home-sections><?php esc_html_e( 'Add common Home sections', 'simple-rms-theme' ); ?></button>
					<p class="rms-wizard-field__instructions"><?php esc_html_e( 'Quick-start sections are templates only. You can still add any layout from the dropdown above.', 'simple-rms-theme' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="rms-wizard-home-section-builder" data-wizard-home-section-builder>
				<div class="rms-wizard-home-section-builder__header" aria-hidden="true">
					<span><?php esc_html_e( 'Section', 'simple-rms-theme' ); ?></span>
					<span><?php esc_html_e( 'Layout key', 'simple-rms-theme' ); ?></span>
					<span><?php esc_html_e( 'Items', 'simple-rms-theme' ); ?></span>
					<span><?php esc_html_e( 'Action', 'simple-rms-theme' ); ?></span>
				</div>
				<div class="rms-wizard-home-section-rows" role="list" data-wizard-home-section-rows></div>
				<p class="rms-wizard-home-section-builder__empty" data-wizard-home-section-empty><?php esc_html_e( 'No sections added yet. Choose a layout and add it to the Home page.', 'simple-rms-theme' ); ?></p>
			</div>

			<template data-wizard-home-section-row-template>
				<article class="rms-wizard-home-section-row" role="listitem" data-wizard-home-section-row>
					<input type="hidden" name="sections[__INDEX__][layout]" value="" data-wizard-home-section-value>
					<div class="rms-wizard-home-section-row__label">
						<strong data-wizard-home-section-label></strong>
						<small data-wizard-home-section-description></small>
						<small class="rms-wizard-home-section-row__no-ai-note" data-wizard-home-section-no-ai hidden><?php esc_html_e( 'No AI copy is generated for this layout. Add real media, testimonials, or project data later.', 'simple-rms-theme' ); ?></small>
					</div>
					<code data-wizard-home-section-key></code>
					<div class="rms-wizard-field rms-wizard-home-section-row__count" data-wizard-home-section-count-wrap hidden>
						<label for="rms-wizard-home-section-count-__INDEX__"><?php esc_html_e( 'Item count', 'simple-rms-theme' ); ?></label>
						<input id="rms-wizard-home-section-count-__INDEX__" class="small-text" type="number" min="1" max="12" step="1" name="sections[__INDEX__][item_count]" value="1" data-wizard-home-section-item-count>
						<p class="rms-wizard-field__instructions"><?php esc_html_e( 'Controls repeater items for this section.', 'simple-rms-theme' ); ?></p>
					</div>
					<button type="button" class="button-link-delete rms-wizard-home-section-row__remove" data-wizard-remove-home-section><?php esc_html_e( 'Remove', 'simple-rms-theme' ); ?></button>
				</article>
			</template>
		</div>
	</form>
	<?php
}

/**
 * Render the guided Landing Page Builder form.
 *
 * @return void
 */
function rms_wizard_render_landing_page_builder_form(): void {
	$sections        = rms_wizard_home_section_choices();
	$default_layouts = [
		'hero',
		'seo-content',
		'vision-mission-v1',
		'badges',
		'portfolio-v1',
		'seo-content',
		'testimonials-v1',
		'seo-content',
	];
	$section_options = array_values(
		array_map(
			static function ( array $section ) use ( $default_layouts ): array {
				$layout = (string) $section['name'];

				return [
					'layout'              => $layout,
					'label'               => $section['label'],
					'description'         => $section['description'],
					'is_keyword_layout'   => in_array( $layout, [ 'hero', 'seo-content' ], true ),
					'is_default'          => in_array( $layout, $default_layouts, true ),
					'default_item_count'  => rms_wizard_home_section_default_item_count( $layout ),
					'has_fillable_fields' => rms_wizard_home_section_has_fillable_fields( $layout ),
				];
			},
			$sections
		)
	);
	?>
	<form class="rms-wizard-fields rms-wizard-guided-form" data-wizard-landing-page-builder-form>
		<div class="rms-wizard-guided-panel">
			<p class="rms-wizard-fields__intro">
				<?php esc_html_e( 'Create one or more SEO or Ads landing pages. Only Hero and SEO Content receive keywords; reusable sections stay neutral and pull from the canonical store. Ads landings are noindex and never auto-added to menus.', 'simple-rms-theme' ); ?>
			</p>

			<div class="rms-wizard-landing-run-progress" data-wizard-landing-run-progress hidden>
				<p class="rms-wizard-landing-run-progress__text" data-wizard-landing-run-progress-text aria-live="polite"></p>
				<p class="rms-wizard-landing-run-progress__current" data-wizard-landing-run-current-title aria-live="polite"></p>
				<button type="button" class="button button-primary" data-wizard-landing-resume><?php esc_html_e( 'Resume run', 'simple-rms-theme' ); ?></button>
			</div>

			<label class="rms-wizard-landing-skip-all">
				<input type="checkbox" name="skip_all" value="1" data-wizard-landing-skip-all>
				<span><?php esc_html_e( 'Skip landing pages for now (complete this step with zero landings)', 'simple-rms-theme' ); ?></span>
			</label>

			<script type="application/json" data-wizard-landing-sections><?php echo wp_json_encode( $section_options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
			<script type="application/json" data-wizard-landing-default-layouts><?php echo wp_json_encode( $default_layouts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>

			<div class="rms-wizard-landing-toolbar">
				<button type="button" class="button button-secondary" data-wizard-add-landing><?php esc_html_e( 'Add landing', 'simple-rms-theme' ); ?></button>
				<p class="rms-wizard-field__instructions"><?php esc_html_e( 'Duplicate a row to start a new landing with a fresh identity. Existing landings hydrate from wizard state on load.', 'simple-rms-theme' ); ?></p>
			</div>

			<div class="rms-wizard-landing-builder" data-wizard-landing-builder>
				<div class="rms-wizard-landing-rows" role="list" data-wizard-landing-rows></div>
				<p class="rms-wizard-landing-builder__empty" data-wizard-landing-empty><?php esc_html_e( 'No landings added yet. Add a landing or skip this step.', 'simple-rms-theme' ); ?></p>
			</div>

			<div class="rms-wizard-landing-replace-panel" data-wizard-landing-replace-panel>
				<strong><?php esc_html_e( 'Replace canonical reusable sections', 'simple-rms-theme' ); ?></strong>
				<p class="rms-wizard-field__instructions"><?php esc_html_e( 'Optional. Mark reusable layouts to regenerate and overwrite the shared canonical store. Requires confirmation when running the step.', 'simple-rms-theme' ); ?></p>
				<div class="rms-wizard-landing-replace-list" data-wizard-landing-replace-list></div>
			</div>

			<template data-wizard-landing-row-template>
				<article class="rms-wizard-landing-row is-collapsed" role="listitem" data-wizard-landing-row>
					<input type="hidden" name="landings[__INDEX__][id]" value="" data-wizard-landing-id>
					<input type="hidden" name="landings[__INDEX__][landing_key]" value="" data-wizard-landing-key>
					<header class="rms-wizard-landing-row__header">
						<button type="button" class="rms-wizard-landing-row__toggle" id="rms-wizard-landing-toggle-__INDEX__" data-wizard-landing-toggle aria-expanded="false" aria-controls="rms-wizard-landing-panel-__INDEX__">
							<span class="rms-wizard-landing-row__chevron" aria-hidden="true"></span>
							<span class="rms-wizard-landing-row__summary">
								<span class="rms-wizard-landing-row__title" data-wizard-landing-heading><?php esc_html_e( 'Landing 1', 'simple-rms-theme' ); ?></span>
								<span class="rms-wizard-landing-row__type" data-wizard-landing-type-summary><?php esc_html_e( 'SEO', 'simple-rms-theme' ); ?></span>
								<span class="rms-wizard-landing-row__keyword" data-wizard-landing-keyword-summary><?php esc_html_e( 'No primary keyword', 'simple-rms-theme' ); ?></span>
							</span>
						</button>
						<div class="rms-wizard-landing-row__actions">
							<button type="button" class="button-link" data-wizard-duplicate-landing><?php esc_html_e( 'Duplicate', 'simple-rms-theme' ); ?></button>
							<button type="button" class="button-link-delete" data-wizard-remove-landing><?php esc_html_e( 'Remove', 'simple-rms-theme' ); ?></button>
						</div>
					</header>
					<div id="rms-wizard-landing-panel-__INDEX__" class="rms-wizard-landing-row__panel" data-wizard-landing-panel hidden role="region" aria-labelledby="rms-wizard-landing-toggle-__INDEX__">
						<div class="rms-wizard-landing-row__grid">
							<div class="rms-wizard-field">
								<label for="rms-wizard-landing-title-__INDEX__"><?php esc_html_e( 'Title', 'simple-rms-theme' ); ?></label>
								<input id="rms-wizard-landing-title-__INDEX__" type="text" name="landings[__INDEX__][title]" data-wizard-landing-title placeholder="<?php esc_attr_e( 'Kitchen Remodel Landing', 'simple-rms-theme' ); ?>">
							</div>
							<div class="rms-wizard-field">
								<label for="rms-wizard-landing-slug-__INDEX__"><?php esc_html_e( 'Slug', 'simple-rms-theme' ); ?></label>
								<input id="rms-wizard-landing-slug-__INDEX__" type="text" name="landings[__INDEX__][slug]" data-wizard-landing-slug data-wizard-slug-auto="1" placeholder="<?php esc_attr_e( 'kitchen-remodel', 'simple-rms-theme' ); ?>">
							</div>
							<div class="rms-wizard-field">
								<label for="rms-wizard-landing-type-__INDEX__"><?php esc_html_e( 'Landing type', 'simple-rms-theme' ); ?></label>
								<select id="rms-wizard-landing-type-__INDEX__" name="landings[__INDEX__][landing_type]" data-wizard-landing-type>
									<option value="seo"><?php esc_html_e( 'SEO (indexable, menu-eligible)', 'simple-rms-theme' ); ?></option>
									<option value="ads"><?php esc_html_e( 'Ads (noindex, orphan)', 'simple-rms-theme' ); ?></option>
								</select>
							</div>
							<div class="rms-wizard-field">
								<label for="rms-wizard-landing-keyword-__INDEX__"><?php esc_html_e( 'Primary keyword', 'simple-rms-theme' ); ?></label>
								<input id="rms-wizard-landing-keyword-__INDEX__" type="text" name="landings[__INDEX__][primary_keyword]" data-wizard-landing-primary-keyword placeholder="<?php esc_attr_e( 'kitchen remodel near me', 'simple-rms-theme' ); ?>">
							</div>
							<div class="rms-wizard-field rms-wizard-landing-row__subkeywords">
								<label for="rms-wizard-landing-subkeywords-__INDEX__"><?php esc_html_e( 'Subkeywords (comma-separated, max 10)', 'simple-rms-theme' ); ?></label>
								<input id="rms-wizard-landing-subkeywords-__INDEX__" type="text" name="landings[__INDEX__][subkeywords]" data-wizard-landing-subkeywords placeholder="<?php esc_attr_e( 'cabinet refinishing, countertop install', 'simple-rms-theme' ); ?>">
							</div>
						</div>
						<div class="rms-wizard-landing-sections" data-wizard-landing-sections-list>
							<div class="rms-wizard-landing-sections__header">
								<strong><?php esc_html_e( 'Sections', 'simple-rms-theme' ); ?></strong>
								<button type="button" class="button-link" data-wizard-add-landing-section><?php esc_html_e( 'Add section', 'simple-rms-theme' ); ?></button>
							</div>
							<div class="rms-wizard-landing-section-rows" data-wizard-landing-section-rows></div>
						</div>
					</div>
				</article>
			</template>

			<template data-wizard-landing-section-row-template>
				<div class="rms-wizard-landing-section-row" data-wizard-landing-section-row>
					<input type="hidden" name="landings[__LINDEX__][sections][__SINDEX__][item_count]" value="" data-wizard-landing-section-item-count>
					<select name="landings[__LINDEX__][sections][__SINDEX__][layout]" data-wizard-landing-section-layout></select>
					<label class="rms-wizard-landing-section-override">
						<input type="checkbox" name="landings[__LINDEX__][sections][__SINDEX__][override_canonical]" value="1" data-wizard-landing-section-override>
						<span><?php esc_html_e( 'Override canonical (neutral regen, does not write store)', 'simple-rms-theme' ); ?></span>
					</label>
					<button type="button" class="button-link-delete" data-wizard-remove-landing-section><?php esc_html_e( 'Remove', 'simple-rms-theme' ); ?></button>
				</div>
			</template>
		</div>
	</form>
	<?php
}

/**
 * Render a destructive action warning with an explicit checkbox.
 *
 * @param string $step           Step slug.
 * @param string $message        Warning message.
 * @param string $checkbox_label Confirmation checkbox label.
 *
 * @return void
 */
function rms_wizard_render_destructive_confirmation( string $step, string $message, string $checkbox_label ): void {
	$field_id = 'rms-wizard-confirm-' . rms_wizard_field_id_token( $step );
	?>
	<div class="rms-wizard-destructive-warning" data-wizard-destructive-warning="<?php echo esc_attr( $step ); ?>">
		<strong><?php esc_html_e( 'Destructive action', 'simple-rms-theme' ); ?></strong>
		<p><?php echo esc_html( $message ); ?></p>
		<label class="rms-wizard-confirm-checkbox" for="<?php echo esc_attr( $field_id ); ?>">
			<input id="<?php echo esc_attr( $field_id ); ?>" type="checkbox" name="confirm_cleanup" value="1" data-wizard-destructive-confirm="<?php echo esc_attr( $step ); ?>">
			<span><?php echo esc_html( $checkbox_label ); ?></span>
		</label>
	</div>
	<?php
}

/**
 * Render the reusable destructive confirmation modal.
 *
 * @return void
 */
function rms_wizard_render_confirmation_modal(): void {
	?>
	<div class="rms-wizard-confirmation-modal" data-wizard-confirm-dialog hidden>
		<div class="rms-wizard-confirmation-modal__backdrop" data-wizard-confirm-cancel></div>
		<section class="rms-wizard-confirmation-modal__panel" role="dialog" aria-modal="true" aria-labelledby="rms-wizard-confirm-title" aria-describedby="rms-wizard-confirm-message">
			<h2 id="rms-wizard-confirm-title"><?php esc_html_e( 'Confirm destructive action', 'simple-rms-theme' ); ?></h2>
			<p id="rms-wizard-confirm-message" data-wizard-confirm-message></p>
			<div class="rms-wizard-confirmation-modal__actions">
				<button type="button" class="button" data-wizard-confirm-cancel><?php esc_html_e( 'Cancel', 'simple-rms-theme' ); ?></button>
				<button type="button" class="button button-primary" data-wizard-confirm-accept><?php esc_html_e( 'Yes, continue', 'simple-rms-theme' ); ?></button>
			</div>
		</section>
	</div>
	<?php
}

/**
 * Return guided page choices for the Generate Pages step.
 *
 * @return array<string,array{title:string,description:string}>
 */
function rms_wizard_page_generation_choices(): array {
	return [
		'home'         => [ 'title' => __( 'Home', 'simple-rms-theme' ), 'description' => __( 'The main landing page for the site.', 'simple-rms-theme' ) ],
		'about'        => [ 'title' => __( 'About', 'simple-rms-theme' ), 'description' => __( 'Company story, experience, and trust signals.', 'simple-rms-theme' ) ],
		'services'     => [ 'title' => __( 'Services', 'simple-rms-theme' ), 'description' => __( 'Overview of core services from Client Data.', 'simple-rms-theme' ) ],
		'blog'         => [ 'title' => __( 'Blog', 'simple-rms-theme' ), 'description' => __( 'Posts index and content hub.', 'simple-rms-theme' ) ],
		'contact'      => [ 'title' => __( 'Contact', 'simple-rms-theme' ), 'description' => __( 'Contact details and conversion form area.', 'simple-rms-theme' ) ],
		'projects'     => [ 'title' => __( 'Projects', 'simple-rms-theme' ), 'description' => __( 'Portfolio or completed work page.', 'simple-rms-theme' ) ],
		'testimonials' => [ 'title' => __( 'Testimonials', 'simple-rms-theme' ), 'description' => __( 'Customer proof and reviews page.', 'simple-rms-theme' ) ],
	];
}

/**
 * Return all ACF Home section choices.
 *
 * @return array<string,array{key:string,name:string,label:string,description:string,sub_fields:array<int,array<string,mixed>>}>
 */
function rms_wizard_home_section_choices(): array {
	return ( new Inc\Wizard\Flexible_Content_Layouts() )->get_layouts();
}

/**
 * Return common ACF Home section quick-start choices.
 *
 * @return array<string,array{key:string,name:string,label:string,description:string,sub_fields:array<int,array<string,mixed>>}>
 */
function rms_wizard_home_common_section_choices(): array {
	return ( new Inc\Wizard\Flexible_Content_Layouts() )->get_common_layouts();
}

/**
 * Determine whether a Home section layout contains repeater fields.
 *
 * @param array<string,mixed> $section Layout definition.
 */
function rms_wizard_home_section_has_repeaters( array $section ): bool {
	$fields = is_array( $section['sub_fields'] ?? null ) ? $section['sub_fields'] : [];
	$layout = (string) ( $section['name'] ?? '' );

	return rms_wizard_home_section_layout_uses_repeaters( $layout ) || rms_wizard_fields_have_repeaters( $fields );
}

/**
 * Determine whether a known Home section layout uses repeater-driven content.
 */
function rms_wizard_home_section_layout_uses_repeaters( string $layout ): bool {
	$layout = sanitize_key( $layout );
	$layout = 'cta-bar' === $layout ? 'cta-v1' : $layout;

	return in_array(
		$layout,
		[
			'slider',
			'area-coverage-v1',
			'badges',
			'cta-v3',
			'faq-v1',
			'faq-v2',
			'gallery-grid',
			'portfolio-v1',
			'portfolio-v2',
			'portfolio-v3',
			'services-v1',
			'services-v2',
			'services-v3',
			'testimonials-v1',
			'testimonials-v2',
			'testimonials-v3',
			'video-v2',
			'vision-mission-v1',
			'vision-mission-v2',
		],
		true
	);
}

/**
 * Recursively detect repeater fields in an ACF field list.
 *
 * @param array<int,array<string,mixed>> $fields ACF field definitions.
 */
function rms_wizard_fields_have_repeaters( array $fields ): bool {
	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		if ( 'repeater' === (string) ( $field['type'] ?? '' ) ) {
			return true;
		}

		if ( is_array( $field['sub_fields'] ?? null ) && rms_wizard_fields_have_repeaters( $field['sub_fields'] ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Determine whether a Home section layout has AI-fillable text fields.
 */
function rms_wizard_home_section_has_fillable_fields( string $layout ): bool {
	$layout = sanitize_key( $layout );
	$layout = 'cta-bar' === $layout ? 'cta-v1' : $layout;

	return ( new Inc\Wizard\AI_Content_Harness() )->has_fillable_fields( $layout );
}

/**
 * Return the default AI harness item count for a Home section layout.
 */
function rms_wizard_home_section_default_item_count( string $layout ): int {
	$defaults = [
		'slider'            => 2,
		'area-coverage-v1'  => 4,
		'badges'            => 4,
		'cta-v3'            => 3,
		'faq-v1'            => 4,
		'faq-v2'            => 4,
		'gallery-grid'      => 6,
		'portfolio-v1'      => 3,
		'portfolio-v2'      => 3,
		'portfolio-v3'      => 6,
		'services-v1'       => 3,
		'services-v2'       => 3,
		'services-v3'       => 3,
		'testimonials-v1'   => 3,
		'testimonials-v2'   => 3,
		'testimonials-v3'   => 3,
		'video-v2'          => 2,
		'vision-mission-v1' => 2,
		'vision-mission-v2' => 3,
	];

	$layout = sanitize_key( $layout );
	$layout = 'cta-bar' === $layout ? 'cta-v1' : $layout;

	if ( ! rms_wizard_home_section_has_fillable_fields( $layout ) ) {
		return 0;
	}

	return max( 1, min( 12, $defaults[ $layout ] ?? 1 ) );
}

/**
 * Render the Client Data form from the Theme Settings ACF JSON field group.
 *
 * @return void
 */
function rms_wizard_render_client_data_form(): void {
	$field_repository = new Inc\Wizard\Client_Data_Fields();
	$sections         = $field_repository->get_sections();

	if ( [] === $sections ) {
		?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'Theme Settings fields were not found. Run the ACF Import step first and verify that acf-json/group_rms_theme_settings.json exists.', 'simple-rms-theme' ); ?></p>
		</div>
		<?php
		return;
	}
	?>
	<form class="rms-wizard-fields rms-wizard-client-data-fields" data-wizard-client-data-form>
		<p class="rms-wizard-fields__intro">
			<?php esc_html_e( 'Complete the Theme Settings fields below. Schema fields are intentionally excluded from this setup step.', 'simple-rms-theme' ); ?>
		</p>

		<?php foreach ( $sections as $section ) : ?>
			<fieldset class="rms-wizard-fieldset rms-wizard-fieldset--<?php echo esc_attr( $section['slug'] ); ?>">
				<legend><?php echo esc_html( $section['label'] ); ?></legend>
				<div class="rms-wizard-fieldset__fields">
					<?php foreach ( $section['fields'] as $field ) : ?>
						<?php rms_wizard_render_client_data_field( $field, rms_wizard_get_client_data_field_value( $field ) ); ?>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endforeach; ?>
	</form>
	<?php
}

/**
 * Render one Client Data field.
 *
 * @param array<string,mixed> $field       ACF field definition.
 * @param mixed               $value       Current field value.
 * @param string              $name_prefix Optional bracket notation prefix.
 * @param string              $id_prefix   Optional ID prefix.
 *
 * @return void
 */
function rms_wizard_render_client_data_field( array $field, $value = null, string $name_prefix = '', string $id_prefix = '' ): void {
	$type = (string) ( $field['type'] ?? 'text' );

	if ( 'repeater' === $type ) {
		rms_wizard_render_client_data_repeater( $field, $value );
		return;
	}

	$name       = (string) ( $field['name'] ?? '' );
	$label      = (string) ( $field['label'] ?? $name );
	$input_name = '' !== $name_prefix ? $name_prefix . '[' . $name . ']' : $name;
	$field_id   = 'rms-wizard-field-' . rms_wizard_field_id_token( '' !== $id_prefix ? $id_prefix . '-' . $name : $name );
	$required   = ! empty( $field['required'] );
	$style      = rms_wizard_field_width_style( $field );
	?>
	<div class="rms-wizard-field rms-wizard-field--type-<?php echo esc_attr( sanitize_html_class( $type ) ); ?>" <?php echo '' !== $style ? 'style="' . esc_attr( $style ) . '"' : ''; ?>>
		<label for="<?php echo esc_attr( $field_id ); ?>">
			<?php echo esc_html( $label ); ?>
			<?php if ( $required ) : ?>
				<span class="rms-wizard-field__required"><?php esc_html_e( 'Required', 'simple-rms-theme' ); ?></span>
			<?php endif; ?>
		</label>

		<?php rms_wizard_render_client_data_control( $field, $value, $input_name, $field_id, $required ); ?>
		<?php rms_wizard_render_client_data_field_instructions( $field ); ?>
	</div>
	<?php
}

/**
 * Render a non-repeater Client Data control.
 *
 * @param array<string,mixed> $field      ACF field definition.
 * @param mixed               $value      Current field value.
 * @param string              $input_name Input name.
 * @param string              $field_id   Input ID.
 * @param bool                $required   Whether the field is required.
 *
 * @return void
 */
function rms_wizard_render_client_data_control( array $field, $value, string $input_name, string $field_id, bool $required ): void {
	$type         = (string) ( $field['type'] ?? 'text' );
	$scalar_value = rms_wizard_scalar_field_value( $value );
	$placeholder  = (string) ( $field['placeholder'] ?? '' );

	switch ( $type ) {
		case 'textarea':
			$rows = max( 3, absint( $field['rows'] ?? 4 ) );
			?>
			<textarea id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $input_name ); ?>" rows="<?php echo esc_attr( (string) $rows ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" data-wizard-field-type="textarea" <?php echo $required ? 'required aria-required="true"' : ''; ?>><?php echo esc_textarea( $scalar_value ); ?></textarea>
			<?php
			break;

		case 'email':
		case 'url':
		case 'text':
		case 'time_picker':
			$input_type = 'time_picker' === $type ? 'time' : $type;
			?>
			<input id="<?php echo esc_attr( $field_id ); ?>" type="<?php echo esc_attr( $input_type ); ?>" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $scalar_value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" data-wizard-field-type="<?php echo esc_attr( $type ); ?>" <?php echo $required ? 'required aria-required="true"' : ''; ?>>
			<?php
			break;

		case 'select':
			$choices = is_array( $field['choices'] ?? null ) ? $field['choices'] : [];
			?>
			<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $input_name ); ?>" data-wizard-field-type="select" <?php echo $required ? 'required aria-required="true"' : ''; ?>>
				<option value=""><?php esc_html_e( 'Select an option', 'simple-rms-theme' ); ?></option>
				<?php foreach ( $choices as $choice_value => $choice_label ) : ?>
					<option value="<?php echo esc_attr( (string) $choice_value ); ?>" <?php echo (string) $choice_value === $scalar_value ? 'selected' : ''; ?>><?php echo esc_html( (string) $choice_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			break;

		case 'true_false':
			$checked = ! empty( $value ) && '0' !== (string) $value;
			$message = (string) ( $field['message'] ?? '' );
			?>
			<input type="hidden" name="<?php echo esc_attr( $input_name ); ?>" value="0" data-wizard-field-type="true_false">
			<label class="rms-wizard-checkbox" for="<?php echo esc_attr( $field_id ); ?>">
				<input id="<?php echo esc_attr( $field_id ); ?>" type="checkbox" name="<?php echo esc_attr( $input_name ); ?>" value="1" data-wizard-field-type="true_false" <?php checked( $checked ); ?>>
				<span><?php echo esc_html( '' !== $message ? $message : __( 'Enabled', 'simple-rms-theme' ) ); ?></span>
			</label>
			<?php
			break;

		case 'color_picker':
			$color          = sanitize_hex_color( $scalar_value );
			$has_color      = is_string( $color ) && '' !== $color;
			$color_value    = $has_color ? $color : '#000000';
			$empty_attribute = $has_color ? '' : 'data-wizard-empty-color="1"';
			?>
			<input id="<?php echo esc_attr( $field_id ); ?>" type="color" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $color_value ); ?>" data-wizard-field-type="color_picker" <?php echo $empty_attribute; ?>>
			<?php
			break;

		case 'image':
			rms_wizard_render_client_data_image_control( $field, $value, $input_name, $field_id );
			break;

		default:
			?>
			<input id="<?php echo esc_attr( $field_id ); ?>" type="text" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $scalar_value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" data-wizard-field-type="text" <?php echo $required ? 'required aria-required="true"' : ''; ?>>
			<?php
			break;
	}
}

/**
 * Render an ACF image field with WordPress Media Library controls.
 *
 * @param array<string,mixed> $field      ACF field definition.
 * @param mixed               $value      Current field value.
 * @param string              $input_name Input name.
 * @param string              $field_id   Input ID.
 *
 * @return void
 */
function rms_wizard_render_client_data_image_control( array $field, $value, string $input_name, string $field_id ): void {
	$label         = (string) ( $field['label'] ?? __( 'Image', 'simple-rms-theme' ) );
	$attachment_id = is_numeric( $value ) ? absint( $value ) : 0;
	$preview_url   = $attachment_id > 0 ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';

	if ( ! $preview_url && is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
		$preview_url = $value;
	}
	?>
	<div class="rms-wizard-media-field" data-wizard-media-field>
		<div class="rms-wizard-media-field__controls">
			<input id="<?php echo esc_attr( $field_id ); ?>" class="small-text" type="number" min="0" step="1" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( (string) $attachment_id ); ?>" data-wizard-field-type="image" data-wizard-media-input>
			<button type="button" class="button" data-wizard-media-open data-wizard-media-title="<?php echo esc_attr( sprintf( __( 'Select %s', 'simple-rms-theme' ), $label ) ); ?>">
				<?php esc_html_e( 'Select image', 'simple-rms-theme' ); ?>
			</button>
			<button type="button" class="button-link-delete" data-wizard-media-clear>
				<?php esc_html_e( 'Clear', 'simple-rms-theme' ); ?>
			</button>
		</div>
		<div class="rms-wizard-media-field__preview" data-wizard-media-preview>
			<?php if ( $preview_url ) : ?>
				<img src="<?php echo esc_url( $preview_url ); ?>" alt="<?php esc_attr_e( 'Selected image preview', 'simple-rms-theme' ); ?>">
			<?php endif; ?>
			<span><?php echo $attachment_id > 0 ? esc_html( sprintf( __( 'Attachment ID: %d', 'simple-rms-theme' ), $attachment_id ) ) : esc_html__( 'No image selected.', 'simple-rms-theme' ); ?></span>
		</div>
	</div>
	<?php
}

/**
 * Render an ACF repeater field.
 *
 * @param array<string,mixed> $field ACF field definition.
 * @param mixed               $value Current field value.
 *
 * @return void
 */
function rms_wizard_render_client_data_repeater( array $field, $value ): void {
	$name  = (string) ( $field['name'] ?? '' );
	$label = (string) ( $field['label'] ?? $name );
	$rows  = rms_wizard_normalize_repeater_rows( $value );
	?>
	<div class="rms-wizard-field rms-wizard-repeater" data-wizard-repeater="<?php echo esc_attr( $name ); ?>">
		<div class="rms-wizard-repeater__header">
			<div>
				<span class="rms-wizard-repeater__label"><?php echo esc_html( $label ); ?></span>
				<?php rms_wizard_render_client_data_field_instructions( $field ); ?>
			</div>
		</div>

		<div class="rms-wizard-repeater__rows" data-wizard-repeater-rows>
			<?php foreach ( $rows as $index => $row ) : ?>
				<?php rms_wizard_render_client_data_repeater_row( $field, $row, (string) $index ); ?>
			<?php endforeach; ?>
		</div>

		<div class="rms-wizard-repeater__footer">
			<button type="button" class="button" data-wizard-repeater-add="<?php echo esc_attr( $name ); ?>">
				<?php echo esc_html( (string) ( $field['button_label'] ?? __( 'Add Row', 'simple-rms-theme' ) ) ); ?>
			</button>
		</div>

		<template data-wizard-repeater-template>
			<?php rms_wizard_render_client_data_repeater_row( $field, [], '__INDEX__' ); ?>
		</template>
	</div>
	<?php
}

/**
 * Render one repeater row.
 *
 * @param array<string,mixed> $field ACF repeater definition.
 * @param array<string,mixed> $row   Current row data.
 * @param string              $index Row index or template token.
 *
 * @return void
 */
function rms_wizard_render_client_data_repeater_row( array $field, array $row, string $index ): void {
	$name       = (string) ( $field['name'] ?? '' );
	$sub_fields = is_array( $field['sub_fields'] ?? null ) ? $field['sub_fields'] : [];
	?>
	<div class="rms-wizard-repeater__row" data-wizard-repeater-row>
		<div class="rms-wizard-repeater__row-fields">
			<?php foreach ( $sub_fields as $sub_field ) : ?>
				<?php
				$sub_name  = (string) ( $sub_field['name'] ?? '' );
				$sub_value = $row[ $sub_name ] ?? null;
				rms_wizard_render_client_data_field( $sub_field, $sub_value, $name . '[' . $index . ']', $name . '-' . $index );
				?>
			<?php endforeach; ?>
		</div>
		<button type="button" class="button-link-delete rms-wizard-repeater__remove" data-wizard-repeater-remove>
			<?php esc_html_e( 'Remove row', 'simple-rms-theme' ); ?>
		</button>
	</div>
	<?php
}

/**
 * Get the current option value for a Client Data ACF field.
 *
 * @param array<string,mixed> $field ACF field definition.
 *
 * @return mixed
 */
function rms_wizard_get_client_data_field_value( array $field ) {
	$name = (string) ( $field['name'] ?? '' );
	$type = (string) ( $field['type'] ?? 'text' );

	if ( '' === $name ) {
		return '';
	}

	if ( function_exists( 'get_field' ) ) {
		$value = 'image' === $type ? get_field( $name, 'option', false ) : get_field( $name, 'option' );

		if ( rms_wizard_has_client_data_value( $value, $type ) ) {
			return $value;
		}
	}

	$fallback = rms_wizard_get_client_data_option_fallback( $field );

	if ( rms_wizard_has_client_data_value( $fallback, $type ) ) {
		return $fallback;
	}

	return $field['default_value'] ?? ( 'repeater' === ( $field['type'] ?? '' ) ? [] : '' );
}

/**
 * Determine whether a loaded Client Data value should be used.
 *
 * @param mixed  $value Field value.
 * @param string $type  ACF field type.
 */
function rms_wizard_has_client_data_value( $value, string $type ): bool {
	if ( 'true_false' === $type ) {
		return null !== $value;
	}

	if ( 'repeater' === $type ) {
		return is_array( $value );
	}

	return null !== $value && false !== $value;
}

/**
 * Get a stored option fallback when ACF formatted values are unavailable.
 *
 * @param array<string,mixed> $field ACF field definition.
 *
 * @return mixed
 */
function rms_wizard_get_client_data_option_fallback( array $field ) {
	$type = (string) ( $field['type'] ?? 'text' );

	if ( 'repeater' === $type ) {
		return rms_wizard_get_repeater_option_fallback( $field );
	}

	$name = (string) ( $field['name'] ?? '' );

	return '' !== $name ? get_option( 'options_' . $name, null ) : null;
}

/**
 * Reconstruct ACF repeater option rows from raw options table values.
 *
 * @param array<string,mixed> $field ACF repeater field definition.
 *
 * @return array<int,array<string,mixed>>|null
 */
function rms_wizard_get_repeater_option_fallback( array $field ): ?array {
	$name = (string) ( $field['name'] ?? '' );

	if ( '' === $name ) {
		return null;
	}

	$row_count = absint( get_option( 'options_' . $name, 0 ) );

	if ( $row_count <= 0 ) {
		return null;
	}

	$sub_fields = is_array( $field['sub_fields'] ?? null ) ? $field['sub_fields'] : [];
	$rows       = [];

	for ( $index = 0; $index < $row_count; $index++ ) {
		$row = [];

		foreach ( $sub_fields as $sub_field ) {
			$sub_name = (string) ( $sub_field['name'] ?? '' );

			if ( '' === $sub_name ) {
				continue;
			}

			$option_name = 'options_' . $name . '_' . $index . '_' . $sub_name;
			$value       = get_option( $option_name, null );

			if ( null !== $value && false !== $value ) {
				$row[ $sub_name ] = $value;
			}
		}

		$rows[] = $row;
	}

	return $rows;
}

/**
 * Render field instructions when present.
 *
 * @param array<string,mixed> $field ACF field definition.
 *
 * @return void
 */
function rms_wizard_render_client_data_field_instructions( array $field ): void {
	$instructions = trim( (string) ( $field['instructions'] ?? '' ) );

	if ( '' === $instructions ) {
		return;
	}
	?>
	<p class="rms-wizard-field__instructions"><?php echo esc_html( $instructions ); ?></p>
	<?php
}

/**
 * Normalize a scalar field value for safe output in controls.
 *
 * @param mixed $value Raw value.
 *
 * @return string
 */
function rms_wizard_scalar_field_value( $value ): string {
	if ( is_bool( $value ) ) {
		return $value ? '1' : '0';
	}

	return is_scalar( $value ) ? (string) $value : '';
}

/**
 * Normalize repeater rows and keep one empty row available for editing.
 *
 * @param mixed $value Raw repeater value.
 *
 * @return array<int,array<string,mixed>>
 */
function rms_wizard_normalize_repeater_rows( $value ): array {
	if ( ! is_array( $value ) ) {
		return [ [] ];
	}

	$rows = [];

	foreach ( $value as $row ) {
		if ( is_array( $row ) ) {
			$rows[] = $row;
		}
	}

	return [] === $rows ? [ [] ] : array_values( $rows );
}

/**
 * Build a safe HTML ID token.
 *
 * @param string $value Raw token.
 *
 * @return string
 */
function rms_wizard_field_id_token( string $value ): string {
	$token = preg_replace( '/[^A-Za-z0-9_-]+/', '-', $value );
	$token = trim( (string) $token, '-' );

	return '' !== $token ? $token : 'field';
}

/**
 * Get optional field width style from the ACF wrapper width setting.
 *
 * @param array<string,mixed> $field ACF field definition.
 *
 * @return string
 */
function rms_wizard_field_width_style( array $field ): string {
	$wrapper = is_array( $field['wrapper'] ?? null ) ? $field['wrapper'] : [];
	$width   = absint( $wrapper['width'] ?? 0 );

	if ( $width <= 0 || $width > 100 ) {
		return '';
	}

	return '--rms-wizard-field-width: ' . $width . '%;';
}
