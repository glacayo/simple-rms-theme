# Apply Progress: wizard-landing-page-builder

**Mode**: Standard  
**Batch**: PR3 remaining risk blockers + SEO append feedback  
**Date**: 2026-07-22  
**Branch**: `feat/wizard-landing-ui` (feature-branch-chain, base = PR2 `feat/wizard-landing-backend`)

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

### PR3 review-blocker / high-value warning fixes (prior batch)
- [x] BLOCKER: `skip_all` reconciles final-state (noindex/menu) for existing landings before complete; fails closed on sync error; no-op when none exist
- [x] BLOCKER: post-publish failure path best-effort Ads final-state protection (recover post_id → noindex + menu remove); logs success/fail; still returns original error
- [x] WARNING: `remove_page_items` read-back verification; Ads/ineligible removal fail-closed in landing final-state + Menu Setup reconcile
- [x] WARNING: Menu Setup builds pool from eligible landings even when `generated_pages` empty; Ads still excluded
- [x] WARNING: `landing-page.php` guards ACF helpers with `function_exists` before flexible loop
- [x] Docs: JSDoc on `getGeneratedPages()`; breadcrumb behavior documented; blank lines cleaned; no-runner limitation kept

### PR3 remaining risk blockers (this batch)
- [x] BLOCKER: `landing-page.php` ACF-missing path no longer loads template parts that call `get_sub_field()`; minimal title/content fallback when ACF absent; hardcoded section order preserved when ACF present but `page_sections` empty; flexible path unchanged when rows exist
- [x] BLOCKER: `try_preserve_existing_landing()` early failures (`wp_update_post` error, invalid update result, `ensure_landing_meta` failure) call `protect_ads_final_state_best_effort()` before returning original error (logs post_id/landing_key/slug via protection helper)
- [x] WARNING: SEO menu append returns structured result + read-back; failures logged as warning (best-effort). Ads menu removal remains fail-closed. Reconcile exposes `append_failed_page_ids` without flipping removal `verified`
- [x] Minor: JSDoc on `getMenuEligibleLandings()`; Yoast title/description max lengths as named constants

Phase 4 verification tasks remain incomplete (manual WP Admin scenarios).

## Files Changed (this batch)

| File | Action | What Was Done |
|------|--------|---------------|
| `pages/landing-page.php` | Modified | Split ACF-missing vs ACF-empty fallbacks; safe markup without `get_sub_field` |
| `inc/wizard/class-step-landing-page-builder.php` | Modified | Ads protect on preserve early fails; SEO append feedback logs |
| `inc/wizard/class-menu-builder.php` | Modified | Structured `append_page_items` + verify; reconcile reports append failures |
| `inc/wizard/class-step-menu-setup.php` | Modified | Warning log when SEO append incomplete after Ads removal OK |
| `inc/wizard/class-yoast-meta-writer.php` | Modified | `TITLE_MAX_LENGTH` / `DESCRIPTION_MAX_LENGTH` constants |
| `src/ts/admin/wizard.ts` | Modified | JSDoc for `getMenuEligibleLandings` |
| `openspec/changes/wizard-landing-page-builder/apply-progress.md` | Modified | This cumulative progress note |

## Design notes

- **Breadcrumb (flexible path)**: injected **once after the first Hero row only**. If flexible `page_sections` has no Hero layout, **no breadcrumb** is rendered in that path.
- **Breadcrumb (fallback path, ACF present)**: hardcoded order still includes Hero then `breadcrumb-slim`.
- **ACF missing**: minimal `the_title` / `the_content` fallback only — never load section template parts.
- **Menu policy asymmetry**: SEO append = **best-effort** (warn + continue). Ads removal / noindex = **fail-closed**.
- **No automated behavioral tests**: Mode Standard (`strict_tdd: false`, no test runner). Verification is `php -l`, `tsc --noEmit`, and `npm run build` only.

## Verification

```
php -l pages/landing-page.php
php -l inc/wizard/class-step-landing-page-builder.php
php -l inc/wizard/class-menu-builder.php
php -l inc/wizard/class-step-menu-setup.php
php -l inc/wizard/class-yoast-meta-writer.php
npx tsc --noEmit
npm run build
```

Mode: **Standard** (`strict_tdd: false`, no test runner). Manual WP Admin scenarios remain for Phase 4.

## Workload / PR Boundary

- Mode: chained PR slice (feature-branch-chain)
- Current work unit: PR3 remaining risk blockers only
- Boundary: no archive, no commit/push
- Estimated review budget impact: small focused delta

## Status

17/19 tasks complete (Phase 1–3) + PR3 remaining risk blockers addressed. Ready for re-review / Phase 4 verification. No commit performed.
