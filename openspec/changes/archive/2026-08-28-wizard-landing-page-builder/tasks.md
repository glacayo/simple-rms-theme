# Tasks: Wizard Landing Page Builder

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 900-1300 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 foundation → PR 2 backend → PR 3 UI/verification |
| Delivery strategy | auto-chain (`force-chained` from `openspec/config.yaml`) |
| Chain strategy | feature-branch-chain |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Canonical store, state, unlock, completion gate | PR 1 | base = tracker; `php -l` |
| 2 | Landing backend, identity, bootstrap, deletion guard | PR 2 | base = PR 1; PHP/manual |
| 3 | Menu/SEO sync, admin UI, template, verification | PR 3 | base = PR 2; TS/build/manual |

## Phase 1: Foundation Slice

- [x] 1.1 Create `inc/wizard/class-canonical-section-store.php` with lazy option load, `get/has/set_if_empty/replace/summary`, and first-write semantics.
- [x] 1.2 Modify `inc/wizard/class-state-manager.php` and `inc/wizard/class-step-controller.php` for `landing_pages`, `canonical_sections`, shared required steps, parity, and unlock-aware completion.
- [x] 1.3 Create `inc/wizard/class-wizard-unlock-controller.php` and modify `inc/wizard/wizard-init.php` for nonce/capability unlock UI plus `rms_wizard_unlocked_at` / `rms_wizard_unlocked_by`.
- [x] 1.4 Modify `inc/wizard/class-step-controller.php` so only `unlock`/`relock` bypass the completed gate and skip `set_current_step()` / `set_step_status()`.

### Phase 1 compatibility notes (review follow-up)

- Active `REQUIRED_STEPS` remains the existing 7-step list so current completion stays valid. Do **not** require `landing-page-builder` until Phase 2 registers dispatch + UI. `Step_Home_Page_Builder::maybe_mark_completed()` and `Step_Controller::complete()` both consume `get_required_steps()`.
- Do **not** expose or normalize `landing_page_builder` / `landing-page-builder` as executable until Phase 2 adds dispatch. `Step_Controller` keeps a `DISPATCHABLE_STEPS` allowlist (7 real steps + `unlock`/`relock`) and rejects unknown steps **before** `set_current_step()` / `set_step_status()`. Re-add the alias + dispatch case together in Phase 2.
- Canonical store and unlock/relock only report success after real option persistence (or verified equal post-state).
- Controlled unlock stays disabled (`CONTROLLED_UNLOCK_ENABLED = false`) until Phase 2 task 2.6 ships the generate-pages landing deletion guard. Unlock admin-post is not registered while disabled; REST unlock returns 503 via controller gate. Relock remains for stale-marker cleanup. Enable unlock registration + UI in the same 2.6 change.
- Completion flag source of truth is `State_Manager::has_completion_flag()` only (no duplicate unlock-controller helper).
- Stale `rms_wizard_unlocked_at` markers do **not** bypass lock while controlled unlock is disabled (`has_unlock_marker()` vs effective `is_unlocked()`). Relock still clears stale markers. `RMS_WIZARD_FORCE` still bypasses lock.
- Admin view trusts only `get_resume_state()` flags for completed/unlocked/force/controlled-unlock UI.
- `execute_step()` marks progress steps `failed` on unexpected throwables so status cannot stick on `running`; logs if that status write fails; unlock/relock never write step status.
- Verification note: automated behavioral tests unavailable by project config (`strict_tdd: false` / testing unavailable). Phase 1 relies on `php -l` + 4R fresh review + later manual runtime verification (Phase 4).

## Phase 2: Backend Landing Slice

