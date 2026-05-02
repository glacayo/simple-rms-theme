<?php
/**
 * Structured Data (Schema) — AEO/GEO Optimized
 *
 * Implements JSON-LD structured data for a local roofing contractor business.
 * Uses centralized generators for maintainability and consistency.
 * All values pulled from ACF Theme Options with WordPress fallbacks.
 *
 * @see https://developers.google.com/search/docs/appearance/structured-data/overview
 * @see https://schema.org/docs/schemas.html
 */

/**
 * Get the base URL for the website.
 */
function rms_get_site_url(): string {
    return home_url();
}

/**
 * Get the site name.
 */
function rms_get_site_name(): string {
    return get_bloginfo('name') ?: 'Raven Roofing & Construction';
}

/**
 * Get the company name from ACF options, fallback to WordPress bloginfo.
 */
function rms_get_schema_name(): string {
    return rms_get_option('company_name') ?: rms_get_site_name();
}

/**
 * Get the schema business type from ACF, fallback to 'RoofingContractor'.
 */
function rms_get_schema_business_type(): string {
    $type = rms_get_option('schema_business_type');
    if ($type && in_array($type, ['Organization', 'LocalBusiness', 'HomeAndConstructionBusiness', 'RoofingContractor', 'Contractor'], true)) {
        return $type;
    }
    return 'RoofingContractor';
}

/**
 * Get the default schema image URL.
 * Prefers explicit schema_default_image, falls back to company_logo_header.
 */
function rms_get_schema_image(): string {
    $url = rms_get_option('schema_default_image');
    if (!empty($url)) {
        return $url;
    }
    return rms_get_option('company_logo_header') ?: rms_get_site_url() . '/logo.png';
}

/**
 * Get the default schema logo URL.
 * Prefers explicit schema_default_logo, falls back to schema_default_image,
 * then company_logo_header.
 */
function rms_get_schema_logo(): string {
    $url = rms_get_option('schema_default_logo');
    if (!empty($url)) {
        return $url;
    }
    $url = rms_get_option('schema_default_image');
    if (!empty($url)) {
        return $url;
    }
    return rms_get_option('company_logo_header') ?: rms_get_site_url() . '/logo.png';
}

/**
 * Build the areaServed array from ACF repeater.
 * Each row has service_area_name and service_area_type.
 */
function rms_get_schema_area_served(): array {
    $areas = rms_get_option('schema_service_areas');
    if (!is_array($areas) || empty($areas)) {
        return [
            [
                '@type' => 'Place',
                'name' => 'Greater Milwaukee & surrounding counties',
            ],
        ];
    }

    $served = [];
    foreach ($areas as $area) {
        if (!empty($area['service_area_name'])) {
            $served[] = [
                '@type' => 'Place',
                'name' => $area['service_area_name'],
            ];
        }
    }

    if (empty($served)) {
        return [
            [
                '@type' => 'Place',
                'name' => 'Greater Milwaukee & surrounding counties',
            ],
        ];
    }

    return $served;
}

/**
 * Build the sameAs array from rms_get_social_links() + schema_same_as_links.
 */
function rms_get_schema_same_as(): array {
    $same_as = [];

    // Pull from company_social_media via rms_get_social_links()
    $socials = rms_get_social_links();
    foreach ($socials as $social) {
        if (!empty($social['url'])) {
            $same_as[] = $social['url'];
        }
    }

    // Append any explicit schema_same_as_links
    $extra = rms_get_option('schema_same_as_links');
    if (is_array($extra)) {
        foreach ($extra as $link) {
            if (!empty($link['same_as_url'])) {
                $same_as[] = $link['same_as_url'];
            }
        }
    }

    return $same_as;
}

/**
 * Output a JSON-LD script tag with escaped and formatted JSON.
 */
function rms_schema_output(array $schema): void {
    $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    printf(
        "<script type=\"application/ld+json\">\n%s\n</script>\n",
        $json
    );
}

