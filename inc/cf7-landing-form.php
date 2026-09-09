<?php
/**
 * Contact Form 7 — Landing Form Service
 *
 * Guarantees exactly one theme-managed CF7 form for the hero landing
 * section and exposes its canonical shortcode with the theme hero form
 * classes. Inert and safe when CF7 is inactive/uninstalled: the CF7 APIs
 * are detected as callable, never assumed. Identity is the marker post
 * meta `_rms_theme_managed = landing_form`, persisted as option
 * `rms_cf7_landing_form_id`. A form is never adopted or modified because
 * of its title alone.
 *
 * The admin-only Theme Settings control (ACF `message` field) forces an
 * empty sanitized message and delivers its read-only state machine plus
 * separate POST target through a dedicated render hook; it never creates
 * forms at render time and is inert without ACF/CF7.
 *
 * @package Simple_RMS_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RMS_CF7_LANDING_FORM_OPTION', 'rms_cf7_landing_form_id' );
define( 'RMS_CF7_LANDING_FORM_META', '_rms_theme_managed' );
define( 'RMS_CF7_LANDING_FORM_META_VALUE', 'landing_form' );
define( 'RMS_CF7_LANDING_FORM_TITLE', 'RMS Landing Form' );

// Theme Settings control contracts (exact English strings, per issue #121).
define( 'RMS_CF7_CONTROL_ACTION', 'rms_cf7_generate_landing_form' );
define( 'RMS_CF7_CONTROL_FORM_ID', 'rms-cf7-landing-form-generate' );
define( 'RMS_CF7_CONTROL_WARNING', 'Enable the Contact Form 7 plugin before adding the theme form.' );

/** Whether the canonical Contact Form 7 APIs are callable. */
function rms_cf7_landing_form_available(): bool {
	return function_exists( 'wpcf7_save_contact_form' )
		&& function_exists( 'wpcf7_contact_form' )
		&& class_exists( 'WPCF7_ContactForm' );
}

/** Whether the post is a published CF7 form carrying the exact managed marker. */
function rms_cf7_landing_form_is_managed( int $post_id ): bool {
	return $post_id > 0
		&& 'publish' === get_post_status( $post_id )
		&& 'wpcf7_contact_form' === get_post_type( $post_id )
		&& RMS_CF7_LANDING_FORM_META_VALUE === (string) get_post_meta( $post_id, RMS_CF7_LANDING_FORM_META, true );
}

/** Recover a published form carrying the exact managed marker; the title alone never identifies one. */
function rms_cf7_landing_form_recover(): int {
	$found = get_posts(
		array(
			'post_type'   => 'wpcf7_contact_form',
			'post_status' => 'publish',
			'numberposts' => 1,
			'orderby'     => 'date',
			'order'       => 'ASC',
			'fields'      => 'ids',
			'meta_key'    => RMS_CF7_LANDING_FORM_META,
			'meta_value'  => RMS_CF7_LANDING_FORM_META_VALUE,
		)
	);

	foreach ( (array) $found as $post_id ) {
		$post_id = (int) $post_id;
		if ( rms_cf7_landing_form_is_managed( $post_id ) ) {
			return $post_id;
		}
	}

	return 0;
}

/** Hero-compatible form markup: generic required fields only, no roofing choices. */
function rms_cf7_landing_form_markup(): string {
	$group = '<div class="hero__form-group"><label>%1$s</label>[%2$s %3$s class:hero__form-input placeholder "%4$s"]</div>';

	return implode(
		"\n",
		array(
			sprintf( $group, esc_html( __( 'Full Name', 'simple-rms-theme' ) ), 'text*', 'your-name', __( 'Your name', 'simple-rms-theme' ) ),
			sprintf( $group, esc_html( __( 'Email', 'simple-rms-theme' ) ), 'email*', 'your-email', __( 'you@example.com', 'simple-rms-theme' ) ),
			sprintf( $group, esc_html( __( 'Phone', 'simple-rms-theme' ) ), 'tel*', 'your-phone', __( '(555) 123-4567', 'simple-rms-theme' ) ),
			sprintf( $group, esc_html( __( 'Service Interested In', 'simple-rms-theme' ) ), 'text*', 'your-service', __( 'Describe the service you need', 'simple-rms-theme' ) ),
			'<div class="hero__form-group"><label>' . esc_html( __( 'Project Summary', 'simple-rms-theme' ) ) . '</label>[textarea your-summary class:hero__form-input placeholder "' . __( 'Briefly describe your project...', 'simple-rms-theme' ) . '"]</div>',
			'[submit class:btn class:hero__form-submit "' . __( 'Get My Free Estimate', 'simple-rms-theme' ) . '"]',
		)
	);
}

