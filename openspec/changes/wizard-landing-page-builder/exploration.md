# Exploration: Wizard Landing Page Builder

## Current State

The wizard (`inc/wizard/wizard-init.php`, `inc/wizard/class-step-controller.php`, `inc/wizard/class-rest-controller.php`) ships a 7-step flow that ends with a Home Page Builder step:

1. `dependencies` — TGMPA plugin check/install
2. `acf-import` — Import ACF JSON
3. `client-data` — Save Theme Settings via `Client_Data_Fields`
4. `generate-pages` — Create the wizard-selected pages and assign Home/Blog reading options
5. `menu-setup` — Build primary/mobile menus from generated pages
6. `ia-generation` — Configure AI provider, model, and encrypted credentials
7. `home-page-builder` — Build selected ACF flexible-content sections on the Home page

The Home Page Builder (`inc/wizard/class-step-home-page-builder.php`) is the architectural reference for this change. It already:
- Pulls `state.client_data` and validates required harness context via `AI_Content_Harness::validate_required_context()`.
- Composes 3-layer prompts per section: `Layer 1` (global editorial system prompt) + `Layer 2(PAGE_HOME)` → `system`; `Layer 3(layout, item_count, client_context)` → `user`. See `class-step-home-page-builder.php:230-253`.
- Calls the configured AI provider through `AI_Provider_Registry::make_provider( $provider )->generate( $model, $prompt, $context, $system )`.
- Passes decoded payloads through `AI_Content_Reviewer::review()` with `prior_section_payloads` for cross-section repetition detection (`class-step-home-page-builder.php:267-302`).
- Re-uses `Content_Builder::build_page( [ 'id' => $home_page_id, 'section_only' => true, 'sections' => $prepared_sections ] )` to persist the Home page's `page_sections` ACF field (`class-content-builder.php:59-107`).
- Stores `selected_home_sections`, `home_section_rows`, and `home_sections` on the state option (`class-step-home-page-builder.php:115-119`).

The harness is already prepared for landing pages:
- `AI_Content_Harness::PAGE_LANDING` constant is defined (`class-ai-content-harness.php:19`).
- `get_layer2()` only validates `PAGE_HOME !== $page_type` and falls back to a warning; `PAGE_LANDING` would fall back today (`class-ai-content-harness.php:328-348`). A real `PAGE_LANDING` block (purpose, tone, section-ordering guidance) is a TODO and must be added.
- The harness guide (`Wizard ai harness prompt guide.md`, untracked) already lists `hero` and `seo-content` as the keyword-driven landing sections and enumerates the per-field editorial contracts that are now enforced in PHP constants (`AI_Content_Harness::EDITORIAL_RULES`).

A page template already exists: `pages/landing-page.php` (Template Name: "SEO Landing Page") hardcodes the section order hero → breadcrumb-slim → seo-content → vision-mission-v1 → badges → portfolio-v1 → seo-content → testimonials-v1 → seo-content. `header.php` already defers its assets when this template is in use. This template is the rendering surface the new builder must drive for v1; it is not used by the wizard today.

The PRD (`wizard-prd.html`, untracked) already states the user-facing requirement: "tantas landing pages como desee, sin límite" with the same section dynamics as the Home (R-11). The keyword governance model requested by the user (keyword only on Hero + SEO Content, canonical reusable for everything else, per-landing override allowed) is a new product layer that does not exist yet.

State shape today (`class-state-manager.php:25-43`) has `client_data`, `generated_pages`, `home_page_slug`, `blog_page_slug`, `ai_config`, `menu_config`, `selected_home_sections`, `home_sections`. There is no concept of canonical reusable section content, no landing pages registry, no keyword context, and no `landing_type` (seo vs ads).

The wizard TypeScript client (`src/ts/admin/wizard.ts`) is generic over the step list: it reads `state.step_status`, renders step panels from `data-wizard-step-panel`, builds payloads via `collectPayload(step)` (with per-step collectors for `generate-pages`, `menu-setup`, `ia-generation`, `home-page-builder`), and dispatches `POST /rms-wizard/v1/steps/{step}/run` with a JSON body. The only Home Builder–specific branch in TS is `collectHomePageBuilderPayload()` (referenced at `wizard.ts:500-502`); everything else is data-driven from server-rendered JSON inside `<script type="application/json">` blocks.

REST surface (`class-rest-controller.php`) already exposes generic `POST /steps/{step}/run` and `GET /state`, so new step slugs do not need new routes. The completion gate (`Step_Controller::REQUIRED_STEPS`) and `Step_Home_Page_Builder::maybe_mark_completed()` both hardcode the same 7-step list, so a new step requires updating both call sites in lockstep. Adding an 8th step on a site that already shows `rms_wizard_completed = true` must be handled via a controlled unlock path (admin notice + explicit "unlock wizard" action) rather than a destructive reset.

## Product Decisions (confirmed by user)

These decisions are now part of the contract for this change. They are not open questions.

