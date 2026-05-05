<?php
$headline    = get_sub_field('video_v1_headline') ?: 'See Our Work in Action';
$subheadline = get_sub_field('video_v1_subheadline') ?: 'Real Projects, Real Results';
$poster      = get_sub_field('video_v1_poster');
$video_url   = get_sub_field('video_v1_video_url');
$duration    = get_sub_field('video_v1_duration') ?: '1:30';
$video_title = get_sub_field('video_v1_video_title') ?: 'Complete Roof Replacement — Milwaukee Home';
$description = get_sub_field('video_v1_description');
$cta_text    = get_sub_field('video_v1_cta_text') ?: 'Schedule Your Free Inspection';
$cta_url     = get_sub_field('video_v1_cta_url') ?: '#';

$poster_url = '';
if ( $poster ) {
    $poster_url = is_array( $poster ) ? ( $poster['url'] ?? '' ) : $poster;
}
if ( ! $poster_url ) {
    $poster_url = 'https://placehold.co/1280x720/1a1a2e/ffffff?text=Roofing+Project+Video';
}

$youtube_id = '';
if ( $video_url ) {
    if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $video_url, $matches ) ) {
        $youtube_id = $matches[1];
    }
}
$embed_url = $youtube_id ? 'https://www.youtube.com/embed/' . $youtube_id . '?autoplay=1&rel=0' : 'https://www.youtube.com/embed/VIDEO_ID?autoplay=1&rel=0';
?>
<!-- Video V1 — Video Hero with Poster and Play Button -->
<section class="video-v1" aria-labelledby="video-v1-heading">
    <div class="container">
        <div class="video-v1__header">
            <h2 id="video-v1-heading" class="video-v1__headline"><?php echo esc_html( $headline ); ?></h2>
            <h3 class="video-v1__subheadline"><?php echo esc_html( $subheadline ); ?></h3>
        </div>

        <div class="video-v1__wrapper">
            <div class="video-v1__player" id="video-v1-player">
                <!-- Poster image shown until play -->
                <img
                    class="video-v1__poster"
                    src="<?php echo esc_url( $poster_url ); ?>"
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
                    <span class="video-v1__duration"><?php echo esc_html( $duration ); ?></span>
                    <span class="video-v1__title"><?php echo esc_html( $video_title ); ?></span>
                </div>

                <!-- Actual iframe (hidden until play) -->
                <div class="video-v1__iframe-wrapper" hidden id="video-v1-iframe-wrapper">
                    <iframe
                        class="video-v1__iframe"
                        id="video-v1-iframe"
                        src=""
                        data-src="<?php echo esc_url( $embed_url ); ?>"
                        title="<?php echo esc_attr( $video_title ); ?>"
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
                <?php if ( $description ) : ?>
                    <p><?php echo wp_kses_post( $description ); ?></p>
                <?php else : ?>
                    <p>Watch how our team transformed this Milwaukee home with a complete roof replacement. From initial inspection to final cleanup, see the quality craftsmanship and attention to detail that sets us apart.</p>
                <?php endif; ?>
                <a href="<?php echo esc_url( $cta_url ); ?>" class="btn video-v1__cta"><?php echo esc_html( $cta_text ); ?></a>
            </div>
        </div>
    </div>
</section>
