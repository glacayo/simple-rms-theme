# Proposal: Wizard Landing Page Builder

## Intent

The wizard ends at one Home page. Owners need N landing pages (SEO and Ads) without duplicating reusable copy or leaking keywords into shared sections. This adds an 8th step: keyword-governed landings plus a canonical reusable store.

## Scope

### In Scope
- New `landing-page-builder` step generating N landings from `pages/landing-page.php`.
- Per-landing keyword + 0–10 subkeywords; only Hero and SEO Content consume them.
- Canonical store `rms_wizard_canonical_sections` for neutral reusable sections (About, Services, FAQ, Mission, Vision, Why Choose Us, Testimonials…); first-write, explicit replace only.
- Per-landing `override_canonical` regeneration never writes canonical.
- `rms_landing_type` meta (`seo`|`ads`): SEO indexable/menu-eligible, Ads noindex/orphan.
- Per-landing Yoast title/description when active.
- Non-destructive unlock for completed wizard sites.

### Out of Scope
- New ACF layouts; changes to `Content_Builder`, provider registry, or other steps' APIs.
- Bulk regenerate action; new REST routes (generic `/steps/{step}/run` reused).

## Capabilities

### New Capabilities
- `wizard-landing-page-builder`: 8th step orchestrating N landings (keyword governance, landing_type, menu-eligibility, Yoast meta, noindex).
- `wizard-canonical-sections`: first-write reusable store with explicit replace and per-landing override.
- `wizard-controlled-unlock`: capability-gated, reversible re-open of completed sites.

### Modified Capabilities
- `wizard-ai-content-harness`: add `PAGE_LANDING` Layer 2; inject `{{primary_keyword}}`/`{{subkeywords}}` only for `hero`/`seo-content`.
- `wizard-home-page-builder`: on success, first-write reusable payloads to the canonical store (skip hero/seo-content).

## Approach

Approach A. Add `landing-page-builder` last. A `Canonical_Section_Store` (dedicated option) holds reusable payloads via `set_if_empty()`/`replace()`. Reusable rows copy canonical or first-write; keyword rows call the harness with keywords; a layout allowlist keeps other sections neutral, including overrides. Pages use `Content_Builder::build_page()` with `_wp_page_template` in `meta_input`. Ads landings get `rms_landing_type=ads`, `meta-robots-noindex`, and a scoped `wp_robots` filter; `Step_Menu_Setup` filters `menu_eligible`.

## Affected Areas

| Area | Impact |
|---|---|
| `class-step-landing-page-builder.php`, `class-canonical-section-store.php`, `class-wizard-unlock-controller.php` | New classes |
| `class-ai-content-harness.php` | Mod: PAGE_LANDING + keywords |
| `class-step-home-page-builder.php` | Mod: first-write canonical |
| `class-step-controller.php`, `class-state-manager.php`, `class-step-generate-pages.php`, `wizard-init.php` | Mod: wiring, state, UI, unlock |
| `wizard.ts`, `wizard.scss` | Mod: collector, styles |

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Completion-gate drift | High | Update both; assert lists match |
| Canonical clobber on Home re-run | Med | `set_if_empty()` only |
| Ads landing left indexable | Med | Yoast meta + `wp_robots`; assert |
| Keyword leak into reusable sections | Med | Layout allowlist |
| Override AI failure loses copy | Low | Fall back to canonical |

## Rollback Plan

Revert the chained PRs. Delete option `rms_wizard_canonical_sections` and `state.landing_pages`; no schema migration, so existing steps are unaffected. Unlock is a transient override: re-lock restores read-only state without destroying `rms_wizard_completed`.

## Dependencies

- ACF Pro `hero`/`seo-content` layouts and `pages/landing-page.php` (exist).
- Yoast optional (`is_plugin_active(...)` OR `WPSEO_VERSION`); skip-and-log if absent.

## Success Criteria

- [ ] Admin generates ≥1 SEO and ≥1 Ads landing; each renders `pages/landing-page.php` on first load.
- [ ] Hero/SEO Content use the landing keyword; reusable sections stay neutral.
- [ ] Canonical is first-write; re-runs never overwrite it.
- [ ] Ads landings `noindex` and unlisted; SEO landings indexable/menu-eligible.
- [ ] Completed sites re-open via controlled unlock, no destructive reset.
