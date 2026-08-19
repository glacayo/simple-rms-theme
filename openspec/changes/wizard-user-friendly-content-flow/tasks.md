# Tasks: Wizard User-Friendly Content Flow

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~600-800 lines |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (Backend) → PR 2 (Controller) → PR 3 (Frontend) |
| Delivery strategy | ask-on-risk |
| Chain strategy | feature-branch-chain |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Backend step services and builders | PR 1 | Base: feature/wizard-setup. Adds `Step_Generate_Pages`, `Step_Menu_Setup`, `Menu_Builder`, `Step_Home_Page_Builder`. |
| 2 | Controller wiring and logic updates | PR 2 | Base: PR 1 branch. Updates `wizard-init.php` logic, `Step_Controller`, and `Content_Builder`. |
| 3 | Frontend UI and styling | PR 3 | Base: PR 2 branch. Updates `wizard.ts` payload collection, confirmation modals, and `wizard.scss`. |

## Phase 1: Backend Builders & State

- [x] 1.1 Create `inc/wizard/class-menu-builder.php` with wrappers for `wp_create_nav_menu`, `wp_update_nav_menu_item`, and deletion methods.
- [x] 1.2 Modify `inc/wizard/class-content-builder.php` to ensure `build_page()` supports section-only updates for the Home post.
- [x] 1.3 Update `inc/wizard/wizard-init.php` to expand `$steps` to 7 steps, adjusting the required completion order.

## Phase 2: Step Services Implementation

- [x] 2.1 Create `inc/wizard/class-step-generate-pages.php` to handle page selection, destructive cleanup (if confirmed), and Home/Blog role assignments.
- [x] 2.2 Create `inc/wizard/class-step-menu-setup.php` to map pages to menus and perform destructive menu cleanup (if confirmed).
- [x] 2.3 Create `inc/wizard/class-step-home-page-builder.php` to select ACF layouts, AI-fill sections, and save to Home via `Content_Builder::build_page()`.
- [x] 2.4 Modify `inc/wizard/class-step-controller.php` to dispatch new step slugs and update `ai-generation` to be config-only.

## Phase 3: Frontend UI & Styling

- [x] 3.1 Modify `inc/wizard/wizard-init.php` to add HTML panel markup for new steps and destructive confirmation dialogs.
- [x] 3.2 Update `src/ts/admin/wizard.ts` to extend `collectPayload()` for new step forms and implement the confirm modal flow before destructive POSTs.
- [x] 3.3 Update `src/scss/admin/wizard.scss` to style the page picker, section checklist, menu assignment UI, and confirmation modal.

## Phase 4: Testing & Verification

- [ ] 4.1 Write tests for `Menu_Builder` creation and deletion logic.
- [ ] 4.2 Write tests for `Step_Generate_Pages` destructive cleanup and `Step_Home_Page_Builder` section assembly.
- [ ] 4.3 Verify full 7-step UI progression in browser, ensuring `wizard-prd.html` remains unmodified and image fallbacks load.
