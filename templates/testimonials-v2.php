<?php
$headline      = get_sub_field('testimonials_v2_headline') ?: 'Trusted by Hundreds of Homeowners';
$subheadline   = get_sub_field('testimonials_v2_subheadline') ?: "Here's What They Have to Say";
$testimonials  = get_sub_field('testimonials_v2_items');
?>
<!-- Testimonials V2 — Dark Background with Avatar Circles -->
<section class="testimonials-v2" aria-labelledby="testimonials-v2-heading">
    <div class="container">
        <h2 id="testimonials-v2-heading" class="testimonials-v2__headline"><?php echo esc_html( $headline ); ?></h2>
        <h3 class="testimonials-v2__subheadline"><?php echo esc_html( $subheadline ); ?></h3>

        <div class="grid-3 testimonials-v2__grid">
            <?php if ( ! empty( $testimonials ) ) : ?>
                <?php foreach ( $testimonials as $item ) :
                    $avatar = $item['testimonial_avatar'] ?? '';
                    $quote  = $item['testimonial_quote'] ?? '';
                    $author = $item['testimonial_author'] ?? '';
                    $stars  = isset( $item['testimonial_stars'] ) ? absint( $item['testimonial_stars'] ) : 5;
                    $stars  = max( 1, min( 5, $stars ) );
                ?>
                <article class="testimonials-v2__card">
                    <img
                        class="testimonials-v2__avatar"
                        src="<?php echo esc_url( $avatar ?: 'https://placehold.co/80x80' ); ?>"
                        alt="Portrait of <?php echo esc_attr( $author ); ?>"
                        width="80"
                        height="80"
                        loading="lazy"
                        decoding="async"
                    >
                    <p class="testimonials-v2__quote"><?php echo wp_kses_post( $quote ); ?></p>
                    <p class="testimonials-v2__author"><?php echo esc_html( $author ); ?></p>
                    <p class="testimonials-v2__stars" role="img" aria-label="<?php echo esc_attr( $stars . ' out of 5 stars' ); ?>">
                        <?php for ( $i = 0; $i < $stars; $i++ ) : ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <?php endfor; ?>
                    </p>
                </article>
                <?php endforeach; ?>
            <?php else : ?>
                <!-- Fallback hardcoded cards -->
                <article class="testimonials-v2__card">
                    <img
                        class="testimonials-v2__avatar"
                        src="https://placehold.co/80x80"
                        alt="Portrait placeholder for Maria Johnson"
                        width="80"
                        height="80"
                        loading="lazy"
                        decoding="async"
                    >
                    <p class="testimonials-v2__quote">They replaced our entire roof in just three days. The crew was professional, clean, and the quality is outstanding. Highly recommend to anyone needing roofing work.</p>
                    <p class="testimonials-v2__author">Maria Johnson</p>
                    <p class="testimonials-v2__stars" role="img" aria-label="5 out of 5 stars"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></p>
                </article>

                <article class="testimonials-v2__card">
                    <img
                        class="testimonials-v2__avatar"
                        src="https://placehold.co/80x80"
                        alt="Portrait placeholder for Robert Smith"
                        width="80"
                        height="80"
                        loading="lazy"
                        decoding="async"
                    >
                    <p class="testimonials-v2__quote">After the storm damaged our roof, they responded the same day. Fast, reliable, and they handled everything with our insurance company. Couldn't be happier.</p>
                    <p class="testimonials-v2__author">Robert Smith</p>
                    <p class="testimonials-v2__stars" role="img" aria-label="5 out of 5 stars"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></p>
                </article>

                <article class="testimonials-v2__card">
                    <img
                        class="testimonials-v2__avatar"
                        src="https://placehold.co/80x80"
                        alt="Portrait placeholder for Sarah Williams"
                        width="80"
                        height="80"
                        loading="lazy"
                        decoding="async"
                    >
                    <p class="testimonials-v2__quote">Best roofing company we've ever worked with. Fair pricing, excellent communication, and the finished product looks incredible. Our neighbors keep asking who did the work.</p>
                    <p class="testimonials-v2__author">Sarah Williams</p>
                    <p class="testimonials-v2__stars" role="img" aria-label="5 out of 5 stars"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></p>
                </article>
            <?php endif; ?>
        </div>
    </div>
</section>
