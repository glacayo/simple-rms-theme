<?php
/**
 * Focused regression harness for the ACF palette runtime bridge (issue #29).
 *
 * Run: php scripts/test-palette-runtime.php
 */

$theme_root = dirname(__DIR__);
$failures   = 0;

function rms_palette_test_fail(string $name, string $message): void {
    global $failures;
    $failures++;
    fwrite(STDERR, "FAIL {$name}: {$message}\n");
}

function rms_palette_test_pass(string $name): void {
    fwrite(STDOUT, "PASS {$name}\n");
}

function rms_palette_read(string $path): string {
    $contents = file_get_contents($path);
    return is_string($contents) ? $contents : '';
}

function rms_palette_test_reset_fields(array $fields = []): void {
    $GLOBALS['rms_palette_test_fields']   = $fields;
    $GLOBALS['rms_palette_test_enqueued'] = [];
    $GLOBALS['rms_palette_test_inline']   = [];
}

function rms_palette_hex_pattern(): string {
    return '/^#(?:[A-Fa-f0-9]{3}){1,2}$/';
}

function rms_palette_extract_root_values(string $css): array {
    $map = [];
    if (preg_match_all('/--rms-color-(primary|accent-2|accent|surface)\s*:\s*([^;}]+)/', $css, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $map[$match[1]] = trim($match[2]);
        }
    }
    return $map;
}

function rms_palette_normalize_selector(string $selector): string {
    $selector = preg_replace('/\s+/', ' ', trim($selector));
    return is_string($selector) ? $selector : '';
}

/**
 * Parse comment-stripped CSS into selector => ordered property values.
 *
 * @return array<int,array{selector:string,props:array<string,list<string>>,index:int}>
 */
function rms_palette_parse_rules(string $css): array {
    $css = preg_replace('/\/\*[\s\S]*?\*\//', '', $css);
    $css = is_string($css) ? $css : '';
    $rules = [];

    if (!preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER)) {
        return [];
    }

    foreach ($matches as $index => $match) {
        $props = [];
        foreach (explode(';', $match[2]) as $decl) {
            $decl = trim($decl);
            if ($decl === '' || strpos($decl, ':') === false) {
                continue;
            }
            [$prop, $value] = explode(':', $decl, 2);
            $prop = strtolower(trim($prop));
            $props[$prop][] = trim($value);
        }

        foreach (explode(',', $match[1]) as $selector) {
            $selector = rms_palette_normalize_selector($selector);
            if ($selector === '' || strpos($selector, '@') === 0) {
                continue;
            }
            $rules[] = [
                'selector' => $selector,
                'props'    => $props,
                'index'    => $index,
            ];
        }
    }

    return $rules;
}

function rms_palette_find_rule(array $rules, string $selector): ?array {
    $selector = rms_palette_normalize_selector($selector);
    foreach ($rules as $rule) {
        if ($rule['selector'] === $selector) {
            return $rule;
        }
    }
    return null;
}

function rms_palette_rule_values(array $rule, string $prop): array {
    return $rule['props'][strtolower($prop)] ?? [];
}

function rms_palette_rule_last(array $rule, string $prop): ?string {
    $values = rms_palette_rule_values($rule, $prop);
    if ($values === []) {
        return null;
    }
    return $values[count($values) - 1];
}

function rms_palette_specificity(string $selector): int {
    preg_match_all('/#[A-Za-z0-9_-]+/', $selector, $ids);
    preg_match_all('/\.[A-Za-z0-9_-]+|:(?:hover|focus-visible|focus|active)/', $selector, $classes);
    preg_match_all('/(?:^|[\s>+~])([a-z]+)/', $selector, $elements);
    return (count($ids[0]) * 100) + (count($classes[0]) * 10) + count($elements[1]);
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

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color) {
        if ($color === '') {
            return '';
        }
        if (preg_match('/^#(?:[A-Fa-f0-9]{3}){1,2}$/', (string) $color)) {
            return $color;
        }
        return null;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['rms_palette_test_actions'][] = [
            'hook'     => (string) $hook,
            'callback' => $callback,
            'priority' => $priority,
        ];
        return true;
    }
}

