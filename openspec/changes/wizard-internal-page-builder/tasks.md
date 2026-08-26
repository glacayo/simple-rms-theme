# Tasks: Wizard Internal Page Builder

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 1400-2000 |
| Suggested split | See Suggested Work Units |
| Delivery strategy | auto-chain (`force-chained`) |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

**Blocker**: `wizard-user-friendly-content-flow` + `wizard-landing-page-builder` archive before this-change-archives (ADDED-only). Apply may proceed now.

### Suggested Work Units

| Unit | Goal | Base | Test | Harness | Rollback |
|---|---|---|---|---|---|
| 1 | Registry+state | tracker | `php -l` x2 | N/A — no runtime | Delete file/default |
| 2 | Extract `Section_Assembler` | PR1 | `php -l` x3 | Diff Home+Landing rerun | Revert 3 files |
| 3 | About backend | PR2 | `php -l` x2 | New harness file | Drop step class |
| 4 | Templates+shell | PR3 | `php -l` x5 | Load pages manually | Revert templates |
| 5 | Remaining+Testimonials | PR4 | `php -l` x2 | New harness file | Revert extension |
| 6 | Blog chrome | PR5 | `php -l` x2 | Load index manually | Delete `home.php` |
| 7 | Harness L2+sync | PR6 | `php -l` x2 | Extended fakes | Revert L2 bodies |
| 8 | UI+activation+regression | PR7 | `tsc`;`php -l` | Full run manually | Remove step lists |

## Phase 1: Blueprint Registry & State Shape

- [x] 1.1 Create `inc/wizard/class-internal-page-blueprints.php`: `all()` map (about/services/contact/projects/testimonials/blog) → template/layouts/`PAGE_*`/canonical-policy.
- [x] 1.2 Modify `inc/wizard/class-state-manager.php`: add `internal_pages` default (`post_id/layouts/status/reason`); no dispatch-wiring yet.

## Phase 2: Section Assembler Extraction

- [x] 2.1 Create `inc/wizard/class-section-assembler.php` from identical Home (`:423`)/Landing (`:2158`) assembly logic.
- [x] 2.2 Modify `class-step-home-page-builder.php`, `class-step-landing-page-builder.php`: delegate; verify zero diff.

## Phase 3: Internal Builder Backend — About

- [x] 3.1 Create `inc/wizard/class-step-internal-page-builder.php`: plan-states/one-page-per-process/retry/skip-all/overwrite-gate, About only (global mutation fence is owned by Phase 8 controller/dispatch wiring); add `tests/wizard-internal-page-builder-harness.php`.
- [x] 3.2 Create `inc/wizard/class-placeholder-provenance-store.php`: `record/query/queue` (no `sync()` yet).

## Phase 4: Template Conversion (About/Services/Contact/Projects)

- [ ] 4.1 Create `templates/page-sections-loop.php`; unknown layouts skipped without fatal.
- [ ] 4.2 Modify `pages/{about-us,services,contact-us,projects}.php` via loop partial.
- [ ] 4.3 Modify `class-step-generate-pages.php`: assign blueprint `_wp_page_template` at shell-creation; no sections written.

## Phase 5: Remaining Backends + Testimonials Repair

- [ ] 5.1 Extend `class-step-internal-page-builder.php` plan/process to Services, Contact, Projects, Testimonials; extend existing `tests/wizard-placeholder-provenance-harness.php` for later sync/replacement behavior.
- [ ] 5.2 Modify `pages/testimonials.php`: valid-PHP-opener + loop partial; drop `services-page` reference.

## Phase 6: Blog Index Chrome

- [ ] 6.1 Create `home.php`: configurable-chrome + WP-posts-loop + empty-state, zero post writes.
- [ ] 6.2 Modify `header.php`: loop stored `page_sections` layouts (deferred); extend plan to Blog blueprint.

## Phase 7: Harness Layer 2 + Provenance Sync

- [ ] 7.1 Modify `class-ai-content-harness.php`: Layer 2 for `PAGE_ABOUT/SERVICE/CONTACT/BLOG`; add `PAGE_PROJECTS`/`PAGE_TESTIMONIALS`, gallery/item fields blocked.
- [ ] 7.2 Modify `class-placeholder-provenance-store.php`: add `sync()` + `is_placeholder_payload()`; wire `acf/save_post`(20)-in-`wizard-init.php`; extend `test-landing-run-orchestrator.php` fakes.

## Phase 8: Admin UI, Activation, Regression

- [ ] 8.1 Modify `wizard-init.php`, `src/ts/admin/wizard.ts`, `src/scss/admin/wizard.scss`: step label, config, cards, re-dispatch, skip-all.
- [ ] 8.2 Modify `class-step-controller.php`: add step to `REQUIRED_STEPS`+`DISPATCHABLE_STEPS`+dispatch-case (owns the global mutation fence), atomic with 8.1.
- [ ] 8.3 Regression: rerun Home+Landing, diff `page_sections` vs baseline; `php -l` changed PHP; `tsc --noEmit`.