// ─── LocalBusiness (HomeAndConstructionBusiness → RoofingContractor) ─────────

/**
 * Generate the LocalBusiness schema for the roofing contractor.
 * Values sourced from ACF Theme Options with sensible fallbacks.
 *
 * @see https://schema.org/RoofingContractor
 */
function rms_schema_local_business(): array {
    $url          = rms_get_site_url();
    $name         = rms_get_schema_name();
    $business_type = rms_get_schema_business_type();
    $image        = rms_get_schema_image();
    $area_served  = rms_get_schema_area_served();

    // priceRange — require "$$" format from ACF
    $price_range = rms_get_option('schema_price_range') ?: '$$';

    // Short description — ACF textarea
    $description = rms_get_option('schema_short_description');
    if (empty($description)) {
        $description = 'Professional roofing services in Milwaukee, Wisconsin. Installation, repair, replacement, and emergency services for residential and commercial properties.';
    }

    // founder + foundingDate — ACF fields
    $founder      = rms_get_option('schema_founder_name');
    $founding_date = rms_get_option('schema_founding_date');

    // Build founder sub-array only when data exists
    $founder_data = null;
    if (!empty($founder)) {
        $founder_data = [
            '@type' => 'Person',
            'name' => $founder,
        ];
    }

    // Payment methods — pull from ACF
    $payment_methods = get_field('company_payment_methods', 'option');
    $payment_labels = [];
    if (!empty($payment_methods) && is_array($payment_methods)) {
        foreach ($payment_methods as $pm) {
            if (!empty($pm['payment_method_name'])) {
                $payment_labels[] = $pm['payment_method_name'];
            }
        }
    }

    $primary = [
        '@type' => $business_type,
        '@id' => $url . '/#' . strtolower($business_type),
        'name' => $name,
        'description' => $description,
        'url' => $url,
        'telephone' => rms_get_primary_phone() ?: '+1-414-246-8257',
        'email' => rms_get_primary_email() ?: 'info@ravenmarketing.services',
        'image' => $image,
        'priceRange' => $price_range,
        'areaServed' => $area_served,
        'openingHoursSpecification' => [
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'opens' => '08:00',
                'closes' => '18:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'Saturday',
                'opens' => '09:00',
                'closes' => '14:00',
            ],
        ],
        'hasOfferCatalog' => [
            '@type' => 'OfferCatalog',
            'name' => 'Roofing Services',
            'itemListElement' => [
                ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => 'Roof Installation']],
                ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => 'Roof Repair']],
                ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => 'Roof Replacement']],
                ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => 'Roof Inspection']],
                ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => 'Gutter Installation']],
                ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => 'Emergency Services']],
            ],
        ],
        'paymentAccepted' => !empty($payment_labels) ? implode(', ', $payment_labels) : 'Cash, Check, Credit Card, Financing Available',
        'currenciesAccepted' => 'USD',
    ];

    // Conditionally add founder + foundingDate to avoid null keys
    if ($founder_data !== null) {
        $primary['founder'] = $founder_data;
    }
    if (!empty($founding_date)) {
        $primary['foundingDate'] = $founding_date;
    }

    // Remove null keys (array_filter preserves order)
    $primary = array_filter($primary, fn($v) => $v !== null);

    return [
        '@context' => 'https://schema.org',
        '@graph' => [
            $primary,
            // Secondary: HomeAndConstructionBusiness (broader parent type for compatibility)
            [
                '@type' => 'HomeAndConstructionBusiness',
                '@id' => $url . '/#homeconstruction',
                'name' => $name,
                'url' => $url,
                'telephone' => rms_get_primary_phone() ?: '+1-414-246-8257',
                'email' => rms_get_primary_email() ?: 'info@ravenmarketing.services',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Milwaukee',
                    'addressRegion' => 'WI',
                    'addressCountry' => 'US',
                ],
            ],
        ],
    ];
}

