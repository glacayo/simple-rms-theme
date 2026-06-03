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
	$step_slugs = array_keys( $steps );
	$descriptions = [
		'dependencies'     => __( 'Check and install the required WordPress plugins before continuing.', 'simple-rms-theme' ),
		'acf-import'       => __( 'Import ACF JSON field groups from the theme acf-json directory.', 'simple-rms-theme' ),
		'client-data'      => __( 'Save contractor business information into the theme options.', 'simple-rms-theme' ),
		'ai-generation'    => __( 'Generate one content section through the selected AI provider and model.', 'simple-rms-theme' ),
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
						<?php elseif ( 'ai-generation' === $slug ) : ?>
							<form class="rms-wizard-fields">
								<?php
								$ai_providers        = Inc\Wizard\AI_Provider_Registry::list_providers();
								$default_ai_provider = Inc\Wizard\AI_Provider_Registry::default_provider();
								?>
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
							<?php if ( '' !== $next_step_slug ) : ?>
								<button type="button" class="button button-secondary rms-wizard-next-step" data-wizard-next-step="<?php echo esc_attr( $slug ); ?>" data-wizard-next-target="<?php echo esc_attr( $next_step_slug ); ?>" disabled><?php esc_html_e( 'Next step', 'simple-rms-theme' ); ?></button>
							<?php endif; ?>
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
