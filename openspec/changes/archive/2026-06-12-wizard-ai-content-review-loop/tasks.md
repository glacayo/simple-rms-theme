# Tasks: Wizard AI Content Review Loop

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | PR1 140-200; PR2 280-380; PR3 120-180; PR4 80-150; PR5 80-150; PR6 60-120 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR1 Harness → PR2 Reviewer → PR3 Integration → PR4 Logging → PR5 Content Calibration → PR6 Content Polish Calibration |
| Delivery strategy | auto-chain |
| Chain strategy | feature-branch-chain |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Expose harness rules | PR 1 | base=tracker |
| 2 | Add unwired reviewer | PR 2 | base=PR 1 |
| 3 | Wire review loop | PR 3 | base=PR 2 |
| 4 | Finish logging/report | PR 4 | base=PR 3 |
| 5 | Calibrate customer clarity | PR 5 | base=PR 4 |
| 6 | Calibrate content polish | PR 6 | base=PR 5 |

## Guardrails

- No UI v1; no native WP AI APIs; no API keys in code/chat/logs; no dependency on excluded docs; bounded prod logging; dev-only report.

## Phase 1: PR1 Harness Rules
Rollback: revert `inc/wizard/class-ai-content-harness.php`.

- [x] 1.1 In `inc/wizard/class-ai-content-harness.php`, add `EDITORIAL_RULES` + `get_editorial_rules( string $layout ): array` as the single rules source.
- [x] 1.2 Tokenize `layout_rules()` / `get_layer3()` numeric text from that map; do not touch non-harness files.
- [x] 1.3 Verify: `php -l inc/wizard/class-ai-content-harness.php`; static = no duplicate numerics, unresolved tokens, excluded docs, reviewer leakage, builder leakage, logging leakage, or UI changes; `npm run build` only if assets touched.

## Phase 2: PR2 Reviewer Service
Rollback: delete `inc/wizard/class-ai-content-reviewer.php`.

- [x] 2.1 Create `inc/wizard/class-ai-content-reviewer.php` with `review()`, taxonomy, critique prompt/filter, diagnosis-first flow, layout skip, provider reuse.
- [x] 2.2 Add private critique/diagnose/rewrite/decode helpers, `MAX_PASSES = 2`, soft→hard escalation, timeout/error fallback, and non-fillable/hallucinated-key rejection; keep class unwired.
- [x] 2.3 Verify: `php -l inc/wizard/class-ai-content-reviewer.php inc/wizard/class-ai-content-harness.php`; static = no native WP AI APIs, no key leakage, no prod report, no builder coupling; WP Admin unchanged; `npm run build` only if assets touched.

## Phase 3: PR3 Builder Integration
Rollback: remove reviewer wiring or define `WIZARD_REVIEW_ENABLED=false`.

- [x] 3.1 In `inc/wizard/class-step-home-page-builder.php`, inject `AI_Content_Reviewer` via nullable DI and add request-scoped prior payload accumulation in `run()`.
- [x] 3.2 Update `generate_section_overrides()` to review between decode and validate, pass sections 1..N-1, honor `WIZARD_REVIEW_ENABLED`, and keep original decoded fallback.
- [x] 3.3 Verify: `php -l inc/wizard/class-step-home-page-builder.php inc/wizard/class-ai-content-reviewer.php inc/wizard/class-ai-content-harness.php`; static = revalidate reviewer output, no cross-request state, no UI v1; `npm run build` only if assets touched.
- [x] 3.4 Manual WP Admin verification: happy path, repetition context, timeout/credential fallback, and kill-switch bypass.

## Phase 4: PR4 Dev Report + Bounded Production Logging
Rollback: revert logging/report changes in reviewer/builder only.

- [x] 4.1 Add bounded review logging through existing `Logger`: prod logs only `section`, `status`, `iterations`; debug exposes report.
- [x] 4.2 Ensure prompts, full bodies, diagnoses, token estimates, and secrets never reach production logs; report stays off when `WP_DEBUG` is false.
- [x] 4.3 Verify: `php -l inc/wizard/class-ai-content-reviewer.php inc/wizard/class-step-home-page-builder.php inc/wizard/class-ai-content-harness.php`; static = bounded prod payload, dev-only report, no PHP/server error-log writes; `npm run build` only if assets touched.
- [x] 4.4 Manual WP Admin verification: compare `WP_DEBUG` off/on logs after one run.

## Phase 5: PR5 Content Calibration
Rollback: revert prompt/spec calibration in harness/reviewer and PR5 OpenSpec updates only.

- [x] 5.1 Update OpenSpec specs/design/tasks/apply-progress for customer clarity, service-grounded headings, and overtechnical-language review without changing PR1-PR4 completion history.
- [x] 5.2 In `inc/wizard/class-ai-content-harness.php`, compactly add customer-first Layer 1 rules and refine service/SEO layout guidance without adding brittle technical-term blacklists.
- [x] 5.3 In `inc/wizard/class-ai-content-reviewer.php`, add `overtechnical_language`, client-context-aware calibration checks, and rewrite guidance while preserving bounded production logging and existing guardrails.
- [x] 5.4 Manual WP Admin content QA: generate Home copy with real `company_services` and confirm services, headings, and customer clarity in runtime output.

## Phase 6: PR6 Content Polish Calibration
Rollback: revert PR6 prompt/spec calibration in harness/reviewer and PR6 OpenSpec updates only.

- [x] 6.1 Update OpenSpec specs/design/tasks/apply-progress for contractor-agnostic lexical variety, section-angle separation, and missing-differentiator calibration without changing PR1-PR5 completion history.
- [x] 6.2 In `inc/wizard/class-ai-content-harness.php`, compactly add agnostic lexical variety and section-angle guidance while preserving the concrete-first paragraph opening rule and avoiding vertical-specific examples.
- [x] 6.3 In `inc/wizard/class-ai-content-reviewer.php`, add `repetitive_wording`, `section_angle_overlap`, and `missing_differentiator` diagnoses and rewrite guidance while requiring service-specific language to come only from `company_services`/trusted context.
- [x] 6.4 Manual WP Admin content QA: generate Home copy with real `company_services` and confirm reduced repeated praise, distinct section angles, concrete openings, and no invented differentiators.
