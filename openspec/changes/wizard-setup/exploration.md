## Exploration: Wizard Setup — Contractor Theme

### Current State

The theme is a classic WordPress PHP theme (not FSE/block-based) with a modular procedural architecture. All rendering is driven by ACF Flexible Content and template parts.

**What already exists:**
- **TGMPA** (`inc/tgmpa.php`): Manages 6 plugins. ACF PRO is bundled as a zip in `inc/plugins/` and marked `required: true`. Yoast SEO is `required: false` (contradicts PRD assumption that Yoast is always present).
- **ACF Flexible Content** (`inc/acf-flexible-content.php`, `acf-json/group_rms_page_sections.json`): ~25 layout types (Hero, Slider, Services v1–v3, Testimonials v1–v3, CTAs v1–v3, FAQs, Blog, Portfolio, Gallery, Video, Vision-Mission, SEO Content, etc.). All layouts are registered via `acf_add_local_field_group` and mirrored in `acf-json/`.
- **ACF Theme Options** (`inc/acf-theme-options.php`, `acf-json/group_rms_theme_settings.json`): Options page at `rms-theme-settings` with company fields (name, phones, emails, social, branding colors, header/footer version, logos/favicon, schemas).
- **Page Templates** (`pages/`): about-us, blog, contact-us, landing-page, services, testimonials, thank-you, projects — each is a hardcoded sequence of template parts.
- **Front Page** (`front-page.php`): Renders via flexible content loop when sections exist, else falls back to hardcoded order.
- **Schema.org** (`inc/schema.php`): 895 lines of structured data generation (LocalBusiness, FAQ, Breadcrumb, etc.).
- **Vite integration** (`inc/vite-integration.php`): TypeScript/SCSS asset pipeline.
- **Classic Editor**: TinyMCE is removed from posts and pages (`remove_post_type_support`). Content lives exclusively in ACF fields.

**What does NOT exist (all wizard pieces must be built from zero):**
- No admin menu page for a wizard
- No state machine, autosave, or progress tracking
- No AI/LLM integration of any kind
- No programmatic post/page creation from theme code
- No programmatic media upload with placeholder fallback
- No Yoast meta writes from theme code
- No logging/audit trail system
- No completion lock mechanism
- No custom database tables (everything uses WP options/postmeta)
- No Composer autoloading (pure procedural PHP files)
- No WP-Cron background workers (though core cron is available)
- No REST API custom endpoints registered by the theme

**Critical Reality Check — WP 7.0 AI integration:**
The PRD references "WordPress 7.0 native AI integration with providers (Google, OpenAI, Anthropic)" as a cornerstone requirement. This does NOT exist in reality. WordPress currently has no native AI provider framework. This is a **speculative requirement** that needs architectural resolution (see Questions below).

### Affected Areas

- **`functions.php`** — must load new wizard module(s)
- **`inc/`** — new files for wizard core, API adapters, state machine, content generation, media handling, logging
- **`inc/tgmpa.php`** — Yoast currently `required: false`; must become `required: true` or wizard must handle missing Yoast gracefully
- **`inc/acf-flexible-content.php`** — layouts are the target data model for section content generation; needs a catalog/map of which layouts are applicable for Home vs Landing pages
- **`inc/acf-theme-options.php`** — theme options fields map closely to Step 5 (Client Info); wizard could programmatically populate these
- **`acf-json/`** — ACF JSON import step (Step 4) needs to process these files
- **`pages/`** — page templates are the target for Step 7 (Internal Pages); need metadata about which templates are available and which ACF fields they consume
- **`front-page.php`** — wizard must programmatically set the Home page ID and configure `page_on_front` + `show_on_front`
- **`src/`** — SCSS/TS build system; wizard admin UI will need its own Vite entry points or inline admin styles
- **`wp-admin/` (conceptual)** — new admin menu entry, new admin pages for each wizard step
- **Database** — new rows in `wp_posts` (pages, posts), `wp_postmeta` (ACF field values), `wp_options` (theme options, wizard state), `wp_yoast_indexable` (Yoast meta if plugin active)

