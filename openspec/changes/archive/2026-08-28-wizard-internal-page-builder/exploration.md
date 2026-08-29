## Exploration: wizard-internal-page-builder

### Current State

The wizard is an 8-step flow (`Step_Controller::REQUIRED_STEPS`): dependencies → acf-import → client-data → generate-pages → menu-setup → ia-generation → home-page-builder → landing-page-builder.

`Step_Generate_Pages` can create shells for Home, About, Services, Blog, Contact, Projects, and Testimonials. It does **not** assign `_wp_page_template`, does **not** write `page_sections`, and overwrites `post_content` with a short AI/fallback HTML body. Unselected pages are hard-deleted after `confirm_cleanup` (SEO/Ads landings are excluded). Home/Blog roles set `page_on_front` / `page_for_posts`. Result: internal pages currently render through default `page.php` (title + `the_content()`), not the dedicated `pages/*.php` templates.

Only two builders populate ACF flexible content:

- **Home** (`class-step-home-page-builder.php`, ~447 lines): one-shot `run()`, always regenerates selected sections, first-writes reusable rows into `Canonical_Section_Store`, keyword SEO targeting only for `hero` / `seo-content`.
- **Landing** (`class-step-landing-page-builder.php`, ~2294 lines + `Landing_Run_Orchestrator` ~1042 lines): resumable start/process, per-item unchanged skip, `override_canonical` / replace-map, template + `rms_landing_type` via `meta_input`, Yoast/noindex, menu reconcile. `pages/landing-page.php` already loops `page_sections`.

Shared kernel already exists and must be reused, not cloned: `Content_Builder::build_page()` (`section_only`, `page_sections`, Yoast, template whitelist), `AI_Content_Harness` (Layer 1–3, fillable/blocked, reviewer), `Canonical_Section_Store` (`set_if_empty` / `replace`, option `rms_wizard_canonical_sections`), `Wizard_Mutation_Fence` on every mutating `execute_step()`, controlled unlock.

Harness page-type constants exist (`PAGE_HOME`, `PAGE_ABOUT`, `PAGE_SERVICE`, `PAGE_LANDING`, `PAGE_BLOG`, `PAGE_CONTACT`). `get_layer2()` implements **only** `PAGE_HOME` and `PAGE_LANDING`. All other types log a warning and fall back to Home. There is **no** `PAGE_PROJECTS` or `PAGE_TESTIMONIALS`.

Internal templates today are **hardcoded `get_template_part` sequences**, not flexible loops. Several parts are static demo HTML (`templates/services-page.php`, `templates/blog-listing.php`). `pages/testimonials.php` is a 3-line stub without a PHP opener. `header.php` loads internal-page CSS/JS only via `is_page_template(...)`. WordPress `page_for_posts` ignores the Blog page template and uses `index.php` (no `home.php`).

Blog scope for this change is confirmed: configure/build the posts **index/archive** only. Individual post generation is out of scope.

**Overlap with active OpenSpec changes** (folders still live, not archived):

| Change | Overlap | Conflict |
|--------|---------|----------|
| `wizard-setup` | Wizard module, state, REST, TGMPA/Yoast | Do not re-litigate foundation |
| `wizard-user-friendly-content-flow` | Generate Pages shells, menus, Home builder | Generate Pages still writes body HTML only; this change must not turn it into six builders |
| `wizard-ai-content-harness` | PAGE_* constants, Layer 2 fallback contract | Filling PAGE_ABOUT/SERVICE/CONTACT/BLOG Layer 2 is additive; do not break HOME fallback |
| `wizard-landing-page-builder` | Canonical store, mutation fence, resumable run, menu/Yoast, completion gate | Landing class must not be copied. 9th required step needs unlock-aware completion. Landing verification task 4.1 is still open |

Session constraint: `openspec/config.yaml` keeps `chained_pr_strategy: force-chained` and `review_budget_lines: 400`. That value is **not** one of the SDD four-value `delivery_strategy` domain (`ask-on-risk` / `auto-chain` / `single-pr` / `exception-ok`). Later phases must **not** map or rewrite it. Plan chained 400-line slices.

### Affected Areas

