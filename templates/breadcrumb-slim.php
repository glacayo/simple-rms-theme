<?php
/**
 * Template Part: Breadcrumb — Slim Style
 *
 * Solid-background slim breadcrumb bar for SEO landing pages.
 * No background image, no overlay — just a compact centered breadcrumb row.
 */
?>
<section class="breadcrumb-page breadcrumb-page--slim">
    <div class="container breadcrumb-page__content">

        <nav class="breadcrumb-page__nav" aria-label="Breadcrumb">
            <?php if ( function_exists( 'yoast_breadcrumb' ) ) : ?>
                <?php yoast_breadcrumb( '<div class="breadcrumb-page__yoast">', '</div>' ); ?>
            <?php else : ?>
                <span class="breadcrumb-page__plugin-warning">Install Plugin Needed</span>
            <?php endif; ?>
        </nav>
    </div>
</section>