/** Mail template using tags matching the managed form markup. */
function rms_cf7_landing_form_mail(): array {
	$body = sprintf(
		/* translators: 1-5: submitted values, 6: site title, 7: site url. */
		__( "Name: %1\$s\nEmail: %2\$s\nPhone: %3\$s\nService: %4\$s\nSummary: %5\$s\n\n--\nSent from %6\$s %7\$s", 'simple-rms-theme' ),
		'[your-name]', '[your-email]', '[your-phone]', '[your-service]', '[your-summary]', '[_site_title]', '[_site_url]'
	);

	return array(
		'subject'            => __( 'New landing form submission', 'simple-rms-theme' ),
		'sender'             => '[your-name] <[_site_admin_email]>',
		'recipient'          => '[_site_admin_email]',
		'body'               => $body,
		'additional_headers' => 'Reply-To: [your-email]',
	);
}

/** Create the managed form, stamp the marker meta, persist the ID, and verify by reloading. */
function rms_cf7_landing_form_create(): int {
	$form = wpcf7_save_contact_form(
		array(
			'id'     => -1,
			'title'  => RMS_CF7_LANDING_FORM_TITLE,
			'locale' => function_exists( 'get_locale' ) ? get_locale() : 'en_US',
			'form'   => rms_cf7_landing_form_markup(),
			'mail'   => rms_cf7_landing_form_mail(),
		)
	);

	$post_id = ( $form instanceof WPCF7_ContactForm ) ? (int) $form->id() : 0;
	if ( $post_id < 1 ) {
		return 0;
	}

	add_post_meta( $post_id, RMS_CF7_LANDING_FORM_META, RMS_CF7_LANDING_FORM_META_VALUE, true );
	update_option( RMS_CF7_LANDING_FORM_OPTION, $post_id );

	if ( ! ( wpcf7_contact_form( $post_id ) instanceof WPCF7_ContactForm ) ) {
		return 0;
	}

	return $post_id;
}

/** Resolve the managed form ID: reuse the stored one, recover on stale option, or create once. */
function rms_cf7_landing_form_id(): int {
	if ( ! rms_cf7_landing_form_available() ) {
		return 0;
	}

	$found = rms_cf7_landing_form_lookup();
	if ( $found > 0 ) {
		if ( (int) get_option( RMS_CF7_LANDING_FORM_OPTION ) !== $found ) {
			update_option( RMS_CF7_LANDING_FORM_OPTION, $found );
		}

		return $found;
	}

	return rms_cf7_landing_form_create();
}

/** Non-creating lookup: the stored managed id, then exact-marker recovery only. Never creates. */
function rms_cf7_landing_form_lookup(): int {
	if ( ! rms_cf7_landing_form_available() ) {
		return 0;
	}

	$stored = (int) get_option( RMS_CF7_LANDING_FORM_OPTION );
	if ( $stored > 0 && rms_cf7_landing_form_is_managed( $stored ) ) {
		return $stored;
	}

	return rms_cf7_landing_form_recover();
}

/** Format the canonical shortcode for one already-resolved managed id; fails closed to ''. */
function rms_cf7_landing_form_shortcode_for_id( int $post_id ): string {
	if ( ! rms_cf7_landing_form_available() || $post_id < 1 ) {
		return '';
	}

	$form = wpcf7_contact_form( $post_id );
	if ( ! ( $form instanceof WPCF7_ContactForm ) ) {
		return '';
	}

	return sprintf(
		'[contact-form-7 id="%1$d" title="%2$s" html_class="hero__form"]',
		$post_id,
		esc_attr( $form->title() )
	);
}

/** Canonical CF7 shortcode augmented with the hero form class; fails closed to an empty string. */
function rms_cf7_landing_form_shortcode(): string {
	if ( ! rms_cf7_landing_form_available() ) {
		return '';
	}

	return rms_cf7_landing_form_shortcode_for_id( rms_cf7_landing_form_id() );
}

// ─── Theme Settings control (admin-only; inert without ACF/CF7) ─────────

/** Read the current Theme Settings control state without creating or mutating forms. */
function rms_cf7_landing_form_control_state(): array {
	$available = rms_cf7_landing_form_available();
	$id        = $available ? rms_cf7_landing_form_lookup() : 0;

	if ( $id > 0 ) {
		return array(
			'status'    => 'existing',
			'shortcode' => rms_cf7_landing_form_shortcode_for_id( $id ),
		);
	}

	return array(
		'status'    => $available ? 'ready' : 'inactive',
		'shortcode' => '',
	);
}

/** Force an empty harmless message: the control markup never depends on ACF sanitization. */
function rms_cf7_landing_form_control_field( array $field ): array {
	$field['message'] = '';

	return $field;
}

