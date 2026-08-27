<!DOCTYPE html>
<html lang="<?php language_attributes(); ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $vite = Vite_Icons_Integration::get_instance();

    // Critical CSS — inlined to eliminate render-blocking requests
    echo $vite->get_critical_css('src/scss/main.scss', 'critical-base');

    // Page-specific critical CSS (e.g., hero on front page)
    if (is_front_page()) {
        echo $vite->get_critical_css('src/scss/templates/hero.scss', 'critical-hero');

        // Above the fold — eager load
        foreach (['slider', 'badges'] as $section) {
            $css = $vite->get_asset('src/scss/templates/' . $section . '.scss');
            if ($css) {
                wp_enqueue_style('section-' . $section, $css, [], null);
            }
        }

        // Below the fold — deferred (non-render-blocking)
        foreach (['about-us', 'services-v1', 'services-v2', 'services-v3', 'cta-v1', 'cta-v2', 'cta-v3', 'portfolio-v1', 'portfolio-v2', 'portfolio-v3', 'testimonials-v1', 'testimonials-v2', 'testimonials-v3', 'blog-v1', 'area-coverage-v1', 'vision-mission-v1', 'vision-mission-v2', 'seo-content'] as $section) {
            $vite->get_deferred_style('section-' . $section, 'src/scss/templates/' . $section . '.scss');
        }

        // Slider JS (auto-advance + dot navigation)
        $slider_js = $vite->get_asset('src/ts/slider.ts');
        if ($slider_js) {
            wp_enqueue_script('slider-js', $slider_js, [], null, true);
        }

        // Lightbox (shared — portfolio zoom modal)
        $vite->get_deferred_style('lightbox', 'src/scss/components/lightbox.scss');
        $lightbox_js = $vite->get_asset('src/ts/lightbox.ts');
        if ($lightbox_js) {
            wp_enqueue_script('lightbox-js', $lightbox_js, [], null, true);
        }

        // Portfolio filter JS
        $filter_js = $vite->get_asset('src/ts/portfolio-filter.ts');
        if ($filter_js) {
            wp_enqueue_script('portfolio-filter-js', $filter_js, [], null, true);
        }

        // FAQ sections (below the fold — deferred)
        foreach (['faq-v1', 'faq-v2'] as $section) {
            $vite->get_deferred_style('section-' . $section, 'src/scss/templates/' . $section . '.scss');
        }

        // FAQ JS (accordion)
        $faq_js = $vite->get_asset('src/ts/faq.ts');
        if ($faq_js) {
            wp_enqueue_script('faq-js', $faq_js, [], null, true);
        }

        // Video sections (below the fold — deferred)
        foreach (['video-v1', 'video-v2'] as $section) {
            $vite->get_deferred_style('section-' . $section, 'src/scss/templates/' . $section . '.scss');
        }

        // Video JS (poster-to-iframe + lightbox)
        $video1_js = $vite->get_asset('src/ts/video-v1.ts');
        if ($video1_js) {
            wp_enqueue_script('video-v1-js', $video1_js, [], null, true);
        }
        $video2_js = $vite->get_asset('src/ts/video-v2.ts');
        if ($video2_js) {
            wp_enqueue_script('video-v2-js', $video2_js, [], null, true);
        }
    }

    // Wizard internal templates with stored page_sections (not Thank You/default/landing).
    $rms_internal_templates = ['pages/about-us.php', 'pages/services.php', 'pages/contact-us.php', 'pages/projects.php', 'pages/testimonials.php'];
    if (is_page_template($rms_internal_templates)) {
        echo $vite->get_critical_css('src/scss/templates/breadcrumb.scss', 'critical-breadcrumb-internal');
        foreach (rms_page_section_layouts((int) get_queried_object_id()) as $section) {
            $vite->get_deferred_style('section-' . $section, 'src/scss/templates/' . $section . '.scss');
        }
    }

    // Projects lightbox is not a page_sections layout.
    if (is_page_template('pages/projects.php')) {
        $vite->get_deferred_style('lightbox', 'src/scss/components/lightbox.scss');
        $lightbox_js = $vite->get_asset('src/ts/lightbox.ts');
        if ($lightbox_js) {
            wp_enqueue_script('lightbox-js', $lightbox_js, [], null, true);
        }
    }

    // Contact map chrome sits outside the flexible loop.
    if (is_page_template('pages/contact-us.php')) {
        $vite->get_deferred_style('section-contact-map', 'src/scss/templates/contact-map.scss');
    }

    
    // Thank You page — breadcrumb + thank-you + blog-v1
    if (is_page_template('pages/thank-you.php')) {
        echo $vite->get_critical_css('src/scss/templates/breadcrumb.scss', 'critical-breadcrumb-about-us');
        $vite->get_deferred_style('section-thank-you', 'src/scss/templates/thank-you.scss');
        $vite->get_deferred_style('section-blog-v1', 'src/scss/templates/blog-v1.scss');
    }

    // Posts index (`page_for_posts` → home.php). Ignore pages/blog.php page templates.
    if (is_home() && !is_front_page()) {
        echo $vite->get_critical_css('src/scss/templates/breadcrumb.scss', 'critical-breadcrumb-blog-index');
        foreach (rms_page_section_layouts((int) get_option('page_for_posts')) as $section) {
            $vite->get_deferred_style('section-' . $section, 'src/scss/templates/' . $section . '.scss');
        }
    }

    // Regular page using the leftover Blog template (not the posts index).
    if (is_page_template('pages/blog.php') && !is_home()) {
        echo $vite->get_critical_css('src/scss/templates/breadcrumb.scss', 'critical-breadcrumb-about-us');
        $vite->get_deferred_style('section-blog-listing', 'src/scss/templates/blog-listing.scss');
    }

    // Single post — breadcrumb + single-post
    if (is_single()) {
        echo $vite->get_critical_css('src/scss/templates/breadcrumb.scss', 'critical-breadcrumb-single');
        $vite->get_deferred_style('section-single-post', 'src/scss/templates/single-post.scss');
    }

    // SEO Landing Page template
    if (is_page_template('pages/landing-page.php')) {
        echo $vite->get_critical_css('src/scss/templates/breadcrumb.scss', 'critical-breadcrumb-slim');
        // All sections used by landing page — deferred
        foreach (['hero', 'seo-content', 'vision-mission-v1', 'badges', 'portfolio-v1', 'testimonials-v1'] as $section) {
            $vite->get_deferred_style('section-' . $section, 'src/scss/templates/' . $section . '.scss');
        }
    }

     // Header — loaded as separate <link> (not inline)
     $header_version = sanitize_key(rms_get_option('company_header_version') ?: 'header-one');
     $header_css = $vite->get_asset("src/scss/layout/{$header_version}.scss");
     if ($header_css) {
         wp_enqueue_style($header_version, $header_css, [], null);
     }

     // Header menu JS
     $menu_js = $vite->get_asset("src/ts/{$header_version}-menu.ts");
     if ($menu_js) {
         wp_enqueue_script("{$header_version}-menu", $menu_js, [], null, true);
     }

    // Footer — deferred
    $footer_version = rms_get_footer_version();
    if ($footer_version !== '') {
        $vite->get_deferred_style("layout-{$footer_version}", "src/scss/layout/{$footer_version}.scss");
    }
    ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<?php get_template_part("templates/{$header_version}"); ?>