- [x] 2.1 Modify `inc/wizard/class-ai-content-harness.php` with `PAGE_LANDING` Layer 2 and Layer 3 keywords only for `hero` / `seo-content`.
- [x] 2.2 Modify `inc/wizard/class-step-home-page-builder.php` to first-write reusable prepared rows to `Canonical_Section_Store`, excluding `hero` / `seo-content`.
- [x] 2.3 Create `inc/wizard/class-step-landing-page-builder.php` with payload validation, id/key/slug matching, duplicate/collision rejection, renames, and skip-all completion.
- [x] 2.4 Add lazy bootstrap in `inc/wizard/class-step-landing-page-builder.php`: `state.home_sections` → Home `page_sections` → neutral generation; block with actionable error if missing.
- [x] 2.5 Modify `inc/wizard/class-content-builder.php` to whitelist `meta_input` for `_wp_page_template` and `rms_landing_type` only.
- [x] 2.6 Modify `inc/wizard/class-step-generate-pages.php` so `delete_unselected_pages()` excludes pages with `rms_landing_type` meta.

### Phase 2 compatibility notes (PR2 review follow-up)

- **Backend class exists; activation deferred to Phase 3.** `Step_Landing_Page_Builder` is fully implemented (tasks 2.3–2.4), but `landing-page-builder` is **not** in active `REQUIRED_STEPS` or `DISPATCHABLE_STEPS` until Phase 3 wires admin UI + Ads noindex/menu final-state sync. Dispatch `case` + `landing_page_builder` alias remain in code for that activation; alias alone is safe (unknown-step reject before status writes).
- Existing 7-step UI completion stays valid. Do **not** require the 8th step while UI cannot send payload/`skip_all` visibly.
- Identity preflight: non-empty `id` + `landing_key` must refer to the same state row; reject duplicate ids/keys, non-landing and other-landing slug collisions, and stale cross-pair identity. Match order is deterministic: id → key → slug.
- Keyword sections (`hero` / `seo-content`): AI generation/decode/review/empty-validation failures return `WP_Error` (no placeholder publish) unless a valid existing landing payload can be preserved.
- Multi-landing partial failure: successful `landing_pages` are persisted before marking the step failed; counts/post IDs logged; status write failures logged.
- Critical persistence (`save_state`, `set_step_status`, meta safety-net, canonical `replace`/`set_if_empty`) verified via post-state checks and logged/`WP_Error` on failure.
- Reviewer throwables and invalid JSON-on-success are logged with landing key/slug/layout. Reusable/canonical generation/review filters out keyword-driven priors.
- Shared section-assembly helper extraction deferred (deliberate duplication with Home builder) — extract after PR3 or before archive.
- Controlled unlock remains enabled with the 2.6 deletion guard (unchanged by this follow-up).

## Phase 3: Menu, SEO, UI, Template Slice

- [x] 3.1 Modify `inc/wizard/class-menu-builder.php` with idempotent `append_page_items()`, `remove_page_items()`, and shared landing reconciliation.
- [x] 3.2 Modify `inc/wizard/class-step-menu-setup.php` to merge eligible SEO landings, exclude Ads, and reconcile after destructive menu replacement.
- [x] 3.3 Modify `inc/wizard/class-yoast-meta-writer.php` and `inc/wizard/wizard-init.php` for Yoast meta, noindex read-back, `wp_robots`, and Ads sitemap exclusion.
- [x] 3.4 Modify `src/ts/admin/wizard.ts` for landing collection, keyword validation, skip-all, identity hydration, duplicate reset, unlock/relock, and replace modal.
- [x] 3.5 Modify `src/scss/admin/wizard.scss` for landing and unlock admin states only.
- [x] 3.6 Modify `pages/landing-page.php` to render flexible `page_sections` and inject `breadcrumb-slim` once after the first Hero.
- [x] 3.7 **Activate** `landing-page-builder` atomically: add to `REQUIRED_STEPS` + `DISPATCHABLE_STEPS` (alias + dispatch case already present) together with UI + noindex/menu final-state sync. Do not activate required/dispatch without the visible skip/payload path.
- [x] 3.8 Convert landing seed-data cards into accessible collapsed-by-default accordions: visible header summary (title/type/keyword), native toggle button with `aria-expanded`/`aria-controls`, isolated Duplicate/Remove, live summary updates, and preserved form values.

