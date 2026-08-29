<?php
/**
 * Aggregate runner for the #56 header primary CTA harness family.
 *
 * Executes the three focused harnesses in isolated sub-processes and surfaces a
 * single pass/fail rollup. Individual assertions live in:
 *   - tests/header-primary-cta-schema-harness.php
 *   - tests/header-primary-cta-behavior-harness.php
 *   - tests/header-primary-cta-regression-harness.php
 *
 * No framework. Parent process shells out; stubs are isolated per harness.
 *
 * Usage: php tests/header-primary-cta-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$php    = PHP_BINARY;
$dir    = __DIR__;
$suites = array(
    'schema'     => $dir . '/header-primary-cta-schema-harness.php',
    'behavior'   => $dir . '/header-primary-cta-behavior-harness.php',
    'regression' => $dir . '/header-primary-cta-regression-harness.php',
);

$failed = 0;

foreach ( $suites as $name => $path ) {
    $cmd    = '"' . $php . '" ' . escapeshellarg( $path );
    $output = array();
    $code   = 0;
    exec( $cmd, $output, $code );
    if ( 0 !== $code ) {
        fwrite( STDERR, "FAIL {$name}\n" . implode( "\n", $output ) . "\n" );
        $failed++;
        continue;
    }
    echo "PASS {$name}\n";
    echo implode( "\n", $output ) . "\n";
}

if ( $failed > 0 ) {
    fwrite( STDERR, "Harness failed: {$failed} suite(s).\n" );
    exit( 1 );
}

echo 'Harness passed: ' . count( $suites ) . " suites.\n";
exit( 0 );
