<?php
/**
 * Wizard Landing Page Builder aggregate final-state harness (Phase 4 task 4.3).
 *
 * Runs all 14 behavior proof scenarios across:
 *  - Identity & Canonical Store harness (8 scenarios)
 *  - Lifecycle & Protection harness (6 scenarios)
 *
 * Usage: php tests/wizard-landing-final-state-harness.php
 *
 * @package Simple_RMS_Theme
 */

require_once __DIR__ . '/wizard-landing-identity-canonical-harness.php';
require_once __DIR__ . '/wizard-landing-lifecycle-protection-harness.php';

$identity_passed   = rms_run_landing_identity_canonical_tests();
$protection_passed = rms_run_landing_lifecycle_protection_tests();
$total_passed      = $identity_passed + $protection_passed;

echo 'Harness passed: ' . $total_passed . " scenarios.\n";