1. **Canonical store location** — Canonical reusable section content lives in a dedicated option, `rms_wizard_canonical_sections`, NOT inside the wizard state. `State_Manager` does not load it on every read; `Canonical_Section_Store` lazy-loads it on demand. The wizard state only tracks a small `canonical_sections_summary` (which layouts have a canonical payload and the `generated_at` timestamp) so the Landing step can surface staleness without reading the full payloads.
2. **First-write canonical semantics** — Canonical reusable content is **first-write by default**. The Home Page Builder (and Landing Page Builder) can populate the canonical store when it is empty for a given layout. Re-runs **MUST NOT** overwrite canonical content automatically. Overwriting requires an explicit user action (a "publish as canonical" / "replace canonical" affordance — see Open Questions). This applies in both directions: a Home re-run does not silently rewrite canonical About copy, and a Landing run that produces a reusable payload only writes to canonical if the store is empty for that layout.
3. **Testimonials are canonical/reusable** — `testimonials-v1/v2/v3` are part of the reusable set and participate in the canonical store like every other non-hero/non-seo-content layout. They are NOT landing-keyword-specific.
4. **V1 override UX** — A per-section checkbox labeled `Regenerate only for this landing` (internal name `override_canonical`) is the only override mechanism in v1. When checked, the harness is called for that row on that landing and the result is written to the landing's `page_sections` only. The canonical store is never touched on the override path. There is no "regenerate all overridable sections" button in v1.
5. **Subkeywords range** — 0 to 10 subkeywords per landing. The UI accepts a free-text, comma- or newline-separated list; the server clamps the count to 10 and silently drops empties. Hero and SEO Content are the only sections that consume them. Default is 0 (none) when the field is empty.
6. **Yoast meta per landing** — The Landing Page Builder generates a per-landing meta title and description (sourced from the primary keyword + landing type intent) and writes them via the existing `Yoast_Meta_Writer` whenever Yoast is available/active. The capability check is `is_plugin_active( 'wordpress-seo/wordpress-seo.php' )` OR `defined( 'WPSEO_VERSION' )`. If neither is true, the meta generation is skipped with a notice logged to `rms_wizard_log` and the landing still completes successfully.
7. **Landing page template** — v1 uses `pages/landing-page.php` (Template Name: "SEO Landing Page") for every landing page, regardless of `landing_type`. The template already hardcodes the section order; both SEO and Ads landings render the same template with different content/metadata.
8. **Landing type** — Each landing carries a `landing_type` field with two values:
   - `seo` — indexable by default, menu-eligible, organic-intent metadata and content.
   - `ads` — `noindex` by default, NOT added to menu by default, orphan/campaign page, conversion-focused metadata and content.
9. **Menu integration** — SEO landings may be added to the menu (the Landing step exposes a `menu_eligible` flag, defaulting to `true` for SEO). Ads landings have `menu_eligible = false` by default and are never auto-added to a menu. The existing `Step_Menu_Setup` is the integration point; it filters by `menu_eligible`.
10. **Controlled unlock for completed wizard sites** — On sites where `rms_wizard_completed = true`, the wizard is read-only by default. A controlled unlock path (admin notice + an explicit "Unlock wizard for editing" action, gated by `manage_options`) re-opens the wizard without destroying state. No destructive reset.
11. **Page template at insert time** — `_wp_page_template = 'pages/landing-page.php'` is set inside `wp_insert_post`'s `meta_input` argument (or an equivalent insert-time mechanism such as `wp_update_post( [ 'ID' => $id, 'meta_input' => [ '_wp_page_template' => ... ] ] )` immediately after the page is created in the same request). Never via a `post_updated`/`save_post` hook. The first render must see the correct template.
12. **Keyword scope is strict** — Only `hero` and `seo-content` consume `primary_keyword` and `subkeywords`. Reusable sections, including landing-specific overrides, stay keyword-neutral. The harness never injects `{{primary_keyword}}` or `{{subkeywords}}` for any layout other than `hero` and `seo-content`. Overrides (`override_canonical = true`) are subject to the same rule: even when regenerating, the harness is called without keyword placeholders and `validate_fields()` continues to enforce the no-invention contract.
13. **Overrides never overwrite canonical** — The override path writes to the landing's `page_sections` only. The canonical store is read-only on the override path. If the AI returns an empty/invalid payload, the override falls back to the canonical copy (and logs the failure) so the landing never ends up with placeholders.
14. **Untracked local docs are preserved** — `Wizard ai harness prompt guide.md` and `wizard-prd.html` are untracked reference documents. They are not committed, not modified, and not part of the change artifacts.

## What Already Exists (Reusable)

