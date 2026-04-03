<?php
/**
 * Simple RMS Theme Functions
 */

// 1. Configuración Básica del Tema
function simple_rms_theme_setup() {
    // Habilitar soporte para imágenes de tamaño completo
    add_theme_support('post-thumbnails');
    
    // Registrar menús
    register_nav_menus([
        'primary' => __('Primary Menu', 'simple-rms-theme'),
    ]);
}
add_action('after_setup_theme', 'simple_rms_theme_setup');

// 2. Encolar Estilos y Scripts (Vite Integration)
// Esto será reemplazado por tu lógica de Vite, pero dejamos la estructura base.
function simple_rms_theme_scripts() {
    // Ejemplo de cómo se encolaría un CSS genérico (luego lo cambiamos por Vite)
    // wp_enqueue_style('simple-rms-style', get_stylesheet_uri());
}
add_action('wp_enqueue_scripts', 'simple_rms_theme_scripts');

// 3. Incluir la integración con Vite
require_once get_template_directory() . '/inc/vite-integration.php';