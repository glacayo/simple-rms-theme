<?php
/**
 * Vite Integration for WordPress - Versión Optimizada para ROI & PSI
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
        // Flag de desarrollo: Prioriza la existencia del archivo 'hot'
        $this->is_development = file_exists(get_template_directory() . '/hot');
        
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_filter('script_loader_tag', [$this, 'set_script_module'], 10, 3);
    }

    /**
     * Resuelve la URL del asset (Para JS y CSS no crítico)
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
     * Lee y devuelve el contenido del CSS para inyectarlo en el <head>
     * Crucial para alcanzar el 100% en PageSpeed (Elimina Render Blocking)
     */
    public function get_critical_css($entry) {
        // En desarrollo, usamos link normal para mantener el Hot Reload (HMR)
        if ($this->is_development) {
            wp_enqueue_style('critical-dev-' . sanitize_title($entry), $this->vite_server . '/' . $entry, [], null);
            return '';
        }

        $manifest = $this->get_manifest();
        if (!$manifest || !isset($manifest[$entry])) return '';

        $file_path = get_template_directory() . '/dist/' . $manifest[$entry]['file'];
        
        if (file_exists($file_path)) {
            return '<style id="critical-path-' . sanitize_title($entry) . '">' . file_get_contents($file_path) . '</style>';
        }
        return '';
    }

    private function get_manifest() {
        $path = get_template_directory() . '/dist/.vite/manifest.json';
        if (!file_exists($path)) return null;
        return json_decode(file_get_contents($path), true);
    }

    public function enqueue_scripts() {
        // 1. JS Principal
        $main_js = $this->get_asset('src/ts/main.ts');
        if ($main_js) {
            wp_enqueue_script('raven-main', $main_js, [], null, true);
        }

        // 2. CSS Condicional (Solo si NO es crítico)
        // Ejemplo: Si tienes un CSS de footer que no urge cargar:
        if (!is_front_page()) {
             // Lógica para otras páginas
        }
    }

    public function set_script_module($tag, $handle, $src) {
        if (!in_array($handle, ['raven-main'])) return $tag;
        return '<script type="module" src="' . esc_url($src) . '" crossorigin></script>';
    }
}

Vite_Icons_Integration::get_instance();