if (!function_exists('get_template_directory')) {
    function get_template_directory() {
        return $GLOBALS['rms_palette_test_theme_root'];
    }
}

if (!function_exists('get_template_directory_uri')) {
    function get_template_directory_uri() {
        return 'https://example.test/wp-content/themes/simple-rms-theme';
    }
}

if (!function_exists('get_field')) {
    function get_field($selector, $post_id = false) {
        return $GLOBALS['rms_palette_test_fields'][$selector] ?? null;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle = '', $src = '', $deps = [], $ver = false, $media = 'all') {
        $GLOBALS['rms_palette_test_enqueued'][] = [
            'handle' => (string) $handle,
            'src'    => (string) $src,
            'deps'   => is_array($deps) ? $deps : [],
            'ver'    => $ver,
        ];
    }
}

if (!function_exists('wp_add_inline_style')) {
    function wp_add_inline_style($handle, $data) {
        $GLOBALS['rms_palette_test_inline'][] = [
            'handle' => (string) $handle,
            'data'   => (string) $data,
        ];
    }
}

$GLOBALS['rms_palette_test_theme_root'] = $theme_root;
$GLOBALS['rms_palette_test_actions']    = [];
rms_palette_test_reset_fields();

require $theme_root . '/inc/acf-theme-options.php';

$php_source = rms_palette_read($theme_root . '/inc/acf-theme-options.php');
$css_path   = $theme_root . '/assets/css/rms-palette.css';
$css_source = rms_palette_read($css_path);
$header_php = rms_palette_read($theme_root . '/header.php');
$footer_php = rms_palette_read($theme_root . '/footer.php');
$acf_path   = $theme_root . '/acf-json/group_rms_theme_settings.json';
$acf_json   = json_decode(rms_palette_read($acf_path), true);

$palette_fields = [
    'company_palette_color_1',
    'company_palette_color_2',
    'company_palette_color_3',
    'company_palette_color_4',
];
$defaults = ['#0f172a', '#2563eb', '#f59e0b', '#ffffff'];

// 1. All four ACF fields are consumed and emitted.
$consumer_failed = [];
foreach ($palette_fields as $field) {
    if (!preg_match('/rms_get_option\s*\(\s*[\'"]' . preg_quote($field, '/') . '[\'"]\s*\)/', $php_source)) {
        $consumer_failed[] = $field . ' is not read through rms_get_option()';
    }
}

$acf_names = [];
if (!is_array($acf_json)) {
    $consumer_failed[] = 'Theme settings JSON did not parse';
} else {
    foreach ($acf_json['fields'] ?? [] as $candidate) {
        if (isset($candidate['name']) && in_array($candidate['name'], $palette_fields, true)) {
            $acf_names[] = $candidate['name'];
        }
    }
    $missing_acf = array_diff($palette_fields, $acf_names);
    if ($missing_acf !== []) {
        $consumer_failed[] = 'ACF JSON missing: ' . implode(', ', $missing_acf);
    }
}

rms_palette_test_reset_fields([
    'company_palette_color_1' => '#000000',
    'company_palette_color_2' => '#e53935',
    'company_palette_color_3' => '#e53935',
    'company_palette_color_4' => '#ffffff',
]);
$resolved = rms_get_palette_colors();
$inline   = rms_get_palette_inline_css();
$emitted  = rms_palette_extract_root_values($inline);

if ($resolved !== ['#000000', '#e53935', '#e53935', '#ffffff']) {
    $consumer_failed[] = 'rms_get_palette_colors() did not return all four saved values';
}
if (($emitted['primary'] ?? null) !== '#000000' || ($emitted['accent'] ?? null) !== '#e53935') {
    $consumer_failed[] = 'inline CSS is missing primary/accent from saved values';
}
if (($emitted['accent-2'] ?? null) !== '#e53935' || ($emitted['surface'] ?? null) !== '#ffffff') {
    $consumer_failed[] = 'inline CSS is missing accent-2/surface from saved values';
}

if ($consumer_failed !== []) {
    rms_palette_test_fail('test_acf_palette_fields_have_frontend_consumer', implode('; ', $consumer_failed));
} else {
    rms_palette_test_pass('test_acf_palette_fields_have_frontend_consumer');
}

