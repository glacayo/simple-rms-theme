import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';

const require = createRequire(import.meta.url);
const here = dirname(fileURLToPath(import.meta.url));
const themeRoot = resolve(here, '..');
const ts = require('typescript');
const source = readFileSync(resolve(themeRoot, 'src/ts/admin/wizard-internal-preview.ts'), 'utf8');
const transpiled = ts.transpileModule(source, {
  compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ESNext },
}).outputText;
const helperPath = resolve(tmpdir(), 'rms-wizard-internal-preview-harness.mjs');
writeFileSync(helperPath, transpiled);
const {
  buildInternalCardViews,
  displayStepStatus,
  exclusiveMapSelections,
  formatWizardProgress,
  internalPageProgress,
  mapDialogDecision,
  mapOnlyOutcome,
  mappingConfirmationPayload,
  openMapConfirmationDialog,
  planStepFinish,
  takenMapTypes,
} = await import('file:///' + helperPath.replace(/\\/g, '/'));

const assert = (condition, message) => {
  if (!condition) {
    throw new Error(message);
  }
};

let passed = 0;
const blueprints = [
  { type: 'about', label: 'About', layouts: ['about-us'] },
  { type: 'blog', label: 'Blog', layouts: ['blog-v1'] },
  { type: 'services', label: 'Services', layouts: ['services-v1'] },
  { type: 'contact', label: 'Contact', layouts: ['contact-us'] },
  { type: 'projects', label: 'Projects', layouts: ['gallery-grid'] },
  { type: 'testimonials', label: 'Testimonials', layouts: ['testimonials-v1'] },
];

// Real completed-site shape: five unique internal shells — three resolved by
// role/known evidence, two custom-slug mapping_needed. No client titles/content.
const preview = {
  types: {
    about: { post_id: 12, status: 'complete', reason: '', available: true, identity_source: 'type', layouts: ['about-us'] },
    blog: { post_id: 18, status: 'pending', reason: '', available: true, identity_source: 'role', layouts: ['blog-v1'] },
    services: { post_id: 13, status: 'complete', reason: '', available: true, identity_source: 'template', layouts: ['services-v1'] },
    contact: { post_id: 0, status: 'skipped', reason: 'unavailable', available: false, identity_source: 'none', layouts: ['contact-us'] },
    projects: { post_id: 0, status: 'skipped', reason: 'unavailable', available: false, identity_source: 'none', layouts: ['gallery-grid'] },
    testimonials: { post_id: 0, status: 'skipped', reason: 'unavailable', available: false, identity_source: 'none', layouts: ['testimonials-v1'] },
  },
  unmapped: [
    { post_id: 40, slug: 'history', mapping_needed: true },
    { post_id: 41, slug: 'reviews', mapping_needed: true },
  ],
  plan: {
    about: { post_id: 12, status: 'complete' },
    blog: { post_id: 18, status: 'pending' },
    services: { post_id: 13, status: 'complete' },
  },
};

const cards = buildInternalCardViews(blueprints, preview);
assert(cards.length === 5, 'five unique eligible shells, not six blueprint placeholders');
const ids = cards.map((card) => card.postId).sort((a, b) => a - b);
assert(ids.join(',') === '12,13,18,40,41', 'cards keyed by unique generated post ids');
assert(cards.filter((card) => card.resolved).length === 3, 'three resolved shells');
assert(cards.filter((card) => card.mappingNeeded).length === 2, 'two unresolved mapping shells');
assert(!cards.some((card) => card.type === 'contact' || card.type === 'projects' || card.type === 'testimonials'), 'unavailable blueprints excluded from per-page list');
console.log('PASS client-unique-identity-and-unavailable-exclusion');
passed += 1;

const tally = internalPageProgress(cards);
assert(tally.total === 5, 'progress total counts unique eligible shell post ids only');
assert(tally.complete === 2, 'complete counts only resolved complete shells');
console.log('PASS client-progress-exact-total');
passed += 1;

