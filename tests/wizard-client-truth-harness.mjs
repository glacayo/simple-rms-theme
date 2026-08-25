import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { writeFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const ts = require('typescript');

const SENTINEL = 'rms-sentinel-not-a-real-key-22f25';
const here = dirname(fileURLToPath(import.meta.url));
const themeRoot = resolve(here, '..');
const helperSource = readFileSync(resolve(themeRoot, 'src/ts/admin/wizard-helpers.ts'), 'utf8');
const transpiled = ts.transpileModule(helperSource, {
  compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ESNext },
}).outputText;
const helperPath = resolve(tmpdir(), 'rms-wizard-helpers-harness.mjs');
writeFileSync(helperPath, transpiled);
const {
  applyApiKeyInputSafety,
  inputContainsSecret,
  presentStepOutcome,
  SAVED_KEY_PLACEHOLDER,
  summarizeDependencyResult,
} = await import('file:///' + helperPath.replace(/\\/g, '/'));

const assert = (condition, message) => {
  if (!condition) {
    throw new Error(message);
  }
};

let passed = 0;

const input = {
  value: SENTINEL,
  placeholder: 'Enter key',
  attrs: {
    value: SENTINEL,
    'data-api-key': '',
    'data-value': '',
    'data-credential': '',
    'data-key': '',
  },
  getAttribute(name) {
    return this.attrs[name] ?? null;
  },
};

assert(inputContainsSecret(input, SENTINEL), 'sentinel must be visible before action');
console.log('PASS sentinel-present-before-action');
passed += 1;

applyApiKeyInputSafety(input, { clear: true, hasSavedCredential: true });
input.attrs.value = input.value;
assert(input.value === '', 'successful test/save must clear the input value');
assert(input.placeholder === SAVED_KEY_PLACEHOLDER, 'successful test/save must set the saved-key placeholder');
assert(!inputContainsSecret(input, SENTINEL), 'sentinel remained in the DOM after success');
const successSnapshot = JSON.stringify({ placeholder: input.placeholder, value: input.value, attrs: input.attrs });
assert(!successSnapshot.includes(SENTINEL), 'post-success snapshot leaked sentinel');
console.log('PASS sentinel-absent-after-success');
passed += 1;

input.value = SENTINEL;
input.attrs.value = SENTINEL;
applyApiKeyInputSafety(input, { clear: false, hasSavedCredential: true });
input.attrs.value = input.value;
assert(input.value === '', 'hydration with a saved credential must clear the input');
assert(!inputContainsSecret(input, SENTINEL), 'hydration reintroduced the sentinel');
console.log('PASS hydration-never-repopulates');
passed += 1;

input.value = SENTINEL;
input.placeholder = 'Enter key';
input.attrs.value = SENTINEL;
applyApiKeyInputSafety(input, { clear: false, hasSavedCredential: false });
assert(input.value === SENTINEL, 'failed test/save must retain the typed value for correction');
console.log('PASS failure-retains-input');
passed += 1;

assert(presentStepOutcome('complete', true) === 'success', 'complete must present success');
assert(presentStepOutcome('running', true) === 'progress', '#27 running must present progress, not error');
assert(presentStepOutcome('failed', false) === 'error', 'failed must present error');
assert(presentStepOutcome('pending', false) === 'error', 'pending must present error');
assert(presentStepOutcome('complete', false) === 'error', 'complete without success must present error');
console.log('PASS step-outcome-presentation');
passed += 1;

const summary = summarizeDependencyResult({
  'classic-editor': {
    name: 'Classic Editor',
    installed: false,
    active: false,
    action: 'install_failed',
  },
  acf: {
    name: 'ACF Pro',
    installed: true,
    active: false,
    action: 'activation_failed',
  },
  yoast: {
    name: 'Yoast SEO',
    installed: true,
    active: true,
    action: 'already_active',
  },
});
assert(summary.includes('1 of 3 dependencies active'), `unexpected summary: ${summary}`);
assert(summary.includes('Classic Editor: not installed (install_failed)'), 'missing plugin summary is untruthful');
assert(summary.includes('ACF Pro: installed but not active (activation_failed)'), 'inactive plugin summary is untruthful');
assert(!summary.toLowerCase().includes('step completed'), 'failure summary must not say completed');
console.log('PASS dependency-summary-truth');
passed += 1;

const wizardSource = readFileSync(resolve(themeRoot, 'src/ts/admin/wizard.ts'), 'utf8');
assert(wizardSource.includes("from './wizard-helpers'"), 'wizard.ts must use the shared helpers');
assert(wizardSource.includes('presentStepOutcome'), 'wizard.ts must consult presentStepOutcome');
assert(wizardSource.includes('applyApiKeyInputSafety'), 'wizard.ts must clear credentials through the helper');
assert(wizardSource.includes('is still in progress'), 'wizard.ts must keep #27 running as progress');
console.log('PASS production-wiring');
passed += 1;

assert(!successSnapshot.includes(SENTINEL), 'stored success snapshot leaked sentinel');
console.log('PASS no-sentinel-in-success-snapshot');
passed += 1;

console.log(`Harness passed: ${passed} scenarios.`);
