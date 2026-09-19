<?php
/**
 * Focused contract harness for issue #128 slice I128-03 (Raven form lifecycle
 * events: once-only form_start plus confirmed Contact Form 7 form_success).
 *
 * Proves: the tracker source contract (dataLayer-only identity pushes, start on
 * focusin/input, success only from wpcf7mailsent, no field-data access, no
 * forbidden triggers); main.ts imports one tracker with no new Vite entry or
 * PHP enqueue handle; contact-info.php marks its fallback form unchanged; and
 * behavior through an embedded Node fixture that compiles the real module and
 * drives a minimal DOM (filtering, Set dedup, CF7 identity precedence, exact
 * payload keys, defensive dataLayer init, idempotent/DOM-ready init, form-target
 * success gating, zero input-value reads). Uses tests/support/header-cta-support.php
 * only for rms_cta_assert/pass/fail.
 *
 * Usage: php tests/raven-form-events-harness.php
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only.\n" );
    exit( 1 );
}

$theme_root = dirname( __DIR__ );

require __DIR__ . '/support/header-cta-support.php';

/**
 * Read a repository-relative file, returning '' when it cannot be read.
 */
function rms_raven_form_read( string $relative ): string {
    global $theme_root;
    $path = $theme_root . '/' . $relative;
    $src  = is_readable( $path ) ? file_get_contents( $path ) : '';
    return is_string( $src ) ? $src : '';
}

/**
 * Assert that every token is present ($present = true) or absent (false).
 */
function rms_raven_form_tokens( string $source, array $tokens, bool $present, string $name, string $detail ): void {
    $haystack  = preg_replace( '/\s+/', ' ', $source );
    $offenders = array();
    foreach ( $tokens as $token ) {
        $found = false !== strpos( $haystack, preg_replace( '/\s+/', ' ', $token ) );
        if ( $found !== $present ) {
            $offenders[] = $token;
        }
    }
    rms_cta_assert( array() === $offenders, $name, $detail . ' ' . ( $present ? 'Missing' : 'Found' ) . ': ' . implode( ', ', $offenders ) );
}

// 1. src/ts/form-tracker.ts contract.

$tracker = rms_raven_form_read( 'src/ts/form-tracker.ts' );
rms_cta_assert( '' !== $tracker, 'test_tracker_module_exists', 'src/ts/form-tracker.ts must exist and be non-empty' );
rms_raven_form_tokens(
    $tracker,
    array( 'form_start', 'form_success', "addEventListener('wpcf7mailsent'", "addEventListener('focusin'", "addEventListener('input'", 'data-raven-form-id', 'data-raven-form-name', 'aria-label', 'wpcf7-form', 'wpcf7-f', 'Contact Form 7 #', 'dataLayer', 'new Set<string>()', 'initFormTracker' ),
    true,
    'test_tracker_contract_tokens',
    'the tracker must encode the documented form lifecycle contract'
);
rms_cta_assert( 1 === substr_count( $tracker, "'form_success'" ), 'test_tracker_single_success_literal', 'form_success must be declared once so every success path shares one constant' );
rms_raven_form_tokens(
    $tracker,
    array( "addEventListener('submit'", "addEventListener('click'", 'wpcf7submit', 'wpcf7invalid', 'wpcf7spam', 'wpcf7mailfailed' ),
    false,
    'test_tracker_no_forbidden_success_triggers',
    'only wpcf7mailsent may produce a success event'
);
rms_raven_form_tokens(
    $tracker,
    array( '.value', 'FormData', '.elements', 'innerHTML', 'innerText', 'localStorage', 'sessionStorage', 'email', 'phone', 'message', 'address', 'password', 'fetch(' ),
    false,
    'test_tracker_no_pii_or_field_access',
    'the tracker must never read or serialize form field data'
);

// 2. src/ts/main.ts import + single initialization.

$main = rms_raven_form_read( 'src/ts/main.ts' );
rms_raven_form_tokens( $main, array( "import { initFormTracker } from './form-tracker'", 'initFormTracker()' ), true, 'test_main_imports_and_initializes', 'main.ts must import and initialize the global form tracker' );
rms_cta_assert( 1 === substr_count( $main, 'initFormTracker()' ), 'test_main_single_initialization', 'main.ts must call initFormTracker() exactly once' );

// 3. No new Vite entry or PHP enqueue handle.