const resolvedComplete = cards.find((card) => card.postId === 12);
assert(resolvedComplete && resolvedComplete.resolved && resolvedComplete.status === 'complete', 'resolved complete card present');
assert(resolvedComplete.showOverwrite === true && resolvedComplete.showConvert === false && resolvedComplete.mappingNeeded === false, 'complete card: regenerate only, no convert, no mapping select');
const resolvedPending = cards.find((card) => card.postId === 18);
assert(resolvedPending && resolvedPending.status === 'pending', 'resolved pending card present');
assert(resolvedPending.showOverwrite === false && resolvedPending.showConvert === false && resolvedPending.mappingNeeded === false, 'pending card: no destructive action, no mapping select');
const resolvedComplete2 = cards.find((card) => card.postId === 13);
assert(resolvedComplete2 && resolvedComplete2.showOverwrite === true && resolvedComplete2.mappingNeeded === false, 'template-resolved complete card: regenerate only');
console.log('PASS client-action-visibility-matrix-resolved');
passed += 1;

const unresolved = cards.find((card) => card.postId === 40);
assert(unresolved && unresolved.mappingNeeded === true && unresolved.resolved === false, 'unresolved custom shell is a mapping card');
assert(unresolved.showOverwrite === false && unresolved.showConvert === false, 'unresolved card has no regenerate/convert');
assert(unresolved.type === '' && unresolved.label === 'history', 'unresolved card shows page label');
console.log('PASS client-unresolved-map-only');
passed += 1;

// Post-map collapse: after a confirmed server map + refresh, the unresolved
// card becomes exactly one resolved card for the same post id. Mapping to a
// type that had no shell keeps the total at five unique shells.
const afterMap = {
  ...preview,
  types: {
    ...preview.types,
    testimonials: { post_id: 40, status: 'pending', reason: '', available: true, identity_source: 'map', layouts: ['testimonials-v1'] },
  },
  unmapped: preview.unmapped.filter((page) => page.post_id !== 40),
  plan: { ...preview.plan, testimonials: { post_id: 40, status: 'pending' } },
};
const afterCards = buildInternalCardViews(blueprints, afterMap);
const mappedCard = afterCards.filter((card) => card.postId === 40);
assert(mappedCard.length === 1, 'post-map collapse: exactly one card for the mapped post id');
assert(mappedCard[0].resolved === true && mappedCard[0].mappingNeeded === false && mappedCard[0].type === 'testimonials', 'mapped card is resolved with blueprint type');
assert(afterCards.length === 5, 'total stays five unique shells after mapping');
console.log('PASS client-post-map-collapse');
passed += 1;

// Negative control: duplicate post ids in unmapped collapse to one card.
const dupPreview = {
  ...preview,
  unmapped: [
    { post_id: 40, slug: 'history', mapping_needed: true },
    { post_id: 40, slug: 'history', mapping_needed: true },
  ],
};
const dupCards = buildInternalCardViews(blueprints, dupPreview);
assert(dupCards.filter((card) => card.postId === 40).length === 1, 'duplicate unmapped post ids collapse to one card');
// Negative control: a resolved post id that also appears in unmapped stays resolved once.
const clashPreview = {
  ...preview,
  unmapped: [...preview.unmapped, { post_id: 12, slug: 'about', mapping_needed: true }],
};
const clashCards = buildInternalCardViews(blueprints, clashPreview);
assert(clashCards.filter((card) => card.postId === 12).length === 1 && clashCards.find((card) => card.postId === 12).resolved === true, 'resolved post id wins over duplicate unmapped entry');
console.log('PASS client-duplicate-id-negative-controls');
passed += 1;

// Hidden semantics: each control wrap is hidden exactly when its action is
// not allowed (overwrite only on complete, convert only on legacy_unconfirmed,
// mapping only on unresolved shells).
const matrixConsistent = cards.every((card) => {
  if (card.resolved) {
    const overwriteRule = card.status === 'complete';
    const convertRule = card.status === 'skipped' && card.reason === 'legacy_unconfirmed';
    return card.showOverwrite === overwriteRule
      && card.showConvert === convertRule
      && card.mappingNeeded === false;
  }
  return card.mappingNeeded === true && card.showOverwrite === false && card.showConvert === false;
});
assert(matrixConsistent, 'every card control visibility matches its action rule');
const completeCard = cards.find((card) => card.postId === 12);
assert(completeCard.showOverwrite === true, 'complete card keeps regenerate visible');
const pendingCard = cards.find((card) => card.postId === 18);
assert(pendingCard.showOverwrite === false && pendingCard.showConvert === false && pendingCard.mappingNeeded === false, 'pending card has all three controls hidden');
const unresolvedCard = cards.find((card) => card.postId === 40);
assert(unresolvedCard.mappingNeeded === true, 'unresolved card keeps mapping select visible');
console.log('PASS client-hidden-semantics-matrix');
passed += 1;