## Phase 4: Verification

- [x] 4.1 Run `php -l` on `inc/wizard/class-step-landing-page-builder.php`, `inc/wizard/class-canonical-section-store.php`, `inc/wizard/class-wizard-unlock-controller.php`, and modified `inc/wizard/*.php` above.
- [x] 4.2 Run `npx tsc --noEmit` for `src/ts/admin/wizard.ts` and `npm run build` for `src/scss/admin/wizard.scss` / `pages/landing-page.php` assets.
- [x] 4.3 Manually verify `inc/wizard/class-step-landing-page-builder.php`: SEO+Ads, keyword scope, canonical first-write/replace/override, identity/collisions, type flips, Yoast, sitemap/noindex, deletion guard.
- [x] 4.4 Manually verify `src/ts/admin/wizard.ts`, `inc/wizard/class-step-controller.php`, and `pages/landing-page.php`: skip-all, duplicate-row reset, unlock/relock, no step-status pollution, first render, breadcrumb once, DOM < 1500.

### Phase 4 verification evidence (2026-08-29)

- **4.1** `php -l` on the isolated worktree (`simple-rms-theme-wizard-landing-phase4` at `5e418dd`): all 15 change PHP files report `No syntax errors detected` (landing builder, canonical store, unlock controller, run orchestrator, step controller, state manager, AI harness, home/menu/generate/content/Yoast classes, `wizard-init.php`, `pages/landing-page.php`).
- **4.2** `tsc --noEmit` (project-wide + targeted `src/ts/admin/wizard.ts`, strict) exit 0 in the worktree; `npm run build` exit 0 in both the worktree (committed sources, self-contained `npm ci`) and the main worktree.
- **4.3** Modular deterministic harnesses `tests/wizard-landing-identity-canonical-harness.php` (8 scenarios) + `tests/wizard-landing-lifecycle-protection-harness.php` (6 scenarios) + compatibility runner `tests/wizard-landing-final-state-harness.php` (14 scenarios) + shared bootstrap/stubs (`tests/wizard-landing-phase4-bootstrap.php` + `tests/wizard-landing-phase4-stubs.php`): SEO/Ads final state (menu append/remove, noindex write+read-back), keyword scope (PAGE_LANDING + KEYWORD CONTEXT block for hero/seo-content only; reusable rows neutral; subkeywords clamp 0–10), canonical first-write/replace/exclusion of keyword layouts, identity preflight (duplicate id/key/slug, slug vs non-landing collision, slug fallback only to landing meta, id+key cross-pair mismatch, rename collision), empty keyword rejection, replace-canonical confirmation gate, SEO↔Ads type flips (menu + robots reconcile both sides), Yoast title/metadesc active + skip-when-absent, scoped `wp_robots` ads noindex (double protection), WP + Yoast sitemap exclusion, generate-pages deletion guard (meta + state-slug defense-in-depth). All harness files <= 400 lines. Existing committed `scripts/test-landing-run-orchestrator.php` also passes (293 assertions) in the worktree.
- **4.4** New harnesses: `tests/wizard-landing-controller-harness.php` (7 scenarios) — REQUIRED/DISPATCHABLE parity, completed-locked-until-unlock, unlock/relock never write `current_step`/`step_status`, skip-all after unlock preserves completion + releases the fence, landing start/process never write `running`, unknown-step rejection without pollution, locked rejection without writes; `tests/wizard-landing-render-harness.php` (5 scenarios) — flexible rows render in order with `breadcrumb-slim` exactly once after the FIRST hero, second hero adds no breadcrumb, no-hero ⇒ no breadcrumb, empty flexible ⇒ legacy hardcoded order, DOM < 1500; `tests/wizard-landing-acf-missing-harness.php` (1 scenario) — ACF-absent path renders the minimal safe fallback and loads no template parts. Browser (read-only, `simple-rms-theme.local` only): landing page id 432 `stamped-concrete` renders `page-template-pageslanding-page-php` with 447 DOM nodes, one `breadcrumb-page--slim`, robots `index, follow`; wizard admin landing panel renders skip-all/add/accordion/25 replace-canonical controls; 0 console errors on both pages. No POSTs, no mutations, no database restore needed.
- **Verification finding (recorded, not silently changed):** committed `AI_Content_Harness::get_layer3()` injects the KEYWORD CONTEXT block for landing hero/seo-content, but the nested `{{primary_keyword}}`/`{{subkeywords}}` tokens remain literal in the prompt (single-pass `strtr`; replacement values are not rescanned). Identical at HEAD and in the working tree. The design contract (keyword block present, neutral reusable rows) holds; keyword *value* interpolation into the prompt is a behavior gap to adjudicate in the dedicated verify phase. Harness asserts the committed truth.
- Delivery: `auto-chain`, budget 400. Phase 4 added ~1,100 lines of harness/test evidence in the isolated worktree only; production files were NOT modified in this batch. No commit, no push, no branch change, no archive; main worktree uncommitted `wizard-internal-page-builder` Phase 9 + archive artifacts preserved untouched. Independent `sdd-verify` intentionally not run (apply scope).

