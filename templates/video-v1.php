<!-- Video V1 — Video Hero con Poster y Play Button -->
<section class="video-v1" aria-labelledby="video-v1-heading">
    <div class="container">
        <div class="video-v1__header">
            <h2 id="video-v1-heading" class="video-v1__headline">See Our Work in Action</h2>
            <h3 class="video-v1__subheadline">Real Projects, Real Results</h3>
        </div>

        <div class="video-v1__wrapper">
            <div class="video-v1__player" id="video-v1-player">
                <!-- Poster image shown until play -->
                <img
                    class="video-v1__poster"
                    src="https://placehold.co/1280x720/1a1a2e/ffffff?text=Roofing+Project+Video"
                    alt="Video thumbnail showing a completed roofing project"
                    width="1280"
                    height="720"
                    loading="lazy"
                >
                <div class="video-v1__overlay"></div>

                <!-- Play button -->
                <button class="video-v1__play-btn" aria-label="Play video" id="video-v1-play-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <polygon points="5 3 19 12 5 21 5 3"/>
                    </svg>
                </button>

                <!-- Video info overlay -->
                <div class="video-v1__meta">
                    <span class="video-v1__duration">1:30</span>
                    <span class="video-v1__title">Complete Roof Replacement — Milwaukee Home</span>
                </div>

                <!-- Actual iframe (hidden until play) -->
                <div class="video-v1__iframe-wrapper" hidden id="video-v1-iframe-wrapper">
                    <!--
                        TODO: Replace VIDEO_ID with actual YouTube video ID.
                        Example: dQw4w9WgXcQ
                        Format: https://www.youtube.com/embed/VIDEO_ID?autoplay=1
                    -->
                    <iframe
                        class="video-v1__iframe"
                        id="video-v1-iframe"
                        src=""
                        data-src="https://www.youtube.com/embed/VIDEO_ID?autoplay=1&rel=0"
                        title="Raven Roofing — Complete Roof Replacement Project"
                        width="1280"
                        height="720"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen
                        loading="lazy"
                    ></iframe>
                </div>
            </div>

            <!-- Video description -->
            <div class="video-v1__description">
                <p>Watch how our team transformed this Milwaukee home with a complete roof replacement. From initial inspection to final cleanup, see the quality craftsmanship and attention to detail that sets us apart.</p>
                <a href="#" class="btn video-v1__cta">Schedule Your Free Inspection</a>
            </div>
        </div>
    </div>
</section>
