<?php
/**
 * Focused regression harness for footer variants (issue #28).
 *
 * Run: php scripts/test-footer-variants.php
 */

$theme_root = dirname(__DIR__);
$failures   = 0;

function rms_footer_test_fail(string $name, string $message): void {
    global $failures;
    $failures++;
    fwrite(STDERR, "FAIL {$name}: {$message}\n");
}

function rms_footer_test_pass(string $name): void {
    fwrite(STDOUT, "PASS {$name}\n");
}

function rms_footer_read(string $path): string {
    $contents = file_get_contents($path);
    return is_string($contents) ? $contents : '';
}

function rms_footer_test_rrmdir(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if (!is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            rms_footer_test_rrmdir($path);
            continue;
        }
        unlink($path);
    }

    rmdir($dir);
}

function rms_footer_test_reset_fields(array $fields = []): void {
    $GLOBALS['rms_footer_test_fields'] = $fields;
    $GLOBALS['rms_footer_test_parts']  = [];
    if (isset($GLOBALS['rms_footer_test_vite']) && is_object($GLOBALS['rms_footer_test_vite'])) {
        $GLOBALS['rms_footer_test_vite']->deferred = [];
    }
}

function rms_footer_test_count_tags(string $html, string $tag): int {
    return preg_match_all('/<' . preg_quote($tag, '/') . '\b/i', $html, $matches) ? count($matches[0]) : 0;
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower((string) $key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('get_template_directory')) {
    function get_template_directory() {
        return $GLOBALS['rms_footer_test_theme_root'];
    }
}

if (!function_exists('get_field')) {
    function get_field($selector, $post_id = false) {
        return $GLOBALS['rms_footer_test_fields'][$selector] ?? null;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        $url = trim((string) $url);
        if ($url === '' || preg_match('#^(javascript|data):#i', $url)) {
            return '';
        }
        return $url;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') {
        echo esc_html($text);
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default') {
        return esc_attr($text);
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '/') {
        return 'https://example.test' . $path;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') {
        if ($show === 'name') {
            return (string) ($GLOBALS['rms_footer_test_fields']['blogname'] ?? '');
        }
        return $show === 'charset' ? 'UTF-8' : '';
    }
}

if (!function_exists('has_custom_logo')) {
    function has_custom_logo() {
        return false;
    }
}

if (!function_exists('the_custom_logo')) {
    function the_custom_logo() {}
}

if (!function_exists('language_attributes')) {
    function language_attributes() {
        echo 'en';
    }
}

if (!function_exists('bloginfo')) {
    function bloginfo($show = '') {
        echo get_bloginfo($show);
    }
}

if (!function_exists('is_front_page')) {
    function is_front_page() {
        return false;
    }
}

if (!function_exists('is_page_template')) {
    function is_page_template($template = '') {
        return false;
    }
}

if (!function_exists('is_single')) {
    function is_single() {
        return false;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle = '', $src = '', $deps = [], $ver = false, $media = 'all') {}
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle = '', $src = '', $deps = [], $ver = false, $in_footer = false) {}
}

if (!function_exists('wp_head')) {
    function wp_head() {}
}

if (!function_exists('wp_footer')) {
    function wp_footer() {}
}

if (!function_exists('body_class')) {
    function body_class($class = '') {}
}

if (!function_exists('get_template_part')) {
    function get_template_part($slug, $name = null) {
        $GLOBALS['rms_footer_test_parts'][] = $slug;
        if (strpos((string) $slug, 'templates/footer-') !== 0) {
            return;
        }
        $file = get_template_directory() . '/' . $slug . '.php';
        if (is_readable($file)) {
            include $file;
        }
    }
}

if (!class_exists('Vite_Icons_Integration')) {
    class Vite_Icons_Integration {
        public $deferred = [];

        public static function get_instance() {
            if (!isset($GLOBALS['rms_footer_test_vite']) || !($GLOBALS['rms_footer_test_vite'] instanceof self)) {
                $GLOBALS['rms_footer_test_vite'] = new self();
            }
            return $GLOBALS['rms_footer_test_vite'];
        }

        public function get_critical_css($entry, $id = '') {
            return '';
        }

        public function get_asset($entry) {
            return '';
        }

        public function get_deferred_style($handle, $entry): void {
            $this->deferred[] = [
                'handle' => (string) $handle,
                'entry'  => (string) $entry,
            ];
        }
    }
}