const progress = formatWizardProgress({
  progress_text: 'Wizard complete',
  completed_count: 8,
  required_count: 8,
  grandfathered_internal_pages: true,
}, 8, 9);
assert(progress.text === 'Wizard complete', 'grandfathered progress is not 8/9');
assert(displayStepStatus('internal-page-builder', 'pending', { grandfathered_internal_pages: true }) === 'optional', 'ninth step displays optional');
console.log('PASS client-no-retroactive-progress');
passed += 1;

const exclusive = exclusiveMapSelections([
  { postId: 40, type: 'about' },
  { postId: 41, type: 'about' },
  { postId: 42, type: 'services' },
]);
assert(exclusive[0].type === 'about' && exclusive[1].type === '' && exclusive[2].type === 'services', 'later duplicate type is cleared');
assert(takenMapTypes(exclusive, 41).includes('about'), 'taken types exclude the current card');
const confirm = mappingConfirmationPayload([{ post_id: 40, type: 'about' }, { post_id: 42, type: 'services' }], true);
assert(confirm.confirm_map === true && confirm.confirm_map_types.join(',') === 'about,services', 'payload lists exact mapped types');
const denied = mappingConfirmationPayload([{ post_id: 40, type: 'about' }], false);
assert(denied.confirm_map === false && denied.confirm_map_types.length === 0, 'unconfirmed mapping payload is empty');
console.log('PASS client-mapping-exclusive-and-confirmation-payload');
passed += 1;

const makeDialogView = () => {
  const listeners = { accept: [], cancel: [], keydown: [] };
  const view = {
    dialog: { hidden: true },
    message: { textContent: '' },
    accept: {
      focusCount: 0,
      focus() { this.focusCount += 1; },
      addEventListener(type, fn) { listeners.accept.push(fn); },
      removeEventListener(type, fn) {
        listeners.accept = listeners.accept.filter((f) => f !== fn);
      },
    },
    cancel: [
      {
        addEventListener(type, fn) { listeners.cancel.push(fn); },
        removeEventListener(type, fn) {
          listeners.cancel = listeners.cancel.filter((f) => f !== fn);
        },
      },
    ],
    doc: {
      activeElement: { focusCount: 0, focus() { this.focusCount += 1; } },
      addEventListener(type, fn) { listeners.keydown.push(fn); },
      removeEventListener(type, fn) {
        listeners.keydown = listeners.keydown.filter((f) => f !== fn);
      },
    },
  };
  return { view, listeners };
};

const confirmView = makeDialogView();
const confirmPromise = openMapConfirmationDialog(
  confirmView.view,
  'Assign page type?',
  [{ post_id: 40, type: 'about' }, { post_id: 42, type: 'services' }]
);
assert(confirmView.view.dialog.hidden === false, 'mapping dialog opens');
assert(confirmView.view.message.textContent === 'Assign page type?', 'mapping dialog shows its own copy');
assert(confirmView.view.accept.focusCount === 1, 'mapping dialog focuses its own accept');
confirmView.listeners.accept[0]();
const confirmDecision = await confirmPromise;
assert(confirmDecision.confirm_map === true && confirmDecision.confirm_map_types.join(',') === 'about,services', 'mapping dialog confirm resolves exact-set payload');
assert(confirmView.view.dialog.hidden === true, 'mapping dialog closes on confirm');
assert(confirmView.view.doc.activeElement.focusCount === 1, 'mapping dialog restores focus on confirm');
console.log('PASS client-map-dialog-confirm-exact-payload');
passed += 1;

const cancelView = makeDialogView();
const cancelPromise = openMapConfirmationDialog(cancelView.view, 'Assign page type?', [{ post_id: 40, type: 'about' }]);
cancelView.listeners.cancel[0]();
const cancelDecision = await cancelPromise;
assert(cancelDecision.confirm_map === false && cancelDecision.confirm_map_types.length === 0, 'mapping dialog cancel resolves empty payload');
assert(cancelView.view.dialog.hidden === true, 'mapping dialog closes on cancel');
console.log('PASS client-map-dialog-cancel-empty-payload');
passed += 1;

