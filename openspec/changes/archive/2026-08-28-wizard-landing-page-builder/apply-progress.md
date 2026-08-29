# Apply Progress: wizard-landing-page-builder

**Mode**: Standard  
**Batch**: Phase 3.8 landing-card accordion (manual UX observation)  
**Date**: 2026-08-19  
**Branch**: `feature/wizard-setup` (after merged PR chain)

## Completed Tasks (cumulative)

### Phase 1
- [x] 1.1 Canonical section store
- [x] 1.2 State manager + step controller foundation
- [x] 1.3 Unlock controller + admin wiring
- [x] 1.4 Completed-gate allowlist for unlock/relock only

### Phase 2
- [x] 2.1 AI harness `PAGE_LANDING` Layer 2 + Layer 3 keywords (`hero` / `seo-content` only)
- [x] 2.2 Home Builder first-write reusable rows to `Canonical_Section_Store`
- [x] 2.3 `Step_Landing_Page_Builder` payload validation, identity preflight, renames, skip-all
- [x] 2.4 Lazy canonical bootstrap (`state.home_sections` → Home `page_sections` → neutral gen)
- [x] 2.5 `Content_Builder` whitelist `meta_input` (`_wp_page_template`, `rms_landing_type`)
- [x] 2.6 `delete_unselected_pages()` landing guard + controlled unlock enabled

### Phase 2 PR2 review-blocker / reliability follow-up
- [x] Deactivate live required/dispatch until Phase 3 (then re-activated in 3.7)
- [x] Identity cross-pair validation + stricter slug collisions
- [x] Keyword AI failure → `WP_Error` (or preserve existing landing)
- [x] Multi-landing partial-failure state persistence
- [x] Critical persistence post-state checks
- [x] Reviewer/JSON observability + neutral priors
- [x] generate-pages residual landing exclusion (fail-closed)
- [x] build_one_landing exception → WP_Error + partial path
- [x] try_preserve checks update/meta results
- [x] landing_pages_equivalent expanded
- [x] Local try/catch around `build_page()`

### Phase 3
- [x] 3.1 `Menu_Builder`: idempotent `append_page_items()`, `remove_page_items()`, `reconcile_landing_menu_items()`
- [x] 3.2 `Step_Menu_Setup`: merge eligible SEO landings, exclude Ads, post-replace reconciliation
- [x] 3.3 Yoast title/metadesc + noindex write/read-back; `wp_robots`; WP/Yoast sitemap Ads exclusion
- [x] 3.4 Admin TS: landing collector, keyword validation, skip-all, identity hydration, duplicate reset, unlock-aware lock UI, replace-canonical modal
- [x] 3.5 Admin SCSS: landing + unlock states
- [x] 3.6 `pages/landing-page.php`: flexible `page_sections` + breadcrumb once after first Hero
- [x] 3.7 Atomic activation: `landing-page-builder` in `REQUIRED_STEPS` + `DISPATCHABLE_STEPS` with visible UI + final-state sync
- [x] 3.8 Landing seed-data cards are accessible collapsed-by-default accordions (header summary, native toggle, isolated Duplicate/Remove, live summary, preserved form values)

### PR3 review-blocker / high-value warning fixes (prior batch)
- [x] BLOCKER: `skip_all` reconciles final-state (noindex/menu) for existing landings before complete; fails closed on sync error; no-op when none exist
- [x] BLOCKER: post-publish failure path best-effort Ads final-state protection (recover post_id → noindex + menu remove); logs success/fail; still returns original error
- [x] WARNING: `remove_page_items` read-back verification; Ads/ineligible removal fail-closed in landing final-state + Menu Setup reconcile
- [x] WARNING: Menu Setup builds pool from eligible landings even when `generated_pages` empty; Ads still excluded
- [x] WARNING: `landing-page.php` guards ACF helpers with `function_exists` before flexible loop
- [x] Docs: JSDoc on `getGeneratedPages()`; breadcrumb behavior documented; blank lines cleaned; no-runner limitation kept