$GLOBALS['rms_footer_test_theme_root'] = $theme_root;
rms_footer_test_reset_fields();

require $theme_root . '/inc/acf-theme-options.php';

$acf_path = $theme_root . '/acf-json/group_rms_theme_settings.json';
$acf_json = json_decode(rms_footer_read($acf_path), true);
$footer_field = null;
$social_choices = [];

if (!is_array($acf_json)) {
    rms_footer_test_fail('test_acf_footer_choices_map_to_existing_templates', 'Theme settings JSON did not parse.');
} else {
    foreach ($acf_json['fields'] ?? [] as $candidate) {
        if (($candidate['key'] ?? '') === 'field_rms_company_footer_version') {
            $footer_field = $candidate;
        }
        if (($candidate['name'] ?? '') !== 'company_social_media') {
            continue;
        }
        foreach ($candidate['sub_fields'] ?? [] as $sub) {
            if (($sub['name'] ?? '') === 'social_platform' && is_array($sub['choices'] ?? null)) {
                $social_choices = $sub['choices'];
            }
        }
    }
}

if (!is_array($footer_field)) {
    rms_footer_test_fail('test_acf_footer_choices_map_to_existing_templates', 'field_rms_company_footer_version is missing.');
} else {
    $choices  = $footer_field['choices'] ?? [];
    $expected = ['footer-v1' => 'Footer V1', 'footer-v2' => 'Footer V2'];
    if ($choices !== $expected) {
        rms_footer_test_fail(
            'test_acf_footer_choices_map_to_existing_templates',
            'ACF choices must be exactly footer-v1 / footer-v2. Got: ' . json_encode($choices)
        );
    } elseif (($footer_field['default_value'] ?? null) !== 'footer-v2') {
        rms_footer_test_fail(
            'test_acf_footer_choices_map_to_existing_templates',
            'Default value must be footer-v2.'
        );
    } else {
        $missing = [];
        foreach (array_keys($expected) as $slug) {
            $php  = $theme_root . '/templates/' . $slug . '.php';
            $scss = $theme_root . '/src/scss/layout/' . $slug . '.scss';
            if (!is_readable($php) || !is_readable($scss)) {
                $missing[] = $slug;
            }
        }
        if ($missing !== []) {
            rms_footer_test_fail(
                'test_acf_footer_choices_map_to_existing_templates',
                'Missing PHP/SCSS for: ' . implode(', ', $missing)
            );
        } else {
            rms_footer_test_pass('test_acf_footer_choices_map_to_existing_templates');
        }
    }
}

$header_php    = rms_footer_read($theme_root . '/header.php');
$footer_php    = rms_footer_read($theme_root . '/footer.php');
$inline_option = '/rms_get_option\s*\(\s*[\'"]company_footer_version[\'"]/';
$dispatch_failed = [];

if (!preg_match('/rms_get_footer_version\s*\(/', $header_php) || preg_match($inline_option, $header_php)) {
    $dispatch_failed[] = 'header.php must call only rms_get_footer_version()';
}
if (!preg_match('/rms_get_footer_version\s*\(/', $footer_php) || preg_match($inline_option, $footer_php)) {
    $dispatch_failed[] = 'footer.php must call only rms_get_footer_version()';
}

$GLOBALS['rms_footer_test_theme_root'] = $theme_root;
rms_footer_test_reset_fields(['company_footer_version' => 'footer-v1']);
ob_start();
include $theme_root . '/header.php';
include $theme_root . '/footer.php';
ob_end_clean();

$vite = Vite_Icons_Integration::get_instance();
$deferred_entries = array_column($vite->deferred, 'entry');
if (!in_array('src/scss/layout/footer-v1.scss', $deferred_entries, true)) {
    $dispatch_failed[] = 'header.php did not enqueue footer-v1 through the shared resolver';
}
if (!in_array('templates/footer-v1', $GLOBALS['rms_footer_test_parts'], true)) {
    $dispatch_failed[] = 'footer.php did not dispatch templates/footer-v1 through the shared resolver';
}

