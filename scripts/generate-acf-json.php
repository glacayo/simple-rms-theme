<?php
/**
 * ACF JSON Generator
 *
 * Reads the field group definition from inc/acf-flexible-content.php
 * and generates acf-json/group_rms_page_sections.json.
 *
 * Usage: php scripts/generate-acf-json.php
 */

// Determine theme root relative to this script.
$themeRoot = dirname(__DIR__);

// Path to the source PHP file that defines the field group.
$sourceFile = $themeRoot . '/inc/acf-flexible-content.php';

// Path where the generated JSON will be written.
$outputFile = $themeRoot . '/acf-json/group_rms_page_sections.json';

if (!file_exists($sourceFile)) {
    fwrite(STDERR, "Error: Source file not found: {$sourceFile}\n");
    exit(1);
}

// Global variable to capture the field group array.
$GLOBALS['_rms_captured_field_group'] = null;

/**
 * Mock ACF function to capture the field group array.
 *
 * @param array $field_group The field group definition.
 */
function acf_add_local_field_group(array $field_group): void {
    $GLOBALS['_rms_captured_field_group'] = $field_group;
}

/**
 * Mock WordPress add_action — no-op because we will invoke the
 * registration function directly.
 */
function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
    // No-op.
}

// Include the source file. This defines rms_register_acf_page_sections().
require_once $sourceFile;

// Invoke the registration function directly so it calls our mock.
if (!function_exists('rms_register_acf_page_sections')) {
    fwrite(STDERR, "Error: Function rms_register_acf_page_sections() not found in {$sourceFile}\n");
    exit(1);
}

rms_register_acf_page_sections();

$fieldGroup = $GLOBALS['_rms_captured_field_group'];

if (empty($fieldGroup)) {
    fwrite(STDERR, "Error: Failed to capture field group. Make sure acf_add_local_field_group() is called in {$sourceFile}\n");
    exit(1);
}

// Update the modified timestamp to now.
$fieldGroup['modified'] = time();

// Ensure the output directory exists.
$outputDir = dirname($outputFile);
if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0755, true)) {
        fwrite(STDERR, "Error: Unable to create directory: {$outputDir}\n");
        exit(1);
    }
}

// Encode to JSON with pretty printing.
$json = json_encode($fieldGroup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if ($json === false) {
    fwrite(STDERR, "Error: JSON encoding failed: " . json_last_error_msg() . "\n");
    exit(1);
}

if (file_put_contents($outputFile, $json . PHP_EOL) === false) {
    fwrite(STDERR, "Error: Failed to write JSON to {$outputFile}\n");
    exit(1);
}

// Count layouts for the success message.
$layoutCount = 0;
if (!empty($fieldGroup['fields'][0]['layouts']) && is_array($fieldGroup['fields'][0]['layouts'])) {
    $layoutCount = count($fieldGroup['fields'][0]['layouts']);
}

echo "Success: Generated {$outputFile}\n";
echo "Layouts: {$layoutCount}\n";
echo "Modified: " . date('c', $fieldGroup['modified']) . "\n";