- `inc/wizard/class-step-generate-pages.php` — shells exist; no templates/sections; destructive cleanup; Blog reading assignment
- `inc/wizard/class-step-home-page-builder.php` — generation/review/canonical first-write reference; do not duplicate
- `inc/wizard/class-step-landing-page-builder.php` — resumable/canonical/override/Yoast/template pattern; extract, do not clone (~2294 lines)
- `inc/wizard/class-landing-run-orchestrator.php` — resumable lease/plan; generalize or wrap, do not copy
- `inc/wizard/class-canonical-section-store.php` — first-write reusable payloads; internal pages must copy vs replace explicitly
- `inc/wizard/class-ai-content-harness.php` — unimplemented Layer 2 for About/Services/Contact/Blog; no Projects/Testimonials constants
- `inc/wizard/class-content-builder.php` — already supports section-only + template whitelist; reuse
- `inc/wizard/class-step-controller.php` — REQUIRED_STEPS / DISPATCHABLE_STEPS / landing start-process fence exception
- `inc/wizard/class-state-manager.php` — no `internal_pages` / run state today
- `inc/wizard/class-step-menu-setup.php` — already pools `generated_pages`; internal builder should not rebuild menus
- `inc/wizard/wizard-init.php`, `src/ts/admin/wizard.ts` — 8-step UI; landing collector/resume helpers
- `pages/about-us.php`, `pages/services.php`, `pages/contact-us.php`, `pages/projects.php`, `pages/testimonials.php`, `pages/blog.php` — hardcoded (Testimonials broken)
- `page.php`, `index.php` — actual current render surfaces for untemplated pages and `page_for_posts`
- `header.php` — assets gated on page templates
- `templates/services-page.php`, `templates/blog-listing.php`, `templates/contact-map.php` — not harness layouts (`contact-map` / `services-page` missing from FILLABLE)
- `scripts/test-landing-run-orchestrator.php`, `tests/wizard-mutation-fence-harness.php`, `tests/wizard-home-seo-targeting-harness.php` — custom harness pattern

### Approaches

1. **Blueprint registry + one Internal Page Builder step** — Add `internal-page-builder` as a 9th step. One PHP registry maps page type → template, default layouts, harness `PAGE_*`, canonical vs generate policy. One orchestrator builds only the generated pages that exist in `state.generated_pages`. Convert matching `pages/*.php` to the landing flexible-loop pattern and assign `_wp_page_template` at save time.
   - Pros: Matches the product goal (no six builders); reuses Content_Builder / harness / canonical / fence; one completion-gate change; 400-line slices are possible if the landing class is not copied.
   - Cons: Requires template conversion; Services/Blog/Testimonials current files are not ACF-driven; 9th step on completed sites needs unlock.
   - Effort: High (but sliceable)

2. **Six duplicated builders** — About Builder, Services Builder, etc., each cloned from Home or Landing.
   - Pros: Isolated diffs per page type.
   - Cons: Explicitly rejected by product intent; ~2k-line landing clone would blow the 400-line budget immediately; six completion/UI/fence special cases.
   - Effort: High (waste)

3. **Fold into Generate Pages or Home** — Extend `generate-pages` to also fill sections, or add internal tabs to Home.
   - Pros: No 9th step.
   - Cons: Generate Pages is already destructive and AI-timeout heavy; Home SEO targeting and canonical first-write would mix with internal page types; resume/overwrite semantics collide with landing’s start/process model.
   - Effort: Medium (wrong direction)

### Recommendation

**Approach 1**, with these boundaries (proposal must treat unmarked items as open questions, not silent rules):

**Blueprint registry (recommended defaults, not implemented):**

| Type | Shell slug | Template | Default layouts | Harness | Canonical policy |
|------|------------|----------|-----------------|---------|------------------|
| About | `about` | `pages/about-us.php` | `about-us`, `vision-mission-v2` | `PAGE_ABOUT` | Copy canonical if present; first-write only when empty |
| Services | `services` | `pages/services.php` | Prefer existing `services-v1`/`v2`/`v3` + `cta-v2` — **not** static `services-page` unless that template is made ACF-driven | `PAGE_SERVICE` | Same |
| Contact | `contact` | `pages/contact-us.php` | `contact-info` (+ map only if a fillable/factual path is defined) | `PAGE_CONTACT` | `contact-info` may be page-specific; do not invent map data |
| Projects | `projects` | `pages/projects.php` | `gallery-grid` | No PAGE_* today | Gallery is fully blocked; builder can assign template + empty/factual row, not invent projects |
| Testimonials | `testimonials` | `pages/testimonials.php` (currently broken) | `testimonials-v1` (or v2/v3) | No PAGE_* today | Headlines fillable; items blocked (no-invention) |
| Blog index | `blog` + `page_for_posts` | **Not** `pages/blog.php` for the posts index | Index chrome only (`blog-v1` intro and/or `index.php`/`home.php` loop) | `PAGE_BLOG` Layer 2 for chrome only | Do not generate posts; do not pretend `pages/blog.php` renders `page_for_posts` |

