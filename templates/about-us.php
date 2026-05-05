<?php
/**
 * About Us Section Template
 *
 * @package Simple_RMS_Theme
 */

$headline    = get_sub_field( 'about_headline' ) ?: 'We Are The Best Roofing Company';
$subheadline = get_sub_field( 'about_subheadline' ) ?: 'Trusted by Homeowners Across the Region';
$text        = get_sub_field( 'about_text' );
$image       = get_sub_field( 'about_image' ) ?: 'https://placehold.co/600x600';
$badge_years = get_sub_field( 'about_badge_years' ) ?: '25';
$badge_label = get_sub_field( 'about_badge_label' ) ?: 'Years Of Experience';
?>
<!-- About Us Section -->
<section class="about-us">
    <div class="container">
        <div class="grid-2">

            <!-- Left Column -->
            <div class="about-us__col-left">
                <h2 class="about-us__headline"><?php echo esc_html( $headline ); ?></h2>
                <h3 class="about-us__subheadline"><?php echo esc_html( $subheadline ); ?></h3>
                <?php if ( $text ) : ?>
                    <?php echo wp_kses_post( $text ); ?>
                <?php else : ?>
                    <p class="about-us__text">With decades of hands-on experience, our team delivers roofing solutions built to last. We combine premium materials with expert craftsmanship to protect your home against every season. Every project reflects our commitment to quality and attention to detail.</p>
                    <p class="about-us__text">Homeowners trust us because we stand behind our work. From the initial inspection to the final nail, we communicate clearly, show up on time, and treat your property with respect. Our reputation is built on referrals and repeat customers who know we deliver results.</p>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div class="about-us__col-right">
                <div class="about-us__image-wrapper">
                    <img class="about-us__image" src="<?php echo esc_url( $image ); ?>" alt="Professional roofing team working on a residential roof" width="600" height="600" decoding="async" loading="lazy">
                    <div class="about-us__badge">
                        <span class="about-us__badge-years"><?php echo esc_html( $badge_years ); ?></span>
                        <span class="about-us__badge-label"><?php echo esc_html( $badge_label ); ?></span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
