import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';

const require = createRequire(import.meta.url);
const here = dirname(fileURLToPath(import.meta.url));
const themeRoot = resolve(here, '..');
let ts;
try {
  ts = require('typescript');
} catch (_error) {
  const sibling = resolve(here, '../../simple-rms-theme/node_modules/typescript');
  ts = require(sibling);
}
const helperSource = readFileSync(resolve(themeRoot, 'src/ts/admin/wizard-home-seo.ts'), 'utf8');
const transpiled = ts.transpileModule(helperSource, {
  compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ESNext },
}).outputText;
const helperPath = resolve(tmpdir(), 'rms-wizard-home-seo-harness.mjs');
writeFileSync(helperPath, transpiled);
const {
  HOME_SEO_PRIMARY_ERROR_ID,
  HOME_SEO_PRIMARY_REQUIRED,
  HOME_SEO_SECONDARY_CLAMPED,
  analyzeHomeSecondaryKeywords,
  applyHomeSeoTargetingUi,
  buildHomeSeoTargetingPayload,
  clearHomeSeoDirty,
  collectHomeSeoTargetingFromForm,
  createHomeSeoValidationError,
  hydrateHomeSeoTargeting,
  isHomeSeoDirty,
  isHomeSeoValidationError,
  markHomeSeoDirty,
  normalizeHomeKeyword,
  normalizeHomeSecondaryKeywords,
  persistHomeSeoTargeting,
  presentHomeSeoCollectionResult,
  shouldReloadWizardStateOnStepFinish,
  shouldReplaceHomeSeoOnStepFinish,
} = await import('file:///' + helperPath.replace(/\\/g, '/'));

const assert = (condition, message) => {
  if (!condition) {
    throw new Error(message);
  }
};

let passed = 0;

