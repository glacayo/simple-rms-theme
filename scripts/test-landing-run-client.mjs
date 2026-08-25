/**
 * Deterministic proof for landing hydration merge and active-run UI.
 *
 * Imports the production helper used by src/ts/admin/wizard.ts.
 * Run with: node scripts/test-landing-run-client.mjs
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const ts = require('typescript');
const helperPath = path.join(path.dirname(fileURLToPath(import.meta.url)), '../src/ts/admin/landing-run-helpers.ts');
const compiled = ts.transpileModule(fs.readFileSync(helperPath, 'utf8'), {
  compilerOptions: {
    module: ts.ModuleKind.ESNext,
    target: ts.ScriptTarget.ESNext,
    moduleResolution: ts.ModuleResolutionKind.Bundler,
  },
  fileName: helperPath,
});
const compiledUrl = `data:text/javascript;charset=utf-8,${encodeURIComponent(compiled.outputText)}`;
const {
  mergeLandingRowsByKey,
  sectionsFromPlanItem,
  shouldOfferLandingResume,
  isIncompleteLandingRunStatus,
  resolveLandingClientRequest,
} = await import(compiledUrl);

const completed = [
  { landing_key: 'lk_1', id: 11, title: 'One', sections: [{ layout: 'cta-v1' }] },
  { landing_key: 'lk_2', id: 12, title: 'Two', sections: [{ layout: 'cta-v1' }] },
  { landing_key: 'lk_3', id: 13, title: 'Three', sections: [{ layout: 'cta-v1' }] },
  { landing_key: 'lk_4', id: 14, title: 'Four', sections: [{ layout: 'cta-v1' }] },
];
const planItems = [
  ...completed.map((row) => ({ ...row, key: row.landing_key, status: 'completed', sections: [{ layout: 'hero' }] })),
  { key: 'lk_5', landing_key: 'lk_5', id: 0, title: 'Five', status: 'pending', sections: [{ layout: 'badges', item_count: 7, override_canonical: true }] },
  { key: 'lk_6', landing_key: 'lk_6', id: 0, title: 'Six', status: 'pending', sections: [{ layout: 'badges' }] },
  { key: 'lk_7', landing_key: 'lk_7', id: 0, title: 'Seven', status: 'pending', sections: [{ layout: 'badges' }] },
  { key: 'lk_8', landing_key: 'lk_8', id: 0, title: 'Eight', status: 'pending', sections: [{ layout: 'badges' }] },
  { key: 'lk_9', landing_key: 'lk_9', id: 0, title: 'Nine', status: 'pending', sections: [{ layout: 'badges' }] },
];

const merged = mergeLandingRowsByKey(completed, planItems);
assert.equal(merged.length, 9, 'merge restores all 9 plan rows');
assert.equal(merged.filter((row) => Number(row.id) > 0).length, 4, 'completed rows keep post IDs');
assert.equal(new Set(merged.map((row) => row.landing_key)).size, 9, 'merge does not duplicate keys');
assert.equal(merged.find((row) => row.landing_key === 'lk_1')?.sections?.[0]?.layout, 'hero', 'completed-row default sections are replaced by persisted plan sections');
assert.ok(merged.some((row) => row.landing_key === 'lk_9' && row.sections?.[0]?.layout === 'badges'), 'pending plan rows restore their actual sections');

const newlyAdded = merged.find((row) => row.landing_key === 'lk_5');
const newlyAddedSections = sectionsFromPlanItem(newlyAdded);
assert.equal(newlyAddedSections[0]?.item_count, 7, 'newly added plan rows restore item_count');
assert.equal(newlyAddedSections[0]?.override_canonical, true, 'newly added plan rows restore override_canonical');

assert.equal(shouldOfferLandingResume({ processingActive: true, runningStep: null, runStatus: 'running' }), false);
assert.equal(shouldOfferLandingResume({ processingActive: false, runningStep: 'landing-page-builder', runStatus: 'running' }), false);
assert.equal(shouldOfferLandingResume({ processingActive: false, runningStep: null, runStatus: 'interrupted' }), true);
assert.equal(shouldOfferLandingResume({ processingActive: false, runningStep: null, runStatus: 'failed' }), true);
assert.equal(shouldOfferLandingResume({ processingActive: true, runningStep: null, runStatus: 'failed' }), false);
assert.equal(isIncompleteLandingRunStatus('pending'), true);
assert.equal(isIncompleteLandingRunStatus('running'), true);
assert.equal(isIncompleteLandingRunStatus('interrupted'), true);
assert.equal(isIncompleteLandingRunStatus('failed'), true);
assert.equal(isIncompleteLandingRunStatus('completed'), false);

const wizardSource = fs.readFileSync(
  path.join(path.dirname(fileURLToPath(import.meta.url)), '../src/ts/admin/wizard.ts'),
  'utf8'
);

assert.ok(wizardSource.includes("from './landing-run-helpers'"), 'wizard imports the production hydration helper');
assert.ok(wizardSource.includes('processing_active'), 'wizard reads processing_active');
assert.ok(wizardSource.includes('upsertLandingRowFromPlanItem'), 'wizard upserts existing rows by landing key');
assert.ok(wizardSource.includes('replaceLandingRowSections'), 'wizard replaces completed-row sections from the persisted plan');
assert.ok(!/if \(rows\.querySelector\('\[data-wizard-landing-row\]'\)\) \{\s*return;\s*\}/.test(wizardSource), 'plan hydration no longer no-ops when completed rows already exist');
assert.ok(wizardSource.includes('resumeButton.hidden = !canResume'), 'active processing hides Resume');
assert.ok(wizardSource.includes('Processing is active. Refresh state to update.'), 'active processing tells the operator to refresh later');
assert.ok(wizardSource.includes('sectionsFromPlanItem'), 'wizard hydrates newly added rows from persisted plan sections');
assert.ok(wizardSource.includes('isIncompleteLandingRunStatus'), 'wizard gates generic start/retry on incomplete persisted runs');
assert.ok(wizardSource.includes('button.hidden = incompleteLandingRun'), 'wizard hides generic start/retry while an incomplete run exists');
assert.ok(wizardSource.includes('resolveLandingClientRequest'), 'wizard uses the production request decision helper');
assert.ok(wizardSource.includes("void runStep(step, 'run')"), 'Run click passes run intent into the shared handler');
assert.ok(wizardSource.includes("void runStep(step, 'retry')"), 'Retry click passes retry intent into the shared handler');
assert.ok(!wizardSource.includes("!('skip_all' in payload)"), 'wizard no longer treats skip_all:false as skip-all');

const runDecision = resolveLandingClientRequest({ intent: 'run', skipAll: false, incompleteRun: false });
const retryDecision = resolveLandingClientRequest({ intent: 'retry', skipAll: false, incompleteRun: false });
const resumeDecision = resolveLandingClientRequest({ intent: 'resume', skipAll: false, incompleteRun: true });
const skipDecision = resolveLandingClientRequest({ intent: 'run', skipAll: true, incompleteRun: false });
const skipWithIncomplete = resolveLandingClientRequest({ intent: 'retry', skipAll: true, incompleteRun: true });
const retryIncomplete = resolveLandingClientRequest({ intent: 'retry', skipAll: false, incompleteRun: true });
const runIncomplete = resolveLandingClientRequest({ intent: 'run', skipAll: false, incompleteRun: true });
const resumeWithoutRun = resolveLandingClientRequest({ intent: 'resume', skipAll: false, incompleteRun: false });

assert.equal(runDecision.kind, 'start', 'Run without an incomplete run starts');
assert.equal(runDecision.body?.landing_action, 'start', 'Run payload uses landing_action start');
assert.equal(retryDecision.kind, 'start', 'Retry without an incomplete run starts');
assert.equal(retryDecision.body?.landing_action, 'start', 'Retry payload uses landing_action start');
assert.equal(resumeDecision.kind, 'process', 'Resume with an incomplete run processes');
assert.equal(resumeDecision.body?.landing_action, 'process', 'Resume payload uses landing_action process');
assert.equal(skipDecision.kind, 'skip', 'skip-all stays skip-only');
assert.equal(skipDecision.body?.skip_all, true, 'skip-all payload is skip_all true');
assert.equal(skipDecision.body?.landing_action, undefined, 'skip-all does not send landing_action');
assert.equal(skipWithIncomplete.kind, 'skip', 'skip-all stays skip-only even with an incomplete run');
assert.equal(retryIncomplete.kind, 'blocked', 'Retry never starts over an incomplete run');
assert.equal(runIncomplete.kind, 'blocked', 'Run never starts over an incomplete run');
assert.equal(resumeWithoutRun.kind, 'blocked', 'Resume without an incomplete run does not start');
assert.ok(![runIncomplete, retryIncomplete, resumeWithoutRun].some((decision) => decision.body?.landing_action === 'start'), 'incomplete run never selects start');

console.log('  PASS: production helper restores 4 completed + 5 pending and replaces default sections');
console.log('  PASS: newly added plan rows restore item_count and override_canonical');
console.log('  PASS: active processing hides Resume; expired/interrupted can resume');
console.log('  PASS: incomplete persisted runs hide generic start/retry');
console.log('  PASS: wizard.ts imports and applies the production helper');
console.log('  PASS: Run/Retry produce start; Resume produces process; skip-all stays skip-only');
console.log('  PASS: incomplete run never selects start from Run, Retry, or Resume');
console.log('\n  Results: 7 passed, 0 failed');
