<?php
/**
 * Template Part: Breadcrumb
 *
 * Reusable breadcrumb hero for the About Us internal page.
 * Uses a CSS custom property for the background image so
 * it can be swapped easily (ACF, Customizer, etc.).
 *
 * @package SimpleRMS
 */
?>

<section class="breadcrumb-page breadcrumb-page--about" style="--breadcrumb-bg: url('https://placehold.co/1920x700');">
    <div class="breadcrumb-page__overlay"></div>

    <div class="container breadcrumb-page__content">
        <h1 class="breadcrumb-page__title"><?php echo esc_html( get_the_title() ); ?></h1>

        <nav class="breadcrumb-page__nav" aria-label="Breadcrumb">
            <?php if ( function_exists( 'yoast_breadcrumb' ) ) : ?>
                <?php yoast_breadcrumb( '<div class="breadcrumb-page__yoast">', '</div>' ); ?>
            <?php else : ?>
                <span class="breadcrumb-page__plugin-warning">Install Plugin Needed</span>
            <?php endif; ?>
        </nav>
    </div>
</section>