### PR3 remaining risk blockers (prior batch)
- [x] BLOCKER: `landing-page.php` ACF-missing path no longer loads template parts that call `get_sub_field()`; minimal title/content fallback when ACF absent; hardcoded section order preserved when ACF present but `page_sections` empty; flexible path unchanged when rows exist
- [x] BLOCKER: `try_preserve_existing_landing()` early failures (`wp_update_post` error, invalid update result, `ensure_landing_meta` failure) call `protect_ads_final_state_best_effort()` before returning original error (logs post_id/landing_key/slug via protection helper)
- [x] WARNING: SEO menu append returns structured result + read-back; failures logged as warning (best-effort). Ads menu removal remains fail-closed. Reconcile exposes `append_failed_page_ids` without flipping removal `verified`
- [x] Minor: JSDoc on `getMenuEligibleLandings()`; Yoast title/description max lengths as named constants

### Manual verification notes
- [x] User smoke: landing creation succeeded after the merged PR chain.
- [x] Phase 4 verification tasks 4.1–4.4 complete (see Phase 4 evidence below).

## Phase 4: Verification (tasks 4.1–4.4)

Applied in an isolated worktree at the change's committed base (`5e418dd`, detached HEAD at `simple-rms-theme-wizard-landing-phase4`) so the uncommitted verified `wizard-internal-page-builder` Phase 9 work and the completed `wizard-user-friendly-content-flow` archive files in the main worktree were preserved untouched. No commits, pushes, branch changes, or archive operations were made; the Phase 4 harness deliverables live in that worktree's `tests/` directory for the dedicated `sdd-verify` phase to consume. The worktree contains no `.codegraph` index (fresh checkout; nothing copied or reused).

### 4.1 PHP syntax

```text
php -l inc/wizard/class-step-landing-page-builder.php
php -l inc/wizard/class-canonical-section-store.php
php -l inc/wizard/class-wizard-unlock-controller.php
php -l inc/wizard/class-landing-run-orchestrator.php
php -l inc/wizard/class-step-controller.php
php -l inc/wizard/class-state-manager.php
php -l inc/wizard/class-ai-content-harness.php
php -l inc/wizard/class-step-home-page-builder.php
php -l inc/wizard/class-step-menu-setup.php
php -l inc/wizard/class-menu-builder.php
php -l inc/wizard/class-step-generate-pages.php
php -l inc/wizard/class-content-builder.php
php -l inc/wizard/class-yoast-meta-writer.php
php -l inc/wizard/wizard-init.php
php -l pages/landing-page.php
```

Result: all 15 files report `No syntax errors detected` (Local PHP 8.2.29 win64, run against the worktree at `5e418dd`).

### 4.2 Type check + build

```text
npx tsc --noEmit --pretty false
npx tsc --ignoreConfig --noEmit --target ESNext --module ESNext --moduleResolution bundler --strict --lib ESNext,DOM --pretty false src/ts/admin/wizard.ts
npm run build
```

Result: project-wide and targeted `tsc` exit 0 in the worktree (self-contained `npm ci`); `npm run build` exit 0 in both the worktree (committed sources) and the main worktree (`dist/` gitignored, wizard JS/CSS emitted).

### 4.3 Landing builder behavior

- Created `tests/wizard-landing-phase4-bootstrap.php` (shared isolated stubs: `$wpdb` atomic option fake for the mutation fence + run leases, deterministic AI provider through the real `AI_Content_Harness`/`Section_Assembler` pipeline, menu/post/meta stubs).
- Created `tests/wizard-landing-final-state-harness.php` — 14 scenarios: SEO/Ads final state (menu append/remove + noindex write/read-back), keyword scope (KEYWORD CONTEXT block for hero/seo-content only; reusable rows neutral; subkeywords clamped 0–10), canonical first-write/replace/exclusion of keyword layouts, identity preflight (duplicate id/key/slug, slug vs non-landing collision, slug fallback only to landing meta, id+key cross-pair mismatch, rename collision), empty-keyword rejection, replace-canonical confirmation gate, SEO↔Ads type flips (menu + robots reconcile both sides), Yoast title/metadesc active + skip+log when absent, scoped `wp_robots` ads noindex, WP + Yoast sitemap exclusion, generate-pages deletion guard (meta + state-slug defense-in-depth).
- Result: `Harness passed: 14 scenarios.` Existing committed `scripts/test-landing-run-orchestrator.php` also passes in the worktree: `Results: 293 passed, 0 failed`.

