import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';

const require = createRequire(import.meta.url);
const ts = require('typescript');

const here = dirname(fileURLToPath(import.meta.url));
const themeRoot = resolve(here, '..');
const helperSource = readFileSync(resolve(themeRoot, 'src/ts/admin/wizard-helpers.ts'), 'utf8');
const transpiled = ts.transpileModule(helperSource, {
  compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ESNext },
}).outputText;
const helperPath = resolve(tmpdir(), 'rms-wizard-helpers-harness.mjs');
writeFileSync(helperPath, transpiled);
const {
  presentStepOutcome,
  summarizeDependencyResult,
} = await import('file:///' + helperPath.replace(/\\/g, '/'));

const assert = (condition, message) => {
  if (!condition) {
    throw new Error(message);
  }
};

let passed = 0;

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
assert(wizardSource.includes('summarizeDependencyResult'), 'wizard.ts must summarize dependency results');
assert(wizardSource.includes('is still in progress'), 'wizard.ts must keep running as progress');
assert(!wizardSource.includes('applyApiKeyInputSafety'), 'issue #22 must not include credential clearing');
console.log('PASS production-wiring');
passed += 1;

console.log(`Harness passed: ${passed} scenarios.`);
