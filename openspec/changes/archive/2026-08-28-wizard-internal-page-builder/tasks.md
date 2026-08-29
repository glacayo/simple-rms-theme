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
| 6 | Blog chrome | PR6 | `php -l` x2 | Load index manually | Delete `home.php` |
| 7 | Harness L2+sync | PR7 | `php -l` x2 | Extended fakes | Revert L2 bodies |
| 8 | UI+activation+regression | PR8 | `tsc`;`php -l` | Full run manually | Remove step lists |

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

- [x] 4.1 Create `templates/page-sections-loop.php`; unknown layouts skipped without fatal.
- [x] 4.2 Modify `pages/{about-us,services,contact-us,projects}.php` via loop partial.
- [x] 4.3 Modify `class-step-generate-pages.php`: assign blueprint `_wp_page_template` at shell-creation; no sections written.

## Phase 5: Remaining Backends + Testimonials Repair

- [x] 5.1 Extend `class-step-internal-page-builder.php` plan/process to Services, Contact, Projects, Testimonials; extend existing `tests/wizard-placeholder-provenance-harness.php` for later sync/replacement behavior.
- [x] 5.2 Modify `pages/testimonials.php`: valid-PHP-opener + loop partial; drop `services-page` reference; enable Testimonials `_wp_page_template` assignment at Generate Pages shell creation.

## Phase 6: Blog Index Chrome

- [x] 6.1 Create `home.php`: configurable-chrome + WP-posts-loop + empty-state, zero post writes; enable Blog `_wp_page_template` assignment at Generate Pages shell creation.
- [x] 6.2 Modify `header.php`: loop stored `page_sections` layouts (deferred); extend plan to Blog blueprint.

## Phase 7: Harness Layer 2 + Provenance Sync

- [x] 7.1 Modify `class-ai-content-harness.php`: Layer 2 for `PAGE_ABOUT/SERVICE/CONTACT/BLOG`; add `PAGE_PROJECTS`/`PAGE_TESTIMONIALS`, gallery/item fields blocked.
- [x] 7.2 Wire existing `Placeholder_Provenance_Store::sync()` / `is_placeholder_payload()` on `acf/save_post`(20) in `wizard-init.php`; extend `test-landing-run-orchestrator.php` fakes. Do not re-add store methods.

## Phase 8: Admin UI, Activation, Regression

- [x] 8.1 Modify `wizard-init.php`, `src/ts/admin/wizard.ts`, `src/scss/admin/wizard.scss`: step label, config, cards, re-dispatch, skip-all.
- [x] 8.2 Modify `class-step-controller.php`: add step to `REQUIRED_STEPS`+`DISPATCHABLE_STEPS`+dispatch-case (owns the global mutation fence), atomic with 8.1.
- [x] 8.3 Regression: rerun Home+Landing, diff `page_sections` vs baseline; `php -l` changed PHP; `tsc --noEmit`.

## Phase 9: Post-verify correction (runtime coverage)

Correction after failed verify `sha256:38fa7872a57aabb677d5dd4a7236b41e66326bc65cbb43a9ebf9c1327a4d1bf3`. Do not overwrite that report.

- [x] 9.1 Grandfathered completion contract: sites completed before the ninth step stay complete; fresh sites still require nine; unlock/skip/re-lock preserves completion. Maps verify rows 50/51/53.
- [x] 9.2 Stable identity + read-only preview plan: type/role/template/post ID first; legacy slug aliases persist only on mutation; custom slugs require explicit map. Maps UI empty-cards defect and GET no-write.
- [x] 9.3 Runtime coverage for remaining partial/untested rows: resume, persistence-failure fence, WP template/Blog/Services/Testimonials render, REST nonce, Layer 1/guide-absent, canonical first-write/overwrite, existing-page update, placeholder unmarked + factual exclusion.
- [x] 9.4 One-to-one mapping + server-side mapping confirmation: duplicate type/post ID, plan conflicts, mixed-batch atomic rollback, missing confirmation, confirmed success, UI exclusive selects.
- [x] 9.5 Causal mapping rejection proofs: Home/Blog role remap, missing live object, non-page object, subset/superset confirmation — all rejected with zero state/page/ACF/canonical/log writes and unchanged post counts.
- [x] 9.6 Confirmed exact-set mapping success proof: unchanged post content, template/meta, ACF rows, canonical store, post/page counts, page writes, and logs; only stable identity state/type changes.
- [x] 9.7 Independent accessible mapping confirmation dialog: distinct node, focus, cancel/confirm/Escape behavior, and exact-set payload; server remains authoritative. Executable client and markup tests added.
- [x] 9.8 Live completed-site card model: per-page list keyed by unique generated `post_id` (never row count or slug); resolved shells show blueprint type/status/layout/edit with no mapping select; unresolved custom shells show one mapping card with page label/edit and mapping select only; unavailable blueprints excluded from the per-page list and progress; progress total counts unique eligible shell post IDs, complete counts only resolved complete; post-map refresh collapses unresolved to exactly one resolved card; `[hidden]` controls visually/semantically absent (CSS `[hidden]` guard added). Executable client + PHP runtime tests added.
- [x] 9.9 Mapping dialog cancel/Escape local no-op: cancel and Escape close the dialog, restore focus to the exact trigger, preserve all mapping selections and form state, stay on the Internal Page Builder panel, and never fetch/dispatch/render server state or show a generic step-canceled status. Confirm path unchanged (exact confirmed map payload + normal response hydration). Executable client tests added.
- [x] 9.10 Map-only identity mapping is metadata-only: `map_pages` + exact confirmation with no build/process/skip/convert/overwrite action never sets current_step/step_status, never marks complete/running/failed, never revokes grandfathered completion, and never changes card completion counts. Controller detects map-only before generic progress writes (capability/nonce/locks/fence still apply); builder returns distinct `action: mapped` + `page_types_assigned`; client shows specific "Page types assigned" outcome and refreshes cards. Causal controller+builder+client tests added, including negative control.