### Approaches

1. **Inline Theme Module (pure PHP, procedural, filesystem-based state)** — Add `inc/wizard/` directory with PHP files for each step, state stored in a single `wp_options` row as serialized JSON.
   - Pros: Matches existing theme patterns (no new tooling). No autoloader/composer needed. Fast to prototype. Low coupling.
   - Cons: Large monolithic state object. No clean separation of concerns. Difficult to test. Single-table bottlenecks if state grows.
   - Effort: Low for MVP, High for maintenance at scale

2. **Modular OOP with Custom Post Type / DB Tables** — Introduce a `Wizard` namespace with classes for StateManager, StepController, ContentGenerator, MediaHandler, Logger. Custom `wp_wizard_state` table or a CPT. Admin UI via WP List Table-like rendering.
   - Pros: Clean separation of concerns. Testable. Extensible (new steps via interface). Better concurrency/state management.
   - Cons: Breaks existing procedural pattern. Requires PHP namespaces (PHP 7.2+). May need Composer autoload. More initial scaffolding.
   - Effort: Medium-High (but sustainable)

3. **Hybrid: Procedural Facade with OOP Core** — Keep `inc/wizard/wizard-init.php` as a procedural loader (matching existing `functions.php` pattern), but internal logic uses namespaced classes. State in `wp_options` but with a dedicated key structure per step.
   - Pros: Files look familiar to the codebase. Internal quality is maintainable. Middle ground. Easy to integrate.
   - Cons: Mixes two paradigms. Still no autoloading unless you add it. Namespace decision is permanent.
   - Effort: Medium

### Recommendation

**Approach 3 (Hybrid)** is the pragmatic choice. The theme is procedural and has no Composer, but a wizard of this complexity demands at least internal OOP for the state machine, API adapters, and content generators. The public-facing loader (`inc/wizard/wizard-init.php`) stays procedural and hooks into WordPress the same way `inc/tgmpa.php` does. Internally, use `Inc\Wizard\*` namespaced classes. Add a one-line `spl_autoload_register()` in the init file (no Composer needed). This matches the pattern already established by the TGMPA library (a single class file loaded procedurally).

**Rationale**: The wizard has 10 steps with complex state (4 global states × 5 section states × N pages/landings). Doing this procedural-only would produce unmaintainable spaghetti. But introducing a full PSR-4 + Composer setup is overkill for a theme that explicitly avoids that complexity. The hybrid approach keeps the entry point clean while making the internals testable and debuggable.

### Risks

- **WP 7.0 AI integration is fictional**: The PRD's foundational requirement does not exist. The wizard must implement its own AI adapter layer (HTTP calls to provider APIs with WordPress HTTP API) as if it were the native integration. This is the single biggest architectural risk — it means the wizard owns AI communication entirely, including key storage, retry logic, model listing, and error handling. Must decide: store API keys in `wp_options` or use WordPress's nonexistent "native" keychain?
- **Yoast dependency is optional in TGMPA**: The PRD assumes Yoast is always available for Meta Title/Description writes. If Yoast is missing, the wizard must either force-install it (making it `required: true`) or write post meta directly (`_yoast_wpseo_title`, `_yoast_wpseo_metadesc`). The wizard should NOT crash without Yoast — it should degrade gracefully.
- **Content generation is time-consuming**: AI content generation can take 5–30 seconds per section. With 10+ sections per page, this is minutes of waiting. The PRD specifies "incremental, section by section" which mitigates this, but the architecture still needs to handle HTTP timeouts, rate limits, and partial failures with retry+backoff.
- **ACF JSON conflicts**: If the theme's ACF JSON has already been synced, re-importing could create conflicts. The wizard needs a diff/merge strategy or at minimum a "skip if exists" policy.
- **No rollback mechanism**: Per the PRD (Section 9), there is no "undo all" button. The wizard creates real WordPress entities (pages, posts, options). If a developer runs the wizard and gets bad results, they must manually clean up — or we implement a soft "uninstall" feature.
- **Plugin installation is fragile**: TGMPA handles this, but the wizard must wrap it with graceful error handling per plugin. Some plugins (ACF PRO) are bundled, others from wp.org. Network failures are possible.
- **Image placeholders**: The PRD uses `placehold.co`. If that service is down, no placeholder renders. Consider bundling a local fallback placeholder image in the theme.