$vite = rms_raven_form_read( 'vite.config.ts' );
rms_cta_assert( false === strpos( $vite, 'form-tracker' ), 'test_vite_no_new_entry', 'vite.config.ts must not add a form-tracker entry' );
rms_cta_assert( false !== strpos( $vite, "path.resolve(__dirname, 'src/ts/main.ts')" ), 'test_vite_main_entry_intact', 'the existing src/ts/main.ts entry must stay the single global JS input' );
foreach ( glob( $theme_root . '/inc/*.php' ) as $php_path ) {
    $php_src = is_readable( $php_path ) ? file_get_contents( $php_path ) : '';
    rms_cta_assert( false === strpos( (string) $php_src, 'form-tracker' ), 'test_php_no_enqueue_' . basename( $php_path ), basename( $php_path ) . ' must not enqueue or reference the tracker module' );
}
rms_cta_assert( false !== strpos( rms_raven_form_read( 'inc/vite-integration.php' ), "'raven-main'" ), 'test_php_main_handle_intact', 'the existing raven-main handle must stay unchanged' );

// 4. templates/contact-info.php fallback form identity.

$contact = rms_raven_form_read( 'templates/contact-info.php' );
rms_cta_assert( 1 === substr_count( $contact, 'data-raven-form-id="contact-info-default"' ), 'test_contact_form_id_marker', 'the fallback form must carry data-raven-form-id="contact-info-default"' );
rms_cta_assert( 1 === substr_count( $contact, 'data-raven-form-name="Contact Form"' ), 'test_contact_form_name_marker', 'the fallback form must carry data-raven-form-name="Contact Form"' );
rms_cta_assert( 1 === substr_count( $contact, 'data-raven-form-id=' ), 'test_contact_single_identity_marker', 'contact-info.php must mark exactly one element' );
rms_cta_assert(
    false !== strpos( $contact, '<form class="contact-info__form" action="#" method="POST" novalidate data-raven-form-id="contact-info-default" data-raven-form-name="Contact Form">' ),
    'test_contact_form_behavior_unchanged',
    'the marker must be added without changing the form class/action/method/novalidate'
);
rms_cta_assert( 1 === substr_count( $contact, '<form' ) && false !== strpos( $contact, 'do_shortcode($form_shortcode)' ), 'test_contact_shortcode_branch_intact', 'the configured CF7 shortcode branch must stay intact and unmarked' );
foreach ( glob( $theme_root . '/templates/*.php' ) as $template_path ) {
    if ( 'contact-info.php' === basename( $template_path ) ) {
        continue;
    }
    $template_src = is_readable( $template_path ) ? file_get_contents( $template_path ) : '';
    rms_cta_assert( false === strpos( (string) $template_src, 'data-raven-form' ), 'test_no_stray_form_identity_' . basename( $template_path ), basename( $template_path ) . ' must not carry an unowned form identity marker' );
}

// 5. Executable behavior fixture against the real module.

$fixture = <<<'JS'
import { createRequire } from 'node:module';
import { readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

// package.json declares "type": "commonjs", so compile the real TypeScript
// source with the repo's TypeScript devDependency and import it as ESM.
const themeRoot = process.argv[2];
const ts = createRequire(resolve(themeRoot, 'package.json'))('typescript');
const options = { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ESNext } };
const compiled = ts.transpileModule(readFileSync(resolve(themeRoot, 'src/ts/form-tracker.ts'), 'utf8'), options).outputText;
const modulePath = resolve(tmpdir(), 'rms-raven-form-tracker-' + process.pid + '.mjs');
writeFileSync(modulePath, compiled);
const trackerUrl = pathToFileURL(modulePath).href;

const failures = [];
let checks = 0;
const check = (name, ok, detail) => { checks += 1; if (!ok) { failures.push(name); console.error('FAIL ' + name + ': ' + detail); } };
const eq = (name, actual, expected) => check(name, actual === expected, 'expected ' + JSON.stringify(expected) + ', got ' + JSON.stringify(actual));
let loads = 0;
const load = () => import(trackerUrl + '?fixture=' + (loads += 1));
const el = (tag, attrs, parent) => { const node = { tagName: tag.toUpperCase(), attrs: Object.assign({}, attrs || {}), parentElement: parent || null, children: [], getAttribute(name) { return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null; }, closest(selector) { let current = this; while (current) { if (selector[0] === '.' ? String(current.getAttribute('class') || '').split(/\s+/).includes(selector.slice(1)) : current.tagName === selector.toUpperCase()) return current; current = current.parentElement; } return null; }, querySelector(selector) { return this.children.find((child) => child.tagName === 'FORM' && selector.includes('form')) || null; } }; if (parent) parent.children.push(node); return node; };
const doc = (readyState) => {
  const listeners = [];
  return { readyState, addEventListener: (type, handler) => listeners.push({ type, handler }), count: (type) => listeners.filter((e) => e.type === type).length, emit(type, target, detail) { const event = { type, target, detail }; listeners.filter((e) => e.type === type).forEach((e) => e.handler(event)); } };
};
const cf7 = (n) => el('form', { class: 'wpcf7-form' }, el('div', { id: 'wpcf7-f' + n + '-o1', class: 'wpcf7' }));
const pushes = (win) => (win.dataLayer ? win.dataLayer.length : 0);
const keys = (p) => Object.keys(p).join(',');
const boot = (win, dom) => { globalThis.window = win; globalThis.document = dom; };

