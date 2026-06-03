## Exploration: Wizard User-Friendly Content Flow

### Current State

The wizard (`inc/wizard/wizard-init.php`, `class-step-controller.php`, `class-rest-controller.php`) implements 5 sequential steps:

1. **Dependencies** — TGMPA plugin check/install
2. **ACF Import** — Import ACF JSON field groups
3. **Client Data** — Save contractor info to Theme Settings
4. **AI Generation** — Standalone prompt→response via selected provider
5. **Content Creation** — **Raw JSON textarea** expecting `[{"title":"Home","content":"..."}]`

The content-creation step UI (wizard-init.php lines 221-228) presents a single `<textarea>` where the user manually writes a Pages JSON array. The TS frontend (`src/ts/admin/wizard.ts`, lines 391-394) parses this via `JSON.parse`. The backend `Content_Builder::build_pages()` receives this array and calls `wp_insert_post()`/`wp_update_post()` per entry, then saves `page_sections` ACF flexible content via `update_field()`.

**Problem**: This manual JSON flow is not viable for non-technical end users. They cannot construct valid ACF flexible-content page definitions by hand.

### What Already Exists (Reusable)

| Component | File | Relevance |
|-----------|------|-----------|
| Step definitions array | `wizard-init.php:112-118` | 5 hardcoded steps; `complete()` checks all 5 must be `complete` |
| Step dispatch | `class-step-controller.php:115-144` | Switch on step slug; content-creation dispatches to `Content_Builder::build_pages()` |
| Content builder | `class-content-builder.php` | `build_page()` accepts `{title, slug, status, content, sections, seo}`; `prepare_image_fallbacks()` already handles empty image fields with `wizard-placeholder.svg` |
| ACF flexible layouts | `acf-json/group_rms_page_sections.json` | 23+ layouts: hero, slider, about-us, services-v1/v2/v3, testimonials-v1/v2/v3, cta-v1/v2/v3, blog-v1, portfolio-v1/v2/v3, gallery-grid, faq-v1/v2, badges, area-coverage-v1, contact-info, video-v1/v2, vision-mission-v1/v2, seo-content, contact-map |
| ACF theme settings | `acf-json/group_rms_theme_settings.json` | Client data already saved here: company name, phones, emails, address, services, branding, social media, etc. |
| AI provider stack | `class-ai-provider.php`, `class-ollama-provider.php`, `class-ai-provider-registry.php` | Ollama Cloud provider implemented; extensible via `rms_wizard_ai_providers` filter. Credential store using sodium/openssl encryption. |
| AI adapter | `class-ai-adapter.php` | Generic HTTP adapter with retry/backoff (unused currently — Ollama_Provider has its own direct logic) |
| State manager | `class-state-manager.php` | Option-based state with `step_status`, `created_posts`, `generated`, `client_data` fields |
| REST controller | `class-rest-controller.php` | 4 endpoints: `GET /state`, `POST /steps/{step}/run`, `POST /complete`, `POST /ai/models` |
| Menu registration | `inc/setup.php:31-35` | 3 menus: `primary`, `footer`, `mobile`. Custom walkers for V1/V2/V3 headers |
| Front page | `front-page.php` | Renders flexible content loop; falls back to hardcoded template order |
| Page templates | `pages/` | about-us, blog, contact-us, services, testimonials, thank-you, projects, landing-page |
| Template parts | `templates/` | Individual section PHP templates matching ACF layout names |
| TS wizard client | `src/ts/admin/wizard.ts` | 964 lines; steps hardcoded at lines 110-116; `collectPayload()` at line 377; `loadAiModels()` at line 399 |
| Placeholder image | `assets/images/wizard-placeholder.svg` | Already bundled; `Content_Builder::fallback_image_url()` returns it |
| TGMPA plugins | `inc/tgmpa.php` | ACF PRO, Classic Editor, CF7, WP Fastest Cache, Wordfence, Yoast SEO |
| Completion lock | `class-state-manager.php` | `rms_wizard_completed` option; `RMS_WIZARD_FORCE` override |
| Logger | `class-logger.php` | Structured logs in `rms_wizard_log` option |

### New Steps Needed (Proposed Flow)

User wants: **Client Data → Generate Pages → Menu Setup → IA Generation → Home Page Builder**

This requires restructuring the current 5-step wizard into a new multi-step flow:

1. **Dependencies** (keep as-is)
2. **ACF Import** (keep as-is)
3. **Client Data** (keep as-is)
4. **Generate Pages** (NEW — replaces half of content-creation)
   - User selects which pages to generate (Home, About, Services, Blog, Contact, etc.)
   - Mark which page is "Home" and which is "Blog"
   - AI generates page content using Client Data as context
   - Pages are created/updated via `Content_Builder::build_page()`
5. **Menu Setup** (NEW)
   - From generated pages, configure main menu and mobile menu
   - Programmatically create `wp_menu` items linking generated pages
   - Assign menus to `primary` and `mobile` locations
6. **IA Generation** (REBRAND of current AI Generation step)
   - AI provider/model/API key configuration
   - Standalone: user configures AI credentials here before Home Page Builder uses them
   - Keep existing `list_models()`, credential store, etc.
7. **Home Page Builder** (NEW — replaces the other half of content-creation)
   - User selects Home sections from available ACF layouts
   - Common sections: slider, CTA bar, about, services, gallery, testimonials, contact
   - AI fills each section's content from Client Data context
   - Images use placeholders initially; can be replaced later
   - Saves to Home page's `page_sections` ACF field

### Affected Areas

- **`inc/wizard/wizard-init.php`** — Lines 112-126: the `$steps` array and `$descriptions` array must expand from 5 to 7 steps. The inline rendering for `content-creation` (lines 221-228) must be replaced with new step renderings for Generate Pages, Menu Setup, and Home Page Builder. The `content-creation` panel markup (lines 221-240) must be restructured.
- **`inc/wizard/wizard-init.php`** — Line 101: `$required` array inside `complete()` must change from `[dependencies, acf-import, client-data, ai-generation, content-creation]` to include the new step order.
- **`inc/wizard/class-step-controller.php`** — Lines 115-144: `dispatch_step()` switch must add cases for `generate-pages`, `menu-setup`, `home-page-builder`. The `complete()` method at line 95-113 must update `$required` list. The `generate_ai_content()` method (lines 153-191) may need refactoring for multi-section generation.
- **`inc/wizard/class-rest-controller.php`** — No structural changes needed (generic step dispatch), but new step slugs will flow through existing `/steps/{step}/run` route.
- **`inc/wizard/class-content-builder.php`** — `build_page()` already accepts a `{title, slug, status, content, sections, seo}` structure. A new method for building menu items (`wp_create_nav_menu()`, `wp_update_nav_menu_item()`) is needed, or a separate `Menu_Builder` class.
- **`inc/wizard/class-ai-provider.php` / `class-ollama-provider.php`** — AI generation must move from being a standalone step to being a utility used by Generate Pages and Home Page Builder. The current `generate()` method returns `{success, content}` which is suitable. Need prompt templates that include Client Data context for page/section generation.
- **`inc/wizard/class-state-manager.php`** — State must track: `generated_pages` (array of page configs), `selected_home_sections` (array of ACF layout keys), `menu_config` (menu assignments). State shape grows but stays within `wp_options`.
- **`inc/wizard/wizard-init.php`** — The `rms_wizard_render_admin_page()` function rendering each step panel (lines 179-241) must add HTML for the 3 new steps.
- **`src/ts/admin/wizard.ts`** — Lines 110-116: `steps` array must include new step slugs. `collectPayload()` at line 377 must handle new step data shapes. New UI interactions: page selection checkboxes, home/blog radio designation, section multi-select for Home Page Builder, menu drag-drop or assignment UI.
- **`src/scss/admin/wizard.scss`** — New styles for page picker grid, section checklist, menu assignment UI.
- **`inc/setup.php`** — Lines 30-35: existing menu registrations (`primary`, `footer`, `mobile`) are sufficient. No code change needed here, but wizard must call `wp_nav_menu` assignment functions.
- **`front-page.php`** — No change needed. Already reads `page_sections` flexible content. Wizard just needs to populate it.
- **`acf-json/group_rms_page_sections.json`** — No change. Layouts are the data target for Home Page Builder.

### Approaches

1. **Extend existing step architecture with new step services** — Add `Step_Generate_Pages`, `Step_Menu_Setup`, and modify `Content_Builder` to handle Home Page Builder. Rebrand `ai-generation` step as `ia-generation` (provider/config only). Keep `content-creation` step removed entirely (its logic replaced by the 3 new steps).
   - Pros: Follows existing pattern (`Step_*` classes). REST dispatch already generic. `Content_Builder` already handles `build_page()`. Minimal structural changes.
   - Cons: 3 new classes + `Menu_Builder` class. State grows. UI must be rebuilt for 3 steps.
   - Effort: Medium-High

