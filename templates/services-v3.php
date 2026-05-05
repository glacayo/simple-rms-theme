<?php
$headline    = get_sub_field('services_v3_headline') ?: 'Services We Specialize In';
$subheadline = get_sub_field('services_v3_subheadline') ?: 'Hover to Discover More';
$services    = get_sub_field('services_v3_services');
$cta_text    = get_sub_field('services_v3_cta_text') ?: 'See All Services';
$cta_url     = get_sub_field('services_v3_cta_url') ?: '#';
?>
<!-- Services V3 -- Hover Reveal Boxes with Background Images -->
<section class="services-v3">
    <div class="container">
        <h2 class="services-v3__headline"><?php echo esc_html($headline); ?></h2>
        <h3 class="services-v3__subheadline"><?php echo esc_html($subheadline); ?></h3>

        <div class="grid-3">
            <?php if ($services) : ?>
                <?php foreach ($services as $service) : ?>
                    <div class="services-v3__box">
                        <img class="services-v3__bg" src="<?php echo esc_url($service['service_image']); ?>" alt="" width="400" height="400" decoding="async" loading="lazy">
                        <span class="services-v3__name"><?php echo esc_html($service['service_name']); ?></span>
                        <div class="services-v3__overlay">
                            <span class="services-v3__overlay-name"><?php echo esc_html($service['service_name']); ?></span>
                            <p class="services-v3__overlay-text"><?php echo esc_html($service['service_overlay_text']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="services-v3__box">
                    <img class="services-v3__bg" src="https://placehold.co/400x400" alt="" width="400" height="400" decoding="async" loading="lazy">
                    <span class="services-v3__name">Roof Installation</span>
                    <div class="services-v3__overlay">
                        <span class="services-v3__overlay-name">Roof Installation</span>
                        <p class="services-v3__overlay-text">Complete new roof installation using premium materials with industry-leading warranties.</p>
                    </div>
                </div>
                <div class="services-v3__box">
                    <img class="services-v3__bg" src="https://placehold.co/400x400" alt="" width="400" height="400" decoding="async" loading="lazy">
                    <span class="services-v3__name">Roof Repair</span>
                    <div class="services-v3__overlay">
                        <span class="services-v3__overlay-name">Roof Repair</span>
                        <p class="services-v3__overlay-text">Fast and reliable repairs for leaks, storm damage, and wear before they become costly problems.</p>
                    </div>
                </div>
                <div class="services-v3__box">
                    <img class="services-v3__bg" src="https://placehold.co/400x400" alt="" width="400" height="400" decoding="async" loading="lazy">
                    <span class="services-v3__name">Roof Replacement</span>
                    <div class="services-v3__overlay">
                        <span class="services-v3__overlay-name">Roof Replacement</span>
                        <p class="services-v3__overlay-text">Full roof replacement services with minimal disruption and maximum long-term protection.</p>
                    </div>
                </div>
                <div class="services-v3__box">
                    <img class="services-v3__bg" src="https://placehold.co/400x400" alt="" width="400" height="400" decoding="async" loading="lazy">
                    <span class="services-v3__name">Roof Inspection</span>
                    <div class="services-v3__overlay">
                        <span class="services-v3__overlay-name">Roof Inspection</span>
                        <p class="services-v3__overlay-text">Thorough inspections to identify issues early and extend the life of your existing roof.</p>
                    </div>
                </div>
                <div class="services-v3__box">
                    <img class="services-v3__bg" src="https://placehold.co/400x400" alt="" width="400" height="400" decoding="async" loading="lazy">
                    <span class="services-v3__name">Gutter Installation</span>
                    <div class="services-v3__overlay">
                        <span class="services-v3__overlay-name">Gutter Installation</span>
                        <p class="services-v3__overlay-text">Professional gutter systems that protect your foundation from water damage year-round.</p>
                    </div>
                </div>
                <div class="services-v3__box">
                    <img class="services-v3__bg" src="https://placehold.co/400x400" alt="" width="400" height="400" decoding="async" loading="lazy">
                    <span class="services-v3__name">Emergency Services</span>
                    <div class="services-v3__overlay">
                        <span class="services-v3__overlay-name">Emergency Services</span>
                        <p class="services-v3__overlay-text">24/7 emergency response for storm damage and urgent roofing needs when you need us most.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="services-v3__cta">
            <a href="<?php echo esc_url($cta_url); ?>" class="btn services-v3__cta-btn"><?php echo esc_html($cta_text); ?></a>
        </div>
    </div>
</section>
