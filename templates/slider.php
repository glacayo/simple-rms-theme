<!-- Hero Slider — CSS Scroll Snap (zero JS) -->
<section class="slider" aria-label="Hero slider">
    <div class="slider__track">

        <!-- Slide 1 — only h1 on the page -->
        <div class="slider__slide" style="--slide-bg: url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1920&q=80');">
            <div class="slider__overlay slider__overlay--dark"></div>
            <div class="slider__content container">
                <p class="slider__subheadline">Trusted Roofing Experts</p>
                <h1 class="slider__headline">Protecting What Matters Most to Your Family</h1>
                <p class="slider__text">We deliver premium roofing solutions with unmatched craftsmanship and industry-leading warranties. Our certified team handles everything from minor repairs to complete roof replacements with precision and care every time.</p>
                <a href="#estimate" class="slider__cta">Get Free Estimate</a>
            </div>
        </div>

        <!-- Slide 2 -->
        <div class="slider__slide" style="--slide-bg: url('https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=1920&q=80');">
            <div class="slider__overlay slider__overlay--dark"></div>
            <div class="slider__content container">
                <p class="slider__subheadline">Quality You Can Count On</p>
                <h2 class="slider__headline">Residential Roofing Built to Last a Lifetime</h2>
                <p class="slider__text">From asphalt shingles to metal roofing, we use only the highest quality materials installed by experienced professionals. Every project comes with comprehensive warranties and a commitment to your complete satisfaction.</p>
                <a href="#services" class="slider__cta">Explore Services</a>
            </div>
        </div>

        <!-- Slide 3 -->
        <div class="slider__slide" style="--slide-bg: url('https://images.unsplash.com/photo-1558618666-fcd25c85f82e?w=1920&q=80');">
            <div class="slider__overlay slider__overlay--dark"></div>
            <div class="slider__content container">
                <p class="slider__subheadline">Emergency Response</p>
                <h2 class="slider__headline">Storm Damage Repair Available 24/7</h2>
                <p class="slider__text">When disaster strikes, our emergency team responds fast to protect your property from further damage. We work directly with insurance companies to streamline your claim and get your roof restored quickly.</p>
                <a href="#contact" class="slider__cta">Call Now</a>
            </div>
        </div>

    </div>

    <!-- Scroll indicators -->
    <div class="slider__dots" aria-hidden="true">
        <span class="slider__dot slider__dot--active"></span>
        <span class="slider__dot"></span>
        <span class="slider__dot"></span>
    </div>

    <!-- Bottom shape — transitions to next section -->
    <div class="slider__shape" aria-hidden="true">
        <svg viewBox="0 0 1440 120" preserveAspectRatio="none">
            <path d="M0,80 C360,120 720,0 1080,80 C1260,120 1380,40 1440,80 L1440,120 L0,120 Z" fill="#0f172a"/>
        </svg>
    </div>
</section>
