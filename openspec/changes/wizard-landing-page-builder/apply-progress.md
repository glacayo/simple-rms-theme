# Apply Progress: wizard-landing-page-builder

**Mode**: Standard  
**Batch**: Phase 2 Backend Landing Slice (PR2) + PR2 review-blocker fixes + remaining PR2 reliability follow-up + equivalence/`build_page` reliability  
**Date**: 2026-07-21  
**Branch**: `feat/wizard-landing-backend` (feature-branch-chain, base = PR1 foundation)

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

### Phase 2 PR2 review-blocker follow-up
- [x] Deactivate live required/dispatch for `landing-page-builder` until Phase 3 UI+noindex
- [x] Identity cross-pair validation + stricter slug collisions
- [x] Keyword AI failure → `WP_Error` (or preserve existing landing); no placeholder publish
- [x] Multi-landing partial-failure state persistence before failed status
- [x] Critical persistence post-state checks (state, status, meta, canonical write)
- [x] Reviewer/JSON observability + neutral priors for reusable/canonical paths

### Phase 2 PR2 remaining reliability follow-up
- [x] `generate-pages` excludes residual SEO/Ads landings from select/update/Home-Blog assignment (fail-closed `WP_Error`); deletion guard retained
- [x] `build_one_landing()` thrown exceptions converted to `WP_Error` + partial persistence path
- [x] `try_preserve_existing_landing()` checks `wp_update_post()` / meta results (no false preserve success)
- [x] Document duplicated Home/Landing helper families + extraction plan after PR3/before archive
- [x] `landing_pages_equivalent()` expanded beyond id/key/slug/type (title, keywords, menu_eligible, generated_at, preserved)
- [x] Local try/catch around `Content_Builder::build_page()` with post-ID recovery + contextual `WP_Error` (outer partial recovery unchanged)

Phase 3/4 tasks remain incomplete (including 3.7 activation).

## Phase 1 Review Follow-up Fixes (preserved)

| Finding | Severity | Fix |
|---------|----------|-----|
| `landing-page-builder` required before runtime | BLOCKER | Removed from active `REQUIRED_STEPS` in Phase 1 |
| Canonical store nondeterministic persistence | WARNING | Cache mutates only after successful `update_option()` or verified equal full post-state entry |
| Unlock/relock nondeterministic persistence | WARNING | Post-state option equality checks |
| Premature `landing_page_builder` alias pollutes state | WARNING | Unknown-step reject before status writes |
| Stale unlock marker bypasses lock | BLOCKER | `has_unlock_marker()` vs effective `is_unlocked()` |
| Completion flag SoT | WARNING | `State_Manager::has_completion_flag()` only |

## Phase 2 PR2 Review Blocker Fixes

| Finding | Severity | Fix |
|---------|----------|-----|
| Live backend can publish Ads indexable before Phase 3 noindex | BLOCKER | Safer smaller fix: keep `Step_Landing_Page_Builder` implemented but remove `landing-page-builder` from `REQUIRED_STEPS` + `DISPATCHABLE_STEPS`. Dispatch case + alias retained for Phase 3 atomic activation (task 3.7). Existing 7-step UI completion unchanged. |
| UI/required mismatch (7-step UI vs 8th required) | BLOCKER | Same deactivation; completion stays 7 required steps until UI can send payload/`skip_all`. |
| Identity mismatch id+key | BLOCKER | `match_existing_landing()` rejects stale cross-pair when both non-empty id and landing_key disagree; order id → key → slug; slug collisions reject other pages (landing or not) when id already matched. |
| Keyword AI failure publishes placeholders | BLOCKER | `generate_section_copy(..., require_ai=true)` returns `WP_Error` on AI/decode/review/empty-validation failure for hero/seo-content. Updates may preserve existing valid page_sections instead of placeholders. Logs include landing_key/slug/layout. |
| Multi-landing partial failure diverges state | HIGH | On failure after N-1 successes, `persist_partial_landing_progress()` saves `landing_pages` + canonical summary first, logs counts/post IDs, then marks step failed (logs if status write fails). No delete of updated existing landings. |
| Persistence checks | HIGH | `persist_wizard_state` / `mark_step_status` re-read post-state; meta safety-net verifies template+type; canonical `replace`/`set_if_empty` failures logged and fall back when possible. Critical full-run state/status failures return `WP_Error`. |
| Reviewer/JSON observability + keyword priors | HIGH | Reviewer throwables and invalid JSON-on-success logged with context. Reusable/canonical paths use `filter_neutral_priors()` so keyword sections do not pollute neutral generation/review. |
| `generate-pages` can publish/assign residual landings | BLOCKER | Fail-closed: selected slugs resolving to `rms_landing_type` `seo`/`ads` return `WP_Error` (`rms_wizard_page_slug_is_landing`) before cleanup/update; loop + `assign_reading_pages()` refuse landing Home/Blog assignment; deletion guard kept. |
| Partial recovery misses thrown exceptions | WARNING | Each `build_one_landing()` call wrapped in try/catch; `\Throwable` → contextual `WP_Error` then existing `persist_partial_landing_progress()`. |
| `try_preserve_existing_landing()` ignores update result | WARNING | Capture `wp_update_post(..., true)`; on `WP_Error`/invalid result or meta failure log and return `WP_Error` (no false preserved success). |
| `landing_pages_equivalent()` too narrow | WARNING | Normalize/compare id, landing_key, title, slug, landing_type, menu_eligible, primary_keyword, order-insensitive subkeywords, generated_at, preserved — reject stale/partial state as “saved”. |
| `build_page()` throwable after create/update bypasses recovery | WARNING | Local try/catch around `build_page()` → contextual `WP_Error` (`landing_key`/`slug`/`title`, recovered `post_id` when known); outer loop still runs `persist_partial_landing_progress()` for prior successes. |
| Readability/size (shared helper extraction) | WARNING | **Deliberate duplication retained** with Home Builder. See Known limitations for named helper families + extraction plan after PR3/before archive. |

