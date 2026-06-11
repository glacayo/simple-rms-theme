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

	public function get_layer1(): string {
		return <<<'PROMPT'
You are a professional website copywriter and SEO specialist for contractor and home services businesses.
All generated content must be written in English.

ROLE
- Your only job is to write website copy and SEO copy for the requested section.
- Return one JSON object and nothing else.
- Do not design, code, suggest images, recommend tools, or answer questions.

EDITORIAL STANDARDS
- Paragraphs must be useful, specific, and free of padding or filler. Every sentence must help the reader or advance the marketing argument.
- Headlines should be 6 to 12 words, benefit-led, and should not end with punctuation unless they are questions.
- Subheadlines should be 15 to 25 words, expand on the headline, and must not simply restate it.
- Eyebrows and marketing labels should be 2 to 4 words, title case, thematic, and not unverifiable factual claims.
- CTA button text should be 2 to 5 words and begin with an action verb.
- Body copy must follow the paragraph counts and word counts in the layout task. For multi-paragraph body fields, do not repeat the same idea across paragraphs.
- FAQ answers, when explicitly allowed by the layout task, must be complete, reassuring, and informative. Never return questions without answers.
- Service descriptions must focus on customer benefit. Service names may only come from company_services[].service_name in the provided client JSON.

NO-INVENTION GUARDRAILS
- Use only facts present in the provided client JSON.
- Do not invent URLs, shortcodes, image paths, icon classes, booleans, select values, phone numbers, emails, addresses, map links, statistics, counts, dates, years in business, licenses, certifications, awards, accreditations, testimonials, customer names, star ratings, project labels, gallery labels, service names, or service areas.
- If a fact is absent, write generic but useful copy that does not imply proof, numbers, locations, reviews, credentials, or guaranteed outcomes.
- Client context is intentionally lean. Treat company_name, company_covered_areas, and company_services as the only trusted source of business facts.

OUTPUT RULES
- Return only valid JSON.
- Use only the exact allowed keys listed in the section task.
- Do not include blocked keys, unknown keys, markdown code fences, explanations, comments, or text outside the JSON object.
PROMPT;
	}

	public function get_layer2( string $page_type = self::PAGE_HOME ): string {
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
- Give each section a distinct job so the page does not repeat the same headline pattern or value proposition.

Tone: Professional, approachable, confident, local, and human. Never corporate, stiff, exaggerated, or keyword-stuffed.

Write for a general audience discovering this business. Content should feel like a strong first impression, not a hard sales pitch.
PROMPT;
	}

	public function get_layer3( string $layout, int $item_count, array $client_context ): string {
		$layout        = $this->normalize_layout_key( $layout );
		$fillable      = $this->get_fillable_fields( $layout );
		$blocked       = $this->get_blocked_fields( $layout );
		$client_json   = \wp_json_encode( $client_context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$client_json   = false === $client_json ? '{}' : $client_json;
		$service_rules = isset( self::SERVICE_DESCRIPTION_FIELDS[ $layout ] ) ? sprintf( ' For service repeaters, preserve the order of client_json.company_services. Service names/titles must come only from company_services[].service_name; return descriptions only in %s.', (string) self::SERVICE_DESCRIPTION_FIELDS[ $layout ]['description'] ) : '';
		$layout_rules  = $this->layout_rules( $layout, $item_count );

		$template = "Layout: {{layout}}\nRequested item count: {{item_count}}\nAllowed JSON keys: {{fillable_fields}}\nBlocked JSON keys: {{blocked_fields}}\nClient JSON: {{client_json}}\nReturn one compact JSON object using only allowed keys. Do not include blocked or unknown keys.{{service_rules}}\n\n{{layout_rules}}";

		return strtr(
			$template,
			[
				'{{layout}}'          => $layout,
				'{{item_count}}'      => (string) max( 0, $item_count ),
				'{{fillable_fields}}' => implode( ', ', $fillable ),
				'{{blocked_fields}}'  => implode( ', ', $blocked ),
				'{{client_json}}'     => $client_json,
				'{{service_rules}}'   => $service_rules,
				'{{layout_rules}}'    => $layout_rules,
			]
		);
	}

	private function layout_rules( string $layout, int $item_count ): string {
		$rules = [
			'hero'              => <<<'RULES'
Editorial rules:
- hero_title: 6 to 12 words. Lead with a clear benefit, primary service, or company name when present in client data.
- hero_description: one WYSIWYG paragraph of at least 50 words. Reinforce the headline and move the visitor toward action without inventing proof.
RULES,
			'slider'            => <<<'RULES'
Editorial rules:
- slider_slides: return exactly {{item_count}} rows. Each slide must have a different angle, benefit, or audience need.
- slide_subheadline: 2 to 4 words, title case, and thematic.
- slide_headline: 6 to 12 words. Each headline must be distinct and benefit-led.
- slide_text: one WYSIWYG paragraph of at least 50 words that supports the slide headline without inventing proof.
- slide_cta_text: 2 to 5 words, action verb first.
- Do not return slide_bg_image or slide_cta_url.
RULES,
			'about-us'          => <<<'RULES'
Editorial rules:
- about_headline: 6 to 12 words. Communicate trust, local commitment, or practical value.
- about_subheadline: 15 to 25 words. Expand on the headline without restating it or starting with the same word.
- about_text: exactly three HTML paragraphs using <p> tags. Each paragraph must contain 50 to 60 words.
- about_text paragraph 1: company positioning and value proposition.
- about_text paragraph 2: what makes the company different and how they work.
- about_text paragraph 3: trust reinforcement and a soft call to action.
- about_badge_label: 2 to 4 words. Use a short non-factual trust label and never invent years, awards, licenses, or certifications.
RULES,
			'area-coverage-v1'  => <<<'RULES'
Editorial rules:
- area_eyebrow: 2 to 4 words, title case, local in tone without inventing cities.
- area_headline: 6 to 12 words. Reference service area only from company_covered_areas when available.
- area_description: one WYSIWYG paragraph of at least 50 words about local availability and service attitude. Do not invent radius, addresses, or cities.
- area_cta_text: 2 to 5 words, action verb first.
RULES,
			'badges'            => <<<'RULES'
Editorial rules:
- badges_label: 2 to 4 words, title case. Frame local directories or platforms where the business has a public profile and can be found by customers.
- Do not write a why-choose-us headline. Do not return badge items, badge names, icons, URLs, awards, certifications, or credentials.
RULES,
			'blog-v1'           => <<<'RULES'
Editorial rules:
- blog_headline: 6 to 12 words. Invite visitors to read, learn, or explore practical advice.
- blog_cta_text: 2 to 5 words, action verb first.
RULES,
			'contact-info'      => <<<'RULES'
Editorial rules:
- contact_info_headline: 6 to 12 words. Welcoming and action-oriented.
- contact_info_intro: one WYSIWYG paragraph of at least 50 words. Make reaching out feel easy and reassuring without listing phone numbers, emails, addresses, or hours.
RULES,
			'cta-v1'            => <<<'RULES'
Editorial rules:
- cta_v1_headline: 6 to 12 words. Urgent, benefit-led, and specific without unverifiable claims.
- cta_v1_text: one sentence of at least 20 words that supports the headline and reduces hesitation.
- cta_v1_button_text: 2 to 5 words, action verb first.
RULES,
			'cta-v2'            => <<<'RULES'
Editorial rules:
- cta_v2_headline: 6 to 12 words. Urgent, benefit-led, and specific without unverifiable claims.
- cta_v2_text: one sentence of at least 20 words that supports the headline and reduces hesitation.
- cta_v2_primary_text: 2 to 5 words, action verb first, for the higher-commitment action.
- cta_v2_secondary_text: 2 to 5 words, action verb first, for the lower-commitment action.
RULES,
			'cta-v3'            => <<<'RULES'
Editorial rules:
- cta_v3_headline: 6 to 12 words. Confidence-building and action-oriented without inventing numbers or proof.
- cta_v3_button_text: 2 to 5 words, action verb first.
- cta_v3_stats: return exactly {{item_count}} rows.
- stat_label: 2 to 5 words describing what the existing stat number represents. Keep labels generic and do not invent numbers.
- Do not return stat_number.
RULES,
			'faq-v1'            => <<<'RULES'
Editorial rules:
- faq_v1_headline: 6 to 12 words. Invite visitors to find clear answers.
- faq_v1_subheadline: 15 to 25 words. Reassure visitors that common questions are welcome.
- faq_v1_faqs: return exactly {{item_count}} question and answer rows covering different customer concerns.
- faq_question: natural customer question ending with a question mark.
- faq_answer: WYSIWYG answer of at least 60 words. Reassuring, informative, and never evasive.
RULES,
			'faq-v2'            => <<<'RULES'
Editorial rules:
- faq_v2_headline: 6 to 12 words. Invite visitors to find clear answers.
- faq_v2_subheadline: 15 to 25 words. Reassure visitors that common questions are welcome.
- faq_v2_faqs: return exactly {{item_count}} question and answer rows covering different customer concerns.
- faq_question: natural customer question ending with a question mark.
- faq_answer: WYSIWYG answer of at least 60 words. Reassuring, informative, and never evasive.
RULES,
			'gallery-grid'      => <<<'RULES'
Editorial rules:
- This layout has no AI-fillable fields. Return an empty JSON object: {}.
- Do not generate gallery images, labels, or captions.
RULES,
			'portfolio-v1'      => <<<'RULES'
Editorial rules:
- portfolio_v1_headline: 6 to 12 words. Invite visitors to explore the company's work without inventing project details.
- portfolio_v1_subheadline: 15 to 25 words. Reinforce quality and pride without claiming specific results.
- Do not return project images or project labels.
RULES,
			'portfolio-v2'      => <<<'RULES'
Editorial rules:
- portfolio_v2_headline: 6 to 12 words. Invite visitors to explore the company's work without inventing project details.
- portfolio_v2_subheadline: 15 to 25 words. Reinforce quality and pride without claiming specific results.
- Do not return project images or project labels.
RULES,
			'portfolio-v3'      => <<<'RULES'
Editorial rules:
- portfolio_v3_headline: 6 to 12 words. Invite visitors to explore the company's work without inventing project details.
- portfolio_v3_subheadline: 15 to 25 words. Reinforce quality and pride without claiming specific results.
- Do not return filters, project categories, images, or project labels.
RULES,
			'seo-content'       => <<<'RULES'
Editorial rules:
- seo_headline: 6 to 12 words. Topically relevant to the page and service without keyword stuffing.
- seo_subheadline: 15 to 25 words. Expand the headline with a supporting idea.
- seo_text: exactly three HTML paragraphs using <p> tags. Each paragraph must contain at least 50 words.
- seo_text paragraph 1: introduce the service topic with authority and local relevance when supported by client data.
- seo_text paragraph 2: describe the process, benefits, or what customers can expect.
- seo_text paragraph 3: reinforce trust and include a soft call to action.
RULES,
			'services-v1'       => <<<'RULES'
Editorial rules:
- services_v1_headline: 6 to 12 words. Broad and inviting.
- services_v1_subheadline: 15 to 25 words. Reinforce the range and quality of services offered.
- services_v1_services: return exactly {{item_count}} rows matching the order of the first {{item_count}} company_services items.
- service_text: at least 40 words per row, customer-benefit focused, and not just a task description.
- services_v1_cta_text: 2 to 5 words, action verb first.
- Do not return service_title; service names are sourced only from company_services[].service_name.
RULES,
			'services-v2'       => <<<'RULES'
Editorial rules:
- services_v2_headline: 6 to 12 words. Broad and inviting.
- services_v2_subheadline: 15 to 25 words. Reinforce the range and quality of services offered.
- services_v2_services: return exactly {{item_count}} rows matching the order of the first {{item_count}} company_services items.
- service_text: at least 40 words per row, customer-benefit focused, and not just a task description.
- services_v2_cta_text: 2 to 5 words, action verb first.
- Do not return service_title or service_image; service names are sourced only from company_services[].service_name.
RULES,
			'services-v3'       => <<<'RULES'
Editorial rules:
- services_v3_headline: 6 to 12 words. Broad and inviting.
- services_v3_subheadline: 15 to 25 words. Reinforce the range and quality of services offered.
- services_v3_services: return exactly {{item_count}} rows matching the order of the first {{item_count}} company_services items.
- service_overlay_text: at least 40 words per row, punchy, human, and benefit-focused.
- services_v3_cta_text: 2 to 5 words, action verb first.
- Do not return service_name or service_image; service names are sourced only from company_services[].service_name.
RULES,
			'testimonials-v1'   => <<<'RULES'
Editorial rules:
- testimonials_v1_headline: 6 to 12 words. Frame social proof generally without inventing quotes, names, ratings, or review counts.
- testimonials_v1_subheadline: 15 to 25 words. Reinforce trust in a generic, supportable way.
- Do not return testimonial items or any testimonial data.
RULES,
			'testimonials-v2'   => <<<'RULES'
Editorial rules:
- testimonials_v2_headline: 6 to 12 words. Frame social proof generally without inventing quotes, names, ratings, or review counts.
- testimonials_v2_subheadline: 15 to 25 words. Reinforce trust in a generic, supportable way.
- Do not return testimonial items or any testimonial data.
RULES,
			'testimonials-v3'   => <<<'RULES'
Editorial rules:
- testimonials_v3_headline: 6 to 12 words. Frame social proof generally without inventing quotes, names, ratings, or review counts.
- testimonials_v3_subheadline: 15 to 25 words. Reinforce trust in a generic, supportable way.
- Do not return testimonial items or any testimonial data.
RULES,
			'video-v1'          => <<<'RULES'
Editorial rules:
- video_v1_headline: 6 to 12 words. Intrigue the visitor and make the video section feel worth watching.
- video_v1_subheadline: 15 to 25 words. Frame what the visitor is about to see without inventing footage details.
- video_v1_video_title: 4 to 8 words. Use an editorial title only; do not imply a specific real video, date, project, or customer.
- video_v1_description: one WYSIWYG paragraph of at least 50 words. Create curiosity and explain value without inventing media facts.
- video_v1_cta_text: 2 to 5 words, action verb first.
RULES,
			'video-v2'          => <<<'RULES'
Editorial rules:
- video_v2_headline: 6 to 12 words. Invite visitors to watch and learn without inventing video details.
- video_v2_subheadline: 15 to 25 words. Frame the video library generally.
- Do not return video items, thumbnails, URLs, titles, excerpts, or durations.
RULES,
			'vision-mission-v1' => <<<'RULES'
Editorial rules:
- vm_v1_eyebrow: 2 to 4 words, title case.
- vm_v1_headline: 6 to 12 words. Communicate purpose and commitment without unverifiable claims.
- vm_v1_intro: one textarea paragraph of at least 50 words introducing the company's vision and mission in human language.
- vm_v1_cards: return exactly {{item_count}} value-card rows.
- card_title: 2 to 5 words naming a value or principle without inventing credentials.
- card_text: at least 40 words explaining the value in plain, human language.
- vm_v1_cta_text: 2 to 5 words, action verb first.
- Do not return card_highlight.
RULES,
			'vision-mission-v2' => <<<'RULES'
Editorial rules:
- vm_v2_eyebrow: 2 to 4 words, title case.
- vm_v2_headline: 6 to 12 words. Communicate purpose and commitment without unverifiable claims.
- vm_v2_vision_text: at least 50 words, forward-looking and aspirational without invented milestones.
- vm_v2_mission_text: at least 50 words, explaining what the company does, for whom, and why it matters using only client data.
- vm_v2_reasons: return exactly {{item_count}} reason rows.
- reason_text: one sentence of at least 20 words giving a concrete, supportable reason to choose the company.
- vm_v2_cta_text: 2 to 5 words, action verb first.
RULES,
		];

		if ( ! isset( $rules[ $layout ] ) ) {
			return '';
		}

		return strtr( $rules[ $layout ], [ '{{item_count}}' => (string) max( 0, $item_count ) ] );
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

	private function normalize_layout_key( string $layout ): string {
		$layout = \sanitize_key( $layout );

		return 'cta-bar' === $layout ? 'cta-v1' : $layout;
	}

	private function log_warning( string $message ): void {
		\error_log( '[Simple RMS Wizard] ' . $message );
	}
}
