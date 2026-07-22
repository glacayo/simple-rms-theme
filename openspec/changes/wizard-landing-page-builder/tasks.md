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

## Phase 4: Verification

- [ ] 4.1 Run `php -l` on `inc/wizard/class-step-landing-page-builder.php`, `inc/wizard/class-canonical-section-store.php`, `inc/wizard/class-wizard-unlock-controller.php`, and modified `inc/wizard/*.php` above.
- [ ] 4.2 Run `npx tsc --noEmit` for `src/ts/admin/wizard.ts` and `npm run build` for `src/scss/admin/wizard.scss` / `pages/landing-page.php` assets.
- [ ] 4.3 Manually verify `inc/wizard/class-step-landing-page-builder.php`: SEO+Ads, keyword scope, canonical first-write/replace/override, identity/collisions, type flips, Yoast, sitemap/noindex, deletion guard.
- [ ] 4.4 Manually verify `src/ts/admin/wizard.ts`, `inc/wizard/class-step-controller.php`, and `pages/landing-page.php`: skip-all, duplicate-row reset, unlock/relock, no step-status pollution, first render, breadcrumb once, DOM < 1500.
