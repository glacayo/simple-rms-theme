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
        echo $vite->get_critical_css('src/scss/sections/hero.scss', 'critical-hero');

        // Above the fold — eager load
        foreach (['slider', 'badges'] as $section) {
            $css = $vite->get_asset('src/scss/sections/' . $section . '.scss');
            if ($css) {
                wp_enqueue_style('section-' . $section, $css, [], null);
            }
        }

        // Below the fold — deferred (non-render-blocking)
        foreach (['about-us', 'services-v1', 'services-v2', 'services-v3', 'cta-v1', 'cta-v2', 'cta-v3', 'portfolio-v1', 'portfolio-v2', 'portfolio-v3', 'testimonials-v1', 'testimonials-v2', 'testimonials-v3', 'blog-v1', 'area-coverage-v1', 'vision-mission-v1', 'vision-mission-v2', 'seo-content'] as $section) {
            $vite->get_deferred_style('section-' . $section, 'src/scss/sections/' . $section . '.scss');
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
    }

    // Internal page: About Us — breadcrumb above the fold
    if (is_page_template('pages/about-us.php')) {
        echo $vite->get_critical_css('src/scss/sections/breadcrumb-about-us.scss', 'critical-breadcrumb-about-us');
    }

    // Internal page: Services — breadcrumb + services section (below the fold, deferred)
    if (is_page_template('pages/services.php')) {
        echo $vite->get_critical_css('src/scss/sections/breadcrumb-about-us.scss', 'critical-breadcrumb-about-us');
        $vite->get_deferred_style('section-services-page', 'src/scss/sections/services-page.scss');
        $vite->get_deferred_style('section-cta-v2', 'src/scss/sections/cta-v2.scss');
    }

    // Internal page: Projects / Gallery — breadcrumb + gallery grid + lightbox
    if (is_page_template('pages/projects.php')) {
        echo $vite->get_critical_css('src/scss/sections/breadcrumb-about-us.scss', 'critical-breadcrumb-about-us');
        $vite->get_deferred_style('section-gallery-grid', 'src/scss/sections/gallery-grid.scss');
        $vite->get_deferred_style('lightbox', 'src/scss/components/lightbox.scss');
        $lightbox_js = $vite->get_asset('src/ts/lightbox.ts');
        if ($lightbox_js) {
            wp_enqueue_script('lightbox-js', $lightbox_js, [], null, true);
        }
    }

    // Internal page: Contact Us — breadcrumb + contact info + contact map
    if (is_page_template('pages/contact-us.php')) {
        echo $vite->get_critical_css('src/scss/sections/breadcrumb-about-us.scss', 'critical-breadcrumb-about-us');
        $vite->get_deferred_style('section-contact-info', 'src/scss/sections/contact-info.scss');
        $vite->get_deferred_style('section-contact-map', 'src/scss/sections/contact-map.scss');
    }

    // Internal page: Blog — breadcrumb + blog listing
    if (is_page_template('pages/blog.php')) {
        echo $vite->get_critical_css('src/scss/sections/breadcrumb-about-us.scss', 'critical-breadcrumb-about-us');
        $vite->get_deferred_style('section-blog-listing', 'src/scss/sections/blog-listing.scss');
    }

     // Header — loaded as separate <link> (not inline)
     $header_css = $vite->get_asset('src/scss/layout/header-one.scss');
     if ($header_css) {
         wp_enqueue_style('header-one', $header_css, [], null);
     }
     
     // Header menu JS
     $menu_js = $vite->get_asset('src/ts/header-one-menu.ts');
     if ($menu_js) {
         wp_enqueue_script('header-one-menu', $menu_js, [], null, true);
     }

    // Footer — deferred
    $vite->get_deferred_style('layout-footer-v2', 'src/scss/layout/footer-v2.scss');
    ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<?php get_template_part('templates/header-one'); ?>
