<?php get_header(); ?>

<main id="main-content">
    <?php if (have_posts()) : ?>
        <header class="page-header">
            <h1 class="page-title">
                <?php printf(__('Resultados de búsqueda para: %s', 'simple-rms-theme'), '<span>' . get_search_query() . '</span>'); ?>
            </h1>
        </header>

        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                <div class="entry-summary">
                    <?php the_excerpt(); ?>
                </div>
            </article>
        <?php endwhile; the_posts_navigation(); else : ?>
        <header class="page-header">
            <h1 class="page-title"><?php _e('No se encontró nada', 'simple-rms-theme'); ?></h1>
        </header>
        <div class="page-content">
            <p><?php _e('Lo sentimos, pero nada coincidió con tus términos de búsqueda. Por favor, intenta de nuevo con palabras clave diferentes.', 'simple-rms-theme'); ?></p>
            <?php get_search_form(); ?>
        </div>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
