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
     * Resuelve la URL del asset JS principal
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
     * Devuelve las URLs CSS asociadas a un entry del manifest
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
     * Lee y devuelve el contenido del CSS para inyectarlo en el <head>
     * Crucial para alcanzar el 100% en PageSpeed (Elimina Render Blocking)
     */
    private function get_manifest() {
        $path = get_template_directory() . '/dist/.vite/manifest.json';
        if (!file_exists($path)) return null;
        return json_decode(file_get_contents($path), true);
    }

    public function enqueue_scripts() {
        if ($this->is_development) {
            wp_enqueue_script(
                'vite-client',
                $this->vite_server . '/@vite/client',
                [],
                null,
                false
            );
        }

        // JS principal
        $main_js = $this->get_asset('src/ts/main.ts');
        if ($main_js) {
            wp_enqueue_script('raven-main', $main_js, [], null, true);
        }

        // CSS compilado por Vite (solo producción)
        foreach ($this->get_css_assets('src/ts/main.ts') as $index => $css_url) {
            wp_enqueue_style('raven-main-' . $index, $css_url, [], null);
        }
    }

    public function set_script_module($tag, $handle, $src) {
        if (!in_array($handle, ['vite-client', 'raven-main'])) return $tag;
        return '<script type="module" src="' . esc_url($src) . '" crossorigin></script>';
    }
}

Vite_Icons_Integration::get_instance();