// ─── Organization ─────────────────────────────────────────────────────────────

/**
 * Generate the Organization schema.
 *
 * @see https://schema.org/Organization
 */
function rms_schema_organization(): array {
    $url     = rms_get_site_url();
    $name    = rms_get_schema_name();
    $logo    = rms_get_schema_logo();
    $same_as = rms_get_schema_same_as();

    return [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => $url . '/#organization',
        'name' => $name,
        'url' => $url,
        'logo' => $logo,
        'image' => rms_get_schema_image(),
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'telephone' => rms_get_primary_phone() ?: '+1-414-246-8257',
            'email' => rms_get_primary_email() ?: 'info@ravenmarketing.services',
            'contactType' => 'customer service',
            'areaServed' => 'US',
            'availableLanguage' => ['English', 'Spanish'],
        ],
        'sameAs' => $same_as,
    ];
}

// ─── WebSite ─────────────────────────────────────────────────────────────────

/**
 * Generate the WebSite schema with potential search action.
 *
 * @see https://schema.org/WebSite
 */
function rms_schema_website(): array {
    $url = rms_get_site_url();

    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => $url . '/#website',
        'url' => $url,
        'name' => rms_get_site_name(),
        'publisher' => [
            '@id' => $url . '/#organization',
        ],
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => $url . '/?s={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];
}

// ─── WebPage ─────────────────────────────────────────────────────────────────

/**
 * Generate a WebPage schema.
 *
 * @see https://schema.org/WebPage
 */
function rms_schema_webpage(string $title, string $description, string $url = ''): array {
    $site_url = rms_get_site_url();
    $page_url = $url ?: get_permalink();
    $site_name = rms_get_site_name();

    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        '@id' => $page_url . '/#webpage',
        'url' => $page_url,
        'name' => $title,
        'description' => $description,
        'isPartOf' => [
            '@id' => $site_url . '/#website',
        ],
        'publisher' => [
            '@id' => $site_url . '/#organization',
        ],
        'about' => [
            '@id' => $site_url . '/#roofingcontractor',
        ],
    ];
}

// ─── BreadcrumbList ───────────────────────────────────────────────────────────

/**
 * Generate a BreadcrumbList schema from a list of items.
 *
 * @param array $items Array of ['name' => string, 'url' => string]
 *
 * @see https://schema.org/BreadcrumbList
 */
function rms_schema_breadcrumb_list(array $items): array {
    $site_url = rms_get_site_url();
    $item_list = [];

    foreach ($items as $position => $item) {
        $item_list[] = [
            '@type' => 'ListItem',
            'position' => $position + 1,
            'name' => $item['name'],
            'item' => $item['url'],
        ];
    }

    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $item_list,
    ];
}

// ─── ContactPage ─────────────────────────────────────────────────────────────

/**
 * Generate a ContactPage schema.
 *
 * @see https://schema.org/ContactPage
 */
function rms_schema_contact_page(): array {
    $url = get_permalink();

    return [
        '@context' => 'https://schema.org',
        '@type' => 'ContactPage',
        '@id' => $url . '/#contactpage',
        'url' => $url,
        'name' => 'Contact Raven Roofing & Construction',
        'description' => 'Get in touch with Raven Roofing for a free estimate. Contact us for roofing installation, repair, replacement, and emergency services in Milwaukee.',
        'isPartOf' => [
            '@id' => home_url() . '/#website',
        ],
        'publisher' => [
            '@id' => home_url() . '/#organization',
        ],
    ];
}

// ─── Service ─────────────────────────────────────────────────────────────────

/**
 * Generate a Service schema for a roofing service.
 *
 * @param string $name        Service name
 * @param string $description Service description
 *
 * @see https://schema.org/Service
 */