/** Echo the already escaped control markup after ACF's default field output. */
function rms_cf7_landing_form_control_render( array $field = array() ): void {
	unset( $field );

	$state = rms_cf7_landing_form_control_state();
	$html  = '';

	if ( 'inactive' === $state['status'] ) {
		$html .= sprintf(
			'<p class="rms-cf7-control__warning" style="color:#b32d2e;font-weight:600;">%s</p>',
			esc_html( RMS_CF7_CONTROL_WARNING )
		);
	}

	if ( '' !== $state['shortcode'] ) {
		$html .= sprintf(
			'<input type="text" class="rms-cf7-control__shortcode widefat" readonly value="%s" aria-label="Managed hero form shortcode" />',
			esc_attr( $state['shortcode'] )
		);
	}

	$html .= sprintf(
		'<button type="submit" class="button%1$s" form="%2$s"%3$s>%4$s</button>',
		'ready' === $state['status'] ? ' button-primary' : '',
		esc_attr( RMS_CF7_CONTROL_FORM_ID ),
		'inactive' === $state['status'] ? ' disabled' : '',
		esc_html( 'Generate RMS Landing Form' )
	);

	echo $html;
}

/** Print the separate POST target after the ACF options form (no nested forms). */
function rms_cf7_landing_form_control_hidden_form(): void {
	if ( 'rms-theme-settings' !== rms_cf7_landing_form_admin_page() ) {
		return;
	}

	printf(
		'<form id="%1$s" method="post" action="%2$s" hidden><input type="hidden" name="action" value="%3$s" />%4$s</form>',
		esc_attr( RMS_CF7_CONTROL_FORM_ID ),
		esc_url( admin_url( 'admin-post.php' ) ),
		esc_attr( RMS_CF7_CONTROL_ACTION ),
		wp_nonce_field( RMS_CF7_CONTROL_ACTION, 'rms_cf7_nonce', false, false )
	);
}

/** Sanitized current admin page slug from the request, or '' when absent. */
function rms_cf7_landing_form_admin_page(): string {
	if ( ! isset( $_GET['page'] ) || ! function_exists( 'wp_unslash' ) || ! function_exists( 'sanitize_key' ) ) {
		return '';
	}

	$page = wp_unslash( $_GET['page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing

	return is_string( $page ) ? sanitize_key( $page ) : '';
}

/** admin-post handler: capability + dedicated nonce + CF7 recheck + idempotent kernel + safe redirect. */
function rms_cf7_landing_form_handle_generate(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( 'You are not allowed to manage the theme landing form.' ), 403 );
	}

	check_admin_referer( RMS_CF7_CONTROL_ACTION, 'rms_cf7_nonce' );

	$target = admin_url( 'admin.php?page=rms-theme-settings' );

	if ( ! rms_cf7_landing_form_available() ) {
		wp_safe_redirect( add_query_arg( array( 'rms_cf7_status' => 'inactive' ), $target ) );
		exit;
	}

	$id = rms_cf7_landing_form_id();

	wp_safe_redirect(
		add_query_arg( array( 'rms_cf7_status' => $id > 0 ? 'success' : 'error' ), $target )
	);
	exit;
}

/** Escaped admin notice for the allowlisted generate status, on the settings page only. */
function rms_cf7_landing_form_admin_notice(): void {
	$status = isset( $_GET['rms_cf7_status'] ) ? sanitize_key( wp_unslash( $_GET['rms_cf7_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status display

	if ( '' === $status || ! in_array( $status, array( 'success', 'error', 'inactive' ), true ) || 'rms-theme-settings' !== rms_cf7_landing_form_admin_page() ) {
		return;
	}

	$messages = array(
		'success'  => 'RMS Landing Form is ready. Copy the shortcode below into the Hero form field.',
		'error'    => 'The RMS Landing Form could not be created. Check the Contact Form 7 plugin and try again.',
		'inactive' => RMS_CF7_CONTROL_WARNING,
	);

	printf(
		'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
		'success' === $status ? 'success' : 'error',
		esc_html( $messages[ $status ] )
	);
}

if ( function_exists( 'is_admin' ) && is_admin() ) {
	add_filter( 'acf/load_field/key=field_rms_cf7_landing_form_control', 'rms_cf7_landing_form_control_field' );
	add_action( 'acf/render_field/key=field_rms_cf7_landing_form_control', 'rms_cf7_landing_form_control_render' );
	add_action( 'admin_footer', 'rms_cf7_landing_form_control_hidden_form', 20 );
	add_action( 'admin_post_' . RMS_CF7_CONTROL_ACTION, 'rms_cf7_landing_form_handle_generate' );
	add_action( 'admin_notices', 'rms_cf7_landing_form_admin_notice' );
}
