<?php
/**
 * Structured Data (Schema) — AEO/GEO Optimized
 *
 * Implements JSON-LD structured data for a local roofing contractor business.
 * Uses centralized generators for maintainability and consistency.
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
 *
 * @see https://schema.org/RoofingContractor
 */
function rms_schema_local_business(): array {
    $url = rms_get_site_url();

    return [
        '@context' => 'https://schema.org',
        '@graph' => [
            // Primary: RoofingContractor (most specific type)
            [
                '@type' => 'RoofingContractor',
                '@id' => $url . '/#roofingcontractor',
                'name' => 'Raven Roofing & Construction',
                'description' => 'Professional roofing services in Milwaukee, Wisconsin. Installation, repair, replacement, and emergency services for residential and commercial properties.',
                'url' => $url,
                'telephone' => '+1-414-246-8257',
                'email' => 'info@ravenmarketing.services',
                'image' => $url . '/logo.png',
                'priceRange' => '$$',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Milwaukee',
                    'addressRegion' => 'WI',
                    'addressCountry' => 'US',
                ],
                'areaServed' => [
                    '@type' => 'Place',
                    'name' => 'Greater Milwaukee & surrounding counties',
                ],
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
                'paymentAccepted' => 'Cash, Check, Credit Card, Financing Available',
                'currenciesAccepted' => 'USD',
            ],
            // Secondary: HomeAndConstructionBusiness (broader parent type for compatibility)
            [
                '@type' => 'HomeAndConstructionBusiness',
                '@id' => $url . '/#homeconstruction',
                'name' => 'Raven Roofing & Construction',
                'url' => $url,
                'telephone' => '+1-414-246-8257',
                'email' => 'info@ravenmarketing.services',
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
    $url = rms_get_site_url();

    return [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => $url . '/#organization',
        'name' => 'Raven Roofing & Construction',
        'url' => $url,
        'logo' => $url . '/logo.png',
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'telephone' => '+1-414-246-8257',
            'email' => 'info@ravenmarketing.services',
            'contactType' => 'customer service',
            'areaServed' => 'US',
            'availableLanguage' => ['English', 'Spanish'],
        ],
        'sameAs' => [],
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
