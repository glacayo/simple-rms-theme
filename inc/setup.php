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
        'primary'   => __('Primary Menu', 'simple-rms-theme'),
        'footer'    => __('Footer Menu', 'simple-rms-theme'),
        'mobile'    => __('Mobile Menu', 'simple-rms-theme'),
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
    private bool $first_item = true;

    public function start_lvl(&$output, $depth = 0, $args = null): void {
        $class = ($depth === 0)
            ? 'rms-header__dropdown'
            : 'rms-header__dropdown--submenu';
        $output .= '<ul class="' . esc_attr($class) . '">';
    }

    public function end_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '</li></ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void {
        // Close previous sibling's <li> (skip first item — no open <li> to close)
        if ($this->first_item) {
            $this->first_item = false;
        } else {
            $output .= '</li>';
        }

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
        // Don't close <li> here — end_lvl closes it after children
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Custom Walker — Mobile Nav (accordion submenu)
// ══════════════════════════════════════════════════════════════════════════

class RMS_Walker_Nav_Mobile extends Walker_Nav_Menu {
    private bool $first_item = true;

    public function start_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '<ul class="rms-header__mobile-submenu">';
    }

    public function end_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '</li></ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void {
        if ($this->first_item) {
            $this->first_item = false;
        } else {
            $output .= '</li>';
        }

        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes);

        $li_class = '';
        if ($has_children) {
            $li_class = 'rms-header__mobile-nav-item--has-submenu';
        }

        $aria = '';
        if ($has_children) {
            $aria = ' aria-haspopup="true" aria-expanded="false"';
        }

        $url = esc_url($item->url);
        $title = esc_html($item->title);

        $output .= '<li' . ($li_class ? ' class="' . esc_attr($li_class) . '"' : '') . '>';
        $output .= '<a href="' . $url . '"' . $aria . '>' . $title . '</a>';
    }

    public function end_el(&$output, $item, $depth = 0, $args = null): void {
        // Don't close <li> here — end_lvl closes it after children
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Custom Walker — Header V2 Desktop Nav
// ══════════════════════════════════════════════════════════════════════════

class RMS_Walker_Nav_V2_Desktop extends Walker_Nav_Menu {
    private bool $first_item = true;

    public function start_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '<ul class="rms-header-v2__dropdown">';
    }

    public function end_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '</li></ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void {
        if ($this->first_item) {
            $this->first_item = false;
        } else {
            $output .= '</li>';
        }

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
        // Don't close <li> here — end_lvl closes it after children
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Custom Walker — Header V2 Mobile Nav
// ══════════════════════════════════════════════════════════════════════════

class RMS_Walker_Nav_V2_Mobile extends Walker_Nav_Menu {
    private bool $first_item = true;

    public function start_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '<ul class="rms-header-v2__mobile-submenu">';
    }

    public function end_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '</li></ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void {
        if ($this->first_item) {
            $this->first_item = false;
        } else {
            $output .= '</li>';
        }

        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes);

        $li_class = 'rms-header-v2__mobile-nav-item';
        if ($has_children) {
            $li_class .= ' rms-header-v2__mobile-nav-item--has-submenu';
        }

        $aria = '';
        if ($has_children) {
            $aria = ' aria-haspopup="true" aria-expanded="false"';
        }

        $url = esc_url($item->url);
        $title = esc_html($item->title);

        $output .= '<li class="' . esc_attr($li_class) . '">';
        $output .= '<a href="' . $url . '" class="rms-header-v2__mobile-nav-link"' . $aria . '>' . $title . '</a>';
    }

    public function end_el(&$output, $item, $depth = 0, $args = null): void {
        // Don't close <li> here — end_lvl closes it after children
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Custom Walker — Header V3 Desktop Nav
// ══════════════════════════════════════════════════════════════════════════

class RMS_Walker_Nav_V3_Desktop extends Walker_Nav_Menu {
    private bool $first_item = true;

    public function start_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '<ul class="rms-header-v3__dropdown">';
    }

    public function end_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '</li></ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void {
        if ($this->first_item) {
            $this->first_item = false;
        } else {
            $output .= '</li>';
        }

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
        // Don't close <li> here — end_lvl closes it after children
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Custom Walker — Header V3 Mobile Nav
// ══════════════════════════════════════════════════════════════════════════

class RMS_Walker_Nav_V3_Mobile extends Walker_Nav_Menu {
    private bool $first_item = true;

    public function start_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '<ul class="rms-header-v3__mobile-submenu">';
    }

    public function end_lvl(&$output, $depth = 0, $args = null): void {
        $output .= '</li></ul>';
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void {
        if ($this->first_item) {
            $this->first_item = false;
        } else {
            $output .= '</li>';
        }

        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes);

        $li_class = 'rms-header-v3__mobile-nav-item';
        if ($has_children) {
            $li_class .= ' rms-header-v3__mobile-nav-item--has-submenu';
        }

        $aria = '';
        if ($has_children) {
            $aria = ' aria-haspopup="true" aria-expanded="false"';
        }

        $url = esc_url($item->url);
        $title = esc_html($item->title);

        $output .= '<li class="' . esc_attr($li_class) . '">';
        $output .= '<a href="' . $url . '" class="rms-header-v3__mobile-nav-link"' . $aria . '>' . $title . '</a>';
    }

    public function end_el(&$output, $item, $depth = 0, $args = null): void {
        // Don't close <li> here — end_lvl closes it after children
    }
}

// ══════════════════════════════════════════════════════════════════════════
// Remove Classic Editor (TinyMCE) from all post types
// ══════════════════════════════════════════════════════════════════════════
add_action('init', function () {
    foreach (get_post_types() as $post_type) {
        remove_post_type_support($post_type, 'editor');
    }
}, 20);