### Questions to Resolve Before Design

These are architectural decisions that MUST be answered before `sdd-design`:

1. **AI Provider Strategy**: Since "WP 7.0 native AI" does not exist, do we build our own provider abstraction layer (HTTP + WordPress HTTP API → OpenAI/Google/Anthropic), or wait for an official WordPress AI API? **Recommendation**: Build our own abstraction now; it can be adapted later if a native API emerges.
2. **API Key Storage**: Where do we store user-entered API keys? `wp_options` (default), WordPress's non-existent keychain, or a custom encrypted option? **Recommendation**: `wp_options` with a dedicated key, but flag this for security review.
3. **Yoast Dependency**: Make Yoast `required: true` in TGMPA or handle its absence gracefully? **Recommendation**: Make it required — it simplifies the spec and matches the PRD assumption.
4. **State Persistence Granularity**: Single `wp_options` row vs per-step keys vs custom table? **Recommendation**: Single `wp_options` row with structured JSON. It's the simplest and matches the autosave model. If state grows beyond ~64KB, split into per-step keys.
5. **Content Generation Model**: Synchronous (user waits with spinner) vs asynchronous (WP-Cron background job with polling/pusher)? **Recommendation**: Synchronous with server-side streaming or chunked responses. WP-Cron is unreliable for real-time feedback, and the PRD explicitly wants the user to see progress section by section.
6. **Section Reuse / Caching**: Where to cache generated content for section reuse? Transients (default), postmeta on a "scratch" post, or a custom option? **Recommendation**: Transients with the wizard session ID as part of the key. They auto-expire and are designed for temporary data.
7. **Completion Lock**: How to enforce single execution? A boolean option (`rms_wizard_completed` = true)? **Recommendation**: Yes. Simple, atomic, easy to check. Add a `define('RMS_WIZARD_FORCE', true)` escape hatch for developers in `wp-config.php`.
8. **Admin Capability**: Who can run the wizard? `manage_options` (admin) or a custom capability? **Recommendation**: `manage_options` — matches existing Theme Settings page.
9. **Content Language**: The PRD says "content is generated in the language from Step 5." But the wizard UI itself — in English only, or i18n-ready? **Recommendation**: English-only for the wizard UI initially (matches "all artifacts in English" convention). Content language is a parameter passed to the AI prompt.
10. **Image Upload Constraints**: Any file size/type limits? **Recommendation**: Use WordPress defaults. No extra restrictions in the wizard itself.
11. **Plugin Critical vs Optional**: Which plugins block wizard progress if not installed? ACF PRO is clearly critical (required=true). Classic Editor is required. CF7, WP Fastest Cache, Wordfence are optional. Yoast: make it required.
12. **Template Registration**: How does the wizard know which templates exist and which ACF fields they consume? **Recommendation**: A config array or a `wizard-templates.json` manifest in the theme. The wizard reads this to know what's available for Step 7 (internal pages) and Step 6 (home sections).

### Ready for Proposal

**Yes**, with caveats. The PRD provides sufficient functional requirements for a proposal. The 12 questions above must be decided during design, but they do NOT block the proposal phase. The proposal can state assumptions for each.

The orchestrator should tell the user: "The codebase exploration is complete. There are 12 architectural decisions to make, but enough is known to proceed to proposal. The biggest finding is that the PRD depends on 'WP 7.0 native AI' which does not exist — the wizard must build its own AI abstraction layer. I recommend proceeding to sdd-propose and resolving these questions in the design phase."