### Correction record — failed final verification (2026-08-28, evidence_revision sha256:166d8a8…)

Triggered by the failed final verification (`verify-report.md`, verdict `fail`, 1 blocker / 1 critical: KEYWORD CONTEXT token interpolation). Minimal, scope-confined correction, no new change created:

- **`inc/wizard/class-ai-content-harness.php`** — landing `$keyword_block` in `get_layer3()` now concatenates the resolved `$primary` / `$subkeywords` values with the `(none provided)` / `(none)` fallbacks, matching the correct Home-path pattern (the strtr array entries remain as dead-code fallbacks per the verify report's minimal-correction guidance; behavior is now identical between landing and Home paths).
- **`tests/wizard-landing-final-state-harness.php`** — keyword-scope assertions now require the resolved values (`- Primary keyword: concrete repair`, `- Subkeywords: driveway, patio`) and reject literal `{{primary_keyword}}` / `{{subkeywords}}` tokens.
- **`tests/wizard-home-seo-targeting-harness.php`** (committed, ancestor `7580145`) — its two landing placeholder-contract assertions updated the same way to require resolved values (`kitchen remodel near me` / `Kitchen Remodel Near Me, cabinets, Kitchen Remodel Near Me`) and reject literal tokens. This was the only other test asserting the literal-token behavior.
- **Harness promotion** — the five Phase 4 landing harness files were promoted from the isolated worktree `simple-rms-theme-wizard-landing-phase4` (untracked there) into the main worktree `tests/`: `wizard-landing-phase4-bootstrap.php`, `wizard-landing-final-state-harness.php`, `wizard-landing-controller-harness.php`, `wizard-landing-render-harness.php`, `wizard-landing-acf-missing-harness.php`. SHA-256 verified byte-identical to the worktree copies.

Verification results (Local PHP 8.2.27 win64, node v24.11.0): `php tests/wizard-landing-final-state-harness.php` → 14 scenarios passed (`identity-canonical` 8/8, `lifecycle-protection` 6/6); `wizard-landing-controller-harness.php` → 7; `wizard-landing-render-harness.php` → 5; `wizard-landing-acf-missing-harness.php` → 1; `wizard-home-seo-targeting-harness.php` → 9; `scripts/test-landing-run-orchestrator.php` → 293 passed; `scripts/test-landing-run-client.mjs` → 7 passed. `php -l` clean on all harness + change PHP files; `npx tsc --noEmit` exit 0; `npm run build` exit 0. All new harness files are individually <= 400 lines. The failed `verify-report.md` was intentionally preserved untouched for the dedicated re-verification run.
