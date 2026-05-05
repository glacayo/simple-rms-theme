<?php
/**
 * Blog V1 Section Template
 *
 * @package Simple_RMS_Theme
 */

$headline = get_sub_field( 'blog_headline' ) ?: 'Latest Blog Posts';
$cta_text = get_sub_field( 'blog_cta_text' ) ?: 'Read More Blog Posts';
$cta_url  = get_sub_field( 'blog_cta_url' ) ?: '#';
?>
<!-- Blog V1 — Featured Post + Grid Cards -->
<section class="blog-v1" aria-labelledby="blog-v1-heading">
    <div class="container">
        <h2 id="blog-v1-heading" class="blog-v1__headline"><?php echo esc_html( $headline ); ?></h2>

        <div class="blog-v1__layout grid-2">
            <article class="blog-v1__featured">
                <a href="#" class="blog-v1__featured-media" aria-label="Read How to Spot Roof Damage Before It Gets Worse">
                    <img
                        src="https://placehold.co/600x400"
                        alt="Inspector checking a residential roof for damage"
                        width="600"
                        height="400"
                        loading="lazy"
                        decoding="async"
                    >
                </a>
                <div class="blog-v1__featured-body">
                    <p class="blog-v1__featured-meta">
                        <span>March 12, 2026</span>
                        <span aria-hidden="true">•</span>
                        <span>Roof Repair</span>
                    </p>
                    <h3 class="blog-v1__featured-title">How to Spot Roof Damage Before It Gets Worse</h3>
                    <p class="blog-v1__featured-excerpt">Early signs of damage can save you thousands. Learn what to look for before problems spread.</p>
                    <a href="#" class="blog-v1__read-more">Read More</a>
                </div>
            </article>

            <div class="blog-v1__grid grid-2">
                <article class="blog-v1__card">
                    <a href="#" class="blog-v1__card-media" aria-label="Read 5 Reasons to Schedule an Annual Roof Inspection">
                        <img
                            src="https://placehold.co/300x200"
                            alt="Roof specialist reviewing annual inspection checklist"
                            width="300"
                            height="200"
                            loading="lazy"
                            decoding="async"
                        >
                    </a>
                    <div class="blog-v1__card-body">
                        <h4 class="blog-v1__card-title">5 Reasons to Schedule an Annual Roof Inspection</h4>
                        <p class="blog-v1__card-meta">March 8, 2026</p>
                    </div>
                </article>

                <article class="blog-v1__card">
                    <a href="#" class="blog-v1__card-media" aria-label="Read Residential vs Commercial Roofing: Key Differences">
                        <img
                            src="https://placehold.co/300x200"
                            alt="Split view of residential and commercial roofing materials"
                            width="300"
                            height="200"
                            loading="lazy"
                            decoding="async"
                        >
                    </a>
                    <div class="blog-v1__card-body">
                        <h4 class="blog-v1__card-title">Residential vs Commercial Roofing: Key Differences</h4>
                        <p class="blog-v1__card-meta">March 3, 2026</p>
                    </div>
                </article>

                <article class="blog-v1__card">
                    <a href="#" class="blog-v1__card-media" aria-label="Read What to Do After Storm Damage Hits Your Property">
                        <img
                            src="https://placehold.co/300x200"
                            alt="Storm-damaged roof with emergency tarp preparation"
                            width="300"
                            height="200"
                            loading="lazy"
                            decoding="async"
                        >
                    </a>
                    <div class="blog-v1__card-body">
                        <h4 class="blog-v1__card-title">What to Do After Storm Damage Hits Your Property</h4>
                        <p class="blog-v1__card-meta">February 26, 2026</p>
                    </div>
                </article>

                <article class="blog-v1__card">
                    <a href="#" class="blog-v1__card-media" aria-label="Read Choosing the Right Gutter System for Your Home">
                        <img
                            src="https://placehold.co/300x200"
                            alt="New gutter system installed along home roofline"
                            width="300"
                            height="200"
                            loading="lazy"
                            decoding="async"
                        >
                    </a>
                    <div class="blog-v1__card-body">
                        <h4 class="blog-v1__card-title">Choosing the Right Gutter System for Your Home</h4>
                        <p class="blog-v1__card-meta">February 18, 2026</p>
                    </div>
                </article>
            </div>
        </div>

        <div class="blog-v1__cta-wrap">
            <a href="<?php echo esc_url( $cta_url ); ?>" class="btn blog-v1__cta"><?php echo esc_html( $cta_text ); ?></a>
        </div>
    </div>
</section>