const escapeView = makeDialogView();
const escapePromise = openMapConfirmationDialog(escapeView.view, 'Assign page type?', [{ post_id: 40, type: 'about' }]);
escapeView.listeners.keydown[0]({ key: 'Escape' });
const escapeDecision = await escapePromise;
assert(escapeDecision.confirm_map === false, 'mapping dialog Escape cancels');
assert(escapeView.view.dialog.hidden === true, 'mapping dialog closes on Escape');
console.log('PASS client-map-dialog-escape-cancels');
passed += 1;

const decision = mapDialogDecision(true, [{ post_id: 40, type: 'about' }]);
assert(decision.confirm_map === true && decision.confirm_map_types.join(',') === 'about', 'map dialog decision helper confirms exact set');
const decisionDenied = mapDialogDecision(false, [{ post_id: 40, type: 'about' }]);
assert(decisionDenied.confirm_map === false && decisionDenied.confirm_map_types.length === 0, 'map dialog decision helper denies empty');
console.log('PASS client-map-dialog-decision-helper');
passed += 1;

// Cancel/Escape are local no-ops: the finish plan must not reload state,
// must not show a generic canceled status, and must not change the panel.
const canceledPlan = planStepFinish({ canceled: true, validationBlocked: false });
assert(canceledPlan.reload === false, 'canceled run does not reload server state');
assert(canceledPlan.statusMessage === '' && canceledPlan.noticeMessage === '', 'canceled run shows no generic canceled status or notice');
const confirmedPlan = planStepFinish({ canceled: false, validationBlocked: false });
assert(confirmedPlan.reload === true, 'confirmed run reloads state for normal hydration');
const blockedPlan = planStepFinish({ canceled: false, validationBlocked: true });
assert(blockedPlan.reload === false, 'validation-blocked run does not reload state');
console.log('PASS client-cancel-finish-plan-local-noop');
passed += 1;

// Focus restoration: cancel and Escape both restore focus to the exact
// trigger element that opened the dialog.
const focusView = makeDialogView();
const focusPromise = openMapConfirmationDialog(focusView.view, 'Assign page type?', [{ post_id: 40, type: 'about' }]);
focusView.listeners.cancel[0]();
await focusPromise;
assert(focusView.view.doc.activeElement.focusCount === 1, 'cancel restores focus to the trigger element');
const escapeFocusView = makeDialogView();
const escapeFocusPromise = openMapConfirmationDialog(escapeFocusView.view, 'Assign page type?', [{ post_id: 40, type: 'about' }]);
escapeFocusView.listeners.keydown[0]({ key: 'Escape' });
await escapeFocusPromise;
assert(escapeFocusView.view.doc.activeElement.focusCount === 1, 'Escape restores focus to the trigger element');
console.log('PASS client-dialog-focus-restoration');
passed += 1;

// Confirm path is unaffected: it still resolves the exact confirmed map
// payload and restores focus.
const confirmFocusView = makeDialogView();
const confirmFocusPromise = openMapConfirmationDialog(confirmFocusView.view, 'Assign page type?', [{ post_id: 40, type: 'about' }, { post_id: 42, type: 'services' }]);
confirmFocusView.listeners.accept[0]();
const confirmFocusDecision = await confirmFocusPromise;
assert(confirmFocusDecision.confirm_map === true && confirmFocusDecision.confirm_map_types.join(',') === 'about,services', 'confirm still resolves exact confirmed map payload');
assert(confirmFocusView.view.doc.activeElement.focusCount === 1, 'confirm restores focus to the trigger element');
console.log('PASS client-confirm-path-unaffected');
passed += 1;

// Map-only outcome copy never claims the step completed.
const mapOutcome = mapOnlyOutcome();
assert(mapOutcome.statusMessage === 'Page types assigned.', 'map-only status is specific, not Step completed');
assert(mapOutcome.noticeMessage.includes('Build or convert pages'), 'map-only notice directs to build/convert, not completion');
assert(!mapOutcome.statusMessage.includes('completed') && !mapOutcome.noticeMessage.includes('completed successfully'), 'map-only outcome never claims step completion');
console.log('PASS client-map-only-outcome-specific');
passed += 1;

console.log(`Harness passed: ${passed} scenarios.`);