| Component | File | Relevance |
|---|---|---|
| `Step_Controller::REQUIRED_STEPS` and dispatch switch | `inc/wizard/class-step-controller.php:17-25, 124-159` | Pattern for adding a new step: append slug, add `case`, optional aliases, append to `maybe_mark_completed()` in step service |
| `Step_Generate_Pages` page-list + Home/Blog role model | `inc/wizard/class-step-generate-pages.php` | Reuse the same `{slug, title}` model for landing list; reuse the destructive-confirmation pattern; landing roles must include `landing` distinct from `home`/`blog` |
| `Step_Home_Page_Builder` per-section AI generation loop | `inc/wizard/class-step-home-page-builder.php:41-125` | The architectural template for Landing Page Builder; same harness call, same reviewer pass, same `build_page( [ 'section_only' => true, 'sections' => ... ] )` save path |
| `AI_Content_Harness` fillable/blocked/text-repeater contracts | `inc/wizard/class-ai-content-harness.php:29-96` | Single source of truth for which fields are keyword-driven vs reusable. The reusable set is implicit: any layout that is NOT hero or seo-content. |
| `AI_Content_Harness::PAGE_LANDING` constant | `inc/wizard/class-ai-content-harness.php:19` | Exists but `get_layer2()` only returns the PAGE_HOME block. A `PAGE_LANDING` block must be authored. |
| `AI_Content_Reviewer` cross-section repetition pass | `inc/wizard/class-ai-content-reviewer.php:130-174` | Already accepts arbitrary `prior_sections`; reusable across landings if the builder passes prior landing + canonical payloads. |
| `Content_Builder::build_page( [ 'id', 'section_only', 'sections', 'seo' ] )` | `inc/wizard/class-content-builder.php:59-107` | Saves `page_sections` and Yoast meta in one call. Works for any `post_type=page` regardless of template. |
| `Yoast_Meta_Writer` | `inc/wizard/class-yoast-meta-writer.php` | Already wired in `build_page()` when `seo` is provided. Use for per-landing meta title/description when Yoast is active. |
| `pages/landing-page.php` (Template Name: SEO Landing Page) | `pages/landing-page.php` | The existing rendering target. v1 uses this for every landing regardless of `landing_type`. |
| ACF `hero` and `seo-content` layouts | `acf-json/group_rms_page_sections.json` (hero at L20-145, seo-content at L1582-1680) | Both layouts already exist with the exact fillable fields (`hero_title`, `hero_description`; `seo_headline`, `seo_subheadline`, `seo_text`). Keyword-driven sections are ready. |
| `state.home_sections` and `selected_home_sections` | `class-step-home-page-builder.php:115-119` | Proven shape for a per-page sections registry. Replicate as `state.landing_pages[].sections`. |
| Wizard TS step-list and per-step collectors | `src/ts/admin/wizard.ts:160-167, 472-505` | Add the new slug to `steps[]`; add a `landing-page-builder` branch in `collectPayload()`. |
| `<script type="application/json">` panels for static choice data | `inc/wizard/wizard-init.php:483-484, 615-617` | Pattern for shipping layout options / common-section shortcuts to TS without an extra REST round-trip. |
| Destructive-confirmation modal + checkbox pattern | `inc/wizard/wizard-init.php:549-561, 568-582` | Reuse for the landing-deletion warning (only "delete this landing" though, not bulk — landings are N independent pages, not a single set). |
| `data-wizard-add-section` / common-shortcut UX | `inc/wizard/wizard-init.php:486-535` | Same UI vocabulary as Home Page Builder: layout dropdown + item count + per-row override checkbox. |

## What Does Not Exist Yet

