<?php
/**
 * Page Sections — Flexible Content Loop
 *
 * Iterates over the 'page_sections' flexible content field and renders
 * the corresponding template part for each layout.
 *
 * @package Simple_RMS_Theme
 */

if (have_rows('page_sections')) :
    while (have_rows('page_sections')) : the_row();
        $layout = get_row_layout();
        get_template_part('templates/' . $layout);
    endwhile;
endif;
