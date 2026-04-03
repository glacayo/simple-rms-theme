<?php get_header(); ?>

<main id="main-content">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article <?php post_class(); ?>>
            <header class="entry-header">
                <h1><?php the_title(); ?></h1>
                <div class="entry-meta">
                    <?php the_date(); ?> por <?php the_author(); ?>
                </div>
            </header>

            <?php if (has_post_thumbnail()) : ?>
                <div class="post-thumbnail">
                    <?php the_post_thumbnail('large'); ?>
                </div>
            <?php endif; ?>

            <div class="entry-content">
                <?php the_content(); ?>
            </div>

            <footer class="entry-footer">
                <?php the_tags('<span class="tags-links">Tags: ', ', ', '</span>'); ?>
            </footer>
        </article>

        <?php 
        if (comments_open() || get_comments_number()) :
            comments_template();
        endif;
        ?>

    <?php endwhile; endif; ?>
</main>

<?php get_footer(); ?>
