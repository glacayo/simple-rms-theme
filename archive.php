<?php get_header(); ?>

<main id="main-content">
    <header class="archive-header">
        <?php 
        the_archive_title('<h1 class="archive-title">', '</h1>');
        the_archive_description('<div class="archive-description">', '</div>');
        ?>
    </header>

    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article <?php post_class(); ?>>
            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
            <div class="entry-summary">
                <?php the_excerpt(); ?>
            </div>
        </article>
    <?php endwhile; the_posts_navigation(); else : ?>
        <p><?php _e('No content found.', 'simple-rms-theme'); ?></p>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
