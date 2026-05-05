<?php
$headline      = get_sub_field('testimonials_v3_headline') ?: 'Client Testimonials';
$subheadline   = get_sub_field('testimonials_v3_subheadline') ?: 'Hear From Our Satisfied Customers';
$testimonials  = get_sub_field('testimonials_v3_items');
?>
<!-- Testimonials V3 — Editorial Magazine Style -->
<section class="testimonials-v3" aria-labelledby="testimonials-v3-heading">
    <div class="container">
        <h2 id="testimonials-v3-heading" class="testimonials-v3__headline"><?php echo esc_html( $headline ); ?></h2>
        <h3 class="testimonials-v3__subheadline"><?php echo esc_html( $subheadline ); ?></h3>

        <div class="testimonials-v3__grid">
            <?php if ( ! empty( $testimonials ) ) : ?>
                <?php
                $total = count( $testimonials );
                foreach ( $testimonials as $index => $item ) :
                    $quote  = $item['testimonial_quote'] ?? '';
                    $author = $item['testimonial_author'] ?? '';
                    $role   = $item['testimonial_role'] ?? '';
                    $stars  = isset( $item['testimonial_stars'] ) ? absint( $item['testimonial_stars'] ) : 5;
                    $stars  = max( 1, min( 5, $stars ) );
                    $is_last = ( $index === $total - 1 );
                    $card_class = 'testimonials-v3__card' . ( $is_last ? ' testimonials-v3__card--full' : '' );
                ?>
                <article class="<?php echo esc_attr( $card_class ); ?>">
                    <span class="testimonials-v3__quote-mark" aria-hidden="true">&ldquo;</span>
                    <div class="testimonials-v3__content">
                        <p class="testimonials-v3__quote"><?php echo wp_kses_post( $quote ); ?></p>
                        <p class="testimonials-v3__author"><?php echo esc_html( $author ); ?></p>
                        <p class="testimonials-v3__role"><?php echo esc_html( $role ); ?></p>
                        <p class="testimonials-v3__stars" role="img" aria-label="<?php echo esc_attr( $stars . ' out of 5 stars' ); ?>">
                            <?php for ( $i = 0; $i < $stars; $i++ ) : ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            <?php endfor; ?>
                        </p>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php else : ?>
                <!-- Fallback hardcoded cards -->
                <article class="testimonials-v3__card">
                    <span class="testimonials-v3__quote-mark" aria-hidden="true">&ldquo;</span>
                    <div class="testimonials-v3__content">
                        <p class="testimonials-v3__quote">They replaced our entire roof in just three days. The crew was professional, clean, and the quality is outstanding. Highly recommend to anyone needing roofing work.</p>
                        <p class="testimonials-v3__author">Maria Johnson</p>
                        <p class="testimonials-v3__role">Homeowner</p>
                        <p class="testimonials-v3__stars" role="img" aria-label="5 out of 5 stars"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></p>
                    </div>
                </article>

                <article class="testimonials-v3__card">
                    <span class="testimonials-v3__quote-mark" aria-hidden="true">&ldquo;</span>
                    <div class="testimonials-v3__content">
                        <p class="testimonials-v3__quote">After the storm damaged our roof, they responded the same day. Fast, reliable, and they handled everything with our insurance company. Couldn't be happier.</p>
                        <p class="testimonials-v3__author">Robert Smith</p>
                        <p class="testimonials-v3__role">Property Manager</p>
                        <p class="testimonials-v3__stars" role="img" aria-label="5 out of 5 stars"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></p>
                    </div>
                </article>

                <article class="testimonials-v3__card testimonials-v3__card--full">
                    <span class="testimonials-v3__quote-mark" aria-hidden="true">&ldquo;</span>
                    <div class="testimonials-v3__content">
                        <p class="testimonials-v3__quote">Best roofing company we've ever worked with. Fair pricing, excellent communication, and the finished product looks incredible. Our neighbors keep asking who did the work.</p>
                        <p class="testimonials-v3__author">Sarah Williams</p>
                        <p class="testimonials-v3__role">Homeowner</p>
                        <p class="testimonials-v3__stars" role="img" aria-label="5 out of 5 stars"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></p>
                    </div>
                </article>
            <?php endif; ?>
        </div>
    </div>
</section>
