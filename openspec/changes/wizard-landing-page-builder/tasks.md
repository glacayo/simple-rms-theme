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

- [ ] 2.1 Modify `inc/wizard/class-ai-content-harness.php` with `PAGE_LANDING` Layer 2 and Layer 3 keywords only for `hero` / `seo-content`.
- [ ] 2.2 Modify `inc/wizard/class-step-home-page-builder.php` to first-write reusable prepared rows to `Canonical_Section_Store`, excluding `hero` / `seo-content`.
- [ ] 2.3 Create `inc/wizard/class-step-landing-page-builder.php` with payload validation, id/key/slug matching, duplicate/collision rejection, renames, and skip-all completion.
- [ ] 2.4 Add lazy bootstrap in `inc/wizard/class-step-landing-page-builder.php`: `state.home_sections` → Home `page_sections` → neutral generation; block with actionable error if missing.
- [ ] 2.5 Modify `inc/wizard/class-content-builder.php` to whitelist `meta_input` for `_wp_page_template` and `rms_landing_type` only.
- [ ] 2.6 Modify `inc/wizard/class-step-generate-pages.php` so `delete_unselected_pages()` excludes pages with `rms_landing_type` meta.

## Phase 3: Menu, SEO, UI, Template Slice

- [ ] 3.1 Modify `inc/wizard/class-menu-builder.php` with idempotent `append_page_items()`, `remove_page_items()`, and shared landing reconciliation.
- [ ] 3.2 Modify `inc/wizard/class-step-menu-setup.php` to merge eligible SEO landings, exclude Ads, and reconcile after destructive menu replacement.
- [ ] 3.3 Modify `inc/wizard/class-yoast-meta-writer.php` and `inc/wizard/wizard-init.php` for Yoast meta, noindex read-back, `wp_robots`, and Ads sitemap exclusion.
- [ ] 3.4 Modify `src/ts/admin/wizard.ts` for landing collection, keyword validation, skip-all, identity hydration, duplicate reset, unlock/relock, and replace modal.
- [ ] 3.5 Modify `src/scss/admin/wizard.scss` for landing and unlock admin states only.
- [ ] 3.6 Modify `pages/landing-page.php` to render flexible `page_sections` and inject `breadcrumb-slim` once after the first Hero.

## Phase 4: Verification

- [ ] 4.1 Run `php -l` on `inc/wizard/class-step-landing-page-builder.php`, `inc/wizard/class-canonical-section-store.php`, `inc/wizard/class-wizard-unlock-controller.php`, and modified `inc/wizard/*.php` above.
- [ ] 4.2 Run `npx tsc --noEmit` for `src/ts/admin/wizard.ts` and `npm run build` for `src/scss/admin/wizard.scss` / `pages/landing-page.php` assets.
- [ ] 4.3 Manually verify `inc/wizard/class-step-landing-page-builder.php`: SEO+Ads, keyword scope, canonical first-write/replace/override, identity/collisions, type flips, Yoast, sitemap/noindex, deletion guard.
- [ ] 4.4 Manually verify `src/ts/admin/wizard.ts`, `inc/wizard/class-step-controller.php`, and `pages/landing-page.php`: skip-all, duplicate-row reset, unlock/relock, no step-status pollution, first render, breadcrumb once, DOM < 1500.
