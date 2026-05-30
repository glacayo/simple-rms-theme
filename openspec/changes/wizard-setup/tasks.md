# Tasks: Wizard Setup

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 1000 - 1400 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (Foundation) → PR 2 (Core logic) → PR 3 (API & Controllers) → PR 4 (UI/Assets) |
| Delivery strategy | ask-on-risk |
| Chain strategy | feature-branch-chain |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Foundation, Loader & Fallback Asset | PR 1 | Base: feature/tracker branch. Adds logger, state, TGMPA config, placeholder. |
| 2 | Step Business Logic & Services | PR 2 | Base: PR 1 branch. AI adapter, ACF importer, content builder. |
| 3 | REST Routing & Orchestration Controllers | PR 3 | Base: PR 2 branch. REST endpoints & controllers hooking logic. |
| 4 | Admin GUI & Vite Build Integration | PR 4 | Base: PR 3 branch. TS/SCSS view assets & Vite configuration. |

## Phase 1: Foundation & Infrastructure

- [x] 1.1 Modify `functions.php` to require the entry-point file `inc/wizard/wizard-init.php`.
- [x] 1.2 Modify `inc/tgmpa.php` to set Yoast SEO plugin as required.
- [x] 1.3 Create local theme-fallback asset `assets/images/wizard-placeholder.svg`.
- [x] 1.4 Create `inc/wizard/class-logger.php` to write structured, persistent logs.
- [x] 1.5 Create `inc/wizard/class-state-manager.php` using `wp_options` for state cache & locks.

## Phase 2: Core Step Services

- [x] 2.1 Create `inc/wizard/class-step-dependencies.php` to check/install plugins via TGMPA wrappers.
- [x] 2.2 Create `inc/wizard/class-step-acf-import.php` to import ACF field definitions and log existing group conflicts.
- [x] 2.3 Create `inc/wizard/class-step-client-data.php` to persist client inputs in ACF settings.
- [x] 2.4 Create `inc/wizard/class-ai-adapter.php` with `wp_remote_request()` and exponential backoff (max 3 retries).
- [x] 2.5 Create `inc/wizard/class-content-builder.php` to build pages, flexible contents, and attach fallback images.
- [x] 2.6 Create `inc/wizard/class-yoast-meta-writer.php` to populate SEO titles and descriptions on generated pages.

## Phase 3: REST Endpoints & Orchestration

- [x] 3.1 Create loader `inc/wizard/wizard-init.php` to load classes, register admin page, and enqueue assets.
- [x] 3.2 Create `inc/wizard/class-step-controller.php` to orchestrate actions, manage resume, and verify access.
- [x] 3.3 Create `inc/wizard/class-rest-controller.php` to expose endpoints for step executions and state retrieval.

## Phase 4: Frontend Development & Build

- [x] 4.1 Create UI stylesheet `src/scss/admin/wizard.scss` with steps and progress layouts.
- [x] 4.2 Create client script `src/ts/admin/wizard.ts` for step-navigation, async calls, progress bars, and retries.
- [x] 4.3 Modify `vite.config.ts` to add `admin/wizard` entry points for SCSS and TypeScript build compilation.

## Phase 5: Verification & Testing

- [ ] 5.1 Test: Verify unauthorized access is blocked (must have `manage_options`).
- [ ] 5.2 Test: Verify dependencies are checked/installed correctly via TGMPA.
- [ ] 5.3 Test: Verify ACF JSON import skip/log behavior on existing conflicts.
- [ ] 5.4 Test: Verify AI Adapter retry/backoff mechanism on simulated failures.
- [ ] 5.5 Test: Verify content, ACF flexible fields, and Yoast meta are successfully created.
- [ ] 5.6 Test: Verify state/lock storage and Developer Force Bypass (`RMS_WIZARD_FORCE`).
