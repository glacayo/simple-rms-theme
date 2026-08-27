import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';

const require = createRequire(import.meta.url);
const here = dirname(fileURLToPath(import.meta.url));
const themeRoot = resolve(here, '..');
const ts = require('typescript');
const helperSource = readFileSync(resolve(themeRoot, 'src/ts/admin/wizard-helpers.ts'), 'utf8');
const transpiled = ts.transpileModule(helperSource, {
  compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ESNext },
}).outputText;
const helperPath = resolve(tmpdir(), 'rms-wizard-page-type-harness.mjs');
writeFileSync(helperPath, transpiled);
const { buildGeneratePagePayloadItem, sanitizeWizardPageType } = await import('file:///' + helperPath.replace(/\\/g, '/'));

const assert = (condition, message) => {
  if (!condition) {
    throw new Error(message);
  }
};

let passed = 0;

const ui = buildGeneratePagePayloadItem({
  slug: 'about-us',
  title: 'About Us',
  role: '',
  type: 'about',
});
assert(ui.type === 'about' && ui.slug === 'about-us' && ui.generate === true, 'UI item must send type about with custom slug');
console.log('PASS client-explicit-type-custom-slug');
passed += 1;

const noType = buildGeneratePagePayloadItem({ slug: 'about', title: 'About', role: '' });
assert(!Object.prototype.hasOwnProperty.call(noType, 'type'), 'missing type must not be inferred from slug');
console.log('PASS client-no-slug-inference');
passed += 1;

assert(sanitizeWizardPageType('../header') === 'header', 'path traversal must not survive as a type');
assert(sanitizeWizardPageType('About') === 'about', 'catalog type is lowercased');
console.log('PASS client-type-sanitized');
passed += 1;

const wizardSource = readFileSync(resolve(themeRoot, 'src/ts/admin/wizard.ts'), 'utf8');
assert(wizardSource.includes('buildGeneratePagePayloadItem'), 'wizard.ts must serialize pages through the helper');
assert(wizardSource.includes('dataset.wizardPageType'), 'wizard.ts must read immutable row type, not slug');
assert(!/pages\[slug\]\s*=\s*\{\s*slug,\s*title:\s*rawTitle/.test(wizardSource), 'payload must not omit type');
console.log('PASS client-wizard-reads-row-type');
passed += 1;

const initSource = readFileSync(resolve(themeRoot, 'inc/wizard/wizard-init.php'), 'utf8');
assert(initSource.includes("'type'        => $slug"), 'common pages JSON must include immutable type');
assert(initSource.includes('data-wizard-page-type'), 'row markup must store type off the slug inputs');
console.log('PASS client-markup-type-identity');
passed += 1;

console.log(`Harness passed: ${passed} scenarios.`);
