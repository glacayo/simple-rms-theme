<?php
/**
 * Theme Setup
 * Registers supports, menus, image sizes, and other theme configuration.
 */

function simple_rms_setup() {
    // ─── Theme Supports ─────────────────────────────────────────────
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);
    add_theme_support('custom-logo', [
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');

    // ─── Navigation Menus ──────────────────────────────────────────
    register_nav_menus([
        'primary'         => __('Primary Menu', 'simple-rms-theme'),
        'footer-menu'     => __('Footer Menu', 'simple-rms-theme'),
        'footer-services' => __('Services Footer', 'simple-rms-theme'),
        'mobile'          => __('Mobile Menu', 'simple-rms-theme'),
    ]);

    // ─── Custom Image Sizes ────────────────────────────────────────
    add_image_size('hero-desktop', 1920, 1080, true);
    add_image_size('hero-mobile', 768, 600, true);
    add_image_size('card-thumbnail', 600, 400, true);
    add_image_size('project-thumbnail', 800, 600, true);
    add_image_size('testimonial-avatar', 96, 96, true);

    // Make sizes available in media library
    add_filter('image_size_names_choose', function ($sizes) {
        return array_merge($sizes, [
            'hero-desktop'      => __('Hero Desktop', 'simple-rms-theme'),
            'hero-mobile'       => __('Hero Mobile', 'simple-rms-theme'),
            'card-thumbnail'    => __('Card Thumbnail', 'simple-rms-theme'),
            'project-thumbnail' => __('Project Thumbnail', 'simple-rms-theme'),
        ]);
    });

    // ─── Content Width ─────────────────────────────────────────────
    global $content_width;
    if (!isset($content_width)) {
        $content_width = 1280;
    }
}
add_action('after_setup_theme', 'simple_rms_setup');

// ─── Widget Areas ────────────────────────────────────────────────────────
function simple_rms_widgets_init() {
    register_sidebar([
        'name'          => __('Footer Widgets', 'simple-rms-theme'),
        'id'            => 'footer-widgets',
        'before_widget' => '<div class="footer-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="footer-widget__title">',
        'after_title'   => '</h3>',
    ]);
}
add_action('widgets_init', 'simple_rms_widgets_init');

// ══════════════════════════════════════════════════════════════════════════
// Custom Walker — Desktop Nav (2-level dropdown)
// ══════════════════════════════════════════════════════════════════════════

class RMS_Walker_Nav_Primary extends Walker_Nav_Menu {
    public function start_lvl(&$output, $depth = 0, $args = null): void {
        $class = ($depth === 0)
            ? 'rms-header__dropdown'
            : 'rms-header__dropdown--submenu';
        $output .= '<ul class="' . esc_attr($class) . '">';
    }

    public function end_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '</ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes);

        $li_class = 'rms-header__nav-item';
        if ($has_children) {
            $li_class .= ' rms-header__nav-item--has-dropdown';
        }

        $link_class = ($depth === 0) ? 'rms-header__nav-link' : '';

        $aria = '';
        if ($has_children) {
            $aria = ' aria-haspopup="true" aria-expanded="false"';
        }

        $url = esc_url($item->url);
        $title = esc_html($item->title);
        $link = '<a href="' . $url . '"' . ($link_class ? ' class="' . esc_attr($link_class) . '"' : '') . $aria . '>' . $title . '</a>';

        $output .= '<li class="' . esc_attr($li_class) . '">';
        $output .= $link;
    }

    public function end_el(&$output, $item, $depth = 0, $args = null): void {
        $output .= '</li>';
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Shared mobile submenu toggle (used by every mobile walker)
// ══════════════════════════════════════════════════════════════════════════

/**
 * Renders the parent of a mobile submenu with a real toggle button.
 *
 * Placeholder parents (`#` or an empty URL) become the toggle button itself,
 * so no mobile link points to an empty anchor. Parents with a URL keep their
 * link plus an icon-only toggle. Each toggle starts collapsed and points at
 * its submenu through aria-controls.
 */
trait RMS_Walker_Mobile_Submenu_Toggle {
    private string $pending_submenu_id = '';

    private function is_placeholder_url($url): bool {
        $url = trim((string) $url);

        return $url === '' || $url === '#';
    }

    /**
     * @param object $item       Menu item.
     * @param string $block      BEM block, e.g. 'rms-header-v2'.
     * @param string $link_class Class for the item label/link ('' for none).
     */
    private function mobile_parent_markup($item, string $block, string $link_class): string {
        $submenu_id = $block . '-submenu-' . (int) $item->ID;
        $this->pending_submenu_id = $submenu_id;

        $title = esc_html($item->title);
        $class_attr = $link_class !== '' ? ' class="' . esc_attr($link_class) . '"' : '';
        $chevron = '<span class="' . esc_attr($block . '__mobile-nav-chevron') . '" aria-hidden="true"></span>';
        $toggle_attrs = ' type="button" aria-expanded="false" aria-controls="' . esc_attr($submenu_id) . '"';

        if ($this->is_placeholder_url($item->url)) {
            return '<button class="' . esc_attr($block . '__mobile-nav-toggle') . '"' . $toggle_attrs . '>'
                . '<span' . $class_attr . '>' . $title . '</span>' . $chevron
                . '</button>';
        }

        /* translators: %s: parent menu item title. */
        $label = sprintf(__('Toggle %s submenu', 'simple-rms-theme'), $item->title);

        return '<a href="' . esc_url($item->url) . '"' . $class_attr . '>' . $title . '</a>'
            . '<button class="' . esc_attr($block . '__mobile-nav-toggle ' . $block . '__mobile-nav-toggle--icon') . '"'
            . $toggle_attrs . ' aria-label="' . esc_attr($label) . '">' . $chevron . '</button>';
    }

    private function mobile_submenu_open_tag(string $class): string {
        $id_attr = $this->pending_submenu_id !== ''
            ? ' id="' . esc_attr($this->pending_submenu_id) . '"'
            : '';
        $this->pending_submenu_id = '';

        return '<ul class="' . esc_attr($class) . '"' . $id_attr . '>';
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Custom Walker — Mobile Nav (accordion submenu)
// ══════════════════════════════════════════════════════════════════════════

class RMS_Walker_Nav_Mobile extends Walker_Nav_Menu {
    use RMS_Walker_Mobile_Submenu_Toggle;

    public function start_lvl(&$output, $depth = 0, $args = null): void {
        $output .= $this->mobile_submenu_open_tag('rms-header__mobile-submenu');
    }

    public function end_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '</ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes);

        $li_class = '';
        if ($has_children) {
            $li_class = 'rms-header__mobile-nav-item--has-submenu';
        }

        $output .= '<li' . ($li_class ? ' class="' . esc_attr($li_class) . '"' : '') . '>';

        if ($has_children) {
            $output .= $this->mobile_parent_markup($item, 'rms-header', '');
            return;
        }

        $output .= '<a href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a>';
    }

    public function end_el(&$output, $item, $depth = 0, $args = null): void {
        $output .= '</li>';
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Custom Walker — Header V2 Desktop Nav
// ══════════════════════════════════════════════════════════════════════════

class RMS_Walker_Nav_V2_Desktop extends Walker_Nav_Menu {
    public function start_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '<ul class="rms-header-v2__dropdown">';
    }

    public function end_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '</ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes);

        $li_class = 'rms-header-v2__nav-item';
        if ($has_children) {
            $li_class .= ' rms-header-v2__nav-item--has-dropdown';
        }

        $link_class = 'rms-header-v2__nav-link';

        $aria = '';
        if ($has_children) {
            $aria = ' aria-haspopup="true" aria-expanded="false"';
        }

        $url = esc_url($item->url);
        $title = esc_html($item->title);

        $output .= '<li class="' . esc_attr($li_class) . '">';
        $output .= '<a href="' . $url . '" class="' . esc_attr($link_class) . '"' . $aria . '>' . $title . '</a>';
    }

    public function end_el(&$output, $item, $depth = 0, $args = null): void {
        $output .= '</li>';
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Custom Walker — Header V2 Mobile Nav
// ══════════════════════════════════════════════════════════════════════════

class RMS_Walker_Nav_V2_Mobile extends Walker_Nav_Menu {
    use RMS_Walker_Mobile_Submenu_Toggle;

    public function start_lvl(&$output, $depth = 0, $args = null): void {
        $output .= $this->mobile_submenu_open_tag('rms-header-v2__mobile-submenu');
    }

    public function end_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '</ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes);

        $li_class = 'rms-header-v2__mobile-nav-item';
        if ($has_children) {
            $li_class .= ' rms-header-v2__mobile-nav-item--has-submenu';
        }

        $output .= '<li class="' . esc_attr($li_class) . '">';

        if ($has_children) {
            $output .= $this->mobile_parent_markup($item, 'rms-header-v2', 'rms-header-v2__mobile-nav-link');
            return;
        }

        $output .= '<a href="' . esc_url($item->url) . '" class="rms-header-v2__mobile-nav-link">' . esc_html($item->title) . '</a>';
    }

    public function end_el(&$output, $item, $depth = 0, $args = null): void {
        $output .= '</li>';
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Custom Walker — Header V3 Desktop Nav
// ══════════════════════════════════════════════════════════════════════════

class RMS_Walker_Nav_V3_Desktop extends Walker_Nav_Menu {
    public function start_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '<ul class="rms-header-v3__dropdown">';
    }

    public function end_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '</ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes);

        $li_class = 'rms-header-v3__nav-item';
        if ($has_children) {
            $li_class .= ' rms-header-v3__nav-item--has-dropdown';
        }

        $link_class = 'rms-header-v3__nav-link';

        $aria = '';
        if ($has_children) {
            $aria = ' aria-haspopup="true" aria-expanded="false"';
        }

        $url = esc_url($item->url);
        $title = esc_html($item->title);

        $output .= '<li class="' . esc_attr($li_class) . '">';
        $output .= '<a href="' . $url . '" class="' . esc_attr($link_class) . '"' . $aria . '>' . $title . '</a>';
    }

    public function end_el(&$output, $item, $depth = 0, $args = null): void {
        $output .= '</li>';
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Custom Walker — Header V3 Mobile Nav
// ══════════════════════════════════════════════════════════════════════════

class RMS_Walker_Nav_V3_Mobile extends Walker_Nav_Menu {
    use RMS_Walker_Mobile_Submenu_Toggle;

    public function start_lvl(&$output, $depth = 0, $args = null): void {
        $output .= $this->mobile_submenu_open_tag('rms-header-v3__mobile-submenu');
    }

    public function end_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '</ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes);

        $li_class = 'rms-header-v3__mobile-nav-item';
        if ($has_children) {
            $li_class .= ' rms-header-v3__mobile-nav-item--has-submenu';
        }

        $output .= '<li class="' . esc_attr($li_class) . '">';

        if ($has_children) {
            $output .= $this->mobile_parent_markup($item, 'rms-header-v3', 'rms-header-v3__mobile-nav-link');
            return;
        }

        $output .= '<a href="' . esc_url($item->url) . '" class="rms-header-v3__mobile-nav-link">' . esc_html($item->title) . '</a>';
    }

    public function end_el(&$output, $item, $depth = 0, $args = null): void {
        $output .= '</li>';
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Remove Classic Editor (TinyMCE) only from selected public post types
// ══════════════════════════════════════════════════════════════════════════
add_action('init', function () {
    foreach (['post', 'page'] as $post_type) {
        remove_post_type_support($post_type, 'editor');
    }
}, 20);