// A — tracked-form filtering, CF7 identity precedence, name fallbacks.
{
  const t = await load();
  check('filter/tracked', t.isTrackedForm(cf7(1)) && t.isTrackedForm(el('form', { class: 'wpcf7-form' })) && t.isTrackedForm(el('form', { 'data-raven-form-id': 'x' })), 'CF7 and explicitly marked forms must be tracked');
  ['search-form', 'comment-form', 'login-form', 'admin-form'].forEach((cls) => check('filter/untracked/' + cls, !t.isTrackedForm(el('form', { class: cls })), cls + ' must stay untracked'));
  eq('id/dom-wrapper', t.resolveFormId(cf7(123)), 'cf7-123');
  eq('id/unit-class', t.resolveFormId(el('form', { class: 'wpcf7-f45-o2' }, el('div', { class: 'wpcf7' }))), 'cf7-45');
  eq('id/detail-number', t.resolveFormId(cf7(1), { contactFormId: 456 }), 'cf7-456');
  eq('id/detail-string', t.resolveFormId(cf7(1), { contactFormId: '789' }), 'cf7-789');
  eq('id/detail-unit-tag', t.resolveFormId(cf7(1), { unitTag: 'wpcf7-f321-o1' }), 'cf7-321');
  eq('id/explicit-wins', t.resolveFormId(el('form', { 'data-raven-form-id': 'contact-info-default' }, el('div', { id: 'wpcf7-f999-o1' })), { contactFormId: 999 }), 'contact-info-default');
  eq('id/start-success-stable', t.resolveFormId(cf7(123)) + '|' + t.resolveFormId(cf7(123), { contactFormId: 123, unitTag: 'wpcf7-f123-o1' }), 'cf7-123|cf7-123');
  eq('id/no-parse-fallback', t.resolveFormId(el('form', { class: 'wpcf7-form' })), 'cf7-form');
  eq('name/explicit-wins', t.resolveFormName(el('form', { 'data-raven-form-name': 'Contact Form', 'aria-label': 'ignored' }), 'contact-info-default'), 'Contact Form');
  eq('name/aria-label', t.resolveFormName(el('form', { 'aria-label': 'Get a Quote' }), 'cf7-7'), 'Get a Quote');
  eq('name/cf7-fallback', t.resolveFormName(cf7(123), 'cf7-123'), 'Contact Form 7 #123');
  check('name/never-landing-title', t.resolveFormName(cf7(123), 'cf7-123') !== 'Request a Free Estimate', 'CF7 forms must not inherit the managed landing title');
}

// B — form_start: once per form, tracked only, no field-data reads.
{
  const dom = doc('complete');
  const win = { dataLayer: [] };
  boot(win, dom);
  const t = await load();
  t.initFormTracker();
  const form = cf7(123);
  let reads = 0;
  const field = el('input', {}, form);
  Object.defineProperty(field, 'value', { get() { reads += 1; return 'rms-secret-sentinel-9f2'; } });
  dom.emit('focusin', field);
  dom.emit('input', field);
  dom.emit('focusin', field);
  eq('start/once-per-form', pushes(win), 1);
  eq('start/identity', win.dataLayer[0].event + '|' + win.dataLayer[0].form_id + '|' + win.dataLayer[0].form_name, 'form_start|cf7-123|Contact Form 7 #123');
  eq('start/payload-keys', keys(win.dataLayer[0]), 'event,form_id,form_name');
  dom.emit('input', el('input', {}, cf7(123)));
  eq('start/dedup-same-id-across-nodes', pushes(win), 1);
  dom.emit('input', el('input', {}, cf7(456)));
  eq('start/second-form', pushes(win) + '|' + win.dataLayer[1].form_id, '2|cf7-456');
  const query = el('input', {}, el('form', { class: 'search-form' }));
  dom.emit('focusin', query);
  dom.emit('input', query);
  eq('start/untracked-silent', pushes(win), 2);
  eq('pii/no-field-reads', reads, 0);
  check('pii/no-sentinel', JSON.stringify(win.dataLayer).indexOf('rms-secret-sentinel-9f2') === -1, 'payloads must not carry field values');
}