### 4.4 Controller + template + client

- Created `tests/wizard-landing-controller-harness.php` — 7 scenarios: REQUIRED/DISPATCHABLE step parity (landing required; unlock/relock allowlisted pseudo-steps only), completed site locked until unlock, unlock/relock never write `current_step`/`step_status`, skip-all after unlock (no-op complete, completion preserved, fence released), landing start/process never write `running` and never pollute `current_step`, unknown-step rejection before any write, locked rejection without status writes.
- Created `tests/wizard-landing-render-harness.php` — 5 scenarios: flexible rows render in order with `breadcrumb-slim` exactly once after the FIRST hero, second hero adds no breadcrumb, no hero ⇒ no breadcrumb, empty flexible content ⇒ legacy hardcoded order, DOM < 1500.
- Created `tests/wizard-landing-acf-missing-harness.php` — 1 scenario: with ACF functions genuinely absent, `pages/landing-page.php` renders the minimal safe fallback and loads no template parts (no `get_sub_field` fatals).
- Client: existing committed `scripts/test-landing-run-client.mjs` passes in the worktree (`Results: 7 passed, 0 failed`), covering `resolveLandingClientRequest` (Run/Retry→start, Resume→process, skip-all→skip, incomplete run blocks start), hydration merge, resume offer, and section restoration.
- Result: `7 + 5 + 1` harness scenarios passed.

### Browser verification (read-only, simple-rms-theme.local only)

- Identity proof before navigation: REST root reports `name=simple-rms-theme`, `url=http://simple-rms-theme.local` (the authorized target). No other Local site was accessed.
- Landing page id 432 `stamped-concrete` (template `pages/landing-page.php`): body class `page-template-pageslanding-page-php`; 447 total DOM nodes (< 1500); exactly one `breadcrumb-page--slim`; exactly one hero section; robots `index, follow` (SEO landing, correctly not noindexed); sections render in the flexible order (hero, seo-content, vision-mission, badges, portfolio, testimonials); 0 console errors.
- Wizard admin page: landing panel renders skip-all checkbox, Add Landing button, accordion toggle (`aria-expanded`), 1 hydrated row, 25 replace-canonical layout controls; 0 console errors.
- All browser interactions were GET navigations + DOM inspection only — no POSTs, no form submissions, no destructive scenarios, no database mutation. Therefore no backup restore was required; final state == initial state (verified by construction: read-only session).

### Verification finding (recorded, not silently changed)

Committed `AI_Content_Harness::get_layer3()` injects the mandatory KEYWORD CONTEXT block into landing hero/seo-content prompts, but the nested `{{primary_keyword}}` / `{{subkeywords}}` tokens remain literal in the emitted prompt because `strtr` is single-pass (replacement values are not rescanned). Behavior is identical at HEAD `5e418dd` and in the working tree (the uncommitted diff only adds `compose_factual_context`). The design contract holds: the keyword block is present for keyword layouts only, and reusable rows stay keyword-neutral. Keyword value interpolation into the prompt is a behavior gap to adjudicate in the dedicated `sdd-verify` phase — the harness asserts the committed truth rather than being bent to a desired output.

### Correction record — failed final verification (2026-08-28)

Triggered by the failed final verification (`verify-report.md`, verdict `fail`, 1 blocker / 1 critical finding: KEYWORD CONTEXT token interpolation failure; `evidence_revision sha256:166d8a8…`). Applied ONLY the minimal correction; no new change created, no unrelated logic touched.