// 2. Invalid/empty values use stable per-field defaults.
$default_failed = [];
$invalid_cases = [
    [
        'fields'   => [],
        'expected' => $defaults,
    ],
    [
        'fields'   => [
            'company_palette_color_1' => '',
            'company_palette_color_2' => 'not-a-color',
            'company_palette_color_3' => '#gggggg',
            'company_palette_color_4' => '   ',
        ],
        'expected' => $defaults,
    ],
    [
        'fields'   => [
            'company_palette_color_1' => '#000000',
            'company_palette_color_2' => 'red',
            'company_palette_color_3' => '#fff',
            'company_palette_color_4' => null,
        ],
        'expected' => ['#000000', '#2563eb', '#fff', '#ffffff'],
    ],
    [
        'fields'   => [
            'company_palette_color_1' => ['#000000'],
            'company_palette_color_2' => 255,
            'company_palette_color_3' => false,
            'company_palette_color_4' => '#ABCDEF',
        ],
        'expected' => ['#0f172a', '#2563eb', '#f59e0b', '#ABCDEF'],
    ],
];

foreach ($invalid_cases as $case) {
    rms_palette_test_reset_fields($case['fields']);
    $actual = rms_get_palette_colors();
    if ($actual !== $case['expected']) {
        $default_failed[] = json_encode($case['fields']) . ' => ' . json_encode($actual);
    }
}

if (!function_exists('rms_get_palette_defaults') || rms_get_palette_defaults() !== $defaults) {
    $default_failed[] = 'stable compiled defaults drifted';
}

if ($default_failed !== []) {
    rms_palette_test_fail('test_invalid_palette_values_use_stable_defaults', implode('; ', $default_failed));
} else {
    rms_palette_test_pass('test_invalid_palette_values_use_stable_defaults');
}

// 3. Emitted declarations contain only valid hex; raw/malicious strings never leak.
$sanitize_failed = [];
$malicious = [
    'company_palette_color_1' => '#000000;background:url(javascript:alert(1))',
    'company_palette_color_2' => 'expression(alert(1))',
    'company_palette_color_3' => '</style><script>alert(1)</script>',
    'company_palette_color_4' => 'rgb(0,0,0)',
];
rms_palette_test_reset_fields($malicious);
$sanitized_colors = rms_get_palette_colors();
$sanitized_inline = rms_get_palette_inline_css();

if ($sanitized_colors !== $defaults) {
    $sanitize_failed[] = 'malicious values were not replaced with defaults';
}

foreach ($malicious as $raw) {
    if (strpos($sanitized_inline, $raw) !== false) {
        $sanitize_failed[] = 'raw value leaked into inline CSS';
    }
}

$root_values = rms_palette_extract_root_values($sanitized_inline);
foreach (['primary', 'accent', 'accent-2', 'surface'] as $token) {
    $value = $root_values[$token] ?? '';
    if (!preg_match(rms_palette_hex_pattern(), $value)) {
        $sanitize_failed[] = $token . ' emitted a non-hex value: ' . $value;
    }
}

if (preg_match('/--rms-color-(?:primary|accent|accent-2|surface)\s*:\s*(?!#(?:[A-Fa-f0-9]{3}){1,2}\s*(;|$))/', $sanitized_inline)) {
    $sanitize_failed[] = 'inline CSS contains a non-hex custom property';
}

if (preg_match('/error_log\s*\(|var_dump\s*\(|print_r\s*\(/', $php_source)) {
    $sanitize_failed[] = 'palette PHP must not log or dump raw values';
}

if ($sanitize_failed !== []) {
    rms_palette_test_fail('test_emitted_palette_values_are_sanitized', implode('; ', $sanitize_failed));
} else {
    rms_palette_test_pass('test_emitted_palette_values_are_sanitized');
}

// 4. Exact selector/property/order/specificity checks — not comment windows.
$css_rules = rms_palette_parse_rules($css_source);
$integration_failed = [];