// C — form_success: confirmed wpcf7mailsent on the CF7 form only, defensive dataLayer.
{
  const dom = doc('complete');
  const win = {};
  boot(win, dom);
  const t = await load();
  t.initFormTracker();
  const form = cf7(321);
  ['submit', 'click', 'wpcf7submit', 'wpcf7invalid', 'wpcf7spam', 'wpcf7mailfailed'].forEach((type) => dom.emit(type, form));
  eq('success/no-optimistic-triggers', pushes(win), 0);
  dom.emit('wpcf7mailsent', form, { contactFormId: 321 });
  eq('success/confirmed-form-target', pushes(win), 1);
  check('success/defensive-dataLayer', Array.isArray(win.dataLayer), 'window.dataLayer must be created defensively');
  eq('success/identity', win.dataLayer[0].event + '|' + win.dataLayer[0].form_id + '|' + keys(win.dataLayer[0]), 'form_success|cf7-321|event,form_id,form_name');
  dom.emit('wpcf7mailsent', form, { contactFormId: 321 });
  eq('success/repeat-allowed', pushes(win), 2);
  const unitForm = cf7(654);
  dom.emit('wpcf7mailsent', unitForm, { unitTag: 'wpcf7-f654-o1' });
  eq('success/unit-tag-only', win.dataLayer[2].form_id, 'cf7-654');
  const marked = el('form', { 'data-raven-form-id': 'contact-info-default', 'data-raven-form-name': 'Contact Form' });
  dom.emit('wpcf7mailsent', marked);
  eq('success/explicit-raw-raven-silent', pushes(win), 3);
  dom.emit('wpcf7mailsent', el('form', { class: 'login-form' }));
  eq('success/untracked-silent', pushes(win), 3);
}

// D — idempotent init, preserved dataLayer, DOM-ready deferral.
{
  const dom = doc('complete');
  const win = { dataLayer: [{ event: 'existing' }] };
  boot(win, dom);
  const t = await load();
  t.initFormTracker();
  t.initFormTracker();
  eq('init/listener-counts', dom.count('focusin') + ',' + dom.count('input') + ',' + dom.count('wpcf7mailsent'), '1,1,1');
  dom.emit('focusin', el('input', {}, cf7(7)));
  eq('init/single-push', pushes(win) + '|' + win.dataLayer[0].event, '2|existing');
  const loading = doc('loading');
  const fresh = { dataLayer: [] };
  boot(fresh, loading);
  const deferred = await load();
  deferred.initFormTracker();
  eq('dom-ready/deferred', loading.count('focusin'), 0);
  const later = cf7(9);
  loading.emit('focusin', el('input', {}, later));
  eq('dom-ready/no-push-before-ready', pushes(fresh), 0);
  loading.readyState = 'complete';
  loading.emit('DOMContentLoaded', loading);
  loading.emit('focusin', el('input', {}, later));
  eq('dom-ready/attaches-after-ready', pushes(fresh), 1);
}

if (failures.length > 0) {
  console.error('FIXTURE FAILED ' + failures.length + ' of ' + checks + ' checks');
  process.exit(1);
}
console.log('FIXTURE OK checks=' + checks);
JS;

$fixture_path    = sys_get_temp_dir() . '/rms-raven-form-events-' . getmypid() . '.mjs';
$fixture_written = file_put_contents( $fixture_path, $fixture );
rms_cta_assert( false !== $fixture_written, 'test_behavior_fixture_written', 'could not write the Node fixture to the system temp directory' );

$fixture_output = array();
$fixture_code   = 1;
exec( 'node ' . escapeshellarg( $fixture_path ) . ' ' . escapeshellarg( $theme_root ) . ' 2>&1', $fixture_output, $fixture_code );
$fixture_text = implode( "\n", $fixture_output );

rms_cta_assert( 0 === $fixture_code, 'test_behavior_fixture_passes', 'Node fixture exited ' . $fixture_code . ":\n" . $fixture_text );
$fixture_checks = preg_match( '/FIXTURE OK checks=(\d+)/', $fixture_text, $matches ) ? (int) $matches[1] : 0;
rms_cta_assert( $fixture_checks >= 35, 'test_behavior_fixture_depth', 'the fixture must assert at least 35 behaviors, saw ' . $fixture_checks );
if ( $failures > 0 ) {
    fwrite( STDERR, "Raven form events harness failed: {$failures} check(s).\n" );
    exit( 1 );
}

echo "Raven form events harness passed: once-only form_start, confirmed CF7 form_success, tracked-form filtering, exact identity payloads, and no field-data access.\n";
exit( 0 );