function rms_schema_service(string $name, string $description): array {
    $site_url = rms_get_site_url();

    return [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => $name,
        'description' => $description,
        'provider' => [
            '@id' => $site_url . '/#roofingcontractor',
        ],
        'areaServed' => [
            '@type' => 'Place',
            'name' => 'Greater Milwaukee & surrounding counties',
        ],
    ];
}

// ─── Reviews / AggregateRating ───────────────────────────────────────────────

/**
 * REVIEW SCHEMA — TEMPLATE (NOT ACTIVE)
 *
 * These functions generate Review and AggregateRating schemas.
 * They are NOT currently output because the testimonials on the site are
 * placeholder examples (Maria Johnson, Robert Smith, Sarah Williams) and NOT
 * real verified customer reviews.
 *
 * TO ACTIVATE: Replace the placeholder data below with REAL reviews from
 * actual customers. Google strictly policies prohibit fake reviews and
 * may penalize your site if implemented without genuine data.
 *
 * Required for activation:
 * - Real customer name (first and last, or full name)
 * - Real review text (specific to the service provided)
 * - Real rating value (1-5)
 * - Date of the review (ISO 8601 format)
 * - Optionally: review source URL, reviewer profile image
 *
 * @see https://schema.org/Review
 * @see https://schema.org/AggregateRating
 */

/**
 * Individual Review schema — TEMPLATE.
 *
 * Replace each placeholder array with real customer review data.
 * Each review MUST be from a verified customer.
 *
 * @see https://schema.org/Review
 */
function rms_schema_review_template(): array {
    $url = rms_get_site_url();

    /**
     * TODO: Replace with real reviews from verified customers.
     *
     * Example format:
     * [
     *     'author' => 'Jane Doe',           // Real customer name
     *     'review_body' => 'Text of review', // Real review text
     *     'rating_value' => 5,               // 1-5 stars
     *     'date_published' => '2024-06-15', // ISO 8601 date
     *     'review_url' => '',                // Optional: link to review source
     * ]
     */
    $reviews = [
        // ─── REAL CUSTOMER REVIEW 1 ───────────────────────────────────────────
        [
            'author' => 'REAL_CUSTOMER_NAME_HERE', // e.g., 'Jane Doe'
            'review_body' => 'REAL_REVIEW_TEXT_HERE', // e.g., 'They replaced our entire roof in three days...'
            'rating_value' => 5, // 1-5 stars
            'date_published' => 'YYYY-MM-DD', // e.g., '2024-06-15'
            'review_url' => '', // Optional: Google, Yelp, BBB link
        ],
        // ─── REAL CUSTOMER REVIEW 2 ───────────────────────────────────────────
        [
            'author' => 'REAL_CUSTOMER_NAME_HERE',
            'review_body' => 'REAL_REVIEW_TEXT_HERE',
            'rating_value' => 5,
            'date_published' => 'YYYY-MM-DD',
            'review_url' => '',
        ],
        // ─── REAL CUSTOMER REVIEW 3 ───────────────────────────────────────────
        [
            'author' => 'REAL_CUSTOMER_NAME_HERE',
            'review_body' => 'REAL_REVIEW_TEXT_HERE',
            'rating_value' => 5,
            'date_published' => 'YYYY-MM-DD',
            'review_url' => '',
        ],
    ];

    /**
     * ACTIVATE: Uncomment this function and the corresponding add_action()
     * at the bottom of this file ONLY after filling in real customer data.
     *
     * DO NOT activate with placeholder data — Google will detect fake reviews.
     */
    return []; // Return empty until real data is provided

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Review',
        'reviewRating' => [
            '@type' => 'Rating',
            'ratingValue' => $reviews[0]['rating_value'],
            'bestRating' => 5,
            'worstRating' => 1,
        ],
        'author' => [
            '@type' => 'Person',
            'name' => $reviews[0]['author'],
        ],
        'reviewBody' => $reviews[0]['review_body'],
        'datePublished' => $reviews[0]['date_published'],
        'publisher' => [
            '@id' => $url . '/#organization',
        ],
    ];

    if (!empty($reviews[0]['review_url'])) {
        $schema['url'] = $reviews[0]['review_url'];
    }

    return $schema;
}

