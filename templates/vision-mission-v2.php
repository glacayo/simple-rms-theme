<?php
$eyebrow  = get_sub_field('vm_v2_eyebrow') ?: 'Our Foundation';
$headline = get_sub_field('vm_v2_headline') ?: 'Built on Principle. Driven by Purpose.';
$vision   = get_sub_field('vm_v2_vision_text');
$mission  = get_sub_field('vm_v2_mission_text');
$reasons  = get_sub_field('vm_v2_reasons');
$cta_text = get_sub_field('vm_v2_cta_text') ?: 'Request a Consultation';
$cta_url  = get_sub_field('vm_v2_cta_url') ?: '#contact';
?>
<!-- Vision Mission V2 — Split Layout with Emphasis Panel -->
<section class="vision-mission-v2" aria-labelledby="vision-mission-v2-heading">
    <div class="container">
        <div class="vision-mission-v2__layout">
            <div class="vision-mission-v2__content">
                <p class="vision-mission-v2__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
                <h2 id="vision-mission-v2-heading" class="vision-mission-v2__headline"><?php echo esc_html( $headline ); ?></h2>

                <div class="vision-mission-v2__block">
                    <h3 class="vision-mission-v2__label">Vision</h3>
                    <?php if ( $vision ) : ?>
                        <p class="vision-mission-v2__text"><?php echo wp_kses_post( $vision ); ?></p>
                    <?php else : ?>
                        <p class="vision-mission-v2__text">To become the most trusted roofing and exterior contractor in our region by delivering exceptional craftsmanship, honest communication, and lasting value for every client.</p>
                    <?php endif; ?>
                </div>

                <div class="vision-mission-v2__divider" aria-hidden="true"></div>

                <div class="vision-mission-v2__block">
                    <h3 class="vision-mission-v2__label">Mission</h3>
                    <?php if ( $mission ) : ?>
                        <p class="vision-mission-v2__text"><?php echo wp_kses_post( $mission ); ?></p>
                    <?php else : ?>
                        <p class="vision-mission-v2__text">Our mission is to protect homes and businesses through dependable service, premium materials, and solutions built to perform for years to come.</p>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="vision-mission-v2__panel" aria-label="Reasons to choose our company">
                <h3 class="vision-mission-v2__panel-title">Why Choose Us</h3>
                <ul class="vision-mission-v2__list">
                    <?php
                    $license = rms_get_option('company_license');
                    if ( $license ) : ?>
                    <li class="vision-mission-v2__list-item">
                        <span class="vision-mission-v2__check" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="m9 11 3 3L22 4"/></svg>
                        </span>
                        <?php echo esc_html( $license ); ?>
                    </li>
                    <?php endif; ?>

                    <?php if ( ! empty( $reasons ) ) : ?>
                        <?php foreach ( $reasons as $reason ) :
                            $reason_text = $reason['reason_text'] ?? '';
                        ?>
                        <li class="vision-mission-v2__list-item">
                            <span class="vision-mission-v2__check" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="m9 11 3 3L22 4"/></svg>
                            </span>
                            <?php echo esc_html( $reason_text ); ?>
                        </li>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <!-- Fallback hardcoded reasons -->
                        <li class="vision-mission-v2__list-item">
                            <span class="vision-mission-v2__check" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="m9 11 3 3L22 4"/></svg>
                            </span>
                            Premium Materials Only
                        </li>
                        <li class="vision-mission-v2__list-item">
                            <span class="vision-mission-v2__check" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="m9 11 3 3L22 4"/></svg>
                            </span>
                            Fast Turnaround Times
                        </li>
                        <li class="vision-mission-v2__list-item">
                            <span class="vision-mission-v2__check" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="m9 11 3 3L22 4"/></svg>
                            </span>
                            Transparent Estimates
                        </li>
                        <li class="vision-mission-v2__list-item">
                            <span class="vision-mission-v2__check" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="m9 11 3 3L22 4"/></svg>
                            </span>
                            Local Experience You Can Trust
                        </li>
                        <li class="vision-mission-v2__list-item">
                            <span class="vision-mission-v2__check" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="m9 11 3 3L22 4"/></svg>
                            </span>
                            Work Backed by Warranty
                        </li>
                    <?php endif; ?>
                </ul>
                <a class="btn vision-mission-v2__cta" href="<?php echo esc_url( $cta_url ); ?>" aria-label="<?php echo esc_attr( $cta_text ); ?>"><?php echo esc_html( $cta_text ); ?></a>
            </aside>
        </div>
    </div>
</section>
