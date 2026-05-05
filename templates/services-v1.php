<?php
$headline    = get_sub_field('services_v1_headline') ?: 'Our Professional Services';
$subheadline = get_sub_field('services_v1_subheadline') ?: 'Quality Solutions for Every Need';
$bg_image    = get_sub_field('services_v1_bg_image');
$services    = get_sub_field('services_v1_services');
$cta_text    = get_sub_field('services_v1_cta_text') ?: 'View All Services';
$cta_url     = get_sub_field('services_v1_cta_url') ?: '#';
?>
<!-- Services V1 -- Cards with Icons on Image Background -->
<section class="services-v1"<?php if ($bg_image) echo ' style="--services-bg: url(\'' . esc_url($bg_image) . '\');"'; ?>>
    <div class="services-v1__overlay"></div>
    <div class="container">
        <h2 class="services-v1__headline"><?php echo esc_html($headline); ?></h2>
        <h3 class="services-v1__subheadline"><?php echo esc_html($subheadline); ?></h3>

        <div class="grid-3">
            <?php if ($services) : ?>
                <?php foreach ($services as $service) : ?>
                    <div class="services-v1__card">
                        <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                        <h4 class="services-v1__card-title"><?php echo esc_html($service['service_title']); ?></h4>
                        <p class="services-v1__card-text"><?php echo esc_html($service['service_text']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="services-v1__card">
                    <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    <h4 class="services-v1__card-title">Roof Installation</h4>
                    <p class="services-v1__card-text">Complete new roof installation using premium materials with industry-leading warranties.</p>
                </div>
                <div class="services-v1__card">
                    <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    <h4 class="services-v1__card-title">Roof Repair</h4>
                    <p class="services-v1__card-text">Fast and reliable repairs for leaks, storm damage, and wear before they become costly problems.</p>
                </div>
                <div class="services-v1__card">
                    <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    <h4 class="services-v1__card-title">Roof Replacement</h4>
                    <p class="services-v1__card-text">Full roof replacement services with minimal disruption and maximum long-term protection.</p>
                </div>
                <div class="services-v1__card">
                    <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    <h4 class="services-v1__card-title">Roof Inspection</h4>
                    <p class="services-v1__card-text">Thorough inspections to identify issues early and extend the life of your existing roof.</p>
                </div>
                <div class="services-v1__card">
                    <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    <h4 class="services-v1__card-title">Gutter Installation</h4>
                    <p class="services-v1__card-text">Professional gutter systems that protect your foundation from water damage year-round.</p>
                </div>
                <div class="services-v1__card">
                    <span class="services-v1__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2 5 5 0 0 1-5 5 5 5 0 0 1-5-5 2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    <h4 class="services-v1__card-title">Emergency Services</h4>
                    <p class="services-v1__card-text">24/7 emergency response for storm damage and urgent roofing needs when you need us most.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="services-v1__cta">
            <a href="<?php echo esc_url($cta_url); ?>" class="btn btn--outline-white services-v1__cta-btn"><?php echo esc_html($cta_text); ?></a>
        </div>
    </div>
</section>
