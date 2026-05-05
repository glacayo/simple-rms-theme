<?php
$headline    = get_sub_field('services_v2_headline') ?: 'What We Offer';
$subheadline = get_sub_field('services_v2_subheadline') ?: 'Expert Services You Can Rely On';
$services    = get_sub_field('services_v2_services');
$cta_text    = get_sub_field('services_v2_cta_text') ?: 'Explore Our Services';
$cta_url     = get_sub_field('services_v2_cta_url') ?: '#';
?>
<!-- Services V2 -- Image Boxes on Solid Background -->
<section class="services-v2">
    <div class="container">
        <h2 class="services-v2__headline"><?php echo esc_html($headline); ?></h2>
        <h3 class="services-v2__subheadline"><?php echo esc_html($subheadline); ?></h3>

        <div class="grid-3">
            <?php if ($services) : ?>
                <?php foreach ($services as $service) : ?>
                    <div class="services-v2__box">
                        <img class="services-v2__image" src="<?php echo esc_url($service['service_image']); ?>" alt="<?php echo esc_attr($service['service_title']); ?> service" width="400" height="250" decoding="async" loading="lazy">
                        <h4 class="services-v2__box-title"><?php echo esc_html($service['service_title']); ?></h4>
                        <p class="services-v2__box-text"><?php echo esc_html($service['service_text']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="services-v2__box">
                    <img class="services-v2__image" src="https://placehold.co/400x250" alt="Roof Installation service" width="400" height="250" decoding="async" loading="lazy">
                    <h4 class="services-v2__box-title">Roof Installation</h4>
                    <p class="services-v2__box-text">Complete new roof installation using premium materials with industry-leading warranties.</p>
                </div>
                <div class="services-v2__box">
                    <img class="services-v2__image" src="https://placehold.co/400x250" alt="Roof Repair service" width="400" height="250" decoding="async" loading="lazy">
                    <h4 class="services-v2__box-title">Roof Repair</h4>
                    <p class="services-v2__box-text">Fast and reliable repairs for leaks, storm damage, and wear before they become costly problems.</p>
                </div>
                <div class="services-v2__box">
                    <img class="services-v2__image" src="https://placehold.co/400x250" alt="Roof Replacement service" width="400" height="250" decoding="async" loading="lazy">
                    <h4 class="services-v2__box-title">Roof Replacement</h4>
                    <p class="services-v2__box-text">Full roof replacement services with minimal disruption and maximum long-term protection.</p>
                </div>
                <div class="services-v2__box">
                    <img class="services-v2__image" src="https://placehold.co/400x250" alt="Roof Inspection service" width="400" height="250" decoding="async" loading="lazy">
                    <h4 class="services-v2__box-title">Roof Inspection</h4>
                    <p class="services-v2__box-text">Thorough inspections to identify issues early and extend the life of your existing roof.</p>
                </div>
                <div class="services-v2__box">
                    <img class="services-v2__image" src="https://placehold.co/400x250" alt="Gutter Installation service" width="400" height="250" decoding="async" loading="lazy">
                    <h4 class="services-v2__box-title">Gutter Installation</h4>
                    <p class="services-v2__box-text">Professional gutter systems that protect your foundation from water damage year-round.</p>
                </div>
                <div class="services-v2__box">
                    <img class="services-v2__image" src="https://placehold.co/400x250" alt="Emergency Services" width="400" height="250" decoding="async" loading="lazy">
                    <h4 class="services-v2__box-title">Emergency Services</h4>
                    <p class="services-v2__box-text">24/7 emergency response for storm damage and urgent roofing needs when you need us most.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="services-v2__cta">
            <a href="<?php echo esc_url($cta_url); ?>" class="btn services-v2__cta-btn"><?php echo esc_html($cta_text); ?></a>
        </div>
    </div>
</section>