/**
 * AggregateRating schema — TEMPLATE.
 *
 * Calculate these values from REAL reviews only.
 *
 * @see https://schema.org/AggregateRating
 */
function rms_schema_aggregate_rating_template(): array {
    $url = rms_get_site_url();

    /**
     * TODO: Replace with calculated values from real customer reviews.
     *
     * - rating_count: Total number of real reviews (not just the 3 shown on site)
     * - rating_value: Average of all real review ratings (e.g., 4.8)
     *
     * Example:
     *   'ratingCount' => 127,   // Based on 127 real Google/BBB reviews
     *   'ratingValue' => 4.9,   // Average rating across all reviews
     */
    $rating_count = 0;  // TODO: Set to actual count of verified reviews
    $rating_value = 0;  // TODO: Set to actual average rating (1-5)

    /**
     * ACTIVATE: Uncomment this function and the corresponding add_action()
     * at the bottom of this file ONLY after filling in real data.
     *
     * DO NOT activate with fake numbers — this must reflect genuine reviews.
     */
    return []; // Return empty until real data is provided

    return [
        '@context' => 'https://schema.org',
        '@type' => 'AggregateRating',
        'ratingValue' => (string) $rating_value,
        'reviewCount' => (string) $rating_count,
        'bestRating' => 5,
        'worstRating' => 1,
        'itemReviewed' => [
            '@id' => $url . '/#roofingcontractor',
        ],
    ];
}

/**
 * Full Review + AggregateRating setup — TEMPLATE.
 *
 * Outputs individual reviews and aggregate rating for the testimonials page.
 * Currently returns early because review data is placeholder.
 *
 * TO ACTIVATE:
 * 1. Fill in real customer data in rms_schema_review_template()
 * 2. Fill in real aggregate values in rms_schema_aggregate_rating_template()
 * 3. Uncomment the return statements in both functions above
 * 4. Uncomment the add_action() at the bottom of this file
 *
 * @see https://schema.org/Review
 * @see https://schema.org/AggregateRating
 */
function rms_schema_testimonials_page(): void {
    if (!is_page_template('pages/testimonials.php')) {
        return;
    }

    /**
     * TEMPORARY: Exit early until real customer reviews are provided.
     * The testimonials on this site are placeholder examples:
     * - Maria Johnson
     * - Robert Smith
     * - Sarah Williams
     *
     * These are NOT real verified reviews and cannot be used in schema.
     */
    return;

    // ─── ACTIVATE BELOW WHEN REAL DATA IS AVAILABLE ─────────────────────────

    // Individual reviews
    $review_schema = rms_schema_review_template();
    if (!empty($review_schema)) {
        rms_schema_output($review_schema);
    }

    // Aggregate rating (site-wide)
    $aggregate_schema = rms_schema_aggregate_rating_template();
    if (!empty($aggregate_schema)) {
        rms_schema_output($aggregate_schema);
    }
}
// add_action('wp_head', 'rms_schema_testimonials_page', 1);
// ─── TEMPLATE END ──────────────────────────────────────────────────────────────

// ─── Header Hook ──────────────────────────────────────────────────────────────

/**
 * Output global schemas in the <head>.
 * These schemas appear on every page: LocalBusiness, Organization, WebSite.
 */
function rms_schema_header(): void {
    // LocalBusiness + HomeAndConstructionBusiness
    rms_schema_output(rms_schema_local_business());

    // Organization
    rms_schema_output(rms_schema_organization());

    // WebSite with SearchAction
    rms_schema_output(rms_schema_website());
}
add_action('wp_head', 'rms_schema_header', 1);

// ─── Service Page Hook ────────────────────────────────────────────────────────