$empty_root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rms-footer-empty-' . bin2hex(random_bytes(4));
if (!mkdir($empty_root) && !is_dir($empty_root)) {
    $dispatch_failed[] = 'could not create empty theme root for header/footer skip';
} else {
    $GLOBALS['rms_footer_test_theme_root'] = $empty_root;
    rms_footer_test_reset_fields(['company_footer_version' => 'footer-v1']);
    ob_start();
    include $theme_root . '/header.php';
    include $theme_root . '/footer.php';
    ob_end_clean();

    $footer_deferred = array_filter(
        Vite_Icons_Integration::get_instance()->deferred,
        static function (array $item): bool {
            return strpos($item['entry'], 'src/scss/layout/footer-') === 0;
        }
    );
    $footer_parts = array_filter(
        $GLOBALS['rms_footer_test_parts'],
        static function (string $slug): bool {
            return strpos($slug, 'templates/footer-') === 0;
        }
    );
    if ($footer_deferred !== []) {
        $dispatch_failed[] = 'header.php enqueued footer CSS when resolver returned empty';
    }
    if ($footer_parts !== []) {
        $dispatch_failed[] = 'footer.php dispatched a template when resolver returned empty';
    }
    rms_footer_test_rrmdir($empty_root);
}

if ($dispatch_failed !== []) {
    rms_footer_test_fail('test_header_footer_share_normalization', implode('; ', $dispatch_failed));
} else {
    rms_footer_test_pass('test_header_footer_share_normalization');
}

$GLOBALS['rms_footer_test_theme_root'] = $theme_root;
$resolution_failed = [];
$production_cases = [
    'footer-v1'    => 'footer-v1',
    'footer-v2'    => 'footer-v2',
    'footer-one'   => 'footer-v1',
    'footer-two'   => 'footer-v2',
    ''             => 'footer-v2',
    'footer-three' => 'footer-v2',
    'not a slug!!' => 'footer-v2',
];

foreach ($production_cases as $raw => $expected_slug) {
    rms_footer_test_reset_fields(['company_footer_version' => $raw]);
    $actual = rms_get_footer_version();
    if ($actual !== $expected_slug) {
        $resolution_failed[] = $raw . " => {$actual} (expected {$expected_slug})";
    }
}

rms_footer_test_reset_fields([]);
if (rms_get_footer_version() !== 'footer-v2') {
    $resolution_failed[] = 'missing option => ' . rms_get_footer_version();
}

$v2_only_root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rms-footer-v2-only-' . bin2hex(random_bytes(4));
$v2_php_dir   = $v2_only_root . '/templates';
$v2_scss_dir  = $v2_only_root . '/src/scss/layout';
if (!mkdir($v2_php_dir, 0777, true) || !mkdir($v2_scss_dir, 0777, true)) {
    $resolution_failed[] = 'could not create V2-only theme root';
} elseif (
    !copy($theme_root . '/templates/footer-v2.php', $v2_php_dir . '/footer-v2.php')
    || !copy($theme_root . '/src/scss/layout/footer-v2.scss', $v2_scss_dir . '/footer-v2.scss')
) {
    $resolution_failed[] = 'could not copy V2 assets into isolated root';
} else {
    $GLOBALS['rms_footer_test_theme_root'] = $v2_only_root;
    rms_footer_test_reset_fields(['company_footer_version' => 'footer-v1']);
    $actual = rms_get_footer_version();
    if ($actual !== 'footer-v2') {
        $resolution_failed[] = "V1 missing/V2 present => {$actual} (expected footer-v2)";
    }
    rms_footer_test_reset_fields(['company_footer_version' => 'footer-one']);
    $actual = rms_get_footer_version();
    if ($actual !== 'footer-v2') {
        $resolution_failed[] = "alias footer-one with missing V1 => {$actual} (expected footer-v2)";
    }
}

$closed_root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rms-footer-closed-' . bin2hex(random_bytes(4));
if (!mkdir($closed_root) && !is_dir($closed_root)) {
    $resolution_failed[] = 'could not create empty theme root for fail-closed check';
} else {
    $GLOBALS['rms_footer_test_theme_root'] = $closed_root;
    rms_footer_test_reset_fields(['company_footer_version' => 'footer-v2']);
    $closed = rms_get_footer_version();
    if ($closed !== '') {
        $resolution_failed[] = "missing fallback should fail closed, got {$closed}";
    }
    rms_footer_test_rrmdir($closed_root);
}