$required_rules = [
    'body' => ['color' => 'var(--rms-color-primary)'],
    '.rms-header-v2__top-bar' => ['background-color' => 'var(--rms-color-primary)'],
    '.rms-header-v2__main-bar .rms-header-v2__menu-toggle .rms-header-v2__menu-icon span' => [
        'background-color' => 'var(--rms-color-primary-strong)',
    ],
    '.btn' => [
        'color'            => 'var(--rms-color-surface)',
        'background-color' => 'var(--rms-color-accent)',
        'border-color'     => 'var(--rms-color-accent)',
    ],
    '.btn:hover' => [
        'background-color' => 'var(--rms-color-accent-strong)',
        'border-color'     => 'var(--rms-color-accent-strong)',
    ],
    '.btn:focus-visible' => [
        'background-color' => 'var(--rms-color-accent-strong)',
    ],
    '.btn.btn--outline-white' => [
        'color'            => 'var(--rms-color-surface)',
        'background-color' => 'transparent',
        'border-color'     => 'var(--rms-color-surface)',
    ],
    '.btn.btn--outline-white:hover' => [
        'color'            => 'var(--rms-color-primary)',
        'background-color' => 'var(--rms-color-surface)',
    ],
    '.rms-header-v2__top-bar-right .rms-header-v2__cta-btn:hover' => [
        'background-color' => 'var(--rms-color-accent-strong)',
    ],
    '.footer-v2__cta-button:hover' => [
        'background-color' => 'var(--rms-color-accent-strong)',
    ],
    '.hero__form-submit:hover' => [
        'background-color' => 'var(--rms-color-accent-strong)',
    ],
    '.slider__overlay--dark' => [
        'background-color' => 'color-mix(in srgb, var(--rms-color-primary) 75%, transparent)',
    ],
    '.hero__overlay--dark' => [
        'background-color' => 'color-mix(in srgb, var(--rms-color-primary) 75%, transparent)',
    ],
    '.hero__overlay--primary' => [
        'background-color' => 'color-mix(in srgb, var(--rms-color-accent) 80%, transparent)',
    ],
    '.portfolio-v2__overlay' => [
        'background-color' => 'color-mix(in srgb, var(--rms-color-primary) 80%, transparent)',
    ],
    '.area-coverage-v1__map-ring' => [
        'stroke' => 'var(--rms-color-accent)',
        'filter' => 'drop-shadow(0 0 8px color-mix(in srgb, var(--rms-color-accent) 35%, transparent))',
    ],
    '.cta-v1' => [
        'background-image' => null,
    ],
    '.rms-header__nav-bar' => [
        'background-color' => 'var(--rms-color-accent)',
    ],
    '.rms-header__cta-btn' => [
        'color'            => 'var(--rms-color-surface)',
        'background-color' => 'var(--rms-color-accent)',
    ],
    '.rms-header__cta-btn:hover' => [
        'background-color' => 'var(--rms-color-accent-strong)',
    ],
    '.rms-header__dropdown > li > a:hover' => [
        'color' => 'var(--rms-color-accent)',
    ],
    '.rms-header__mobile-nav-list > li > a:hover' => [
        'color' => 'var(--rms-color-accent)',
    ],
    '.rms-header__cta-btn:focus-visible' => [
        'outline-color' => 'var(--rms-color-accent)',
    ],
    '.rms-header-v3__nav-link::after' => [
        'background-color' => 'var(--rms-color-accent)',
    ],
    '.rms-header-v3__social-link:hover' => [
        'color' => 'var(--rms-color-accent)',
    ],
    '.rms-header-v3__mobile-phone:hover' => [
        'color' => 'var(--rms-color-accent)',
    ],
    '.rms-header-v3__nav-link:focus-visible' => [
        'outline-color' => 'var(--rms-color-accent)',
    ],
    '.footer-v1__social-link:focus-visible' => [
        'outline-color' => 'var(--rms-color-accent)',
    ],
];

foreach ($required_rules as $selector => $expectations) {
    $rule = rms_palette_find_rule($css_rules, $selector);
    if ($rule === null) {
        $integration_failed[] = $selector . ' is missing';
        continue;
    }
    foreach ($expectations as $prop => $value) {
        if ($value === null) {
            if (rms_palette_rule_values($rule, $prop) === []) {
                $integration_failed[] = $selector . ' is missing ' . $prop;
            }
            continue;
        }
        $last = rms_palette_rule_last($rule, $prop);
        if ($last !== $value) {
            $integration_failed[] = $selector . ' ' . $prop . ' last value is ' . ($last ?? 'missing');
        }
    }
}