/**
 * Output Service schemas on service-related pages.
 */
function rms_schema_services_page(): void {
    if (!is_page_template('pages/services.php')) {
        return;
    }

    $services = [
        [
            'name' => 'Roof Installation',
            'description' => 'Complete new roof installation using premium materials with industry-leading warranties.',
        ],
        [
            'name' => 'Roof Repair',
            'description' => 'Fast and reliable repairs for leaks, storm damage, and wear before they become costly problems.',
        ],
        [
            'name' => 'Roof Replacement',
            'description' => 'Full roof replacement services with minimal disruption and maximum long-term protection.',
        ],
        [
            'name' => 'Roof Inspection',
            'description' => 'Thorough inspections to identify issues early and extend the life of your existing roof.',
        ],
        [
            'name' => 'Gutter Installation',
            'description' => 'Professional gutter systems that protect your foundation from water damage year-round.',
        ],
        [
            'name' => 'Emergency Services',
            'description' => '24/7 emergency response for storm damage and urgent roofing needs when you need us most.',
        ],
    ];

    foreach ($services as $service) {
        rms_schema_output(rms_schema_service($service['name'], $service['description']));
    }
}
add_action('wp_head', 'rms_schema_services_page', 1);

// ─── Contact Page Hook ────────────────────────────────────────────────────────

/**
 * Output ContactPage schema on the contact page.
 */
function rms_schema_contact_page_hook(): void {
    if (!is_page_template('pages/contact-us.php')) {
        return;
    }

    rms_schema_output(rms_schema_contact_page());
}
add_action('wp_head', 'rms_schema_contact_page_hook', 1);

// ─── FAQPage Schema ───────────────────────────────────────────────────────────

/**
 * FAQPage schema for the FAQ section on front page.
 *
 * @see https://schema.org/FAQPage
 */
function rms_schema_faq_page(): array {
    $url = rms_get_site_url();

    $faqs = [
        [
            'question' => 'How much does a new roof cost?',
            'answer' => 'The cost of a new roof varies based on several factors including the size of your roof, the materials chosen, and the complexity of the installation. We offer free estimates and provide detailed quotes with no obligation. Most residential roof replacements in the Milwaukee area range from $8,000 to $25,000 depending on these factors.',
        ],
        [
            'question' => 'How long does a roof installation take?',
            'answer' => 'Most residential roof installations are completed in 1 to 3 days, depending on the size and complexity of your roof. Weather conditions and material availability can affect the timeline. We work efficiently while maintaining our high quality standards.',
        ],
        [
            'question' => 'Do you work with insurance companies?',
            'answer' => 'Yes, we have extensive experience working with insurance claims. Storm damage is often covered by your homeowner\'s insurance policy. We can help guide you through the claims process, provide detailed documentation, and work directly with your insurance company.',
        ],
        [
            'question' => 'What warranty do you offer?',
            'answer' => 'We stand behind our work with a comprehensive craftsmanship warranty. Manufacturer warranties on materials vary by product, typically ranging from 25 to 50 years. We also provide a 5-year labor warranty on all installations.',
        ],
        [
            'question' => 'Do you offer free inspections?',
            'answer' => 'Absolutely. We offer free, no-obligation roof inspections. Our thorough assessments check for visible damage, wear patterns, potential leak sources, and overall condition. You\'ll receive a detailed written report.',
        ],
        [
            'question' => 'What areas do you serve in Milwaukee?',
            'answer' => 'We serve the greater Milwaukee metropolitan area and surrounding counties including Waukesha, Ozaukee, Washington, and Racine counties.',
        ],
    ];

    $mainEntity = [];
    foreach ($faqs as $faq) {
        $mainEntity[] = [
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq['answer'],
            ],
        ];
    }

    return [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $mainEntity,
    ];
}

/**
 * Output FAQPage schema on front page.
 */