| Gap | Why it matters |
|---|---|
| Dedicated `rms_wizard_canonical_sections` option | The canonical store must be a separate option, not part of `state`. Without it, the wizard state row keeps growing and the 64KB `wp_options.option_value` LONGTEXT path becomes a real risk. |
| First-write canonical guard | The current Home Page Builder always overwrites `home_sections`. A guard that only writes to the canonical store when it is empty for a given layout does not exist; reruns would currently clobber canonical copy. |
| `landing_type` field on landings | The landing registry needs a `landing_type` (`seo`/`ads`) and a derived `menu_eligible` flag. Today the page list is `{slug, title}` only. |
| `noindex` enforcement for Ads landings | There is no filter or `meta-robots-noindex` write tied to the wizard today. The Ads path needs a `wp_robots` filter (and Yoast's `meta-robots-noindex` post meta for sites with Yoast) keyed on the landing's `_wp_page_template`. |
| Per-landing override flag for reusable sections | The Home Page Builder always treats the saved payload as final. Landings need a per-row `override_canonical` toggle that, when off, copies canonical content into the landing's `page_sections` without calling the AI. |
| Controlled unlock for completed wizard sites | Sites with `rms_wizard_completed = true` need a non-destructive way to re-open the wizard. The existing `RMS_WIZARD_FORCE` dev constant is not appropriate for production sites. |
| `meta_input` page-template assignment | The current `wp_insert_post` calls do not pass `_wp_page_template` in `meta_input`. The new step must either extend `Content_Builder::build_page()` to accept a template arg, or perform the `meta_input` write inline as part of the page creation step (preferred for BC). |
| Keyword context in harness Layer 3 | `Wizard ai harness prompt guide.md` (untracked) already documents `{{keyword}}` and `{{subkeywords}}` placeholders for PAGE_LANDING and PAGE_BLOG, but the PHP harness does not replace them. `get_layer3()` only injects `item_count` and `client_json`. |
| `get_layer2(PAGE_LANDING)` body | Today the method only branches on `PAGE_HOME !== $page_type` and warns. The landing context (purpose, tone, section-ordering guidance, and ads-vs-seo intent) is missing. |
| Landing page registry / list management | No UI to add, edit, remove, or duplicate landings after the first run. The step is a one-shot today. |
| `state.landing_pages[].meta` (per-landing Yoast + keyword set) | The `Yoast_Meta_Writer` exists, but the builder step does not yet drive it. The PRD requires per-landing meta title/description generation. |
| Reviewer cross-landing context | `AI_Content_Reviewer::review()` accepts `prior_sections`, but today the builder only feeds it Home sections from the same run. For landings, the prior context should include canonical reusable payloads so the reviewer can detect contradictions between, e.g., a landing-specific hero and the canonical about-us. |
| Landing "Reset to canonical" affordance | Required so the admin can drop an override and re-pick up canonical copy for a given row. (Regenerate-as-override is separate and is a per-row checkbox; "reset to canonical" is the reverse.) |

## Affected Areas

### Files that WILL change

| File | Why |
|---|---|
| `inc/wizard/class-ai-content-harness.php` | Add `get_layer2(PAGE_LANDING)` body (covers both SEO and Ads intent). Add `{{primary_keyword}}` and `{{subkeywords}}` replacement in `get_layer3()` gated on `string $page_type` (default `PAGE_HOME`) and on the layout being `hero` or `seo-content`. Expose `is_reusable_layout( $layout )` helper. Never inject keyword placeholders for reusable layouts, even when `override_canonical = true`. |
| `inc/wizard/class-state-manager.php` | Extend `defaults()` with `landing_pages` (array keyed by landing slug, each with `{ id, slug, title, landing_type, menu_eligible, primary_keyword, subkeywords[], section_rows[], sections[], meta, template_assigned, generated_at }`) and a small `canonical_sections_summary` (layout_key → `{ generated_at, source, has_payload }`). Do NOT add the full canonical payloads to the state option; that lives in `rms_wizard_canonical_sections`. |
| `inc/wizard/class-step-controller.php` | Append `landing-page-builder` to `REQUIRED_STEPS`, add `case 'landing-page-builder':` to `dispatch_step()`, add alias `landing_page_builder` in `normalize_step()`. Update `complete()` to detect already-completed wizard sites and gate re-completion behind the controlled unlock path. |
| `inc/wizard/class-step-generate-pages.php` | When a landing slug is in the payload, set `_wp_page_template = 'pages/landing-page.php'` inside `wp_insert_post`'s `meta_input` so the first render is correct. Add a `landing` role distinct from `home`/`blog`. Landings do not receive Home/Blog reading settings. |
| `inc/wizard/class-step-home-page-builder.php` | After a successful Home run, copy the produced `home_sections` payloads into `Canonical_Section_Store` for the reusable layouts (skip `hero` and `seo-content`) **only when the store is empty for that layout**. On re-runs, the existing `home_sections` payload is regenerated, but the canonical store is read-only. `maybe_mark_completed()` must be updated to include `landing-page-builder`. |
| `inc/wizard/class-ai-content-reviewer.php` | No new diagnoses required. Optionally extend `critique_system_prompt` to mention "ignore keyword repetition in the Hero and SEO Content sections; flag keyword stuffing only if it appears elsewhere." |
| `inc/wizard/wizard-init.php` | Add `'landing-page-builder' => __( 'Landing Page Builder' )` to `$steps` and a description. Add `rms_wizard_render_landing_page_builder_form()` mirroring the Home form, plus a landing-list block above it. Add a `landing_type` selector (seo/ads) and a per-row `override_canonical` checkbox. Add the controlled-unlock admin notice + action handler (gated by `manage_options`). Register the new step in the per-step `if/elseif` chain. Wire `Step_Menu_Setup` to filter by `menu_eligible` so Ads landings are not auto-added. |
| `src/ts/admin/wizard.ts` | Append `{ slug: 'landing-page-builder', label: 'Landing Page Builder' }` to `steps[]`. Add a `landing-page-builder` branch in `collectPayload()`. Add `collectLandingPageBuilderPayload()` that emits `{ landings: [ { slug, title, landing_type, menu_eligible, primary_keyword, subkeywords[], sections: [ { layout, item_count, override_canonical } ] } ] }`. Add UI handlers for add-landing, remove-landing, landing-type toggle, per-landing keyword inputs (clamped to 10), and per-row `override_canonical` checkboxes. |
| `src/scss/admin/wizard.scss` | New BEM blocks: `rms-wizard-landing-list`, `rms-wizard-landing-card`, `rms-wizard-landing-keywords`, `rms-wizard-landing-type`, `rms-wizard-section-row__override-canonical`, `rms-wizard-controlled-unlock`. |
| `inc/wizard/wizard-init.php` (controlled unlock) | New admin notice + a nonce-protected `admin-post.php` action `rms_wizard_unlock` that flips a transient/option and re-renders the wizard. Capability check: `manage_options`. Reversible (the admin can re-lock). |

### New PHP classes (proposed)

| Class | File | Purpose |
|---|---|---|
| `Step_Landing_Page_Builder` | `inc/wizard/class-step-landing-page-builder.php` | Orchestrates one or more landing pages. Reuses `Content_Builder`, `AI_Content_Harness`, `AI_Content_Reviewer`, and a new `Canonical_Section_Store`. Sets `_wp_page_template` in `meta_input` at page-insert time. Writes per-landing Yoast meta when Yoast is active. Sets `meta-robots-noindex` for Ads landings. |
| `Canonical_Section_Store` | `inc/wizard/class-canonical-section-store.php` | Encapsulates the canonical reusable section store. Methods: `get( $layout )`, `set_if_empty( $layout, $payload, $source )`, `replace( $layout, $payload, $source )` (explicit user action), `has( $layout )`, `all()`, `summary()`. Backed by a dedicated option `rms_wizard_canonical_sections` (single key per site, small payload) — separate from the wizard state option. |
| `Landing_Keyword_Resolver` | (could live in `AI_Content_Harness` as a new public method `get_landing_keyword_context( $landing_state )`) | Returns the keyword context block that Layer 3 will inject for `hero` and `seo-content` on a given landing. Clamps subkeywords to 0-10. Not strictly required; can be a private method on the harness in v1. |
| `Landing_Page_Repository` (optional) | `inc/wizard/class-landing-page-repository.php` | Read/write helpers over `state.landing_pages` and the `pages/landing-page.php` template assignment. Can be inlined in `Step_Landing_Page_Builder` for v1; pull it out only if it grows. |
| `Wizard_Unlock_Controller` | `inc/wizard/class-wizard-unlock-controller.php` | Handles the controlled-unlock flow for completed wizard sites. Methods: `is_locked()`, `request_unlock()`, `lock_again()`. Capability-gated; reversible; no destructive state reset. |

### Files that MUST NOT change (untracked local docs)

- `Wizard ai harness prompt guide.md` — untracked reference, do not commit or modify.
- `wizard-prd.html` — untracked PRD, do not commit or modify.

### Files that should NOT change (BC / scope guards)

- `inc/wizard/class-content-builder.php` — `build_page()` already supports `section_only` + per-page sections + Yoast. Reuse. Page-template assignment is performed inline by `Step_Landing_Page_Builder` via `meta_input` on the `wp_insert_post`/`wp_update_post` call, not by extending `build_page()`.
- `inc/wizard/class-ai-provider-registry.php`, `inc/wizard/class-ai-provider.php` — provider interface is provider-neutral and the home builder already exercises it; no new interface change needed.
- `inc/wizard/class-flexible-content-layouts.php` — layout definitions are layout-keyed; no new layout needed for v1.
- `inc/wizard/class-step-menu-setup.php`, `inc/wizard/class-step-acf-import.php`, `inc/wizard/class-step-dependencies.php`, `inc/wizard/class-step-client-data.php` — out of scope; reuse as-is. `Step_Menu_Setup` may be filtered by `menu_eligible` but its public API does not change.

## Approaches

### Approach A — New step "Landing Page Builder" with dedicated canonical store (recommended)

Add `landing-page-builder` as the 8th wizard step, sitting after `home-page-builder`. Each landing page is a row in a new `state.landing_pages` registry with `landing_type` (`seo`|`ads`), `menu_eligible` (derived), `primary_keyword`, and `subkeywords[]` (0-10). A `Canonical_Section_Store` (backed by a dedicated `rms_wizard_canonical_sections` option) holds the reusable content with first-write semantics. The step's loop, for each landing:

1. For each row, classify it as `reusable` (all layouts except `hero` and `seo-content`) or `keyword` (`hero`, `seo-content`).
2. For `reusable` rows:
   - If the row has `override_canonical = false` AND the canonical store has a payload for the layout → copy it directly into the landing's `page_sections`, skip the AI call. If the AI later fails on an override row, fall back to the canonical copy and log.
   - If the row has `override_canonical = true` OR the canonical store is empty for the layout → call the harness as in the Home Page Builder, then `validate_fields()`. If the canonical store is empty, persist the resulting payload into it via `set_if_empty()` (first-write). If the canonical store already has a payload AND the row is `override_canonical = true`, the result is written to the landing's `page_sections` only and marked `landing_specific = true`. The canonical store is not modified.
3. For `keyword` rows → always call the harness with `Layer 2 = PAGE_LANDING` (which encodes the ads-vs-seo intent), and inject `{{primary_keyword}}` and `{{subkeywords}}` into `Layer 3`. Never canonicalize.
4. Build the page via `Content_Builder::build_page( [ 'id' => $post_id, 'section_only' => true, 'sections' => $prepared, 'seo' => $meta ] )` where `seo` is generated from the primary keyword and `landing_type`.
5. If Yoast is active (`is_plugin_active('wordpress-seo/wordpress-seo.php')` OR `defined('WPSEO_VERSION')`), write the meta title/description via `Yoast_Meta_Writer`. If not, log a notice and continue.
6. Set `_wp_page_template = 'pages/landing-page.php'` inside `wp_insert_post`'s `meta_input` for the created page (or via an equivalent insert-time `wp_update_post` call in the same request). The first render must see the correct template.
7. If `landing_type = ads`, write `meta-robots-noindex = 1` post meta and register a `wp_robots` filter scoped to the landing template that emits `noindex`. If `landing_type = seo`, do not set the noindex flag and let Yoast's defaults apply.
8. If `menu_eligible = true` (default for `seo`, false for `ads`), the page becomes visible to `Step_Menu_Setup`. `Step_Menu_Setup` filters out non-eligible landings.
9. Persist `state.landing_pages[ slug ]` and the updated `canonical_sections_summary`.

The harness needs two surgical changes: a real `get_layer2(PAGE_LANDING)` block, and `{{primary_keyword}}`/`{{subkeywords}}` placeholders honored in `get_layer3()` when the active page type is landing and the layout is `hero` or `seo-content`.

- Pros: Clear separation between canonical reusable content and landing-specific content. New step fits the existing 7-step pattern with no BC break. The harness already supports `PAGE_LANDING` as a constant; this just fills it in. First-write semantics are enforced by `set_if_empty()` in the store; the admin never silently loses canonical copy on a rerun. Dedicated `rms_wizard_canonical_sections` option keeps the wizard state option small (mitigates the 64KB LONGTEXT risk). The Home Page Builder also benefits: the existing `home_sections` payloads populate the canonical store on first run via `set_if_empty()`, so the very first landing that references `about-us` reuses what the Home already produced, while a Home rerun that changes the about copy does not silently clobber canonical.
- Cons: Adds an 8th step that runs after `home-page-builder`; the completion gate must include it. State grows, but only by summary metadata — canonical payloads are offloaded to their own option. SEO/Ads noindex enforcement adds a `wp_robots` filter (small) and Yoast post-meta writes (small). The Step_Controller's `REQUIRED_STEPS` list and `Step_Home_Page_Builder::maybe_mark_completed()` both need to grow.
- Effort: Medium-High

### Approach B — Fold landings into the existing `home-page-builder` step

Add a "landing tabs" UI inside the same step: one Home tab + N landing tabs. Single `state.home_sections` becomes `state.pages[].sections` (Home + landings). Reuses everything in place.

- Pros: No new step. No `REQUIRED_STEPS` change. Single review pass per run. Smaller surface area.
- Cons: Breaks the existing `selected_home_sections`, `home_sections`, and `home_page_id()` lookups. The Home tab's prior-section context would now include landing sections, which contaminates the existing cross-section repetition detection. The completion gate is satisfied by the Home tab, even when no landing exists. The UI becomes harder to resume — the admin cannot run "add another landing" without re-running the Home tab. The state shape diverges further from the existing v1 contract and would need a one-time migration in `State_Manager::defaults()`. Does not give landings a `landing_type`, a `menu_eligible` flag, or a per-landing template assignment without overloading the Home tab's data model. Controlled unlock becomes harder because re-opening the wizard for landing-only edits would also re-expose the Home tab.
- Effort: Medium

### Approach C — Single multi-page payload (no per-landing state, no canonical store)

The admin submits a list of landings in one big payload. The step generates N landings, stores their sections directly in `page_sections`, and forgets them. No canonical store; every landing gets its own AI-generated copy for every section.

- Pros: Smallest code. No canonical store. Keyword-only on Hero/SEO.
- Cons: Violates the user's explicit canonical-reusable requirement. Generates redundant AI content for About / Services / Mission / Testimonials / etc. across N landings, which is exactly what the PRD and the product intent say to avoid. The reviewer will flag repetition across landings with no way to consolidate. No `landing_type`, no menu-eligibility filtering, no Ads vs SEO split.
- Effort: Low

### Approach D — Post-meta "canonical" flag, no new step

Add a checkbox per row in the existing `home-page-builder` form: "this is the canonical version of this section." The first run sets canonical post-meta on a hidden "source of truth" page; later landings deep-link those sections at render time via a `get_template_part` override.

- Pros: No new step, no canonical store. Uses existing WP post-meta.
- Cons: Couples content reuse to template rendering, which means the canonical page must always be reachable and the landing template must dynamically load parts. This makes the canonical content harder to audit and impossible to version in state. The "override tied only to that landing" rule requires the landing to either inline its own copy or short-circuit at render — both options leak the wizard's design into template logic. Reviewer cross-section context is harder to wire because the canonical payload is not in the wizard state. First-write semantics are not enforceable: the admin can flip the canonical flag on a later run and silently overwrite.
- Effort: Medium (but in the wrong direction — pushes content reuse into templates instead of the wizard)

## Recommendation

**Approach A** — a new `landing-page-builder` step + a `Canonical_Section_Store` backed by the dedicated `rms_wizard_canonical_sections` option, with first-write semantics, per-landing `landing_type` (seo/ads), per-landing `menu_eligible`, per-row `override_canonical`, strict keyword scope, and controlled unlock for completed wizard sites. It is the only approach that satisfies all 14 product decisions without leaking keyword context into reusable sections and without coupling content reuse to template rendering.

Concretely:
- The new step is the 8th and last step. It reuses `Content_Builder`, `AI_Content_Harness`, `AI_Content_Reviewer`, and `Yoast_Meta_Writer` exactly as the Home Page Builder does.
- The `Canonical_Section_Store` is backed by `rms_wizard_canonical_sections` (a map `layout_key => [ 'payload' => [...], 'source' => 'home'|'landing', 'generated_at' => 'mysql' ]`). On the first `home-page-builder` run, after a successful save, the step calls `set_if_empty()` for every reusable payload (`! in_array( $layout, ['hero','seo-content'] )`). On the first `landing-page-builder` run for a given layout, if the store is empty, the AI result becomes the canonical entry via `set_if_empty()`; on subsequent runs, the landing writes its own copy only and the store is untouched. Replacements require an explicit `replace()` call from a user-confirmed action (see Open Questions).
- The override is per-row and per-landing. A landing can opt to regenerate a reusable section even when canonical content exists; the AI result is stored in the landing's `page_sections` only and is marked `landing_specific = true` in the row metadata, which signals "do NOT touch the canonical store." The harness `validate_fields()` keeps enforcing the no-invention guardrails so the override copy remains keyword-neutral and grounded in `client_data`. If the AI returns empty/invalid, the override falls back to the canonical copy.
- Hero and SEO Content are never canonical. They are landing-specific, keyword-driven, and always call the AI with the landing's primary keyword + subkeywords (0-10). The harness `get_layer3()` learns to replace `{{primary_keyword}}` and `{{subkeywords}}` placeholders when the page type is `PAGE_LANDING` and the layout is `hero` or `seo-content`. Reusable layouts, including override rows, are never given keyword placeholders. The `Wizard ai harness prompt guide.md` already documents these placeholders; this change moves them from doc-only to PHP-enforced.
- `get_layer2(PAGE_LANDING)` is filled in with the page-type block: landing purpose (single conversion goal), tone (specific, not generic), section-ordering guidance that aligns with the existing `pages/landing-page.php` template order, and ads-vs-seo intent branching.
- Per-landing meta title/description are generated with the primary keyword and `landing_type` and written via `Yoast_Meta_Writer` whenever Yoast is available/active. When Yoast is not active, the generation is skipped and logged.
- Generated landing pages get `_wp_page_template = 'pages/landing-page.php'` set inside `wp_insert_post`'s `meta_input` (or an equivalent insert-time `wp_update_post` in the same request). The first render is always correct.
- `landing_type = ads` writes `meta-robots-noindex = 1` post meta and registers a `wp_robots` filter scoped to the landing template. `landing_type = seo` does not set the noindex flag.
- `menu_eligible` is derived from `landing_type` (`seo = true`, `ads = false`) and is consumed by `Step_Menu_Setup`. SEO landings may be added; Ads landings are never auto-added.
- Completed wizard sites use a `Wizard_Unlock_Controller` for the controlled unlock path. No destructive reset.

This approach honors all 14 product decisions:
1. Keyword only on Hero and SEO Content — enforced by a layout allowlist inside the harness, with `{{primary_keyword}}`/`{{subkeywords}}` replacement gated on both page type and layout.
2. Reusable sections keyword-neutral — enforced by `AI_Content_Harness::validate_fields()` (no keyword field in fillable contracts for reusable layouts) and by never injecting keyword placeholders for those layouts, even on override.
3. Canonical-first / first-write / explicit replace — enforced by `Canonical_Section_Store::set_if_empty()` and `replace()`.
4. Override does not overwrite canonical — enforced because the override path writes only into the landing's `page_sections` and never into the store.
5. Testimonials are canonical/reusable — `testimonials-v1/v2/v3` participate in `set_if_empty()` like every other reusable layout.
6. SEO vs Ads — enforced by `landing_type` and the resulting noindex, menu, and meta behavior.
7. Yoast meta when available — capability check at the top of the Yoast write path; skip-and-log otherwise.
8. Controlled unlock — `Wizard_Unlock_Controller` with capability check and reversible state.
9. Page template at insert time — `meta_input` on `wp_insert_post` (or equivalent `wp_update_post` in the same request).

## Risks

- **Completion-gate drift**: `Step_Controller::REQUIRED_STEPS` and `Step_Home_Page_Builder::maybe_mark_completed()` both hardcode the same 7-step list. Adding an 8th step means updating both call sites in the same change. Mitigation: add a unit-style sanity check in `Step_Controller::complete()` that compares `REQUIRED_STEPS` against the keys present in `state.step_status` and returns a `WP_Error` if they diverge. Combined with the controlled unlock, the existing-wizard path is non-destructive.
- **Canonical content drift across Home re-runs**: If the admin re-runs the Home Page Builder and changes the canonical About copy, every landing that previously reused it will be stale. Because of first-write semantics, the rerun does NOT update the canonical store; the existing canonical copy stays. Mitigation: surface a "Reusable content changed since your last landing run" notice in the Landing step UI by comparing `state.landing_pages[].sections[ layout ].generated_at` against the current Home run's `home_sections[ layout ].generated_at` and the canonical store's `generated_at`. The admin can choose to explicitly `replace()` the canonical copy (or keep the old one). A future enhancement could add a "Reset to canonical" affordance per row.
- **Harness layer 2/3 changes affect Home Page Builder**: The Home Page Builder calls `get_layer2(PAGE_HOME)` and `get_layer3()`. If the `PAGE_LANDING` branch accidentally becomes the default for non-landing contexts, the Home will start using landing rules. Mitigation: keep the default branch in `get_layer2()` as the current `PAGE_HOME` block, and gate the keyword replacement in `get_layer3()` behind a third `string $page_type` argument (defaulting to `PAGE_HOME`) plus a layout allowlist (`hero`, `seo-content`). Update both call sites (`Step_Home_Page_Builder` and the new `Step_Landing_Page_Builder`) explicitly.
- **State option size**: `state.landing_pages` and `canonical_sections_summary` will grow with each landing. The full canonical payloads are offloaded to `rms_wizard_canonical_sections`, so the wizard state option only carries summary metadata. The MySQL `wp_options.option_value` LONGTEXT risk is mitigated. Mitigation: keep only the section list and last generated payload per landing in state; full review reports stay in `rms_wizard_log` (which is already capped in `class-logger.php` to last 20 entries for the UI).
- **Reviewer repetition flags for intentional keyword use**: The reviewer already flags `keyword_stuffing`. Hero and SEO Content intentionally use the primary keyword. If the reviewer over-flags them, the rewrite pass may dilute the keyword and force a fall-back to the original (preserving keyword). Mitigation: the fall-back path is the safety net today, but the right fix is to teach the reviewer that hero/seo-content layouts are allowed to repeat the primary keyword once or twice. This can be a small `Layer 3` extension in the harness rather than a reviewer change.
- **Page-template assignment race**: Setting `_wp_page_template` after the page is created means a render between insert and meta update shows the default template. Mitigation: pass `_wp_page_template` in `wp_insert_post`'s `meta_input` so the first render is correct, or perform an equivalent insert-time `wp_update_post` in the same request. The new step performs the `meta_input` write inline at insert time.
- **Override regressions**: If `override_canonical = true` on a reusable section and the harness returns an empty payload (AI failure), the landing loses the canonical copy and ends up with placeholders. Mitigation: if AI fails AND the canonical store has the layout, fall back to the canonical copy and log the failure. This keeps the landing functional even when the override attempt fails.
- **Cross-landing reviewer context**: Without feeding prior landing payloads into the reviewer, two landings that both regenerate the same reusable section can drift. Mitigation: pass the canonical payload as the prior-section context for any reusable section that is being overridden, so the reviewer can warn if the new copy contradicts the canonical version.
- **Ads landing indexability**: Forgetting to set `meta-robots-noindex = 1` or forgetting to register the `wp_robots` filter would leave an Ads landing indexable. Mitigation: write both as part of the same insert path (Yoast post meta + a `wp_robots` filter scoped to `_wp_page_template = pages/landing-page.php` and `landing_type = ads`). Add an explicit assertion in `Step_Landing_Page_Builder::finalize_landing()` that reads back the post meta and refuses to mark the landing complete if the post meta is missing.
- **SEO landing accidentally noindexed**: A misapplied `wp_robots` filter could suppress SEO landings. Mitigation: scope the filter on BOTH `_wp_page_template` AND the landing's stored `landing_type` post meta. Do not use a single-key check.
- **Ads landing in menu**: Forgetting to filter `Step_Menu_Setup` by `menu_eligible` would add Ads landings to the menu. Mitigation: add a single `array_filter( $pages, fn( $p ) => $p['menu_eligible'] ?? true )` at the top of `Step_Menu_Setup::collect_pages()`. Add a unit-style assertion that no landing with `landing_type = ads` appears in the menu output.
- **Controlled unlock abuse**: A controlled unlock that flips a flag without an audit trail or a re-lock path leaves wizard re-runs running unsupervised. Mitigation: `Wizard_Unlock_Controller` writes a `rms_wizard_unlocked_at` timestamp and `rms_wizard_unlocked_by` user ID, exposes a re-lock action, and only flips the lock on a `manage_options` capability check + a nonce. The original `rms_wizard_completed` is not destroyed; the unlock is a transient override.
- **TS payload shape growth**: `collectLandingPageBuilderPayload()` will be the most complex collector in `wizard.ts`. The existing `collectHomePageBuilderPayload()` is already non-trivial. Mitigation: extract a shared `collectSectionRows(form, scope)` helper that both collectors reuse, and keep the keyword + override + landing_type fields on a single row template.
- **Resource budget**: 8 steps push the wizard close to the existing 7-step review budget (400 lines per PR). The change is naturally chained: PR 1 = `Canonical_Section_Store` (dedicated option) + state summary + harness Layer 2/3 changes; PR 2 = `Step_Landing_Page_Builder` + step controller wiring + controlled unlock; PR 3 = TS + SCSS + completion-gate update. Per `openspec/config.yaml` chained-pr strategy is `force-chained`.

## Open Questions

None blocking proposal. The five previously open questions are now resolved by the confirmed defaults below and are promoted into the change contract alongside the existing 14 product decisions.

### Confirmed defaults (formerly open questions)

1. **Canonical overwrite affordance** — Confirmed: explicit button/action in the builder where the section is visible (per-row "Replace canonical" button, gated by a confirmation modal and a nonce). No automatic replace. The button is available wherever the canonical store is read for that layout (Home Page Builder + Landing Page Builder). It calls `Canonical_Section_Store::replace( $layout, $payload, $source )` only after the user confirms.
2. **Controlled unlock UX** — Confirmed: Setup Wizard notice + an explicit user action ("Unlock wizard for editing"), gated by `manage_options` and a nonce. No destructive reset of `rms_wizard_completed`. The unlock writes `rms_wizard_unlocked_at` and `rms_wizard_unlocked_by`, is reversible via a re-lock action, and may be paired with a `wp rms wizard unlock` WP-CLI command for ops.
3. **Subkeywords default** — Confirmed: empty subkeywords field means `0` subkeywords. The server clamps to the 0-10 range and silently drops empties; the harness is never given invented default subkeywords. The UI does not seed a starting number when the field is left empty.
4. **Ads `noindex` belt-and-suspenders** — Confirmed: double protection. (a) Write `meta-robots-noindex = 1` Yoast post meta whenever Yoast is available/active (`is_plugin_active('wordpress-seo/wordpress-seo.php')` OR `defined('WPSEO_VERSION')`). (b) Register a `wp_robots` filter scoped to the Ads landing pages (filter on BOTH `_wp_page_template = pages/landing-page.php` AND the stored `landing_type` post meta = `ads`). The post meta is the source of truth; the filter is read-only on it. `Step_Landing_Page_Builder::finalize_landing()` reads the post meta back and refuses to mark the landing complete if the noindex post meta is missing.
5. **Ads `landing_type` persistence** — Confirmed: custom post meta `rms_landing_type` written at insert time (inside `wp_insert_post`'s `meta_input` or an equivalent insert-time `wp_update_post` in the same request). It is decoupled from any plugin, survives wizard state resets, and is the key the `wp_robots` filter reads.

## Ready for Proposal

**Yes** — the 14 product decisions plus the 5 confirmed defaults are now part of the exploration (19 contract items total), the Open Questions list has no remaining blockers, and the recommended approach (Approach A) is concrete enough to write a proposal against.

The orchestrator should proceed to `sdd-propose` for change `wizard-landing-page-builder`. The product decisions in §"Product Decisions" and the confirmed defaults in §"Open Questions" are part of the change contract and are not open for renegotiation in the proposal.
