<?php
/**
 * Wizard AI content harness.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Encodes prompt contracts and validates AI section copy against layout field rules.
 */
final class AI_Content_Harness {
	public const PAGE_HOME    = 'PAGE_HOME';
	public const PAGE_ABOUT   = 'PAGE_ABOUT';
	public const PAGE_SERVICE = 'PAGE_SERVICE';
	public const PAGE_LANDING = 'PAGE_LANDING';
	public const PAGE_BLOG    = 'PAGE_BLOG';
	public const PAGE_CONTACT = 'PAGE_CONTACT';

	private const APPROVED_CONTEXT_FIELDS = [
		'company_name', 'company_language', 'company_estimate_available', 'company_license', 'company_covered_areas',
		'company_phones', 'company_emails', 'company_address_line_1', 'company_address_line_2', 'company_city',
		'company_state', 'company_postal_code', 'company_country', 'company_schedule', 'company_payment_methods', 'company_services',
	];

	private const REQUIRED_CONTEXT_FIELDS = [ 'company_name' ];

	private const FILLABLE_FIELDS = [
		'hero' => [ 'hero_title', 'hero_description' ],
		'slider' => [],
		'about-us' => [ 'about_headline', 'about_subheadline', 'about_text', 'about_badge_label' ],
		'area-coverage-v1' => [ 'area_eyebrow', 'area_headline', 'area_description', 'area_cta_text' ],
		'badges' => [ 'badges_label' ],
		'blog-v1' => [ 'blog_headline', 'blog_cta_text' ],
		'contact-info' => [ 'contact_info_headline', 'contact_info_intro' ],
		'cta-v1' => [ 'cta_v1_headline', 'cta_v1_text', 'cta_v1_button_text' ],
		'cta-v2' => [ 'cta_v2_headline', 'cta_v2_text', 'cta_v2_primary_text', 'cta_v2_secondary_text' ],
		'cta-v3' => [ 'cta_v3_headline', 'cta_v3_button_text' ],
		'faq-v1' => [ 'faq_v1_headline', 'faq_v1_subheadline' ],
		'faq-v2' => [ 'faq_v2_headline', 'faq_v2_subheadline' ],
		'gallery-grid' => [],
		'portfolio-v1' => [ 'portfolio_v1_headline', 'portfolio_v1_subheadline' ],
		'portfolio-v2' => [ 'portfolio_v2_headline', 'portfolio_v2_subheadline' ],
		'portfolio-v3' => [ 'portfolio_v3_headline', 'portfolio_v3_subheadline' ],
		'seo-content' => [ 'seo_headline', 'seo_subheadline', 'seo_text' ],
		'services-v1' => [ 'services_v1_headline', 'services_v1_subheadline', 'services_v1_services', 'services_v1_cta_text' ],
		'services-v2' => [ 'services_v2_headline', 'services_v2_subheadline', 'services_v2_services', 'services_v2_cta_text' ],
		'services-v3' => [ 'services_v3_headline', 'services_v3_subheadline', 'services_v3_services', 'services_v3_cta_text' ],
		'testimonials-v1' => [ 'testimonials_v1_headline', 'testimonials_v1_subheadline' ],
		'testimonials-v2' => [ 'testimonials_v2_headline', 'testimonials_v2_subheadline' ],
		'testimonials-v3' => [ 'testimonials_v3_headline', 'testimonials_v3_subheadline' ],
		'video-v1' => [ 'video_v1_headline', 'video_v1_subheadline', 'video_v1_video_title', 'video_v1_description', 'video_v1_cta_text' ],
		'video-v2' => [ 'video_v2_headline', 'video_v2_subheadline' ],
		'vision-mission-v1' => [ 'vm_v1_eyebrow', 'vm_v1_headline', 'vm_v1_intro', 'vm_v1_cta_text' ],
		'vision-mission-v2' => [ 'vm_v2_eyebrow', 'vm_v2_headline', 'vm_v2_vision_text', 'vm_v2_mission_text', 'vm_v2_cta_text' ],
	];

