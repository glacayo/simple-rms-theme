<?php
/**
 * Vite Integration for WordPress
 * Connects PHP with Vite assets (HMR in dev, manifest in production).
 */

class Vite_Icons_Integration {
    private static $instance = null;
    private $is_development = false;
    private $vite_server = 'http://localhost:3000';

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->is_development = file_exists(get_template_directory() . '/hot');

        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_filter('script_loader_tag', [$this, 'set_script_module'], 10, 3);
    }

    // ─── URL Resolution ─────────────────────────────────────────────────

    /**
     * Resolves the URL for a JS or CSS entry.
     */
    public function get_asset($entry) {
        if ($this->is_development) {
            return $this->vite_server . '/' . $entry;
        }

        $manifest = $this->get_manifest();
        if (!$manifest || !isset($manifest[$entry])) return '';

        return get_template_directory_uri() . '/dist/' . $manifest[$entry]['file'];
    }

    /**
     * Returns the CSS file URLs associated with a JS entry from the manifest.
     * (Only relevant for entries that import CSS via JS — not used with standalone CSS entries)
     */
    public function get_css_assets($entry) {
        if ($this->is_development) {
            return [];
        }

        $manifest = $this->get_manifest();
        if (!$manifest || !isset($manifest[$entry]) || empty($manifest[$entry]['css'])) {
            return [];
        }

        return array_map(function ($css_file) {
            return get_template_directory_uri() . '/dist/' . $css_file;
        }, $manifest[$entry]['css']);
    }

    /**
     * Inlines the compiled CSS for a given entry directly into <head>.
     * This eliminates render-blocking requests — critical for PSI 100/100.
     *
     * @param string $entry  The Vite entry name (e.g., 'hero', 'main-css')
     * @param string $id     Optional custom style id attribute
     * @return string  HTML <style> tag or empty string
     */
    public function get_critical_css($entry, $id = '') {
        // In dev mode, load as a normal link tag so HMR still works
        if ($this->is_development) {
            wp_enqueue_style(
                'critical-dev-' . sanitize_title($entry),
                $this->vite_server . '/' . $entry,
                [],
                null
            );
            return '';
        }

        $manifest = $this->get_manifest();
        if (!$manifest || !isset($manifest[$entry])) return '';

        $file_path = get_template_directory() . '/dist/' . $manifest[$entry]['file'];

        if (file_exists($file_path)) {
            $style_id = $id ?: 'critical-' . sanitize_title($entry);
            return '<style id="' . esc_attr($style_id) . '">' . file_get_contents($file_path) . '</style>';
        }

        return '';
    }

    // ─── Manifest ────────────────────────────────────────────────────────

    private function get_manifest() {
        $path = get_template_directory() . '/dist/.vite/manifest.json';
        if (!file_exists($path)) return null;
        return json_decode(file_get_contents($path), true);
    }

    // ─── Enqueue ─────────────────────────────────────────────────────────

    public function enqueue_scripts() {
        // 1. Vite client (dev only — enables HMR)
        if ($this->is_development) {
            wp_enqueue_script(
                'vite-client',
                $this->vite_server . '/@vite/client',
                [],
                null,
                false
            );
        }

        // 2. Main JS
        $main_js = $this->get_asset('src/ts/main.ts');
        if ($main_js) {
            wp_enqueue_script('raven-main', $main_js, [], null, true);
        }
    }

    // ─── Deferred CSS (below the fold) ─────────────────────────────────

    /**
     * Outputs a non-render-blocking <link> using the media="print" swap trick.
     * CSS loads without blocking, then applies to all media once loaded.
     *
     * @param string $handle  Unique handle for deduplication
     * @param string $entry   Vite entry path
     */
    public function get_deferred_style($handle, $entry): void {
        $css = $this->get_asset($entry);
        if (!$css) return;
        echo '<link rel="stylesheet" href="' . esc_url($css) . '" media="print" onload="this.media=\'all\'">';
        echo '<noscript><link rel="stylesheet" href="' . esc_url($css) . '"></noscript>';
    }

    // ─── Script Tag Modifier ─────────────────────────────────────────────

    public function set_script_module($tag, $handle, $src) {
        if (!in_array($handle, ['vite-client', 'raven-main', 'header-one-menu', 'slider-js', 'lightbox-js', 'portfolio-filter-js'])) return $tag;
        return '<script type="module" src="' . esc_url($src) . '" crossorigin></script>';
    }
}

Vite_Icons_Integration::get_instance();
