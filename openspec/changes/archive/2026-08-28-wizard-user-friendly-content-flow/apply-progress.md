# Apply Progress: Wizard User-Friendly Content Flow

## Mode

Standard mode. `openspec/config.yaml` was not present, no strict TDD mode was resolved, and verification used syntax/type/build checks.

## Workload / PR Boundary

- Delivery strategy: feature-branch-chain
- Current work unit: PR 3 frontend UI and styling
- Boundary: PR 3 base is the PR 2 controller-wiring slice conceptually; in this workspace PR 1 + PR 2 changes are local on `feature/wizard-setup`.
- This slice implements guided admin rendering, frontend payload collection/confirmation flow, and wizard UI styling only.
- Excluded from this slice: new backend service behavior, Phase 4 tests, commits, pushes, staging unrelated files, and `wizard-prd.html` changes.

## Completed Tasks

- [x] 1.1 Created `inc/wizard/class-menu-builder.php` with wrappers for menu creation, menu item replacement, location assignment, and destructive menu/location cleanup.
- [x] 1.2 Updated `inc/wizard/class-content-builder.php` so `build_page()` can perform section-only updates on an existing Home page while preserving normal page creation behavior.
- [x] 1.3 Updated `inc/wizard/wizard-init.php` to expose the seven-step order and backend-facing step descriptions.
- [x] 2.1 Created `inc/wizard/class-step-generate-pages.php` for selected page validation, destructive cleanup confirmation, page creation/update, Home/Blog assignment, reading settings, and state storage.
- [x] 2.2 Created `inc/wizard/class-step-menu-setup.php` for wizard-generated-page-only menu selection, destructive menu cleanup confirmation, primary/mobile menu replacement, location assignment, and state storage.
- [x] 2.3 Created `inc/wizard/class-step-home-page-builder.php` for selected ACF Home sections, AI-assisted section copy with per-section fallbacks, image placeholders, and section-only Home saving via `Content_Builder::build_page()`.
- [x] 2.4 Updated `inc/wizard/class-step-controller.php` to dispatch `generate-pages`, `menu-setup`, `ia-generation`, and `home-page-builder`; changed completion requirements to the seven-step flow; made IA Generation save provider/model/credential status without generating content.
- [x] 3.1 Updated `inc/wizard/wizard-init.php` with guided panels for Generate Pages, Menu Setup, IA Generation, and Home Page Builder, including destructive confirmation warnings, checkboxes, and reusable confirmation modal markup.
- [x] 3.2 Updated `src/ts/admin/wizard.ts` to use the seven-step flow, collect payloads for `generate-pages`, `menu-setup`, `ia-generation`, and `home-page-builder`, populate Menu Setup from `state.generated_pages`, require destructive confirmations before POSTs, preserve AI model loading, and remove raw Pages JSON handling.
- [x] 3.3 Updated `src/scss/admin/wizard.scss` to style the page picker, generated-page menu assignment lists, Home section checklist, destructive warnings, confirmation checkbox controls, and confirmation modal.

## Verification

```text
php -l inc/wizard/wizard-init.php
npx tsc --noEmit --pretty false
npx tsc --ignoreConfig --noEmit --target ESNext --module ESNext --moduleResolution bundler --strict --lib ESNext,DOM --pretty false src/ts/admin/wizard.ts
npm run build
php -l inc/wizard/class-content-builder.php
php -l inc/wizard/class-state-manager.php
php -l inc/wizard/class-menu-builder.php
php -l inc/wizard/class-step-generate-pages.php
php -l inc/wizard/class-step-menu-setup.php
php -l inc/wizard/class-step-home-page-builder.php
php -l inc/wizard/class-step-controller.php
php -l inc/wizard/wizard-init.php
```

Result: all PHP syntax checks reported `No syntax errors detected`; TypeScript checks emitted no errors; `npm run build` completed successfully.

## Notes

- `inc/wizard/wizard-init.php` now renders `ia-generation` instead of the removed `ai-generation`/`content-creation` panel path.
- Menu Setup options are populated client-side only from `state.generated_pages`; no existing WordPress pages are queried or rendered by the frontend slice.
- Generate Pages and Menu Setup both require the inline confirmation checkbox and a final confirmation modal before `confirm_cleanup` is sent.
- Home Page Builder uses supported ACF layout keys: `slider`, `cta-v1`, `about-us`, `services-v1`, `gallery-grid`, `testimonials-v1`, and `contact-info`.
- `wizard-prd.html` was not touched.

## Correction: Custom Generate Pages Flow

- Updated PR 3 frontend work without unchecking completed tasks: Generate Pages no longer forces the fixed common-page checklist as the only source of pages.
- Admins can add arbitrary custom page rows with editable title and slug fields, choose exactly one Home page, optionally choose one Blog page, remove rows, and use the common pages only as a quick-start action.
- `collectGeneratePagesPayload()` now validates at least one page, requires one Home page, allows no Blog page, prevents duplicate slugs, and sends the existing backend-compatible `pages` object with `home_slug`, `blog_slug`, and `confirm_cleanup`.

### Correction Verification

```text
php -l inc/wizard/wizard-init.php
npx tsc --ignoreConfig --noEmit --target ESNext --module ESNext --moduleResolution bundler --strict --lib ESNext,DOM --pretty false src/ts/admin/wizard.ts
npm run build
```

