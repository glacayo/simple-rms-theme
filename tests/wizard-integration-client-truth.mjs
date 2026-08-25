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

const compileHelper = (relativePath, outName) => {
  const source = readFileSync(resolve(themeRoot, relativePath), 'utf8');
  const transpiled = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ESNext },
  }).outputText;
  const helperPath = resolve(tmpdir(), outName);
  writeFileSync(helperPath, transpiled);
  return helperPath;
};

const wizardHelpers = await import(
  'file:///' + compileHelper('src/ts/admin/wizard-helpers.ts', 'rms-wizard-helpers-integration.mjs').replace(/\\/g, '/')
);
const landingHelpers = await import(
  'file:///' + compileHelper('src/ts/admin/landing-run-helpers.ts', 'rms-landing-helpers-integration.mjs').replace(/\\/g, '/')
);
const homeSeoHelpers = await import(
  'file:///' + compileHelper('src/ts/admin/wizard-home-seo.ts', 'rms-wizard-home-seo-integration.mjs').replace(/\\/g, '/')
);

const assert = (condition, message) => {
  if (!condition) {
    throw new Error(message);
  }
};

const {
  applyApiKeyInputSafety,
  inputContainsSecret,
  presentStepOutcome,
  SAVED_KEY_PLACEHOLDER,
  summarizeDependencyResult,
} = wizardHelpers;
const {
  applyHomeSeoTargetingUi,
  hydrateHomeSeoTargeting,
  isHomeSeoDirty,
  markHomeSeoDirty,
  shouldReplaceHomeSeoOnStepFinish,
} = homeSeoHelpers;

assert(presentStepOutcome('failed', false) === 'error', 'dependency failure must present error');
assert(presentStepOutcome('pending', false) === 'error', 'pending must present error');
assert(presentStepOutcome('running', true) === 'progress', 'healthy landing running must present progress');
assert(presentStepOutcome('complete', true) === 'success', 'complete must present success');

const failureSummary = summarizeDependencyResult({
  acf: { name: 'ACF Pro', installed: false, active: false, action: 'install_failed' },
});
assert(failureSummary.includes('not installed (install_failed)'), 'dependency summary must stay truthful');
assert(!failureSummary.toLowerCase().includes('step completed'), 'dependency failure must never say Step completed');

const progressCopy = 'Landing Page Builder is still in progress.';
assert(!progressCopy.toLowerCase().includes('step completed'), 'progress copy must never say Step completed');

const input = {
  value: SENTINEL,
  placeholder: 'Enter key',
  attrs: { value: SENTINEL },
  getAttribute(name) {
    return this.attrs[name] ?? null;
  },
};
applyApiKeyInputSafety(input, { clear: true, hasSavedCredential: true });
input.attrs.value = input.value;
assert(input.value === '', 'successful Test/Load and IA save must clear the key');
assert(input.placeholder === SAVED_KEY_PLACEHOLDER, 'cleared key must use the saved placeholder');
assert(!inputContainsSecret(input, SENTINEL), 'sentinel remained after merged-client clear');

const runDecision = landingHelpers.resolveLandingClientRequest({
  intent: 'run',
  skipAll: false,
  incompleteRun: false,
});
assert(runDecision.kind === 'start', 'merged landing helper must still route Run to start');

const skipAgainstIncomplete = landingHelpers.resolveLandingClientRequest({
  intent: 'run',
  skipAll: true,
  incompleteRun: true,
});
assert(skipAgainstIncomplete.kind === 'skip', 'client skip-all still posts skip_all against an incomplete run');
assert(skipAgainstIncomplete.body?.skip_all === true, 'client skip-all payload stays skip_all true');
assert(skipAgainstIncomplete.body?.landing_action === undefined, 'client skip-all must not send landing_action');

const field = (name, value = '') => ({
  name,
  value,
  disabled: false,
  required: false,
  checked: false,
  hidden: false,
  attrs: {},
  dataset: {},
  setAttribute(key, next) {
    this.attrs[key] = next;
  },
  matches(selector) {
    return selector.split(',').some((part) => part.trim().includes(name));
  },
});
const createHomeSeoForm = () => ({
  dataset: {},
  toggle: field('enabled'),
  fields: { hidden: true },
  primary: field('primary'),
  secondary: field('secondary'),
  error: field('primary-error'),
  notice: field('secondary-notice'),
  querySelector(selector) {
    if (selector.includes('home-seo-enabled')) return this.toggle;
    if (selector.includes('home-seo-fields') && !selector.includes('primary') && !selector.includes('secondary')) return this.fields;
    if (selector.includes('home-seo-primary-error')) return this.error;
    if (selector.includes('home-seo-primary')) return this.primary;
    if (selector.includes('home-seo-secondary-notice')) return this.notice;
    if (selector.includes('home-seo-secondary')) return this.secondary;
    return null;
  },
});