	private const BLOCKED_FIELDS = [
		'hero' => [ 'hero_bg_image', 'hero_reviews_label', 'hero_form_shortcode' ],
		'slider' => [ 'slider_slides', 'slide_bg_image', 'slide_cta_url' ],
		'about-us' => [ 'about_image', 'about_badge_years' ],
		'area-coverage-v1' => [ 'area_radius', 'area_cities', 'city_name', 'area_cta_url', 'area_map_image' ],
		'badges' => [ 'badges_items', 'badge_icon', 'badge_name', 'badge_url' ],
		'blog-v1' => [ 'blog_cta_url' ],
		'contact-info' => [ 'contact_info_form_shortcode' ],
		'cta-v1' => [ 'cta_v1_button_url' ],
		'cta-v2' => [ 'cta_v2_primary_url', 'cta_v2_secondary_url' ],
		'cta-v3' => [ 'cta_v3_button_url', 'cta_v3_stats', 'stat_number', 'stat_label' ],
		'faq-v1' => [ 'faq_v1_faqs', 'faq_question', 'faq_answer' ],
		'faq-v2' => [ 'faq_v2_faqs', 'faq_question', 'faq_answer' ],
		'gallery-grid' => [ 'gallery_items', 'gallery_thumbnail', 'gallery_full', 'gallery_label' ],
		'portfolio-v1' => [ 'portfolio_v1_projects', 'project_image', 'project_label' ],
		'portfolio-v2' => [ 'portfolio_v2_projects', 'project_image', 'project_label' ],
		'portfolio-v3' => [ 'portfolio_v3_filters', 'portfolio_v3_projects', 'filter_label', 'project_image', 'project_label', 'project_category' ],
		'seo-content' => [ 'seo_image', 'seo_modifier', 'seo_bg_style', 'seo_bg_image' ],
		'services-v1' => [ 'services_v1_bg_image', 'service_title', 'services_v1_cta_url' ],
		'services-v2' => [ 'service_image', 'service_title', 'services_v2_cta_url' ],
		'services-v3' => [ 'service_image', 'service_name', 'services_v3_cta_url' ],
		'testimonials-v1' => [ 'testimonials_v1_items', 'testimonial_quote', 'testimonial_author', 'testimonial_role', 'testimonial_stars' ],
		'testimonials-v2' => [ 'testimonials_v2_items', 'testimonial_avatar', 'testimonial_quote', 'testimonial_author', 'testimonial_stars' ],
		'testimonials-v3' => [ 'testimonials_v3_items', 'testimonial_quote', 'testimonial_author', 'testimonial_role', 'testimonial_stars' ],
		'video-v1' => [ 'video_v1_poster', 'video_v1_video_url', 'video_v1_duration', 'video_v1_cta_url' ],
		'video-v2' => [ 'video_v2_videos', 'video_thumbnail', 'video_url', 'video_title', 'video_excerpt', 'video_duration' ],
		'vision-mission-v1' => [ 'vm_v1_cards', 'card_title', 'card_text', 'card_highlight', 'vm_v1_cta_url' ],
		'vision-mission-v2' => [ 'vm_v2_reasons', 'reason_text', 'vm_v2_cta_url' ],
	];

	private const SERVICE_DESCRIPTION_FIELDS = [
		'services-v1' => [ 'field' => 'services_v1_services', 'description' => 'service_text' ],
		'services-v2' => [ 'field' => 'services_v2_services', 'description' => 'service_text' ],
		'services-v3' => [ 'field' => 'services_v3_services', 'description' => 'service_overlay_text' ],
	];

	public function get_layer1(): string {
		return 'You are writing contractor website section copy. Return JSON only. Do not invent URLs, shortcodes, image paths, icon classes, booleans, select values, statistics, geographic claims, dates, testimonials, customer names, star ratings, project labels, gallery labels, awards, licenses, certifications, or service names. Use only facts present in the provided client JSON. If a fact is absent, write generic copy that does not imply proof, numbers, locations, reviews, or credentials.';
	}

	public function get_layer2( string $page_type = self::PAGE_HOME ): string {
		if ( self::PAGE_HOME !== $page_type ) {
			$this->log_warning( sprintf( 'Unsupported AI content harness page type "%s"; falling back to PAGE_HOME.', $page_type ) );
		}

		return 'Page context: Home page for a contractor business. Write concise, conversion-focused section copy grounded in client data. Keep section copy layout-aware and avoid repeating the same headline structure across sections.';
	}

