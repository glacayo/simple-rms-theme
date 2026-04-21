<?php
/**
 * Template Part: Single Post
 *
 * @package SimpleRMS
 */

while ( have_posts() ) : the_post();
?>

<article class="single-post">

    <div class="container">

        <div class="single-post__container">

            <div class="single-post__featured-image">
                <?php if ( has_post_thumbnail() ) : ?>
                    <?php the_post_thumbnail('full'); ?>
                <?php endif; ?>
            </div>

    <div class="single-post__header">
        <h1 class="single-post__title"><?php the_title(); ?></h1>
        <div class="single-post__meta">
            <span class="single-post__meta-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <time class="single-post__meta-date"><?php echo esc_html( get_the_date( 'F j, Y' ) ); ?></time>
            </span>
            <span class="single-post__meta-sep">&middot;</span>
            <span class="single-post__meta-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span class="single-post__meta-author"><?php echo esc_html( get_the_author() ); ?></span>
            </span>
            <?php
            $tags = get_the_tags();
            if ( $tags ) :
                ?>
                <span class="single-post__meta-sep">&middot;</span>
                <span class="single-post__meta-tags">
                    <?php foreach ( $tags as $tag ) : ?>
                        <span class="single-post__meta-tag">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                            <?php echo esc_html( $tag->name ); ?>
                        </span>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

            <div class="single-post__content">
                <?php the_content(); ?>
            </div>

            <nav class="single-post__navigation">
                <?php
                the_post_navigation([
                    'prev_text' => '&laquo; %title',
                    'next_text' => '%title &raquo;',
                ]);
                ?>
            </nav>

        </div><!-- .single-post__container -->

    </div><!-- .container -->
</article>

<?php endwhile; ?>