- **`inc/wizard/class-ai-content-harness.php`** — landing `$keyword_block` in `get_layer3()` now concatenates resolved `$primary` / `$subkeywords` with the `(none provided)` / `(none)` fallbacks, mirroring the correct Home-path pattern (verify report's minimal-correction guidance). The `{{primary_keyword}}` / `{{subkeywords}}` strtr entries remain as dead-code fallbacks; no API impact. Home keyword path and the unrelated `compose_factual_context` hunk (pre-existing Phase 9 uncommitted work) are byte-for-byte untouched.
- **`tests/wizard-landing-final-state-harness.php`** — keyword-scope assertions updated: require resolved values (`- Primary keyword: concrete repair`, `- Subkeywords: driveway, patio`) and reject literal `{{primary_keyword}}` / `{{subkeywords}}` tokens.
- **`tests/wizard-home-seo-targeting-harness.php`** — its two landing placeholder-contract assertions updated the same way (require `kitchen remodel near me` / `Kitchen Remodel Near Me, cabinets, Kitchen Remodel Near Me`, reject literal tokens). This committed harness (ancestor `7580145`) was the only other test asserting the broken literal-token behavior.
- **Harness promotion & review budget refactoring** — the Phase 4 landing test infrastructure was modularized into behavior-first files each <= 400 lines:
  - `tests/wizard-landing-phase4-stubs.php` (349 lines) — shared WP runtime, fake WPDB with lease fences, menu & template stubs, and deterministic AI provider
  - `tests/wizard-landing-phase4-bootstrap.php` (174 lines) — theme class requirements, test state management, and fake builder fixtures
  - `tests/wizard-landing-identity-canonical-harness.php` (280 lines) — 8 scenarios: keyword scope & clamping, canonical store first-write/replace/exclusion, identity preflight, collision rejections, required keyword validation
  - `tests/wizard-landing-lifecycle-protection-harness.php` (328 lines) — 6 scenarios: SEO/Ads final state, type flips reconciliation, Yoast meta integration, scoped wp_robots filter, sitemap exclusion, generate-pages deletion guard
  - `tests/wizard-landing-final-state-harness.php` (21 lines) — compatibility aggregate runner executing all 14 scenarios
  - `tests/wizard-landing-controller-harness.php` (196 lines) — 7 scenarios: parity, unlock/relock, skip-all, no status pollution
  - `tests/wizard-landing-render-harness.php` (155 lines) — 5 scenarios: template render, breadcrumb once, DOM < 1500
  - `tests/wizard-landing-acf-missing-harness.php` (70 lines) — 1 scenario: ACF-degraded safe fallback

Verification results (Local PHP 8.2.27 win64, node v24.11.0): final-state 14/14 (`identity-canonical` 8/8, `lifecycle-protection` 6/6), controller 7/7, render 5/5, acf-missing 1/1, home-seo-targeting 9/9, `scripts/test-landing-run-orchestrator.php` 293/293, `scripts/test-landing-run-client.mjs` 7/7. `php -l` clean on all harness + change PHP files; `npx tsc --noEmit` exit 0; `npm run build` exit 0. The failed `verify-report.md` was preserved untouched for the dedicated re-verification run. No commit, no stage, no branch change, no push, no archive.

## Files Changed (this batch)

| File | Action | What Was Done |
|------|--------|---------------|
| `openspec/changes/wizard-landing-page-builder/tasks.md` | Modified | Phase 4 tasks 4.1–4.4 checked + evidence block |
| `openspec/changes/wizard-landing-page-builder/apply-progress.md` | Modified | This cumulative progress note + Phase 4 evidence |
| `tests/wizard-landing-phase4-bootstrap.php` (worktree) | Created | Shared isolated stubs for the landing Phase 4 harnesses |
| `tests/wizard-landing-final-state-harness.php` (worktree) | Created | 14 scenarios: SEO/Ads final state, keyword scope, canonical, identity/collisions, type flips, Yoast, noindex/sitemap, deletion guard |
| `tests/wizard-landing-controller-harness.php` (worktree) | Created | 7 scenarios: parity, unlock/relock, skip-all, no status pollution |
| `tests/wizard-landing-render-harness.php` (worktree) | Created | 5 scenarios: template render, breadcrumb once, DOM < 1500 |
| `tests/wizard-landing-acf-missing-harness.php` (worktree) | Created | 1 scenario: ACF-degraded safe fallback |

## Status

20/20 tasks complete (Phase 1–4). Phase 4 verification evidence lives in the isolated worktree `simple-rms-theme-wizard-landing-phase4` for the dedicated `sdd-verify` phase. Production files were NOT modified in this batch; main worktree uncommitted `wizard-internal-page-builder` work and `wizard-user-friendly-content-flow` archive/spec-sync files preserved untouched. No commit performed.

## Files Changed (this batch)

| File | Action | What Was Done |
|------|--------|---------------|
| `inc/wizard/wizard-init.php` | Modified | Landing row template is now an accordion: native toggle, summary slots, hidden panel with stable ids |
| `src/ts/admin/wizard.ts` | Modified | Collapse-by-default for add/duplicate/hydrate; live summary sync; isolated Duplicate/Remove; remapped aria ids |
| `src/scss/admin/wizard.scss` | Modified | Accordion header, chevron state, summary chips, focus-visible, reduced-motion |
| `openspec/changes/wizard-landing-page-builder/tasks.md` | Modified | Added and completed Phase 3 task 3.8 |
| `openspec/changes/wizard-landing-page-builder/apply-progress.md` | Modified | This cumulative progress note |

## Design notes

- Accordion state is CSS/`hidden` only. Fields stay in the DOM so collapse/expand does not drop values or payload collection.
- Header summary: title (`Landing N` fallback), type (`SEO`/`Ads`), primary keyword (`No primary keyword` fallback). Updates live from title/type/keyword fields.
- Duplicate and Remove remain sibling actions outside the toggle, so their clicks never flip accordion state.
- Newly added, duplicated, and hydrated cards start collapsed. User-initiated add/duplicate focuses the toggle, not a hidden field.
- Payload, identity, skip-all, sections, and canonical replace behavior are unchanged.

## Work Unit Evidence

| Evidence | Result |
|---|---|
| Focused test command and exact result | `npx tsc --noEmit` — exit 0, no output. Local PHP 8.2.29 `php -l inc/wizard/wizard-init.php` — `No syntax errors detected`. |
| Runtime harness command/scenario and exact result | N/A — no automated runtime harness. Accordion is WP Admin UI. User already smoked successful landing creation. Remaining manual check: default collapsed, isolated Duplicate/Remove, live summary, form persistence. |
| Rollback boundary | Revert `inc/wizard/wizard-init.php`, `src/ts/admin/wizard.ts`, `src/scss/admin/wizard.scss`, plus the 3.8 task/progress notes. Does not touch landing payload or backend. |

## Verification

```
php -l inc/wizard/wizard-init.php
npx tsc --noEmit
npm run build
```

- `php -l` (Local PHP 8.2.29 win64): No syntax errors detected in `inc/wizard/wizard-init.php`
- `npx tsc --noEmit`: pass (exit 0)
- `npm run build`: pass (`tsc && vite build`, wizard CSS/JS rebuilt)

Mode: **Standard** (`strict_tdd: false`, no test runner). Phase 4 tasks 4.1–4.4 complete — see the Phase 4 evidence block above.

## Workload / PR Boundary

- Mode: chained PR slice follow-up on merged `feature/wizard-setup`
- Current work unit: Phase 3.8 landing-card accordion only
- Boundary: template + admin TS/SCSS + OpenSpec task/progress; no archive, no commit/push
- Estimated review budget impact: small focused UX delta

## Status

20/20 tasks complete (Phase 1–4 including verification harnesses). Ready for the dedicated `sdd-verify` phase; the isolated worktree `simple-rms-theme-wizard-landing-phase4` holds the Phase 4 harness deliverables. No commit performed.
