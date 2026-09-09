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
 * @package Simple_RMS_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RMS_CF7_LANDING_FORM_OPTION', 'rms_cf7_landing_form_id' );
define( 'RMS_CF7_LANDING_FORM_META', '_rms_theme_managed' );
define( 'RMS_CF7_LANDING_FORM_META_VALUE', 'landing_form' );
define( 'RMS_CF7_LANDING_FORM_TITLE', 'RMS Landing Form' );

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

	$stored = (int) get_option( RMS_CF7_LANDING_FORM_OPTION );
	if ( $stored > 0 && rms_cf7_landing_form_is_managed( $stored ) ) {
		return $stored;
	}

	$recovered = rms_cf7_landing_form_recover();
	if ( $recovered > 0 ) {
		update_option( RMS_CF7_LANDING_FORM_OPTION, $recovered );
		return $recovered;
	}

	return rms_cf7_landing_form_create();
}

/** Canonical CF7 shortcode augmented with the hero form class; fails closed to an empty string. */
function rms_cf7_landing_form_shortcode(): string {
	if ( ! rms_cf7_landing_form_available() ) {
		return '';
	}

	$post_id = rms_cf7_landing_form_id();
	if ( $post_id < 1 ) {
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