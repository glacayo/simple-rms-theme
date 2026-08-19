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
		'company_name', 'company_covered_areas', 'company_services',
	];

	private const REQUIRED_CONTEXT_FIELDS = [ 'company_name' ];

	private const FILLABLE_FIELDS = [
		'hero' => [ 'hero_title', 'hero_description' ],
		'slider' => [ 'slider_slides' ],
		'about-us' => [ 'about_headline', 'about_subheadline', 'about_text', 'about_badge_label' ],
		'area-coverage-v1' => [ 'area_eyebrow', 'area_headline', 'area_description', 'area_cta_text' ],
		'badges' => [ 'badges_label' ],
		'blog-v1' => [ 'blog_headline', 'blog_cta_text' ],
		'contact-info' => [ 'contact_info_headline', 'contact_info_intro' ],
		'cta-v1' => [ 'cta_v1_headline', 'cta_v1_text', 'cta_v1_button_text' ],
		'cta-v2' => [ 'cta_v2_headline', 'cta_v2_text', 'cta_v2_primary_text', 'cta_v2_secondary_text' ],
		'cta-v3' => [ 'cta_v3_headline', 'cta_v3_button_text', 'cta_v3_stats' ],
		'faq-v1' => [ 'faq_v1_headline', 'faq_v1_subheadline', 'faq_v1_faqs' ],
		'faq-v2' => [ 'faq_v2_headline', 'faq_v2_subheadline', 'faq_v2_faqs' ],
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
		'vision-mission-v1' => [ 'vm_v1_eyebrow', 'vm_v1_headline', 'vm_v1_intro', 'vm_v1_cards', 'vm_v1_cta_text' ],
		'vision-mission-v2' => [ 'vm_v2_eyebrow', 'vm_v2_headline', 'vm_v2_vision_text', 'vm_v2_mission_text', 'vm_v2_reasons', 'vm_v2_cta_text' ],
	];

	private const BLOCKED_FIELDS = [
		'hero' => [ 'hero_bg_image', 'hero_reviews_label', 'hero_form_shortcode' ],
		'slider' => [ 'slide_bg_image', 'slide_cta_url' ],
		'about-us' => [ 'about_image', 'about_badge_years' ],
		'area-coverage-v1' => [ 'area_radius', 'area_cities', 'city_name', 'area_cta_url', 'area_map_image' ],
		'badges' => [ 'badges_items', 'badge_icon', 'badge_name', 'badge_url' ],
		'blog-v1' => [ 'blog_cta_url' ],
		'contact-info' => [ 'contact_info_form_shortcode' ],
		'cta-v1' => [ 'cta_v1_button_url' ],
		'cta-v2' => [ 'cta_v2_primary_url', 'cta_v2_secondary_url' ],
		'cta-v3' => [ 'cta_v3_button_url', 'stat_number' ],
		'faq-v1' => [],
		'faq-v2' => [],
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
		'vision-mission-v1' => [ 'card_highlight', 'vm_v1_cta_url' ],
		'vision-mission-v2' => [ 'vm_v2_cta_url' ],
	];

	private const TEXT_REPEATER_FIELDS = [
		'slider'            => [ 'slider_slides' => [ 'slide_subheadline', 'slide_headline', 'slide_text', 'slide_cta_text' ] ],
		'faq-v1'            => [ 'faq_v1_faqs' => [ 'faq_question', 'faq_answer' ] ],
		'faq-v2'            => [ 'faq_v2_faqs' => [ 'faq_question', 'faq_answer' ] ],
		'vision-mission-v1' => [ 'vm_v1_cards' => [ 'card_title', 'card_text' ] ],
		'vision-mission-v2' => [ 'vm_v2_reasons' => [ 'reason_text' ] ],
		'cta-v3'            => [ 'cta_v3_stats' => [ 'stat_label' ] ],
	];

	private const SERVICE_DESCRIPTION_FIELDS = [
		'services-v1' => [ 'field' => 'services_v1_services', 'description' => 'service_text' ],
		'services-v2' => [ 'field' => 'services_v2_services', 'description' => 'service_text' ],
		'services-v3' => [ 'field' => 'services_v3_services', 'description' => 'service_overlay_text' ],
	];

	private const EDITORIAL_RULES = [
		'global'  => [
			'headline_range'              => [ 'description' => '6 to 12 words', 'words' => [ 'min' => 6, 'max' => 12 ] ],
			'subheadline_range'           => [ 'description' => '15 to 25 words', 'words' => [ 'min' => 15, 'max' => 25 ] ],
			'label_range'                 => [ 'description' => '2 to 4 words', 'words' => [ 'min' => 2, 'max' => 4 ] ],
			'cta_range'                   => [ 'description' => '2 to 5 words', 'words' => [ 'min' => 2, 'max' => 5 ] ],
			'video_title_range'           => [ 'description' => '4 to 8 words', 'words' => [ 'min' => 4, 'max' => 8 ] ],
			'wysiwyg_paragraph_min_50'    => [ 'description' => 'one WYSIWYG paragraph of at least 50 words', 'paragraphs' => [ 'type' => 'wysiwyg', 'count' => 1 ], 'words' => [ 'min' => 50 ] ],
			'textarea_paragraph_min_50'   => [ 'description' => 'one textarea paragraph of at least 50 words', 'paragraphs' => [ 'type' => 'textarea', 'count' => 1 ], 'words' => [ 'min' => 50 ] ],
			'three_html_paragraphs_50_60' => [ 'description' => 'exactly three HTML paragraphs using <p> tags. Each paragraph must contain 50 to 60 words', 'paragraphs' => [ 'type' => 'html', 'count' => 3 ], 'words' => [ 'min' => 50, 'max' => 60, 'per' => 'paragraph' ] ],
			'three_html_paragraphs_min_50' => [ 'description' => 'exactly three HTML paragraphs using <p> tags. Each paragraph must contain at least 50 words', 'paragraphs' => [ 'type' => 'html', 'count' => 3 ], 'words' => [ 'min' => 50, 'per' => 'paragraph' ] ],
			'faq_answer_min_60'           => [ 'description' => 'WYSIWYG answer of at least 60 words', 'words' => [ 'min' => 60 ] ],
			'service_description_min_40'  => [ 'description' => 'at least 40 words per row', 'words' => [ 'min' => 40, 'per' => 'row' ] ],
			'copy_min_40'                 => [ 'description' => 'at least 40 words', 'words' => [ 'min' => 40 ] ],
			'body_min_50'                 => [ 'description' => 'at least 50 words', 'words' => [ 'min' => 50 ] ],
			'sentence_min_20'             => [ 'description' => 'one sentence of at least 20 words', 'sentences' => 1, 'words' => [ 'min' => 20 ] ],
			'requested_item_count_rows'   => [ 'description' => 'return exactly the requested item-count rows', 'rows' => 'item_count' ],
		],
		'layouts' => [
			'hero'              => [
				'hero_title'       => [ 'source' => 'headline_range', 'role' => 'primary headline' ],
				'hero_description' => [ 'source' => 'wysiwyg_paragraph_min_50', 'role' => 'body copy' ],
			],
			'slider'            => [
				'slider_slides'     => [ 'source' => 'requested_item_count_rows', 'role' => 'repeater rows' ],
				'slide_subheadline' => [ 'source' => 'label_range', 'role' => 'slide eyebrow' ],
				'slide_headline'    => [ 'source' => 'headline_range', 'role' => 'slide headline' ],
				'slide_text'        => [ 'source' => 'wysiwyg_paragraph_min_50', 'role' => 'slide body copy' ],
				'slide_cta_text'    => [ 'source' => 'cta_range', 'role' => 'CTA button text' ],
			],
			'about-us'          => [
				'about_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'about_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
				'about_text'        => [ 'source' => 'three_html_paragraphs_50_60', 'role' => 'body copy' ],
				'about_badge_label' => [ 'source' => 'label_range', 'role' => 'trust label' ],
			],
			'area-coverage-v1'  => [
				'area_eyebrow'     => [ 'source' => 'label_range', 'role' => 'section eyebrow' ],
				'area_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'area_description' => [ 'source' => 'wysiwyg_paragraph_min_50', 'role' => 'body copy' ],
				'area_cta_text'    => [ 'source' => 'cta_range', 'role' => 'CTA button text' ],
			],
			'badges'            => [ 'badges_label' => [ 'source' => 'label_range', 'role' => 'marketing label' ] ],
			'blog-v1'           => [
				'blog_headline' => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'blog_cta_text' => [ 'source' => 'cta_range', 'role' => 'CTA button text' ],
			],
			'contact-info'      => [
				'contact_info_headline' => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'contact_info_intro'    => [ 'source' => 'wysiwyg_paragraph_min_50', 'role' => 'intro copy' ],
			],
			'cta-v1'            => [
				'cta_v1_headline'    => [ 'source' => 'headline_range', 'role' => 'CTA headline' ],
				'cta_v1_text'        => [ 'source' => 'sentence_min_20', 'role' => 'supporting CTA sentence' ],
				'cta_v1_button_text' => [ 'source' => 'cta_range', 'role' => 'CTA button text' ],
			],
			'cta-v2'            => [
				'cta_v2_headline'       => [ 'source' => 'headline_range', 'role' => 'CTA headline' ],
				'cta_v2_text'           => [ 'source' => 'sentence_min_20', 'role' => 'supporting CTA sentence' ],
				'cta_v2_primary_text'   => [ 'source' => 'cta_range', 'role' => 'primary CTA text' ],
				'cta_v2_secondary_text' => [ 'source' => 'cta_range', 'role' => 'secondary CTA text' ],
			],
			'cta-v3'            => [
				'cta_v3_headline'    => [ 'source' => 'headline_range', 'role' => 'CTA headline' ],
				'cta_v3_button_text' => [ 'source' => 'cta_range', 'role' => 'CTA button text' ],
				'cta_v3_stats'       => [ 'source' => 'requested_item_count_rows', 'role' => 'stat repeater rows' ],
				'stat_label'         => [ 'source' => 'cta_range', 'role' => 'stat label' ],
			],
			'faq-v1'            => [
				'faq_v1_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'faq_v1_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
				'faq_v1_faqs'        => [ 'source' => 'requested_item_count_rows', 'role' => 'FAQ repeater rows' ],
				'faq_answer'         => [ 'source' => 'faq_answer_min_60', 'role' => 'FAQ answer' ],
			],
			'faq-v2'            => [
				'faq_v2_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'faq_v2_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
				'faq_v2_faqs'        => [ 'source' => 'requested_item_count_rows', 'role' => 'FAQ repeater rows' ],
				'faq_answer'         => [ 'source' => 'faq_answer_min_60', 'role' => 'FAQ answer' ],
			],
			'portfolio-v1'      => [
				'portfolio_v1_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'portfolio_v1_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
			],
			'portfolio-v2'      => [
				'portfolio_v2_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'portfolio_v2_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
			],
			'portfolio-v3'      => [
				'portfolio_v3_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'portfolio_v3_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
			],
			'seo-content'       => [
				'seo_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'seo_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
				'seo_text'        => [ 'source' => 'three_html_paragraphs_min_50', 'role' => 'SEO body copy' ],
			],
			'services-v1'       => [
				'services_v1_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'services_v1_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
				'services_v1_services'    => [ 'source' => 'requested_item_count_rows', 'role' => 'service repeater rows' ],
				'service_text'            => [ 'source' => 'service_description_min_40', 'role' => 'service description' ],
				'services_v1_cta_text'    => [ 'source' => 'cta_range', 'role' => 'CTA button text' ],
			],
			'services-v2'       => [
				'services_v2_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'services_v2_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
				'services_v2_services'    => [ 'source' => 'requested_item_count_rows', 'role' => 'service repeater rows' ],
				'service_text'            => [ 'source' => 'service_description_min_40', 'role' => 'service description' ],
				'services_v2_cta_text'    => [ 'source' => 'cta_range', 'role' => 'CTA button text' ],
			],
			'services-v3'       => [
				'services_v3_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'services_v3_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
				'services_v3_services'    => [ 'source' => 'requested_item_count_rows', 'role' => 'service repeater rows' ],
				'service_overlay_text'    => [ 'source' => 'service_description_min_40', 'role' => 'service overlay description' ],
				'services_v3_cta_text'    => [ 'source' => 'cta_range', 'role' => 'CTA button text' ],
			],
			'testimonials-v1'   => [
				'testimonials_v1_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'testimonials_v1_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
			],
			'testimonials-v2'   => [
				'testimonials_v2_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'testimonials_v2_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
			],
			'testimonials-v3'   => [
				'testimonials_v3_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'testimonials_v3_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
			],
			'video-v1'          => [
				'video_v1_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'video_v1_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
				'video_v1_video_title' => [ 'source' => 'video_title_range', 'role' => 'video editorial title' ],
				'video_v1_description' => [ 'source' => 'wysiwyg_paragraph_min_50', 'role' => 'video description' ],
				'video_v1_cta_text'    => [ 'source' => 'cta_range', 'role' => 'CTA button text' ],
			],
			'video-v2'          => [
				'video_v2_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'video_v2_subheadline' => [ 'source' => 'subheadline_range', 'role' => 'section subheadline' ],
			],
			'vision-mission-v1' => [
				'vm_v1_eyebrow'  => [ 'source' => 'label_range', 'role' => 'section eyebrow' ],
				'vm_v1_headline' => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'vm_v1_intro'    => [ 'source' => 'textarea_paragraph_min_50', 'role' => 'intro copy' ],
				'vm_v1_cards'    => [ 'source' => 'requested_item_count_rows', 'role' => 'value-card repeater rows' ],
				'card_title'     => [ 'source' => 'cta_range', 'role' => 'value-card title' ],
				'card_text'      => [ 'source' => 'copy_min_40', 'role' => 'value-card copy' ],
				'vm_v1_cta_text' => [ 'source' => 'cta_range', 'role' => 'CTA button text' ],
			],
			'vision-mission-v2' => [
				'vm_v2_eyebrow'     => [ 'source' => 'label_range', 'role' => 'section eyebrow' ],
				'vm_v2_headline'    => [ 'source' => 'headline_range', 'role' => 'section headline' ],
				'vm_v2_vision_text' => [ 'source' => 'body_min_50', 'role' => 'vision copy' ],
				'vm_v2_mission_text' => [ 'source' => 'body_min_50', 'role' => 'mission copy' ],
				'vm_v2_reasons'     => [ 'source' => 'requested_item_count_rows', 'role' => 'reason repeater rows' ],
				'reason_text'       => [ 'source' => 'sentence_min_20', 'role' => 'reason sentence' ],
				'vm_v2_cta_text'    => [ 'source' => 'cta_range', 'role' => 'CTA button text' ],
			],
		],
	];

	public static function get_editorial_rules( string $layout ): array {
		$layout       = self::normalize_layout_key_static( $layout );
		$global_rules = self::EDITORIAL_RULES['global'];
		$field_rules  = [];

		foreach ( self::EDITORIAL_RULES['layouts'][ $layout ] ?? [] as $field => $field_rule ) {
			$source        = (string) ( $field_rule['source'] ?? '' );
			$resolved_rule = isset( $global_rules[ $source ] ) ? array_merge( $global_rules[ $source ], $field_rule ) : $field_rule;

			unset( $resolved_rule['source'] );

			$field_rules[ $field ] = $resolved_rule;
		}

		return [
			'layout' => $layout,
			'global' => $global_rules,
			'fields' => $field_rules,
		];
	}

	public function get_layer1(): string {
		$template = <<<'PROMPT'
You are a professional website copywriter and SEO specialist for contractor and local service businesses.
All generated content must be written in English.

ROLE
- Your only job is to write website copy and SEO copy for the requested section.
- Return one JSON object and nothing else.
- Do not design, code, suggest images, recommend tools, or answer questions.

EDITORIAL STANDARDS
- Write for customers and property owners first. Technical language is allowed only when it is paired with a clear customer benefit.
- Lead body copy with a concrete benefit, outcome, or customer concern before explaining the method.
- Avoid repeating the same praise adjectives or phrases across the page; prefer concrete outcomes over generic praise.
- Give each section a distinct job or angle, such as process, result, customer experience, trust, service overview, or CTA, without repeating the same promise.
- Headings and subheadings should use concrete service/search-intent language from company_services when applicable, not repeated abstract quality promises.
- Paragraphs must be useful, specific, and free of padding or filler. Every sentence must help the reader or advance the marketing argument.
- Headlines should be {{headline_range}}, benefit-led, and should not end with punctuation unless they are questions.
- Subheadlines should be {{subheadline_range}}, expand on the headline, and must not simply restate it.
- Eyebrows and marketing labels should be {{label_range}}, title case, thematic, and not unverifiable factual claims.
- CTA button text should be {{cta_range}} and begin with an action verb.
- Body copy must follow the paragraph counts and word counts in the layout task. For multi-paragraph body fields, do not repeat the same idea across paragraphs.
- FAQ answers, when explicitly allowed by the layout task, must be complete, reassuring, and informative. Never return questions without answers.
- Service descriptions must focus on customer benefit. Service names may only come from company_services[].service_name in the provided client JSON.
- Do not mention or imply services that are not present in company_services.

NO-INVENTION GUARDRAILS
- Use only facts present in the provided client JSON.
- Do not invent URLs, shortcodes, image paths, icon classes, booleans, select values, phone numbers, emails, addresses, map links, statistics, counts, dates, years in business, guarantees, brands, licenses, certifications, awards, accreditations, bilingual service, special equipment, testimonials, customer names, star ratings, project labels, gallery labels, service names, or service areas.
- If a fact is absent, write generic but useful copy that does not imply proof, numbers, locations, reviews, credentials, or guaranteed outcomes.
- Client context is intentionally lean. Treat company_name, company_covered_areas, and company_services as the only trusted source of business facts.

OUTPUT RULES
- Return only valid JSON.
- Use only the exact allowed keys listed in the section task.
- Do not include blocked keys, unknown keys, markdown code fences, explanations, comments, or text outside the JSON object.
PROMPT;

		return strtr( $template, self::get_editorial_rule_replacements() );
	}

	/**
	 * Layouts that may receive landing keyword context.
	 *
	 * @var string[]
	 */
	private const KEYWORD_LAYOUTS = [
		'hero',
		'seo-content',
	];

	public function get_layer2( string $page_type = self::PAGE_HOME ): string {
		if ( self::PAGE_LANDING === $page_type ) {
			return <<<'PROMPT'
PAGE CONTEXT: Landing Page

You are writing content for a dedicated landing page with a single conversion goal. Visitors arrive with a specific intent (organic search or paid campaign) and should be guided toward one clear action.

Editorial purpose:
- Focus the page on one primary offer, service, or search intent without inventing proof.
- Keep the conversion path clear: promise, proof/trust, process or value, and call to action.
- Align section roles with a typical landing order: hero, SEO content, trust/value sections (vision/mission, badges, portfolio), social proof, and closing SEO content.
- Give each section a distinct job so the page does not repeat the same headline pattern or praise language.

Ads vs SEO intent:
- SEO landings support organic discovery, trust, and long-form helpful copy around a search intent.
- Ads landings support paid traffic with a tighter offer focus and stronger conversion urgency, still without inventing guarantees or proof.

Tone: Specific, confident, local, and human. Never generic brochure copy, corporate jargon, exaggerated claims, or keyword stuffing.

Write for visitors who already have intent. Content should feel purposeful and conversion-ready, not like a broad Home page overview.
PROMPT;
		}

		if ( self::PAGE_HOME !== $page_type ) {
			$this->log_warning( sprintf( 'Unsupported AI content harness page type "%s"; falling back to PAGE_HOME.', $page_type ) );
		}

		return <<<'PROMPT'
PAGE CONTEXT: Home Page

You are writing content for the website's main Home page. This is the first impression for visitors who are discovering the business and deciding whether to continue.

Editorial purpose:
- Establish trust and credibility immediately without inventing credentials or proof.
- Communicate the service offering and service area only when supported by client data.
- Move visitors toward action: calling, requesting an estimate, exploring services, or learning more.
- Give each section a distinct job so the page does not repeat the same headline pattern, praise language, or value proposition.

Tone: Professional, approachable, confident, local, and human. Never corporate, stiff, exaggerated, or keyword-stuffed.

Write for a general audience discovering this business. Content should feel like a strong first impression, not a hard sales pitch.
PROMPT;
	}

	/**
	 * Whether a layout may consume landing keyword placeholders.
	 */
	public function is_keyword_layout( string $layout ): bool {
		return in_array( $this->normalize_layout_key( $layout ), self::KEYWORD_LAYOUTS, true );
	}

	/**
	 * Whether a layout is reusable/canonical-eligible (not keyword-driven).
	 */
	public function is_reusable_layout( string $layout ): bool {
		$layout = $this->normalize_layout_key( $layout );

		return '' !== $layout && ! $this->is_keyword_layout( $layout );
	}

	/**
	 * Normalize landing keyword context: drop empties and clamp subkeywords to 0–10.
	 *
	 * @param array<string,mixed> $keywords Raw keyword payload.
	 *
	 * @return array{primary_keyword:string,subkeywords:string[]}
	 */
	public function normalize_keywords( array $keywords ): array {
		$primary = trim( \sanitize_text_field( (string) ( $keywords['primary_keyword'] ?? $keywords['keyword'] ?? '' ) ) );
		$raw     = $keywords['subkeywords'] ?? $keywords['sub_keywords'] ?? [];

		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[\n,]+/', $raw ) ?: [];
		}

		$subkeywords = [];

		foreach ( is_array( $raw ) ? $raw : [] as $item ) {
			$item = trim( \sanitize_text_field( (string) $item ) );

			if ( '' === $item ) {
				continue;
			}

			$subkeywords[] = $item;

			if ( count( $subkeywords ) >= 10 ) {
				break;
			}
		}

		return [
			'primary_keyword' => $primary,
			'subkeywords'     => $subkeywords,
		];
	}

	/**
	 * @param array<string,mixed> $client_context Approved client context.
	 * @param array<string,mixed> $keywords       Optional landing keywords (PAGE_LANDING only).
	 */
	public function get_layer3( string $layout, int $item_count, array $client_context, string $page_type = self::PAGE_HOME, array $keywords = [] ): string {
		$layout        = $this->normalize_layout_key( $layout );
		$fillable      = $this->get_fillable_fields( $layout );
		$blocked       = $this->get_blocked_fields( $layout );
		$client_json   = \wp_json_encode( $client_context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$client_json   = false === $client_json ? '{}' : $client_json;
		$service_rules = isset( self::SERVICE_DESCRIPTION_FIELDS[ $layout ] ) ? sprintf( ' For service repeaters, preserve the order of client_json.company_services. Service names/titles and service-specific benefits must come only from company_services[].service_name and service_short_description; return descriptions only in %s.', (string) self::SERVICE_DESCRIPTION_FIELDS[ $layout ]['description'] ) : '';
		$layout_rules  = $this->layout_rules( $layout, $item_count );
		$normalized    = $this->normalize_keywords( $keywords );
		$inject_kw     = self::PAGE_LANDING === $page_type && $this->is_keyword_layout( $layout );
		$primary       = $inject_kw ? $normalized['primary_keyword'] : '';
		$subkeywords   = $inject_kw ? implode( ', ', $normalized['subkeywords'] ) : '';
		$keyword_block = '';

		if ( $inject_kw ) {
			$keyword_block = "\n\nKEYWORD CONTEXT (mandatory for this section only):\n"
				. '- Primary keyword: {{primary_keyword}}' . "\n"
				. '- Subkeywords: {{subkeywords}}' . "\n"
				. "- Naturally incorporate the primary keyword in headlines and body copy where it fits.\n"
				. "- Use subkeywords sparingly and only when natural. Do not keyword-stuff.\n"
				. "- Do not invent services, locations, or proof to force keyword usage.";
		}

		$template = "Layout: {{layout}}\nRequested item count: {{item_count}}\nAllowed JSON keys: {{fillable_fields}}\nBlocked JSON keys: {{blocked_fields}}\nClient JSON: {{client_json}}\nReturn one compact JSON object using only allowed keys. Do not include blocked or unknown keys.{{service_rules}}\n\n{{layout_rules}}{{keyword_block}}";

		return strtr(
			$template,
			[
				'{{layout}}'           => $layout,
				'{{item_count}}'       => (string) max( 0, $item_count ),
				'{{fillable_fields}}'  => implode( ', ', $fillable ),
				'{{blocked_fields}}'   => implode( ', ', $blocked ),
				'{{client_json}}'      => $client_json,
				'{{service_rules}}'    => $service_rules,
				'{{layout_rules}}'     => $layout_rules,
				'{{keyword_block}}'    => $keyword_block,
				'{{primary_keyword}}'  => '' !== $primary ? $primary : '(none provided)',
				'{{subkeywords}}'      => '' !== $subkeywords ? $subkeywords : '(none)',
			]
		);
	}

	private function layout_rules( string $layout, int $item_count ): string {
		$rules = [
			'hero'              => <<<'RULES'
Editorial rules:
- hero_title: {{headline_range}}. Lead with a clear benefit, primary service, or company name when present in client data.
- hero_description: {{wysiwyg_paragraph_min_50}}. Reinforce the headline and move the visitor toward action without inventing proof.
RULES,
			'slider'            => <<<'RULES'
Editorial rules:
- slider_slides: return exactly {{item_count}} rows. Each slide must have a different angle, benefit, or audience need.
- slide_subheadline: {{label_range}}, title case, and thematic.
- slide_headline: {{headline_range}}. Each headline must be distinct and benefit-led.
- slide_text: {{wysiwyg_paragraph_min_50}} that supports the slide headline without inventing proof.
- slide_cta_text: {{cta_range}}, action verb first.
- Do not return slide_bg_image or slide_cta_url.
RULES,
			'about-us'          => <<<'RULES'
Editorial rules:
- about_headline: {{headline_range}}. Communicate trust, local commitment, or practical value.
- about_subheadline: {{subheadline_range}}. Expand on the headline without restating it or starting with the same word.
- about_text: {{three_html_paragraphs_50_60}}.
- about_text paragraph 1: company positioning and value proposition.
- about_text paragraph 2: what makes the company different and how they work.
- about_text paragraph 3: trust reinforcement and a soft call to action.
- about_badge_label: {{label_range}}. Use a short non-factual trust label and never invent years, awards, licenses, or certifications.
RULES,
			'area-coverage-v1'  => <<<'RULES'
Editorial rules:
- area_eyebrow: {{label_range}}, title case, local in tone without inventing cities.
- area_headline: {{headline_range}}. Reference service area only from company_covered_areas when available.
- area_description: {{wysiwyg_paragraph_min_50}} about local availability and service attitude. Do not invent radius, addresses, or cities.
- area_cta_text: {{cta_range}}, action verb first.
RULES,
			'badges'            => <<<'RULES'
Editorial rules:
- badges_label: {{label_range}}, title case. Frame local directories or platforms where the business has a public profile and can be found by customers.
- Do not write a why-choose-us headline. Do not return badge items, badge names, icons, URLs, awards, certifications, or credentials.
RULES,
			'blog-v1'           => <<<'RULES'
Editorial rules:
- blog_headline: {{headline_range}}. Invite visitors to read, learn, or explore practical advice.
- blog_cta_text: {{cta_range}}, action verb first.
RULES,
			'contact-info'      => <<<'RULES'
Editorial rules:
- contact_info_headline: {{headline_range}}. Welcoming and action-oriented.
- contact_info_intro: {{wysiwyg_paragraph_min_50}}. Make reaching out feel easy and reassuring without listing phone numbers, emails, addresses, or hours.
RULES,
			'cta-v1'            => <<<'RULES'
Editorial rules:
- cta_v1_headline: {{headline_range}}. Urgent, benefit-led, and specific without unverifiable claims.
- cta_v1_text: {{sentence_min_20}} that supports the headline and reduces hesitation.
- cta_v1_button_text: {{cta_range}}, action verb first.
RULES,
			'cta-v2'            => <<<'RULES'
Editorial rules:
- cta_v2_headline: {{headline_range}}. Urgent, benefit-led, and specific without unverifiable claims.
- cta_v2_text: {{sentence_min_20}} that supports the headline and reduces hesitation.
- cta_v2_primary_text: {{cta_range}}, action verb first, for the higher-commitment action.
- cta_v2_secondary_text: {{cta_range}}, action verb first, for the lower-commitment action.
RULES,
			'cta-v3'            => <<<'RULES'
Editorial rules:
- cta_v3_headline: {{headline_range}}. Confidence-building and action-oriented without inventing numbers or proof.
- cta_v3_button_text: {{cta_range}}, action verb first.
- cta_v3_stats: return exactly {{item_count}} rows.
- stat_label: {{cta_range}} describing what the existing stat number represents. Keep labels generic and do not invent numbers.
- Do not return stat_number.
RULES,
			'faq-v1'            => <<<'RULES'
Editorial rules:
- faq_v1_headline: {{headline_range}}. Invite visitors to find clear answers.
- faq_v1_subheadline: {{subheadline_range}}. Reassure visitors that common questions are welcome.
- faq_v1_faqs: return exactly {{item_count}} question and answer rows covering different customer concerns.
- faq_question: natural customer question ending with a question mark.
- faq_answer: {{faq_answer_min_60}}. Reassuring, informative, and never evasive.
RULES,
			'faq-v2'            => <<<'RULES'
Editorial rules:
- faq_v2_headline: {{headline_range}}. Invite visitors to find clear answers.
- faq_v2_subheadline: {{subheadline_range}}. Reassure visitors that common questions are welcome.
- faq_v2_faqs: return exactly {{item_count}} question and answer rows covering different customer concerns.
- faq_question: natural customer question ending with a question mark.
- faq_answer: {{faq_answer_min_60}}. Reassuring, informative, and never evasive.
RULES,
			'gallery-grid'      => <<<'RULES'
Editorial rules:
- This layout has no AI-fillable fields. Return an empty JSON object: {}.
- Do not generate gallery images, labels, or captions.
RULES,
			'portfolio-v1'      => <<<'RULES'
Editorial rules:
- portfolio_v1_headline: {{headline_range}}. Invite visitors to explore the company's work without inventing project details.
- portfolio_v1_subheadline: {{subheadline_range}}. Reinforce quality and pride without claiming specific results.
- Do not return project images or project labels.
RULES,
			'portfolio-v2'      => <<<'RULES'
Editorial rules:
- portfolio_v2_headline: {{headline_range}}. Invite visitors to explore the company's work without inventing project details.
- portfolio_v2_subheadline: {{subheadline_range}}. Reinforce quality and pride without claiming specific results.
- Do not return project images or project labels.
RULES,
			'portfolio-v3'      => <<<'RULES'
Editorial rules:
- portfolio_v3_headline: {{headline_range}}. Invite visitors to explore the company's work without inventing project details.
- portfolio_v3_subheadline: {{subheadline_range}}. Reinforce quality and pride without claiming specific results.
- Do not return filters, project categories, images, or project labels.
RULES,
			'seo-content'       => <<<'RULES'
Editorial rules:
- seo_headline: {{headline_range}}. Use concrete service/search-intent language from company_services when available without keyword stuffing.
- seo_subheadline: {{subheadline_range}}. Expand the headline with a customer benefit or outcome, not another abstract quality claim.
- seo_text: {{three_html_paragraphs_min_50}}.
- seo_text paragraph 1: open with a concrete customer concern, benefit, or outcome before explaining method or authority.
- seo_text paragraph 2: describe the process, benefits, or what customers can expect.
- seo_text paragraph 3: reinforce trust and include a soft call to action.
RULES,
			'services-v1'       => <<<'RULES'
Editorial rules:
- services_v1_headline: {{headline_range}}. Use concrete service/search-intent language from company_services when available.
- services_v1_subheadline: {{subheadline_range}}. Explain the customer benefit of the listed services without repeating abstract quality wording.
- services_v1_services: return exactly {{item_count}} rows matching the order of the first {{item_count}} company_services items.
- service_text: {{service_description_min_40}}, customer-benefit focused, and not just a task description.
- services_v1_cta_text: {{cta_range}}, action verb first.
- Do not return service_title; service names are sourced only from company_services[].service_name.
RULES,
			'services-v2'       => <<<'RULES'
Editorial rules:
- services_v2_headline: {{headline_range}}. Use concrete service/search-intent language from company_services when available.
- services_v2_subheadline: {{subheadline_range}}. Explain the customer benefit of the listed services without repeating abstract quality wording.
- services_v2_services: return exactly {{item_count}} rows matching the order of the first {{item_count}} company_services items.
- service_text: {{service_description_min_40}}, customer-benefit focused, and not just a task description.
- services_v2_cta_text: {{cta_range}}, action verb first.
- Do not return service_title or service_image; service names are sourced only from company_services[].service_name.
RULES,
			'services-v3'       => <<<'RULES'
Editorial rules:
- services_v3_headline: {{headline_range}}. Use concrete service/search-intent language from company_services when available.
- services_v3_subheadline: {{subheadline_range}}. Explain the customer benefit of the listed services without repeating abstract quality wording.
- services_v3_services: return exactly {{item_count}} rows matching the order of the first {{item_count}} company_services items.
- service_overlay_text: {{service_description_min_40}}, punchy, human, and benefit-focused.
- services_v3_cta_text: {{cta_range}}, action verb first.
- Do not return service_name or service_image; service names are sourced only from company_services[].service_name.
RULES,
			'testimonials-v1'   => <<<'RULES'
Editorial rules:
- testimonials_v1_headline: {{headline_range}}. Frame social proof generally without inventing quotes, names, ratings, or review counts.
- testimonials_v1_subheadline: {{subheadline_range}}. Reinforce trust in a generic, supportable way.
- Do not return testimonial items or any testimonial data.
RULES,
			'testimonials-v2'   => <<<'RULES'
Editorial rules:
- testimonials_v2_headline: {{headline_range}}. Frame social proof generally without inventing quotes, names, ratings, or review counts.
- testimonials_v2_subheadline: {{subheadline_range}}. Reinforce trust in a generic, supportable way.
- Do not return testimonial items or any testimonial data.
RULES,
			'testimonials-v3'   => <<<'RULES'
Editorial rules:
- testimonials_v3_headline: {{headline_range}}. Frame social proof generally without inventing quotes, names, ratings, or review counts.
- testimonials_v3_subheadline: {{subheadline_range}}. Reinforce trust in a generic, supportable way.
- Do not return testimonial items or any testimonial data.
RULES,
			'video-v1'          => <<<'RULES'
Editorial rules:
- video_v1_headline: {{headline_range}}. Intrigue the visitor and make the video section feel worth watching.
- video_v1_subheadline: {{subheadline_range}}. Frame what the visitor is about to see without inventing footage details.
- video_v1_video_title: {{video_title_range}}. Use an editorial title only; do not imply a specific real video, date, project, or customer.
- video_v1_description: {{wysiwyg_paragraph_min_50}}. Create curiosity and explain value without inventing media facts.
- video_v1_cta_text: {{cta_range}}, action verb first.
RULES,
			'video-v2'          => <<<'RULES'
Editorial rules:
- video_v2_headline: {{headline_range}}. Invite visitors to watch and learn without inventing video details.
- video_v2_subheadline: {{subheadline_range}}. Frame the video library generally.
- Do not return video items, thumbnails, URLs, titles, excerpts, or durations.
RULES,
			'vision-mission-v1' => <<<'RULES'
Editorial rules:
- vm_v1_eyebrow: {{label_range}}, title case.
- vm_v1_headline: {{headline_range}}. Communicate purpose and commitment without unverifiable claims.
- vm_v1_intro: {{textarea_paragraph_min_50}} introducing the company's vision and mission in human language.
- vm_v1_cards: return exactly {{item_count}} value-card rows.
- card_title: {{cta_range}} naming a value or principle without inventing credentials.
- card_text: {{copy_min_40}} explaining the value in plain, human language.
- vm_v1_cta_text: {{cta_range}}, action verb first.
- Do not return card_highlight.
RULES,
			'vision-mission-v2' => <<<'RULES'
Editorial rules:
- vm_v2_eyebrow: {{label_range}}, title case.
- vm_v2_headline: {{headline_range}}. Communicate purpose and commitment without unverifiable claims.
- vm_v2_vision_text: {{body_min_50}}, forward-looking and aspirational without invented milestones.
- vm_v2_mission_text: {{body_min_50}}, explaining what the company does, for whom, and why it matters using only client data.
- vm_v2_reasons: return exactly {{item_count}} reason rows.
- reason_text: {{sentence_min_20}} giving a concrete, supportable reason to choose the company.
- vm_v2_cta_text: {{cta_range}}, action verb first.
RULES,
		];

		if ( ! isset( $rules[ $layout ] ) ) {
			return '';
		}

		return strtr(
			$rules[ $layout ],
			array_merge(
				[ '{{item_count}}' => (string) max( 0, $item_count ) ],
				self::get_editorial_rule_replacements( $layout )
			)
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

	public function get_text_repeater_fields( string $layout ): array {
		$layout = $this->normalize_layout_key( $layout );

		return self::TEXT_REPEATER_FIELDS[ $layout ] ?? [];
	}

	public function validate_fields( string $layout, array $decoded ): array {
		$layout         = $this->normalize_layout_key( $layout );
		$fillable       = array_flip( $this->get_fillable_fields( $layout ) );
		$blocked        = array_flip( $this->get_blocked_fields( $layout ) );
		$text_repeaters = $this->get_text_repeater_fields( $layout );
		$clean          = [];

		foreach ( $decoded as $key => $value ) {
			$key = (string) $key;

			if ( isset( $blocked[ $key ] ) || ! isset( $fillable[ $key ] ) ) {
				continue;
			}

			$sanitized = $this->sanitize_allowed_value( $layout, $key, $value );

			if ( isset( $text_repeaters[ $key ] ) && [] === $sanitized ) {
				continue;
			}

			$clean[ $key ] = $sanitized;
		}

		return $clean;
	}

	private function sanitize_allowed_value( string $layout, string $key, $value ) {
		$text_repeaters = $this->get_text_repeater_fields( $layout );

		if ( isset( $text_repeaters[ $key ] ) ) {
			$allowed_subfields = array_flip( $text_repeaters[ $key ] );
			$rows              = [];

			foreach ( is_array( $value ) ? $value : [] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$clean_row = [];

				foreach ( $row as $sub_key => $sub_value ) {
					$sub_key = (string) $sub_key;

					if ( ! isset( $allowed_subfields[ $sub_key ] ) ) {
						continue;
					}

					$clean_row[ $sub_key ] = $this->sanitize_copy( $sub_value );
				}

				if ( [] !== $clean_row ) {
					$rows[] = $clean_row;
				}
			}

			return $rows;
		}

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

	private static function get_editorial_rule_replacements( string $layout = '' ): array {
		$rules        = self::get_editorial_rules( $layout );
		$replacements = [];

		foreach ( $rules['global'] as $token => $rule ) {
			$description = $rule['description'] ?? '';

			if ( is_scalar( $description ) && '' !== (string) $description ) {
				$replacements[ '{{' . $token . '}}' ] = (string) $description;
			}
		}

		return $replacements;
	}

	private function normalize_layout_key( string $layout ): string {
		return self::normalize_layout_key_static( $layout );
	}

	private static function normalize_layout_key_static( string $layout ): string {
		$layout = \sanitize_key( $layout );

		return 'cta-bar' === $layout ? 'cta-v1' : $layout;
	}

	private function log_warning( string $message ): void {
		\error_log( '[Simple RMS Wizard] ' . $message );
	}
}
