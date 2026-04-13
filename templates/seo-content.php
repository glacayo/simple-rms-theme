<!-- SEO Content — Reusable Two-Column Section -->
<!--
  Modifier classes available:
    .seo-content--reverse         → swap columns (image left / text right)
    .seo-content--bg-light        → light gray background ($color-gray-50)
    .seo-content--bg-dark         → dark background ($color-gray-900)
    .seo-content--has-bg-image    → background image via CSS variable --seo-bg-image
    .seo-content--overlay-dark    → dark overlay on top of background image

  CSS custom properties:
    --seo-bg-image: url('...')    → background image when using .seo-content--has-bg-image
    --seo-bg-color: #hex          → override solid background color

  Heading block is optional — remove .seo-content__header entirely and the section still works.

  Usage examples:
    1. Default light background:
       <section class="seo-content seo-content--bg-light">
    2. Reversed columns, dark background:
       <section class="seo-content seo-content--reverse seo-content--bg-dark">
    3. Background image with overlay:
       <section class="seo-content seo-content--has-bg-image seo-content--overlay-dark" style="--seo-bg-image: url('https://placehold.co/1600x900');">
-->
<section class="seo-content seo-content--bg-light" aria-labelledby="seo-content-heading">
    <div class="container">

        <!-- Optional heading block — remove this div if no heading is needed -->
        <div class="seo-content__header">
            <h2 id="seo-content-heading" class="seo-content__headline">Content Section Built for SEO and Flexibility</h2>
            <h3 class="seo-content__subheadline">Reusable layout prepared for ACF integration</h3>
        </div>

        <div class="seo-content__grid grid-2">
            <!-- Text column -->
            <div class="seo-content__text">
                <p>Our team brings decades of combined experience to every roofing project we take on. From initial inspection to final cleanup, we follow a proven process designed to protect your investment and keep your property secure for years to come.</p>

                <p>We specialize in residential and commercial roofing systems, including asphalt shingles, metal roofing, flat roof membranes, and tile installations. Every material we use meets or exceeds industry standards for durability, weather resistance, and energy efficiency.</p>

                <ul class="seo-content__list">
                    <li>Licensed, insured, and locally owned</li>
                    <li>Free inspections and transparent estimates</li>
                    <li>Premium materials backed by manufacturer warranties</li>
                    <li>Storm damage repair and insurance claim assistance</li>
                </ul>

                <p>Whether you need a full roof replacement, emergency leak repair, or routine maintenance, we deliver reliable results with clear communication at every step.</p>
            </div>

            <!-- Image column -->
            <div class="seo-content__media">
                <img
                    class="seo-content__image"
                    src="https://placehold.co/700x500"
                    alt="Professional roofing team working on a residential project"
                    width="700"
                    height="500"
                    loading="lazy"
                >
            </div>
        </div>

    </div>
</section>