$menu_icon = '.rms-header-v2__main-bar .rms-header-v2__menu-toggle .rms-header-v2__menu-icon span';
$compiled_menu_spec = rms_palette_specificity($menu_icon);
$bridge_menu_rule = rms_palette_find_rule($css_rules, $menu_icon);
if ($bridge_menu_rule === null || rms_palette_specificity($bridge_menu_rule['selector']) < $compiled_menu_spec) {
    $integration_failed[] = 'menu icon selector loses to compiled specificity ' . $compiled_menu_spec;
}

$btn_rule = rms_palette_find_rule($css_rules, '.btn');
$outline_white = rms_palette_find_rule($css_rules, '.btn.btn--outline-white');
if ($btn_rule === null || $outline_white === null || $outline_white['index'] <= $btn_rule['index']) {
    $integration_failed[] = '.btn.btn--outline-white must be declared after .btn';
}

$outline_white_hover = rms_palette_find_rule($css_rules, '.btn.btn--outline-white:hover');
if ($outline_white_hover !== null && rms_palette_rule_last($outline_white_hover, 'background-color') !== 'var(--rms-color-surface)') {
    $integration_failed[] = 'outline-white hover must keep a white/surface fill';
}

$dark_overlay = rms_palette_find_rule($css_rules, '.slider__overlay--dark');
if ($dark_overlay !== null) {
    $bg = rms_palette_rule_values($dark_overlay, 'background-color');
    if (count($bg) < 2 || strpos($bg[0], 'rgba(15, 23, 42') === false) {
        $integration_failed[] = 'slider dark overlay is missing the compiled-color fallback';
    }
}

if ($integration_failed !== []) {
    rms_palette_test_fail('test_computed_style_integration_palette', implode('; ', $integration_failed));
} else {
    rms_palette_test_pass('test_computed_style_integration_palette');
}

// 5. No generated/dist CSS edits and no DB mutation in the palette unit.
$dist_failed = [];
if (preg_match('/update_option\s*\(|add_option\s*\(|delete_option\s*\(|update_field\s*\(/', $php_source)) {
    $dist_failed[] = 'palette PHP must not mutate options/DB';
}
if (strpos($php_source, '/dist/') !== false || strpos($css_source, '/dist/') !== false) {
    $dist_failed[] = 'palette implementation must not reference dist CSS';
}
if (!is_readable($css_path)) {
    $dist_failed[] = 'authored assets/css/rms-palette.css is missing';
}

$changed = [];
exec('git -C ' . escapeshellarg($theme_root) . ' status --porcelain', $changed);
foreach ($changed as $line) {
    if (preg_match('/\bdist\//', $line)) {
        $dist_failed[] = 'git status includes a dist path: ' . $line;
    }
}

if ($dist_failed !== []) {
    rms_palette_test_fail('test_no_generated_css_edits', implode('; ', $dist_failed));
} else {
    rms_palette_test_pass('test_no_generated_css_edits');
}

// 6. Bridge contains no !important declarations.
$css_code = preg_replace('/\/\*[\s\S]*?\*\//', '', $css_source);
$php_code = preg_replace('/\/\*[\s\S]*?\*\//', '', $php_source);
$php_code = preg_replace('/\/\/.*$/m', '', $php_code);
if (preg_match('/!important/i', (string) $css_code) || preg_match('/!important/i', (string) $php_code)) {
    rms_palette_test_fail('test_palette_bridge_no_important', 'Found !important in the palette bridge.');
} else {
    rms_palette_test_pass('test_palette_bridge_no_important');
}

// 7. Authored stylesheet is enqueued through a safe WordPress mechanism.
$enqueue_failed = [];
$hooked = false;
foreach ($GLOBALS['rms_palette_test_actions'] as $action) {
    if ($action['hook'] === 'wp_enqueue_scripts' && $action['callback'] === 'rms_enqueue_palette_bridge') {
        $hooked = true;
        if ((int) $action['priority'] !== 20) {
            $enqueue_failed[] = 'enqueue hook priority must be 20';
        }
    }
}
if (!$hooked) {
    $enqueue_failed[] = 'rms_enqueue_palette_bridge is not hooked to wp_enqueue_scripts';
}