rms_footer_test_rrmdir($v2_only_root);
$GLOBALS['rms_footer_test_theme_root'] = $theme_root;

if ($resolution_failed !== []) {
    rms_footer_test_fail(
        'test_invalid_legacy_value_renders_deterministic_fallback',
        implode('; ', $resolution_failed)
    );
} else {
    rms_footer_test_pass('test_invalid_legacy_value_renders_deterministic_fallback');
}

$markup_failed = [];
$GLOBALS['rms_footer_test_theme_root'] = $theme_root;
rms_footer_test_reset_fields([]);
ob_start();
include $theme_root . '/templates/footer-v1.php';
$empty_html = (string) ob_get_clean();
if (rms_footer_test_count_tags($empty_html, 'footer') !== 1) {
    $markup_failed[] = 'empty ACF render does not contain exactly one <footer>';
}
if (stripos($empty_html, '<nav') !== false) {
    $markup_failed[] = 'empty ACF render emitted a social <nav>';
}

if ($social_choices === []) {
    $markup_failed[] = 'ACF social_platform choices were not found';
}

$required_social_platforms = ['facebook', 'instagram', 'linkedin', 'x', 'twitter', 'youtube', 'tiktok', 'pinterest', 'other'];
foreach ($required_social_platforms as $platform) {
    if (!array_key_exists($platform, $social_choices) && !in_array($platform, ['twitter', 'x'], true)) {
        $markup_failed[] = "ACF social_platform choices are missing {$platform}";
    }
}

foreach (['footer-v1' => 'footer-v1__social-link', 'footer-v2' => 'footer-v2__social-link'] as $slug => $link_class) {
    foreach ($social_choices as $platform => $label) {
        rms_footer_test_reset_fields([
            'company_social_media' => [[
                'social_is_active' => 1,
                'social_platform'  => $platform,
                'social_url'       => 'https://example.test/' . $platform,
                'social_label'     => $label,
            ]],
        ]);
        ob_start();
        include $theme_root . '/templates/' . $slug . '.php';
        $html = (string) ob_get_clean();

        if (rms_footer_test_count_tags($html, 'footer') !== 1) {
            $markup_failed[] = "{$slug} {$platform} render does not contain exactly one <footer>";
            continue;
        }
        if (rms_footer_test_count_tags($html, 'nav') !== 1) {
            $markup_failed[] = "{$slug} {$platform} render did not emit exactly one social <nav>";
            continue;
        }
        if (preg_match('/<nav\b[^>]*>\s*<\/nav>/i', $html)) {
            $markup_failed[] = "{$slug} {$platform} render emitted an empty social <nav>";
            continue;
        }

        $href = 'https://example.test/' . $platform;
        if (!preg_match(
            '/<a\b[^>]*class="' . preg_quote($link_class, '/') . '"[^>]*>/i',
            $html,
            $link_match
        )) {
            $markup_failed[] = "{$slug} {$platform} render is missing a social link";
            continue;
        }

        $link = $link_match[0];
        if (strpos($link, 'href="' . $href . '"') === false) {
            $markup_failed[] = "{$slug} {$platform} link href is missing";
        }
        if (strpos($link, 'aria-label="' . $label . '"') === false) {
            $markup_failed[] = "{$slug} {$platform} link is missing an accessible name";
        }
        if (strpos($link, 'target="_blank"') === false || !preg_match('/rel="[^"]*noopener[^"]*noreferrer[^"]*"/', $link)) {
            $markup_failed[] = "{$slug} {$platform} link is missing external-link safety";
        }
        if (stripos($html, '<svg') === false) {
            $markup_failed[] = "{$slug} {$platform} is missing a generic-or-platform icon";
        }
    }

    rms_footer_test_reset_fields([
        'company_social_media' => [[
            'social_is_active' => 1,
            'social_platform'  => 'youtube',
            'social_url'       => '',
            'social_label'     => 'YouTube',
        ]],
    ]);
    ob_start();
    include $theme_root . '/templates/' . $slug . '.php';
    $invalid_social_html = (string) ob_get_clean();
    if (stripos($invalid_social_html, '<nav') !== false) {
        $markup_failed[] = "{$slug} invalid/empty social data still emitted a <nav>";
    }
}