const crossStepForm = createHomeSeoForm();
crossStepForm.toggle.checked = true;
crossStepForm.primary.value = 'unsaved draft';
markHomeSeoDirty(crossStepForm);
assert(shouldReplaceHomeSeoOnStepFinish({ step: 'dependencies', persisted: true }) === false, 'dependency success must not replace Home SEO');
assert(shouldReplaceHomeSeoOnStepFinish({ step: 'ia-generation', persisted: true }) === false, 'IA success must not replace Home SEO');
assert(shouldReplaceHomeSeoOnStepFinish({ step: 'landing-page-builder', persisted: true }) === false, 'landing success must not replace Home SEO');
hydrateHomeSeoTargeting(crossStepForm, { enabled: false }, {
  force: shouldReplaceHomeSeoOnStepFinish({ step: 'landing-page-builder', persisted: true }),
});
assert(isHomeSeoDirty(crossStepForm) === true, 'other-step success cleared Home SEO dirty');
assert(crossStepForm.toggle.checked === true && crossStepForm.primary.value === 'unsaved draft', 'other-step success clobbered Home SEO draft');
console.log('PASS home-draft-preserved-across-other-step-success');

const successForm = createHomeSeoForm();
markHomeSeoDirty(successForm);
successForm.toggle.checked = true;
successForm.primary.value = 'unsaved draft';
assert(shouldReplaceHomeSeoOnStepFinish({ step: 'home-page-builder', persisted: true }) === true, 'Home success must force replace');
hydrateHomeSeoTargeting(successForm, {
  enabled: true,
  primary_keyword: 'composite decking contractor',
  secondary_keywords: ['custom decks'],
}, { force: shouldReplaceHomeSeoOnStepFinish({ step: 'home-page-builder', persisted: true }) });
assert(successForm.primary.value === 'composite decking contractor', 'Home success did not reset to saved values');
assert(isHomeSeoDirty(successForm) === false, 'Home success left dirty set');
applyHomeSeoTargetingUi(successForm, false);
assert(successForm.primary.value === '', 'disabled clear must empty Home SEO values');
console.log('PASS home-success-reset');

const wizardSource = readFileSync(resolve(themeRoot, 'src/ts/admin/wizard.ts'), 'utf8');
assert(wizardSource.includes("from './wizard-helpers'"), 'wizard.ts must keep wizard-helpers');
assert(wizardSource.includes("from './landing-run-helpers'"), 'wizard.ts must keep landing-run-helpers');
assert(wizardSource.includes("from './wizard-home-seo'"), 'wizard.ts must import homepage SEO helpers');
assert((wizardSource.match(/from '\.\/wizard-home-seo'/g) || []).length === 1, 'wizard.ts must import homepage SEO helpers once');
assert(wizardSource.includes('applyStepOutcomePresentation'), 'wizard.ts must use one outcome presenter');
assert((wizardSource.match(/const applyStepOutcomePresentation/g) || []).length === 1, 'wizard.ts must not duplicate outcome presenters');
assert((wizardSource.match(/const hydrateHomeSeoTargetingForm/g) || []).length === 1, 'wizard.ts must not duplicate Home SEO hydrators');
assert((wizardSource.match(/root\.addEventListener\('change'/g) || []).length === 1, 'wizard.ts must keep one root change listener');
assert(wizardSource.includes('is still in progress'), 'wizard.ts must keep running progress copy');
const progressBlock = wizardSource.match(/if \(outcome === 'progress'\) \{[\s\S]*?return;\s*\}/);
assert(progressBlock, 'progress branch must exist');
assert(
  !/setStepActionStatus\([^)]*'Step completed\.'/.test(progressBlock[0]),
  'progress branch must never display Step completed'
);

console.log('PASS dependency-failure-not-completed');
console.log('PASS landing-running-progress-not-completed');
console.log('PASS api-key-cleared-in-merged-client');
console.log('Merged client helper proof passed.');