## Files Changed (Phase 2 batch + review fixes)

| File | Action | What Was Done |
|------|--------|---------------|
| `inc/wizard/class-ai-content-harness.php` | Modified | Distinct `PAGE_LANDING` Layer 2; keyword inject only hero/seo-content |
| `inc/wizard/class-step-home-page-builder.php` | Modified | After success, `set_if_empty` reusable prepared rows |
| `inc/wizard/class-step-landing-page-builder.php` | Created + hardened | Full backend + PR2 safety: identity, keyword fail-closed, partial persist, persistence checks, observability, neutral priors, richer state equivalence, local `build_page()` exception boundary |
| `inc/wizard/class-content-builder.php` | Modified | Whitelist-only `meta_input` |
| `inc/wizard/class-step-generate-pages.php` | Modified | Landing deletion guard + select/update/reading-assignment exclusion (fail-closed) |
| `inc/wizard/class-wizard-unlock-controller.php` | Modified | `CONTROLLED_UNLOCK_ENABLED = true` |
| `inc/wizard/class-step-controller.php` | Modified | PR2: required/dispatch remain 7 steps; dispatch case + alias kept dormant with Phase 3 activation comments |
| `openspec/changes/wizard-landing-page-builder/tasks.md` | Modified | Phase 2 complete + compatibility notes + task 3.7 activation |
| `openspec/changes/wizard-landing-page-builder/apply-progress.md` | Modified | This cumulative progress note + helper-duplication known limitations |

## Intentionally not changed (Phase 3+)

- Menu append/remove / Menu Setup landing merge
- Yoast title/metadesc/noindex + `wp_robots` + ads sitemap
- Admin TS/SCSS landing UI
- `pages/landing-page.php` flexible loop / breadcrumb
- Shared Home/Landing section-assembly extraction (documented follow-up)
- Local docs: `Wizard ai harness prompt guide.md`, `wizard-prd.html`
- No commit/push

## Design notes (Phase 2 + PR2 safety)

- Landing **runtime code** is present; **activation** is Phase 3 (required + dispatchable + UI + noindex/menu).
- Controlled unlock enabled only because 2.6 deletion guard shipped in the same backend slice.
- Ads noindex/menu final-state sync deferred to Phase 3 (by design; PR2 cannot go live without it).
- Keyword scope strict: only `hero` / `seo-content` receive keywords; overrides stay neutral (`PAGE_HOME`).
- Canonical first-write only; overrides never write store; replace requires `replace_canonical` + confirm flag.
- Bootstrap failure returns actionable `WP_Error` (`rms_wizard_canonical_bootstrap_failed`).

## Known limitations

1. Step is not callable via REST until Phase 3 activation — intentional PR2 safety.
2. **Deliberate Home Builder / Landing Builder helper duplication** (readability debt, not a runtime bug). Duplicated helper families today:
   - Section assembly / section row preparation (`section_data`, service row contracts, image fallbacks orchestration)
   - Placeholder copy builders (`placeholder_copy`, `placeholder_repeater_rows`, `placeholder_field_value`)
   - Item-count defaults / clamp (`item_count` and the shared max section item limit **`12`**)
   - Reviewer/config helpers (`reviewer()`, `is_review_enabled()`, review wiring around AI decode)
   - Text/HTML/JSON helpers (`text`, `html`, `section_value`, `decode_json_content`, `truthy`)
   - Completion helper (`maybe_mark_completed` against `Step_Controller::get_required_steps()`)
   - **Follow-up plan**: extract a shared section-assembly / content-helper collaborator **after PR3 lands or before archive** — not in this PR2 reliability patch. Also extract the repeated max item limit `12` to a named constant during that cleanup.
3. No automated behavioral tests / no test runner (`strict_tdd: false`). Reliability is verified via `php -l` + code review only — do not claim automated behavior coverage.
4. Preserve-on-keyword-failure path updates title/slug/meta only; does not rewrite sections.
5. Partial-failure recovery does not roll back newly created posts (prefers state persistence over destructive delete). Thrown exceptions (including local `build_page()` catch) convert to `WP_Error` and use the same partial persistence path as returned errors. Recovered orphan post IDs are logged/error-data only — not auto-added to successful `landing_pages`.

## Verification

```
php -l inc/wizard/class-step-landing-page-builder.php
```

(Plus prior Phase 2 `php -l` on generate-pages/controller/harness/content-builder/unlock/home-builder.)

Mode: **Standard** (`openspec/config.yaml` → `strict_tdd: false`, no test runner). No automated behavior tests exist for these reliability paths.

## Workload / PR Boundary

- Mode: chained PR slice (feature-branch-chain)
- Current work unit: PR2 Backend Landing + remaining reliability follow-up (equivalence + build_page boundary)
- Boundary: Phase 2 tasks 2.1–2.6 hardened for standalone PR safety; no Phase 3 UI/menu/template
- Estimated review budget impact: small incremental reliability patch on PR2 surface

## Status

Phase 1 + Phase 2 complete with PR2 remaining reliability warnings addressed (10/19 tasks incl. new 3.7). Ready for PR2 re-review / next batch Phase 3 UI/menu/SEO/template + activation.