**Reuse vs extract:** Extract a small shared section-assembly helper used by Home/Landing/Internal (landing tasks already deferred this). Do **not** copy `Step_Landing_Page_Builder`. Internal run may wrap/generalize `Landing_Run_Orchestrator` only if the plan item model is parameterized; otherwise a thinner per-page-type runner under the existing mutation fence is safer. Landing `start`/`process` skipping the legacy UI lock must not be cloned blindly.

**Canonical / generation / review:** Reuse harness + reviewer. Fill Layer 2 for About/Services/Contact/Blog. Internal runs copy canonical reusable rows; `override_canonical` writes the page only; `replace()` stays explicit. Keyword layouts stay Home/Landing-only unless a later product rule says otherwise (Blog keywords in old harness notes are **not** confirmed for this change).

**Resume / fence:** Long AI loops need bounded requests. Prefer extending the proven start/process + public run overlay, or keep one `execute_step` under the global fence with per-page progress in state. Never release the fence before `State_Manager::release_lock()`.

**Idempotency / user edits:** Generate Pages already overwrites body copy. Home always overwrites `page_sections`. Landing skips unchanged items. Internal builder **must not** silently clobber post-wizard ACF edits; exact overwrite/confirm rule is unresolved.

**Menu / Yoast:** Menus already include generated internal pages. This step should not rebuild menus. Yoast for internal pages can reuse `Yoast_Meta_Writer` when a per-page SEO payload is in scope; Ads/noindex rules are landing-only.

**Admin UX:** One step, cards for generated internal types only (skip missing shells). Persist `state.internal_pages` + compact run summary. Skip-all allowed so completed 8-step sites can finish a 9th required step without generating.

**Testing:** Follow custom harnesses, not a missing PHPUnit runner. Extend `scripts/test-landing-run-orchestrator.php` for orchestrated run/fence proofs; add a source-contract harness like `tests/wizard-mutation-fence-harness.php` for blueprint/template assignment; keep `php -l` + `tsc --noEmit`.

**Forced-chain / 400-line slices (planning only):**

1. About template → flexible `page_sections` loop + template assignment proof (no new step UI).
2. Blueprint registry + state shape (no landing clone).
3. Internal builder backend for About only, copy-canonical path.
4. Remaining page-type blueprints + template conversions (Services/Contact/Projects separately).
5. Testimonials template repair (only if confirmed in scope).
6. Blog index chrome (`index.php`/`home.php`), not post generation.
7. Admin UI collector + skip-all + 9th-step gate.
8. Harness Layer 2 bodies + custom harness tests.

Each slice must stay under 400 authored lines.

### Risks

- Writing `page_sections` without converting internal templates leaves content invisible (`page.php` / `get_sub_field()` outside a loop).
- Assigning templates without conversion still renders hardcoded demo HTML (`services-page`, `blog-listing`).
- `page_for_posts` ignores `pages/blog.php`; building that template will not change the posts index.
- `pages/testimonials.php` is broken; `gallery-grid` and testimonial **items** cannot be AI-filled under current harness rules.
- Cloning the landing builder or orchestrator will violate the 400-line review budget.
- Adding a 9th `REQUIRED_STEPS` entry locks already-completed wizard sites until skip-all/unlock is wired.
- Generate Pages reruns still delete unselected pages and overwrite body HTML; interaction with later section builds is unresolved.
- Active sibling changes (`wizard-landing-page-builder` verify still open) share controller/state/canonical/fence files.
- `openspec/config.yaml` `chained_pr_strategy: force-chained` is outside the four-value SDD `delivery_strategy` domain; later phases must not map it.

### Ready for Proposal

**No.** The orchestrator should tell the user: exploration found a real builder gap (shells only; Home/Landing already structured), and a reusable blueprint step is the right shape. Do **not** start proposal until these product forks are confirmed:

1. Convert internal `pages/*.php` to flexible `page_sections` loops (landing pattern) and assign templates from the builder — versus keeping hardcoded parts.
2. Rerun policy: landing-style skip-unchanged + explicit overwrite, versus Home-style always regenerate.
3. Services: replace static `services-page` with `services-v*` layouts, or make `services-page` ACF-driven.
4. Whether repairing `pages/testimonials.php` is in this change.
5. Blog index: customize `index.php`/`home.php` chrome only, and keep individual posts out of scope (already confirmed).