Result: PHP syntax check reported `No syntax errors detected`; targeted TypeScript check emitted no errors; `npm run build` completed successfully.

## Correction: Dynamic Home Page Builder Sections

- Updated Home Page Builder so common sections are no longer the only selectable source. The UI now exposes all `page_sections` ACF Flexible Content layouts from `acf-json/group_rms_page_sections.json` through a layout dropdown, appends dynamic section rows in order, and keeps common Home sections as a quick-start action.
- `collectHomePageBuilderPayload()` now collects ordered dynamic rows from hidden `sections[]` inputs, requires at least one row, and sends the existing backend-compatible `sections` array.
- Added `Inc\Wizard\Flexible_Content_Layouts` so both the UI and backend read valid layouts from ACF JSON. `Step_Home_Page_Builder` now accepts any valid layout key, preserves richer mappings for existing common sections, allows repeated layouts, and uses safe generic placeholders for layouts without custom AI mapping.

### Correction Verification

```text
php -l inc/wizard/wizard-init.php
php -l inc/wizard/class-step-home-page-builder.php
php -l inc/wizard/class-flexible-content-layouts.php
npx tsc --ignoreConfig --noEmit --target ESNext --module ESNext --moduleResolution bundler --strict --lib ESNext,DOM --pretty false src/ts/admin/wizard.ts
npm run build
```

Result: all PHP syntax checks reported `No syntax errors detected`; targeted TypeScript check emitted no errors; `npm run build` completed successfully.

## Phase 4: Tests & Browser Verification

Applied in an isolated worktree at the change's committed base (`2caa6bc`, detached HEAD at `simple-rms-theme-wizard-phase4`) so the uncommitted verified `wizard-internal-page-builder` Phase 9 work in the main worktree was preserved untouched. No commits, pushes, or branch changes were made; the Phase 4 harness deliverables live in that worktree's `tests/` directory for the dedicated `sdd-verify` phase to consume. The worktree contains no `.codegraph` index (a fresh checkout; nothing was copied or reused).

### 4.1 Menu_Builder tests

- Created `tests/wizard-menu-builder-harness.php` (5 scenarios): menu creation + reuse by name, item replacement in display order with non-page filtering and stale-item cleanup, location assignment/clearing, `delete_all_menus()` full removal, and item-failure isolation.
- Result: `Harness passed: 5 scenarios.`

### 4.2 Step_Generate_Pages + Step_Home_Page_Builder tests

- Created `tests/wizard-content-flow-steps-harness.php` (12 scenarios): destructive cleanup confirmation gating, confirmed cleanup deleting only unselected pages, landing-page protection, Home required, Blog optional (`page_for_posts` reset), AI-config dependency, empty-sections block, ordered section assembly with `cta-bar`→`cta-v1` alias and unknown-layout rejection, client-data fallback copy, image placeholder fallback, section-only save to the Home post ID, canonical first-write, Home-not-found error, missing client data error, item-count clamp (1–12), and AI-copy whitelist (blocked/invented fields dropped).
- Shared stub bootstrap: `tests/wizard-user-friendly-content-flow-bootstrap.php`.
- Result: `Harness passed: 12 scenarios.`

### 4.3 Browser verification (simple-rms-theme.local only)

- Full 7-step progression is present and complete in the live wizard (Dependencies → ACF Import → Client Data → Generate Pages → Menu Setup → IA Generation → Home Page Builder), force-unlocked via `RMS_WIZARD_FORCE`; steps 8/9 (Landing/Internal Page Builder) are later-phase steps and remain untouched.
- Generate Pages panel: custom page rows with editable title/slug, Home/Blog radios, "Add common pages" quick-start (appends rows without replacing), destructive warning + confirmation checkbox.
- Menu Setup panel: generated-pages-only candidates + menu-eligible SEO landings, primary/mobile assignment, destructive warning.
- IA Generation panel: provider dropdown, masked credential status, saved model (`glm-5.2`) surfaced.
- Home Page Builder panel: Homepage SEO targeting, layout dropdown exposing all 27 ACF Flexible Content layouts from `group_rms_page_sections.json`, "Add common Home sections" quick-start, add/remove section rows.
- Destructive gate: clicking "Run step" on Generate Pages without the confirmation checkbox blocks client-side with "Confirm that existing pages can be deleted or replaced before continuing."; no REST mutation occurred (wizard state unchanged after the attempt).
- Image fallbacks: `wizard-placeholder.svg` returns HTTP 200 with `image/svg+xml`; frontend Home has no broken wizard section images (only the theme's empty lightbox placeholder, which is filled on open).
- `wizard-prd.html`: SHA-256 unchanged before and after (`E238D1D6...A83D6F`).
- Console: 0 errors on the wizard page.

### Phase 4 Verification

```text
php -l tests/wizard-user-friendly-content-flow-bootstrap.php
php -l tests/wizard-menu-builder-harness.php
php -l tests/wizard-content-flow-steps-harness.php
php tests/wizard-menu-builder-harness.php
php tests/wizard-content-flow-steps-harness.php
npx tsc --noEmit --pretty false
npm run build
```

Result: all PHP syntax checks reported `No syntax errors detected`; both harnesses passed (5 + 12 scenarios); TypeScript check emitted no errors; `npm run build` completed successfully (wizard JS/CSS emitted). Independent final `sdd-verify` is intentionally not performed per apply scope.