function rms_schema_faq_page_hook(): void {
    if (!is_front_page()) {
        return;
    }

    rms_schema_output(rms_schema_faq_page());
}
add_action('wp_head', 'rms_schema_faq_page_hook', 1);

// ─── VideoObject Schema ─────────────────────────────────────────────────────

/**
 * VideoObject schema for the video section on front page.
 *
 * TODO: Replace VIDEO_ID placeholders with real video IDs.
 * Each video should have: real YouTube/Vimeo ID, actual upload date, real duration.
 *
 * @see https://schema.org/VideoObject
 */
function rms_schema_video_object_template(): array {
    $url = rms_get_site_url();

    /**
     * TODO: Replace with real video data.
     *
     * Example:
     *   'video_1' => [
     *       'id' => 'dQw4w9WgXcQ',           // Actual YouTube video ID
     *       'title' => 'Complete Roof Replacement',
     *       'description' => 'Watch how our team...',
     *       'upload_date' => '2024-03-15',
     *       'duration' => 'PT1M30S',          // ISO 8601 duration
     *       'thumbnail' => 'https://example.com/thumb1.jpg',
     *   ],
     */
    $videos = [
        'video_1' => [
            'id' => 'VIDEO_ID',              // TODO: Replace
            'title' => 'Complete Roof Replacement — Milwaukee Home',
            'description' => 'Watch how our team transformed this Milwaukee home with a complete roof replacement.',
            'upload_date' => '2024-01-01', // TODO: Replace
            'duration' => 'PT1M30S',         // TODO: Replace (e.g., PT2M15S)
            'thumbnail' => $url . '/images/video-thumbnail-1.jpg', // TODO: Replace
        ],
        'video_2' => [
            'id' => 'VIDEO_ID',              // TODO: Replace
            'title' => 'Storm Damage Repair — Before & After',
            'description' => 'Emergency response and complete restoration after severe storm damage.',
            'upload_date' => '2024-01-01', // TODO: Replace
            'duration' => 'PT2M15S',         // TODO: Replace
            'thumbnail' => $url . '/images/video-thumbnail-2.jpg', // TODO: Replace
        ],
        'video_3' => [
            'id' => 'VIDEO_ID',              // TODO: Replace
            'title' => 'Professional Gutter Installation',
            'description' => 'Seamless gutter system installation protecting homes year-round.',
            'upload_date' => '2024-01-01', // TODO: Replace
            'duration' => 'PT1M45S',         // TODO: Replace
            'thumbnail' => $url . '/images/video-thumbnail-3.jpg', // TODO: Replace
        ],
    ];

    /**
     * ACTIVATE: Uncomment return statement and add_action below ONLY after
     * replacing all VIDEO_ID placeholders and real video data.
     *
     * Currently returns empty because video IDs are placeholders.
     */
    return []; // TEMPLATE — not active

    $schemas = [];
    foreach ($videos as $video) {
        $schemas[] = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $video['title'],
            'description' => $video['description'],
            'thumbnailUrl' => [$video['thumbnail']],
            'uploadDate' => $video['upload_date'],
            'duration' => $video['duration'],
            'embedUrl' => 'https://www.youtube.com/embed/' . $video['id'],
            'publisher' => [
                '@id' => $url . '/#organization',
            ],
        ];
    }

    return $schemas;
}

/**
 * Output VideoObject schemas on front page.
 */
function rms_schema_video_page_hook(): void {
    if (!is_front_page()) {
        return;
    }

    /**
     * TEMPLATE: Currently outputs nothing because video IDs are placeholders.
     * TO ACTIVATE: Replace VIDEO_ID in rms_schema_video_object_template()
     * and uncomment the block below.
     */
    return;

    $schemas = rms_schema_video_object_template();
    foreach ($schemas as $schema) {
        rms_schema_output($schema);
    }
}
// add_action('wp_head', 'rms_schema_video_page_hook', 1);