2. **Consolidate into fewer step services** — Combine Generate Pages + Home Page Builder into a single `Step_Content_Generator` service that handles both page creation and section population. Menu Setup remains separate.
   - Pros: Fewer class files. Shared AI prompt logic.
   - Cons: Larger, more complex step service. Harder to test independently. Blurs separation between page creation and section filling.
   - Effort: Medium

3. **Keep existing step order but replace content-creation with wizard-guided AI form** — Instead of adding 3 steps, keep 5 steps but make the content-creation step a rich UI that guides the user through: select pages → AI generates each → menu assignment → home sections. Collapse the new flow into one "megastep".
   - Pros: Minimal step architecture changes. No `complete()` logic changes. Fewer REST round-trips.
   - Cons: One massive UI panel. Hard to resume at specific sub-step. State tracking gets complex. Contradicts the user's stated desire for separate steps they can see in progress.
   - Effort: Low structural, High UI complexity

### Recommendation

**Approach 1** — Extend with 3 new step services. The wizard already has a clean step-per-class pattern. Adding `Step_Generate_Pages`, `Step_Menu_Setup`, `Home_Page_Builder` (or extend `Content_Builder`) keeps the architecture consistent. The existing `Content_Builder::build_page()` already accepts a `sections` array with image fallback handling, so Home Page Builder can reuse this directly.

The biggest change is that AI generation becomes a **utility** consumed by Generate Pages and Home Page Builder, rather than a standalone step. Keep `ia-generation` as a **configuration** step (provider/model/key) that precedes both AI-consuming steps. Current `generate_ai_content()` in `Step_Controller` already has the right signature — it just needs to be callable from within other step services rather than only from the switch dispatch.

### Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 800 - 1200 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (New PHP step services + state) → PR 2 (Menu builder + controller changes) → PR 3 (TS/SCSS UI + wizard-init.php rendering) |
| Delivery strategy | ask-always (from session preflight) |

### Risks

- **Step count change breaks completion logic**: `Step_Controller::complete()` (line 101) has `$required` array hardcoded to 5 step slugs. Must be updated to 7. The `is_completed()` lock flag (`rms_wizard_completed`) persists across version changes — developers with existing completed wizards using `RMS_WIZARD_FORCE` will need to know the new steps exist.
- **AI generation moves from step to utility**: `generate_ai_content()` currently sets `step_status` for `ai-generation`. In the new flow, `ia-generation` is just provider config (no content generated), while actual AI calls happen inside Generate Pages and Home Page Builder. The step status tracking needs to decouple AI generation from the `ai-generation` step slug.
- **Menu creation is new territory**: No existing menu-programming code in the theme. WordPress menu APIs (`wp_create_nav_menu`, `wp_update_nav_menu_item`, `set_theme_mod('nav_menu_locations')`) need to be learned and tested. Menu items reference post IDs that may not exist yet if pages haven't been created.
- **Frontend TS rewrite is significant**: `wizard.ts` has 964 lines with step handling deeply coupled to the current 5 steps. `collectPayload()` special-cases `content-creation` for JSON parsing. New step panels need new data collection logic. The `nextStepFor()` function and `updateButtons()` logic assume linear step progression.
- **State shape evolution**: Currently `state` has `client_data`, `generated`, `created_posts`. New flow needs `generated_pages` (page configs), `menu_items` (menu assignments), `home_sections` (selected layouts). The `wp_options` row could grow significantly but stays well under the 64KB MySQL limit.
- **wizard-prd.html must remain untouched**: Confirmed from session context — this file is untracked and must not be committed or modified.
- **Ollama Cloud commits not pushed**: `feature/wizard-setup` branch is 3 commits ahead. Need to push before chained PRs.

### Ready for Design

**Yes**. The current codebase is well understood, and the required structural changes are clear. The 7-step flow replaces the current 5-step flow by decomposing the raw JSON content-creation step into 3 user-friendly guided steps while keeping dependencies, ACF import, and client data unchanged.

The orchestrator should proceed to `sdd-propose` with change name `wizard-user-friendly-content-flow`.
