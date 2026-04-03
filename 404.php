<?php get_header(); ?>

<main id="main-content" class="error-404">
    <header class="page-header">
        <h1 class="page-title"><?php _e('Ups! Esa página no existe.', 'simple-rms-theme'); ?></h1>
    </header>

    <div class="page-content">
        <p><?php _e('Parece que no pudimos encontrar lo que buscabas. ¿Tal vez una búsqueda ayude?', 'simple-rms-theme'); ?></p>
        <?php get_search_form(); ?>
    </div>
</main>

<?php get_footer(); ?>
