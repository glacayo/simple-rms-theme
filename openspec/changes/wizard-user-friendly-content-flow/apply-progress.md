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