	public function get_layer3( string $layout, int $item_count, array $client_context ): string {
		$layout        = $this->normalize_layout_key( $layout );
		$fillable      = $this->get_fillable_fields( $layout );
		$blocked       = $this->get_blocked_fields( $layout );
		$client_json   = \wp_json_encode( $client_context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$client_json   = false === $client_json ? '{}' : $client_json;
		$service_rules = isset( self::SERVICE_DESCRIPTION_FIELDS[ $layout ] ) ? sprintf( ' For service repeaters, preserve the order of client_json.company_services. Service names/titles must come only from company_services[].service_name; return descriptions only in %s.', (string) self::SERVICE_DESCRIPTION_FIELDS[ $layout ]['description'] ) : '';

		$template = "Layout: {{layout}}\nRequested item count: {{item_count}}\nAllowed JSON keys: {{fillable_fields}}\nBlocked JSON keys: {{blocked_fields}}\nClient JSON: {{client_json}}\nReturn one compact JSON object using only allowed keys. Do not include blocked or unknown keys.{{service_rules}}";

		return strtr(
			$template,
			[
				'{{layout}}'          => $layout,
				'{{item_count}}'      => (string) max( 0, $item_count ),
				'{{fillable_fields}}' => implode( ', ', $fillable ),
				'{{blocked_fields}}'  => implode( ', ', $blocked ),
				'{{client_json}}'     => $client_json,
				'{{service_rules}}'   => $service_rules,
			]
		);
	}

	public function get_harness_context( array $client_data ): array {
		$context = [];

		foreach ( self::APPROVED_CONTEXT_FIELDS as $field ) {
			if ( ! array_key_exists( $field, $client_data ) ) {
				continue;
			}

			$context[ $field ] = 'company_services' === $field ? $this->service_context( $client_data[ $field ] ) : $this->sanitize_context_value( $client_data[ $field ] );
		}

		return $context;
	}

	public function validate_required_context( array $client_data ): array {
		$missing = [];

		foreach ( self::REQUIRED_CONTEXT_FIELDS as $field ) {
			$value = $client_data[ $field ] ?? '';

			if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
				$missing[] = $field;
			}
		}

		return $missing;
	}

	public function get_fillable_fields( string $layout ): array {
		$layout = $this->normalize_layout_key( $layout );

		return self::FILLABLE_FIELDS[ $layout ] ?? [];
	}

	public function has_fillable_fields( string $layout ): bool {
		return [] !== $this->get_fillable_fields( $layout );
	}

	public function get_blocked_fields( string $layout ): array {
		$layout = $this->normalize_layout_key( $layout );

		return self::BLOCKED_FIELDS[ $layout ] ?? [];
	}

	public function validate_fields( string $layout, array $decoded ): array {
		$layout   = $this->normalize_layout_key( $layout );
		$fillable = array_flip( $this->get_fillable_fields( $layout ) );
		$blocked  = array_flip( $this->get_blocked_fields( $layout ) );
		$clean    = [];

		foreach ( $decoded as $key => $value ) {
			$key = (string) $key;

			if ( isset( $blocked[ $key ] ) || ! isset( $fillable[ $key ] ) ) {
				continue;
			}

			$clean[ $key ] = $this->sanitize_allowed_value( $layout, $key, $value );
		}

		return $clean;
	}

	private function sanitize_allowed_value( string $layout, string $key, $value ) {
		$service_contract = self::SERVICE_DESCRIPTION_FIELDS[ $layout ] ?? null;

		if ( is_array( $service_contract ) && $key === $service_contract['field'] ) {
			$description_field = (string) $service_contract['description'];
			$rows              = [];

			foreach ( is_array( $value ) ? $value : [] as $row ) {
				if ( ! is_array( $row ) || ! array_key_exists( $description_field, $row ) ) {
					continue;
				}

				$rows[] = [ $description_field => $this->sanitize_copy( $row[ $description_field ] ) ];
			}

			return $rows;
		}

		return $this->sanitize_copy( $value );
	}

	private function service_context( $value ): array {
		$services = [];

		foreach ( is_array( $value ) ? $value : [] as $service ) {
			if ( ! is_array( $service ) || empty( $service['service_name'] ) ) {
				continue;
			}

			$services[] = [
				'service_name'              => $this->sanitize_copy( $service['service_name'] ),
				'service_short_description' => $this->sanitize_copy( $service['service_short_description'] ?? '' ),
			];
		}

		return $services;
	}

	private function sanitize_context_value( $value ) {
		if ( is_array( $value ) ) {
			$clean = [];

			foreach ( $value as $key => $item ) {
				if ( is_string( $key ) && ( false !== strpos( $key, 'url' ) || false !== strpos( $key, 'logo' ) || false !== strpos( $key, 'color' ) || false !== strpos( $key, 'social' ) || false !== strpos( $key, 'slug' ) || 'id' === $key ) ) {
					continue;
				}

				$clean[ $key ] = $this->sanitize_context_value( $item );
			}

			return $clean;
		}

		return $this->sanitize_copy( $value );
	}

	private function sanitize_copy( $value ): string {
		return \wp_kses_post( (string) $value );
	}

	private function normalize_layout_key( string $layout ): string {
		$layout = \sanitize_key( $layout );

		return 'cta-bar' === $layout ? 'cta-v1' : $layout;
	}

	private function log_warning( string $message ): void {
		\error_log( '[Simple RMS Wizard] ' . $message );
	}
}