rms_palette_test_reset_fields([
    'company_header_version'  => 'header-two',
    'company_palette_color_1' => '#111111',
    'company_palette_color_2' => '#222222',
    'company_palette_color_3' => '#333333',
    'company_palette_color_4' => '#444444',
]);
rms_enqueue_palette_bridge();

if (count($GLOBALS['rms_palette_test_enqueued']) !== 1) {
    $enqueue_failed[] = 'expected exactly one wp_enqueue_style call';
} else {
    $queued = $GLOBALS['rms_palette_test_enqueued'][0];
    if ($queued['handle'] !== 'rms-palette') {
        $enqueue_failed[] = 'handle must be rms-palette';
    }
    if (strpos($queued['src'], '/assets/css/rms-palette.css') === false) {
        $enqueue_failed[] = 'src must point at authored assets/css/rms-palette.css';
    }
    if (!in_array('header-two', $queued['deps'], true)) {
        $enqueue_failed[] = 'bridge must depend on the active header handle';
    }
    $expected_version = (string) filemtime($css_path);
    if ((string) $queued['ver'] !== $expected_version) {
        $enqueue_failed[] = 'version must be the authored CSS filemtime';
    }
}

$header_fallback_cases = [
    ['label' => 'empty', 'fields' => ['company_header_version' => ''], 'expected' => 'header-one'],
    ['label' => 'invalid', 'fields' => ['company_header_version' => '!!!'], 'expected' => 'header-one'],
    ['label' => 'missing', 'fields' => [], 'expected' => 'header-one'],
    ['label' => 'header-three', 'fields' => ['company_header_version' => 'header-three'], 'expected' => 'header-three'],
];
foreach ($header_fallback_cases as $case) {
    rms_palette_test_reset_fields($case['fields'] + [
        'company_palette_color_1' => '#111111',
        'company_palette_color_2' => '#222222',
        'company_palette_color_3' => '#333333',
        'company_palette_color_4' => '#444444',
    ]);
    rms_enqueue_palette_bridge();
    $dep = $GLOBALS['rms_palette_test_enqueued'][0]['deps'][0] ?? null;
    if ($dep !== $case['expected']) {
        $enqueue_failed[] = 'header dependency for ' . $case['label'] . ' was ' . json_encode($dep);
    }
}

if (count($GLOBALS['rms_palette_test_inline']) !== 1) {
    $enqueue_failed[] = 'expected exactly one wp_add_inline_style call';
} else {
    $inline_style = $GLOBALS['rms_palette_test_inline'][0];
    if ($inline_style['handle'] !== 'rms-palette') {
        $enqueue_failed[] = 'inline CSS must attach to rms-palette';
    }
    $inline_values = rms_palette_extract_root_values($inline_style['data']);
    if ($inline_values !== [
        'primary'  => '#111111',
        'accent'   => '#222222',
        'accent-2' => '#333333',
        'surface'  => '#444444',
    ]) {
        $enqueue_failed[] = 'inline CSS did not emit the four sanitized colors';
    }
}

$missing_root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rms-palette-missing-' . bin2hex(random_bytes(4));
if (!mkdir($missing_root) && !is_dir($missing_root)) {
    $enqueue_failed[] = 'could not create missing-css theme root';
} else {
    $GLOBALS['rms_palette_test_theme_root'] = $missing_root;
    rms_palette_test_reset_fields([]);
    rms_enqueue_palette_bridge();
    if ($GLOBALS['rms_palette_test_enqueued'] !== [] || $GLOBALS['rms_palette_test_inline'] !== []) {
        $enqueue_failed[] = 'missing CSS file must not enqueue or emit tokens';
    }
    rmdir($missing_root);
    $GLOBALS['rms_palette_test_theme_root'] = $theme_root;
}

if ($enqueue_failed !== []) {
    rms_palette_test_fail('test_palette_bridge_enqueued', implode('; ', $enqueue_failed));
} else {
    rms_palette_test_pass('test_palette_bridge_enqueued');
}

