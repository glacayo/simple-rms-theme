<?php
$headline    = get_sub_field('video_v2_headline') ?: 'Video Gallery';
$subheadline = get_sub_field('video_v2_subheadline') ?: 'See Our Roofing Projects Come to Life';
$videos      = get_sub_field('video_v2_videos');
?>
<!-- Video V2 — Video Gallery Grid with Lightbox -->
<section class="video-v2" aria-labelledby="video-v2-heading">
    <div class="container">
        <div class="video-v2__header">
            <h2 id="video-v2-heading" class="video-v2__headline"><?php echo esc_html( $headline ); ?></h2>
            <h3 class="video-v2__subheadline"><?php echo esc_html( $subheadline ); ?></h3>
        </div>

        <div class="video-v2__grid">
            <?php if ( ! empty( $videos ) ) : ?>
                <?php foreach ( $videos as $item ) :
                    $thumbnail     = $item['video_thumbnail'] ?? '';
                    $video_url     = $item['video_url'] ?? '';
                    $card_title    = $item['video_title'] ?? '';
                    $excerpt       = $item['video_excerpt'] ?? '';
                    $card_duration = $item['video_duration'] ?? '';

                    $youtube_id = '';
                    if ( $video_url ) {
                        if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $video_url, $matches ) ) {
                            $youtube_id = $matches[1];
                        }
                    }

                    $thumbnail_url = '';
                    if ( $thumbnail ) {
                        $thumbnail_url = is_array( $thumbnail ) ? ( $thumbnail['url'] ?? '' ) : $thumbnail;
                    }
                    if ( ! $thumbnail_url ) {
                        $thumbnail_url = 'https://placehold.co/640x360/1a1a2e/ffffff?text=Video+Thumbnail';
                    }
                ?>
                <button class="video-v2__card" aria-label="Play video: <?php echo esc_attr( $card_title ); ?>" data-video-id="<?php echo esc_attr( $youtube_id ); ?>" data-video-title="<?php echo esc_attr( $card_title ); ?>">
                    <div class="video-v2__thumbnail-wrapper">
                        <img
                            class="video-v2__thumbnail"
                            src="<?php echo esc_url( $thumbnail_url ); ?>"
                            alt="Thumbnail for <?php echo esc_attr( $card_title ); ?> video"
                            width="640"
                            height="360"
                            loading="lazy"
                        >
                        <div class="video-v2__play-overlay">
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <polygon points="5 3 19 12 5 21 5 3"/>
                            </svg>
                        </div>
                        <span class="video-v2__duration-badge"><?php echo esc_html( $card_duration ); ?></span>
                    </div>
                    <div class="video-v2__card-content">
                        <h4 class="video-v2__card-title"><?php echo esc_html( $card_title ); ?></h4>
                        <p class="video-v2__card-excerpt"><?php echo wp_kses_post( $excerpt ); ?></p>
                    </div>
                </button>
                <?php endforeach; ?>
            <?php else : ?>
                <!-- Fallback Video Card 1 -->
                <button class="video-v2__card" aria-label="Play video: Complete Roof Replacement" data-video-id="VIDEO_ID_1" data-video-title="Complete Roof Replacement — Milwaukee Home">
                    <div class="video-v2__thumbnail-wrapper">
                        <img
                            class="video-v2__thumbnail"
                            src="https://placehold.co/640x360/1a1a2e/ffffff?text=Roof+Replacement"
                            alt="Thumbnail for roof replacement video"
                            width="640"
                            height="360"
                            loading="lazy"
                        >
                        <div class="video-v2__play-overlay">
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <polygon points="5 3 19 12 5 21 5 3"/>
                            </svg>
                        </div>
                        <span class="video-v2__duration-badge">1:30</span>
                    </div>
                    <div class="video-v2__card-content">
                        <h4 class="video-v2__card-title">Complete Roof Replacement</h4>
                        <p class="video-v2__card-excerpt">Full installation on a Milwaukee home — asphalt shingles with improved ventilation.</p>
                    </div>
                </button>

                <!-- Fallback Video Card 2 -->
                <button class="video-v2__card" aria-label="Play video: Storm Damage Repair" data-video-id="VIDEO_ID_2" data-video-title="Storm Damage Repair — Before & After">
                    <div class="video-v2__thumbnail-wrapper">
                        <img
                            class="video-v2__thumbnail"
                            src="https://placehold.co/640x360/1a1a2e/ffffff?text=Storm+Damage"
                            alt="Thumbnail for storm damage repair video"
                            width="640"
                            height="360"
                            loading="lazy"
                        >
                        <div class="video-v2__play-overlay">
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <polygon points="5 3 19 12 5 21 5 3"/>
                            </svg>
                        </div>
                        <span class="video-v2__duration-badge">2:15</span>
                    </div>
                    <div class="video-v2__card-content">
                        <h4 class="video-v2__card-title">Storm Damage Repair</h4>
                        <p class="video-v2__card-excerpt">Emergency response and complete restoration after severe storm damage.</p>
                    </div>
                </button>

                <!-- Fallback Video Card 3 -->
                <button class="video-v2__card" aria-label="Play video: Gutter Installation" data-video-id="VIDEO_ID_3" data-video-title="Professional Gutter Installation">
                    <div class="video-v2__thumbnail-wrapper">
                        <img
                            class="video-v2__thumbnail"
                            src="https://placehold.co/640x360/1a1a2e/ffffff?text=Gutter+Install"
                            alt="Thumbnail for gutter installation video"
                            width="640"
                            height="360"
                            loading="lazy"
                        >
                        <div class="video-v2__play-overlay">
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <polygon points="5 3 19 12 5 21 5 3"/>
                            </svg>
                        </div>
                        <span class="video-v2__duration-badge">1:45</span>
                    </div>
                    <div class="video-v2__card-content">
                        <h4 class="video-v2__card-title">Professional Gutter Installation</h4>
                        <p class="video-v2__card-excerpt">Seamless gutter system installation protecting homes year-round.</p>
                    </div>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lightbox Modal -->
    <div class="video-v2__lightbox" id="video-v2-lightbox" role="dialog" aria-modal="true" aria-label="Video player" hidden>
        <div class="video-v2__lightbox-backdrop" id="video-v2-lightbox-backdrop"></div>
        <div class="video-v2__lightbox-content">
            <button class="video-v2__lightbox-close" aria-label="Close video" id="video-v2-lightbox-close">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="video-v2__lightbox-video-wrapper">
                <iframe
                    class="video-v2__lightbox-iframe"
                    id="video-v2-lightbox-iframe"
                    src=""
                    data-src="https://www.youtube.com/embed/VIDEO_ID?autoplay=1&rel=0"
                    title="Video player"
                    width="1280"
                    height="720"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen
                ></iframe>
            </div>
            <p class="video-v2__lightbox-title" id="video-v2-lightbox-title"></p>
        </div>
    </div>
</section>