$v1_scss = rms_footer_read($theme_root . '/src/scss/layout/footer-v1.scss');
if (!preg_match('/&:focus-visible\s*\{[^}]*outline:\s*2px solid var\(--footer-v1-accent/', $v1_scss)) {
    $markup_failed[] = 'footer-v1.scss must tokenize focus-visible outline with --footer-v1-accent';
}
if (preg_match('/\.footer-v1[^{]*\{[^}]*@include\s+focus-ring/', $v1_scss) || preg_match('/footer-v1__[a-z-]+[^{]*\{[^}]*@include\s+focus-ring/', $v1_scss)) {
    $markup_failed[] = 'footer-v1.scss still uses the global blue focus-ring mixin';
}
if (preg_match('/!important/i', $v1_scss)) {
    $markup_failed[] = 'footer-v1.scss must not use !important';
}

foreach (['footer-v1', 'footer-v2'] as $slug) {
    $template = rms_footer_read($theme_root . '/templates/' . $slug . '.php');
    if (rms_footer_test_count_tags($template, 'footer') !== 1) {
        $markup_failed[] = "{$slug} source does not contain exactly one <footer>";
    }
}

$manifest_path = $theme_root . '/dist/.vite/manifest.json';
if (!is_readable($manifest_path)) {
    $markup_failed[] = 'Vite manifest is missing; run npm run build first';
} else {
    $manifest = json_decode(rms_footer_read($manifest_path), true);
    if (!is_array($manifest)) {
        $markup_failed[] = 'Vite manifest did not parse';
    } else {
        foreach (['footer-v1', 'footer-v2'] as $slug) {
            $entry = 'src/scss/layout/' . $slug . '.scss';
            $file  = $manifest[$entry]['file'] ?? '';
            if (!is_string($file) || $file === '' || !is_readable($theme_root . '/dist/' . $file)) {
                $markup_failed[] = "manifest is missing a built stylesheet for {$slug}";
                continue;
            }
            if ($slug === 'footer-v1') {
                $compiled = rms_footer_read($theme_root . '/dist/' . $file);
                if (preg_match('/\.footer-v1__social-link:focus-visible\s*\{[^}]*outline:\s*2px solid\s*#2563eb/i', $compiled)) {
                    $markup_failed[] = 'compiled footer-v1 still uses a hardcoded blue focus ring';
                }
                if (!preg_match('/\.footer-v1__social-link:focus-visible\s*\{[^}]*outline:\s*2px solid\s*var\(--footer-v1-accent/', $compiled)) {
                    $markup_failed[] = 'compiled footer-v1 must emit a tokenized focus-visible outline';
                }
                if (preg_match('/!important/i', $compiled)) {
                    $markup_failed[] = 'compiled footer-v1 must not use !important';
                }
            }
        }
    }
}

if ($markup_failed !== []) {
    rms_footer_test_fail(
        'test_rendered_page_has_exactly_one_footer_and_matching_css',
        implode('; ', $markup_failed)
    );
} else {
    rms_footer_test_pass('test_rendered_page_has_exactly_one_footer_and_matching_css');
}

$palette_needles = [
    'function rms_get_palette_colors',
    'function rms_enqueue_palette_bridge',
    'rms-palette.css',
];
$palette_hits = [];
$scan_files = [
    $theme_root . '/inc/acf-theme-options.php',
    $theme_root . '/header.php',
    $theme_root . '/footer.php',
    $theme_root . '/templates/footer-v1.php',
    $theme_root . '/src/scss/layout/footer-v1.scss',
];
foreach ($scan_files as $path) {
    $contents = rms_footer_read($path);
    foreach ($palette_needles as $needle) {
        if (strpos($contents, $needle) !== false) {
            $palette_hits[] = basename($path) . ' contains ' . $needle;
        }
    }
}
if (is_file($theme_root . '/assets/css/rms-palette.css')) {
    $palette_hits[] = 'assets/css/rms-palette.css exists';
}

if ($palette_hits !== []) {
    rms_footer_test_fail('test_no_issue_29_palette_implementation', implode('; ', $palette_hits));
} else {
    rms_footer_test_pass('test_no_issue_29_palette_implementation');
}

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} footer variant check(s) failed.\n");
    exit(1);
}

fwrite(STDOUT, "\nAll footer variant checks passed.\n");
exit(0);