// 8. CSS token / selector / gradient / SVG coverage.
$coverage_failed = [];
$required_tokens = [
    '--rms-color-primary',
    '--rms-color-accent',
    '--rms-color-accent-2',
    '--rms-color-surface',
    '--rms-color-primary-strong',
    '--rms-color-primary-muted',
    '--rms-color-accent-strong',
];
$root_rule = rms_palette_find_rule($css_rules, ':root');
foreach ($required_tokens as $token) {
    $found = $root_rule !== null && array_key_exists(strtolower($token), $root_rule['props']);
    if (!$found && strpos($css_source, $token) === false) {
        $coverage_failed[] = 'missing token ' . $token;
    }
}

$cta = rms_palette_find_rule($css_rules, '.cta-v1');
$cta_image = $cta ? rms_palette_rule_last($cta, 'background-image') : null;
if (!is_string($cta_image) || strpos($cta_image, 'linear-gradient(') === false || strpos($cta_image, 'var(--rms-color-accent-strong)') === false) {
    $coverage_failed[] = 'CTA-v1 gradient must consume accent-strong';
}

$shape = rms_palette_find_rule($css_rules, '.slider__shape path');
if ($shape === null || rms_palette_rule_last($shape, 'fill') !== 'var(--rms-color-primary)') {
    $coverage_failed[] = 'slider SVG fill must use the primary token';
}

$ring = rms_palette_find_rule($css_rules, '.area-coverage-v1__map-ring');
if ($ring === null || rms_palette_rule_last($ring, 'stroke') !== 'var(--rms-color-accent)') {
    $coverage_failed[] = 'area-coverage SVG stroke must use the accent token';
}

$header_one_nav = rms_palette_find_rule($css_rules, '.rms-header__nav-bar');
if ($header_one_nav === null || rms_palette_rule_last($header_one_nav, 'background-color') !== 'var(--rms-color-accent)') {
    $coverage_failed[] = 'Header One nav bar must use the accent token';
}
$header_three_underline = rms_palette_find_rule($css_rules, '.rms-header-v3__nav-link::after');
if ($header_three_underline === null || rms_palette_rule_last($header_three_underline, 'background-color') !== 'var(--rms-color-accent)') {
    $coverage_failed[] = 'Header Three active underline must use the accent token';
}

if (!preg_match('/@supports\s*\(\s*color:\s*color-mix/', $css_source)) {
    $coverage_failed[] = 'missing color-mix @supports fallbacks';
}

$open  = substr_count($css_source, '{');
$close = substr_count($css_source, '}');
if ($open === 0 || $open !== $close) {
    $coverage_failed[] = "unbalanced CSS braces ({$open}/{$close})";
}

if ($coverage_failed !== []) {
    rms_palette_test_fail('test_palette_css_coverage', implode('; ', $coverage_failed));
} else {
    rms_palette_test_pass('test_palette_css_coverage');
}

// 9. Integration tree: #28 footer resolver must coexist with the palette bridge.
$issue28_failed = [];
if (!preg_match('/function\s+rms_get_footer_version\s*\(/', $php_source)) {
    $issue28_failed[] = 'acf-theme-options.php is missing rms_get_footer_version';
}
if (!preg_match('/rms_get_footer_version\s*\(/', $header_php) || preg_match('/rms_get_option\s*\(\s*[\'"]company_footer_version[\'"]/', $header_php)) {
    $issue28_failed[] = 'header.php must dispatch footer CSS only through rms_get_footer_version()';
}
if (!preg_match('/rms_get_footer_version\s*\(/', $footer_php) || preg_match('/rms_get_option\s*\(\s*[\'"]company_footer_version[\'"]/', $footer_php)) {
    $issue28_failed[] = 'footer.php must dispatch footer templates only through rms_get_footer_version()';
}

if ($issue28_failed !== []) {
    rms_palette_test_fail('test_issue_28_footer_resolver_coexists', implode('; ', $issue28_failed));
} else {
    rms_palette_test_pass('test_issue_28_footer_resolver_coexists');
}

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} palette runtime check(s) failed.\n");
    exit(1);
}

fwrite(STDOUT, "\nAll palette runtime checks passed.\n");
exit(0);
