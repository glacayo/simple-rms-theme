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
- [ ] Phase 4 verification tasks remain incomplete (accordion UX still needs a WP Admin pass).

Phase 4 verification tasks remain incomplete (manual WP Admin scenarios).

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

Mode: **Standard** (`strict_tdd: false`, no test runner). Phase 4 tasks 4.1–4.4 remain unchecked.

## Workload / PR Boundary

- Mode: chained PR slice follow-up on merged `feature/wizard-setup`
- Current work unit: Phase 3.8 landing-card accordion only
- Boundary: template + admin TS/SCSS + OpenSpec task/progress; no archive, no commit/push
- Estimated review budget impact: small focused UX delta

## Status

18/20 tasks complete (Phase 1–3 including 3.8). Phase 4 verification still pending. Ready for verify after a WP Admin accordion pass. No commit performed.
