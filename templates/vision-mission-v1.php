<?php
$eyebrow  = get_sub_field('vm_v1_eyebrow') ?: 'Who We Are';
$headline = get_sub_field('vm_v1_headline') ?: 'Our Vision, Mission & Why Homeowners Trust Us';
$intro    = get_sub_field('vm_v1_intro') ?: 'We built this company on a simple promise: protect every home like it\'s our own. Here\'s what drives us every day.';
$cards    = get_sub_field('vm_v1_cards');
$cta_text = get_sub_field('vm_v1_cta_text') ?: 'Get Your Free Estimate';
$cta_url  = get_sub_field('vm_v1_cta_url') ?: '#contact';
?>
<!-- Vision Mission V1 — Three-Card Trust Section -->
<section class="vision-mission-v1" aria-labelledby="vision-mission-v1-heading">
    <div class="container">
        <p class="vision-mission-v1__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
        <h2 id="vision-mission-v1-heading" class="vision-mission-v1__headline"><?php echo esc_html( $headline ); ?></h2>
        <p class="vision-mission-v1__intro"><?php echo wp_kses_post( $intro ); ?></p>

        <div class="vision-mission-v1__grid">
            <?php if ( ! empty( $cards ) ) : ?>
                <?php foreach ( $cards as $card ) :
                    $card_title     = $card['card_title'] ?? '';
                    $card_text      = $card['card_text'] ?? '';
                    $card_highlight = ! empty( $card['card_highlight'] );
                    $card_class     = 'vision-mission-v1__card' . ( $card_highlight ? ' vision-mission-v1__card--highlight' : '' );
                ?>
                <article class="<?php echo esc_attr( $card_class ); ?>">
                    <span class="vision-mission-v1__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                    </span>
                    <h3 class="vision-mission-v1__title"><?php echo esc_html( $card_title ); ?></h3>
                    <p class="vision-mission-v1__text"><?php echo wp_kses_post( $card_text ); ?></p>
                </article>
                <?php endforeach; ?>
            <?php else : ?>
                <!-- Fallback hardcoded cards -->
                <article class="vision-mission-v1__card">
                    <span class="vision-mission-v1__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                    </span>
                    <h3 class="vision-mission-v1__title">Our Vision</h3>
                    <p class="vision-mission-v1__text">To become the most trusted roofing and exterior contractor in our region by delivering exceptional craftsmanship, honest communication, and lasting value for every client.</p>
                </article>

                <article class="vision-mission-v1__card">
                    <span class="vision-mission-v1__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
                    </span>
                    <h3 class="vision-mission-v1__title">Our Mission</h3>
                    <p class="vision-mission-v1__text">Our mission is to protect homes and businesses through dependable service, premium materials, and solutions built to perform for years to come.</p>
                </article>

                <article class="vision-mission-v1__card vision-mission-v1__card--highlight">
                    <span class="vision-mission-v1__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                    </span>
                    <h3 class="vision-mission-v1__title">Why Choose Us</h3>
                    <p class="vision-mission-v1__text">Homeowners choose us because we deliver transparent estimates, licensed and insured workmanship, premium materials, reliable turnaround times, and long-term warranty-backed results.</p>
                </article>
            <?php endif; ?>
        </div>

        <div class="vision-mission-v1__cta-wrap">
            <a class="btn vision-mission-v1__cta" href="<?php echo esc_url( $cta_url ); ?>" aria-label="<?php echo esc_attr( $cta_text . ' from our team' ); ?>"><?php echo esc_html( $cta_text ); ?></a>
        </div>
    </div>
</section>