assert(normalizeHomeKeyword('  deck   builder  ') === 'deck builder', 'primary whitespace collapse failed');
assert(
  JSON.stringify(normalizeHomeSecondaryKeywords(['  one  ', 'Two', 'two', '', 'THREE', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven'], 'deck builder'))
    === JSON.stringify(['one', 'Two', 'THREE', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten']),
  'client secondary normalize/dedupe/clamp failed'
);
const eleven = ['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven'];
const analysis = analyzeHomeSecondaryKeywords(eleven, 'deck builder');
assert(analysis.clamped === true, 'eleven unique secondaries must report clamped');
assert(analysis.uniqueCount === 11, 'unique count must include extras');
assert(analysis.keywords.length === 10, 'clamp must keep first 10');
console.log('PASS client-normalize-dedupe-clamp');
passed += 1;

const disabled = buildHomeSeoTargetingPayload({
  enabled: false,
  primaryKeyword: 'stale deck builder',
  secondaryKeywords: 'old secondary',
});
assert(JSON.stringify(disabled) === JSON.stringify({ enabled: false }), 'disabled payload kept stale keywords');
assert(JSON.stringify(persistHomeSeoTargeting(disabled)) === JSON.stringify({ enabled: false }), 'disabled persist kept stale keywords');
console.log('PASS client-disabled-omits-stale-intent');
passed += 1;

const enabled = buildHomeSeoTargetingPayload({
  enabled: true,
  primaryKeyword: '  deck   builder  ',
  secondaryKeywords: 'custom decks, DECK BUILDER, composite decking,, Custom Decks',
});
assert(enabled.enabled === true, 'enabled payload must stay enabled');
assert(enabled.enabled && enabled.primary_keyword === 'deck builder', 'enabled primary was not normalized');
assert(enabled.enabled && JSON.stringify(enabled.secondary_keywords) === JSON.stringify(['custom decks', 'composite decking']), 'enabled secondaries were not normalized');
console.log('PASS client-enabled-normalizes');
passed += 1;

let rejected = false;
try {
  buildHomeSeoTargetingPayload({ enabled: true, primaryKeyword: '   ' });
} catch (error) {
  rejected = error instanceof Error && error.message === HOME_SEO_PRIMARY_REQUIRED;
}
assert(rejected, 'missing primary did not reject on the client');
console.log('PASS client-missing-primary-rejects');
passed += 1;

const changed = buildHomeSeoTargetingPayload({
  enabled: true,
  primaryKeyword: 'composite decking contractor',
});
assert(changed.enabled && changed.primary_keyword === 'composite decking contractor', 'changed keyword was not used');
assert(changed.enabled && !changed.primary_keyword.includes('deck builder'), 'old keyword remained after change');
console.log('PASS client-changed-keyword-used');
passed += 1;

const field = (name, value = '', extras = {}) => ({
  name,
  value,
  disabled: false,
  required: false,
  checked: false,
  hidden: false,
  focused: false,
  textContent: extras.textContent ?? '',
  attrs: {},
  dataset: {},
  setAttribute(key, next) {
    this.attrs[key] = next;
  },
  focus() {
    this.focused = true;
  },
  matches(selector) {
    return selector.split(',').some((part) => part.trim().includes(name));
  },
  ...extras,
});

const createForm = () => {
  const form = {
    dataset: {},
    toggle: field('enabled'),
    fields: { hidden: true },
    primary: field('primary', '', { attrs: { 'aria-describedby': 'rms-wizard-home-seo-help' } }),
    secondary: field('secondary'),
    error: field('primary-error', '', { hidden: true }),
    notice: field('secondary-notice', '', { hidden: true }),
    querySelector(selector) {
      if (selector.includes('home-seo-enabled')) return this.toggle;
      if (selector.includes('home-seo-fields') && !selector.includes('primary') && !selector.includes('secondary')) return this.fields;
      if (selector.includes('home-seo-primary-error')) return this.error;
      if (selector.includes('home-seo-primary')) return this.primary;
      if (selector.includes('home-seo-secondary-notice')) return this.notice;
      if (selector.includes('home-seo-secondary')) return this.secondary;
      return null;
    },
  };
  return form;
};

const form = createForm();

hydrateHomeSeoTargeting(form, {
  enabled: true,
  primary_keyword: 'deck builder',
  secondary_keywords: ['custom decks'],
});
assert(form.toggle.checked === true, 'hydrate did not enable the control');
assert(form.fields.hidden === false, 'hydrate left keyword fields hidden');
assert(form.primary.disabled === false && form.primary.value === 'deck builder', 'hydrate did not restore primary');
assert(form.secondary.value === 'custom decks', 'hydrate did not restore secondaries');

applyHomeSeoTargetingUi(form, false);
assert(form.toggle.checked === false, 'disable did not uncheck the control');
assert(form.fields.hidden === true, 'disable did not hide keyword fields');
assert(form.primary.disabled === true && form.primary.value === '', 'disable did not clear/disable primary');
assert(form.secondary.disabled === true && form.secondary.value === '', 'disable did not clear/disable secondary');
assert(form.toggle.attrs['aria-expanded'] === 'false', 'disable did not update aria-expanded');
assert(form.error.hidden === true && form.error.textContent === '', 'disable left primary error visible');
assert(form.notice.hidden === true, 'disable left clamp notice visible');

form.toggle.checked = false;
const collectedDisabled = collectHomeSeoTargetingFromForm(form);
assert(collectedDisabled.payload.enabled === false, 'disabled form collection leaked keywords');
assert(!('primary_keyword' in collectedDisabled.payload), 'disabled collection included primary');
console.log('PASS client-ui-conditional-state');
passed += 1;

const dirtyForm = createForm();
hydrateHomeSeoTargeting(dirtyForm, { enabled: false });
dirtyForm.toggle.checked = true;
dirtyForm.fields.hidden = false;
dirtyForm.primary.disabled = false;
dirtyForm.primary.value = '';
markHomeSeoDirty(dirtyForm);
assert(isHomeSeoDirty(dirtyForm), 'dirty flag was not set');
hydrateHomeSeoTargeting(dirtyForm, { enabled: false });
assert(dirtyForm.toggle.checked === true, 'generic hydrate clobbered unsaved enabled mode');
assert(dirtyForm.primary.value === '', 'generic hydrate clobbered unsaved primary');
hydrateHomeSeoTargeting(dirtyForm, { enabled: false }, { force: true });
assert(isHomeSeoDirty(dirtyForm) === false, 'forced hydrate must reset dirty');
assert(dirtyForm.toggle.checked === false, 'forced persist/reload hydrate did not restore persisted disabled state');
assert(dirtyForm.primary.value === '', 'forced hydrate must still clear disabled values');
console.log('PASS client-edit-preservation');
passed += 1;

const invalidForm = createForm();
invalidForm.toggle.checked = true;
invalidForm.primary.value = '   ';
const invalidResult = collectHomeSeoTargetingFromForm(invalidForm);
assert(invalidResult.error === 'primary_required', 'enabled empty primary must be a collect error');
assert(invalidResult.message === HOME_SEO_PRIMARY_REQUIRED, 'missing primary message drifted');
presentHomeSeoCollectionResult(invalidForm, invalidResult);
assert(invalidForm.toggle.checked === true, 'missing primary hid the targeting mode');
assert(invalidForm.fields.hidden === false, 'missing primary hid the keyword fields');
assert(invalidForm.primary.attrs['aria-invalid'] === 'true', 'missing primary did not set aria-invalid');
assert(String(invalidForm.primary.attrs['aria-describedby'] || '').includes(HOME_SEO_PRIMARY_ERROR_ID), 'missing primary did not associate the error');
assert(invalidForm.error.hidden === false, 'missing primary error is not visible');
assert(invalidForm.error.textContent === HOME_SEO_PRIMARY_REQUIRED, 'missing primary error text is wrong');
assert(invalidForm.primary.focused === true, 'missing primary did not move focus');
invalidForm.primary.value = 'deck builder';
presentHomeSeoCollectionResult(invalidForm, collectHomeSeoTargetingFromForm(invalidForm), { focus: false });
assert(invalidForm.primary.attrs['aria-invalid'] === 'false', 'corrected primary left aria-invalid');
assert(invalidForm.error.hidden === true, 'corrected primary left the error visible');
console.log('PASS client-validation-focus-aria');
passed += 1;

const clampForm = createForm();
clampForm.toggle.checked = true;
clampForm.primary.value = 'deck builder';
clampForm.secondary.value = eleven.join(', ');
const clampResult = collectHomeSeoTargetingFromForm(clampForm);
assert(clampResult.secondaryClamped === true, 'collect must surface the clamp');
assert(clampResult.payload.enabled && clampResult.payload.secondary_keywords.length === 10, 'collect must keep first 10');
presentHomeSeoCollectionResult(clampForm, clampResult);
assert(clampForm.notice.hidden === false, 'clamp notice stayed hidden');
assert(clampForm.notice.textContent === HOME_SEO_SECONDARY_CLAMPED, 'clamp notice text is wrong');
assert(!String(clampForm.notice.textContent).includes('eleven'), 'clamp notice dumped a raw keyword');
assert(clampForm.secondary.value === clampResult.payload.secondary_keywords.join(', '), 'secondary field was not reduced to the kept 10');
console.log('PASS client-clamp-notice');
passed += 1;

const successForm = createForm();
markHomeSeoDirty(successForm);
successForm.toggle.checked = true;
successForm.primary.value = 'unsaved draft';
assert(shouldReplaceHomeSeoOnStepFinish({ step: 'home-page-builder', persisted: true }) === true, 'Home success must force replace');
hydrateHomeSeoTargeting(successForm, {
  enabled: true,
  primary_keyword: 'composite decking contractor',
  secondary_keywords: ['custom decks'],
}, { force: shouldReplaceHomeSeoOnStepFinish({ step: 'home-page-builder', persisted: true }) });
assert(successForm.primary.value === 'composite decking contractor', 'successful Home persist did not reset to saved values');
assert(isHomeSeoDirty(successForm) === false, 'successful Home persist left dirty set');
clearHomeSeoDirty(successForm);
applyHomeSeoTargetingUi(successForm, false);
assert(successForm.primary.value === '', 'disabled clear must still empty values');
console.log('PASS client-home-success-reset');
passed += 1;

const crossStepForm = createForm();
crossStepForm.toggle.checked = true;
crossStepForm.primary.value = 'unsaved draft';
markHomeSeoDirty(crossStepForm);
assert(shouldReplaceHomeSeoOnStepFinish({ step: 'dependencies', persisted: true }) === false, 'Dependency success must not force replace');
assert(shouldReplaceHomeSeoOnStepFinish({ step: 'ia-generation', persisted: true }) === false, 'IA success must not force replace');
assert(shouldReplaceHomeSeoOnStepFinish({ step: 'landing-page-builder', persisted: true }) === false, 'Landing success must not force replace');
hydrateHomeSeoTargeting(crossStepForm, { enabled: false }, {
  force: shouldReplaceHomeSeoOnStepFinish({ step: 'landing-page-builder', persisted: true }),
});
assert(isHomeSeoDirty(crossStepForm) === true, 'cross-step success cleared dirty');
assert(crossStepForm.toggle.checked === true && crossStepForm.primary.value === 'unsaved draft', 'cross-step success clobbered Home SEO draft');
console.log('PASS client-cross-step-success-preservation');
passed += 1;

const refreshForm = createForm();
refreshForm.toggle.checked = true;
refreshForm.primary.value = 'unsaved draft';
markHomeSeoDirty(refreshForm);
hydrateHomeSeoTargeting(refreshForm, { enabled: false }, { force: true });
assert(isHomeSeoDirty(refreshForm) === false, 'explicit refresh must reset dirty');
assert(refreshForm.toggle.checked === false, 'explicit refresh must replace Home SEO');
console.log('PASS client-refresh-replacement');
passed += 1;

const failureForm = createForm();
failureForm.toggle.checked = true;
failureForm.primary.value = '';
markHomeSeoDirty(failureForm);
const validationError = createHomeSeoValidationError(HOME_SEO_PRIMARY_REQUIRED);
assert(isHomeSeoValidationError(validationError) === true, 'validation error flag missing');
assert(isHomeSeoValidationError(new Error(HOME_SEO_PRIMARY_REQUIRED)) === false, 'plain message must not count as validation flag');
assert(shouldReloadWizardStateOnStepFinish({ validationBlocked: true }) === false, 'Home validation failure must not reload state');
assert(shouldReplaceHomeSeoOnStepFinish({ step: 'home-page-builder', persisted: false, validationBlocked: true }) === false, 'Home validation failure must not force replace');
assert(shouldReplaceHomeSeoOnStepFinish({ step: 'home-page-builder', persisted: false }) === false, 'failed Home request must not force replace');
hydrateHomeSeoTargeting(failureForm, { enabled: false }, {
  force: shouldReplaceHomeSeoOnStepFinish({ step: 'home-page-builder', persisted: false, validationBlocked: true }),
});
assert(failureForm.toggle.checked === true, 'failed Home validation clobbered enabled mode');
assert(failureForm.primary.value === '', 'failed Home validation clobbered the invalid primary field');
console.log('PASS client-failure-preservation');
passed += 1;

const wizardSource = readFileSync(resolve(themeRoot, 'src/ts/admin/wizard.ts'), 'utf8');
const initSource = readFileSync(resolve(themeRoot, 'inc/wizard/wizard-init.php'), 'utf8');
assert(wizardSource.includes("from './wizard-home-seo'"), 'wizard.ts must import homepage SEO helpers');
assert(wizardSource.includes('collectHomeSeoTargetingFromForm'), 'wizard.ts must collect homepage SEO targeting');
assert(wizardSource.includes('hydrateHomeSeoTargeting'), 'wizard.ts must hydrate homepage SEO targeting');
assert(wizardSource.includes('markHomeSeoDirty'), 'wizard.ts must mark unsaved Home SEO edits');
assert(wizardSource.includes('presentHomeSeoCollectionResult'), 'wizard.ts must present helper validation/clamp UI');
assert(wizardSource.includes('replaceHomeSeo'), 'wizard.ts must distinguish forced Home SEO replacement');
assert(wizardSource.includes('shouldReplaceHomeSeoOnStepFinish'), 'wizard.ts must use the helper replacement decision');
assert(wizardSource.includes('createHomeSeoValidationError'), 'wizard.ts must throw the explicit Home SEO validation flag');
assert(wizardSource.includes('isHomeSeoValidationError'), 'wizard.ts must detect Home SEO validation without message matching');
assert(wizardSource.includes("step: 'home-page-builder'") === false || wizardSource.includes('shouldReplaceHomeSeoOnStepFinish'), 'Home replacement must go through the helper');
assert(wizardSource.includes('loadState({ replaceHomeSeo: persisted })') === false, 'any-step persisted success must not force Home SEO replacement');
assert(!wizardSource.includes('message !== HOME_SEO_PRIMARY_REQUIRED'), 'runStep must not use message-exact Home SEO control');
assert(initSource.includes('data-wizard-home-seo-enabled'), 'wizard-init.php must expose the SEO targeting control');
assert(initSource.includes('data-wizard-home-seo-primary-error'), 'wizard-init.php must expose the primary error element');
assert(initSource.includes('role="alert"'), 'primary error must be a live alert');
assert(initSource.includes('data-wizard-home-seo-secondary-notice'), 'wizard-init.php must expose the clamp notice');
assert(initSource.includes('role="status"'), 'clamp notice must use a polite status live region');
assert(initSource.includes('editorial intent'), 'wizard-init.php must describe keywords as editorial intent');
assert(!initSource.toLowerCase().includes('guarantees rankings'), 'UI must not claim ranking guarantees');
assert(!initSource.toLowerCase().includes('writes yoast'), 'UI must not claim Yoast writes');
console.log('PASS client-production-wiring');
passed += 1;

console.log(`Harness passed: ${passed} scenarios.`);
