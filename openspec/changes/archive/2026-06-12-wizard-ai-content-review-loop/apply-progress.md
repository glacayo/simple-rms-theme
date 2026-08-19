# Apply Progress: Wizard AI Content Review Loop

## Mode

- Artifact store: OpenSpec
- Implementation mode: Standard (strict TDD disabled; no automated test runner installed)
- Delivery: force-chained / feature-branch-chain
- Current PR slice: PR6 Content Polish Calibration only

## Completed Tasks

- [x] 1.1 Added `AI_Content_Harness::EDITORIAL_RULES` and `AI_Content_Harness::get_editorial_rules( string $layout ): array` as the harness-owned rules source.
- [x] 1.2 Tokenized Layer 1 and Layer 3/layout numeric editorial text so prompt output is formatted from the shared rules map while keeping generated prompt semantics stable.
- [x] 1.3 Verified `php -l inc/wizard/class-ai-content-harness.php`; static review found no excluded-doc dependency, no API keys, and no reviewer/builder/UI/logging changes.
- [x] 2.1 Added unwired `AI_Content_Reviewer` with `review()`, the approved diagnosis taxonomy, critique prompt/filter, diagnosis-first flow, layout skip, and provider reuse through `AI_Provider_Registry::make_provider()->generate()`.
- [x] 2.2 Added private critique/diagnose/rewrite/decode helpers with `MAX_PASSES = 2`, soft-to-hard rewrite directives, original-payload fallback on provider failure, last-version return on budget exhaustion, and rejected rewrites for disallowed keys.
- [x] 2.2 blocker fix: extended reviewer disallowed-key validation to service repeaters so hallucinated or blocked service subfields such as `service_title`, `service_image`, and `service_name` discard rewrites before PR3 integration.
- [x] 2.3 Verified `php -l inc/wizard/class-ai-content-reviewer.php` and `php -l inc/wizard/class-ai-content-harness.php`; static review found no native WP AI APIs, no API keys, no production logging/reporting, no builder coupling, no excluded-doc dependency, and no asset changes.
- [x] 3.1 Added nullable `AI_Content_Reviewer` DI to `Step_Home_Page_Builder` with lazy construction from the existing harness, and introduced a request-scoped `$prior_section_payloads` list inside `run()`.
- [x] 3.2 Wired review between `decode_json_content()` and `validate_fields()`, passing the current section payload plus sections 1..N-1, enriching reviewer config with client context and item count, honoring `WIZARD_REVIEW_ENABLED`, and falling back to the original decoded payload on reviewer error or invalid output.
- [x] 3.3 Verified builder/reviewer/harness PHP syntax and static PR3 guardrails: reviewer output is revalidated, prior context is local to `run()`, no `State_Manager` persistence is used, no UI/assets were touched, and PR4 logging/reporting was not implemented.
- [x] 4.1 Added bounded review-result logging through the existing wizard `Logger` in `Step_Home_Page_Builder`: production context is limited to `section`, `status`, and `iterations`; debug mode may include the reviewer report.
- [x] 4.2 Confirmed production review logs do not include prompts, generated bodies, diagnoses, token estimates, secrets, client JSON, provider responses, or full reports; `AI_Content_Reviewer::report()` remains `null` unless `WP_DEBUG` is true.
- [x] 4.3 Verified PR4 code/static scope with PHP syntax checks and static review: bounded prod payload, dev-only report, no PHP/server `error_log()` writes, and no UI/assets touched.
- [x] 4.3 blocker fix: tightened both debug-report gates so rich report creation/inclusion requires `WP_DEBUG === true`, not truthy values such as `'false'`, `'0'`, or `1`.
- [x] W2 verification warning fix: hardened `WIZARD_REVIEW_ENABLED` parsing so undefined remains enabled, explicit false values (`false`, `0`, `'0'`, `'false'`, `'off'`, `'no'`) disable review, and explicit true values (`true`, `'1'`, `'true'`, `'on'`, `'yes'`) enable review.
- [x] 5.1 Updated OpenSpec specs, design, tasks, and apply-progress for PR5 customer clarity, service-grounded headings, and overtechnical-language review while preserving PR1-PR4 progress and pending manual WP Admin tasks.
- [x] 5.2 Added compact customer-first Layer 1 rules in `AI_Content_Harness` and refined service/SEO layout guidance so headings favor concrete `company_services` search intent without brittle technical-term blacklists.
- [x] 5.3 Added `overtechnical_language` to `AI_Content_Reviewer`, included trusted client context in critique/rewrite prompt payloads, and calibrated critique/rewrite guidance for jargon, abstract openings, repeated abstract quality concepts, and unsupported service mentions.
- [x] 6.1 Updated OpenSpec specs, design, tasks, and apply-progress for PR6 contractor-agnostic lexical variety, section-angle separation, and missing-differentiator calibration while preserving PR1-PR5 progress and pending manual WP Admin tasks.
- [x] 6.2 Added compact agnostic lexical variety and section-angle guidance in `AI_Content_Harness`, preserving concrete-first paragraph openings and avoiding vertical-specific examples.
- [x] 6.3 Added `repetitive_wording`, `section_angle_overlap`, and `missing_differentiator` diagnoses and rewrite guidance in `AI_Content_Reviewer`, while keeping service-specific language constrained to `company_services`/trusted context and preserving bounded production logging.

## Files Changed

| File | Action | What Was Done |
|------|--------|---------------|
| `inc/wizard/class-ai-content-harness.php` | Modified | Added shared editorial rules, public static accessor, token replacement helpers, tokenized existing prompt rule text, customer-first calibration, and PR6 lexical-variety/section-angle guidance. |
| `inc/wizard/class-ai-content-reviewer.php` | Created/Modified | Added dead-code review service with critique/diagnose/rewrite flow, taxonomy, prompt contracts, fallback handling, harness-rule consumption, service-repeater subfield rejection, and PR6 polish diagnoses. |
| `inc/wizard/class-step-home-page-builder.php` | Modified | Added nullable/lazy reviewer integration, request-scoped prior payload accumulation, review-before-validate wiring, kill-switch bypass, original decoded fallback, and PR4 bounded review logging. |
| `openspec/changes/wizard-ai-content-review-loop/specs/wizard-ai-content-harness/spec.md` | Modified | Added customer content calibration, lexical-variety, section-angle, and service-grounding scenarios. |
| `openspec/changes/wizard-ai-content-review-loop/specs/wizard-ai-content-reviewer/spec.md` | Modified | Added PR5/PR6 content calibration review scenarios and `overtechnical_language`, `repetitive_wording`, `section_angle_overlap`, and `missing_differentiator` diagnoses. |
| `openspec/changes/wizard-ai-content-review-loop/design.md` | Modified | Documented PR5/PR6 calibration approach and updated the PR slice plan. |
| `openspec/changes/wizard-ai-content-review-loop/tasks.md` | Modified | Added and marked PR6 code/static tasks complete while keeping deferred manual WP Admin verification unchecked. |
| `openspec/changes/wizard-ai-content-review-loop/apply-progress.md` | Modified | Preserved PR1-PR5 progress and added PR6 progress/verification. |
| `openspec/changes/wizard-ai-content-review-loop/verify-report.md` | Modified | Recorded W2 as resolved while keeping manual WP Admin verification warnings pending. |

## Verification

- ✅ `php -l inc/wizard/class-ai-content-harness.php`
- ✅ `php -l inc/wizard/class-ai-content-reviewer.php`
- ✅ `php -l inc/wizard/class-step-home-page-builder.php`
- ✅ PR2 blocker re-review: service repeater rewrites now use harness-derived repeater contracts and reject blocked/hallucinated subfields before builder integration.
- ✅ PR3 static review: reviewer is called after decode and before final validation; fallback preserves original decoded content; final payload always flows through `validate_fields()`; prior context is local to one `run()` invocation; kill switch bypasses reviewer when `WIZARD_REVIEW_ENABLED` is defined false.
- ✅ Static cumulative scope check: no UI, assets, native WP AI APIs, API keys, production rich report logging, or excluded-doc dependencies added.
- ✅ PR4 blocker verification: review logs use the existing `Logger` (`wp_options`) and production context is bounded to `section`, `status`, and `iterations`; debug-only `report` is created/included only when `WP_DEBUG === true`; truthy values such as `'false'`, `'0'`, or `1` do not enable rich reports; no prompts, full bodies, full diagnoses, token estimates, API keys, secrets, client JSON, raw provider responses, or PHP/server `error_log()` writes are added.
- ✅ W2 fix verification: `WIZARD_REVIEW_ENABLED` no longer uses a loose `(bool)` cast; common explicit false string values disable the review loop while undefined remains enabled by default.
- ✅ PR5 syntax verification: `php -l inc/wizard/class-ai-content-harness.php`; `php -l inc/wizard/class-ai-content-reviewer.php`; `php -l inc/wizard/class-step-home-page-builder.php`.
- ✅ PR5 static scope check: no UI/assets, native WP AI APIs, API keys, staging, commits, pushes, PR creation, production rich logging, or excluded-doc dependencies added; calibration does not blacklist technical terms and keeps manual tasks pending.
- ✅ PR6 syntax verification: `php -l inc/wizard/class-ai-content-harness.php`; `php -l inc/wizard/class-ai-content-reviewer.php`; `php -l inc/wizard/class-step-home-page-builder.php`.
- ✅ PR6 static scope check: no UI/assets, native WP AI APIs, API keys, staging, commits, pushes, PR creation, rich production reports, excluded-doc dependencies, or vertical-specific service examples added; manual WP Admin tasks remain pending.
- ⏭️ `npm run build` not run because no assets were touched.
- ✅ WP Admin manual verification tasks 3.4, 4.4, 5.4, 6.4 completed and accepted by user (2026-06-12).

## Deviations from Design

None — PR6 follows the design boundary: prompt/rule calibration only, no UI/assets, no provider changes, no vertical-specific service prescriptions, and bounded production logging remains unchanged.

## Issues Found

Fresh PR2 review found that service repeater subfields were not covered by reviewer disallowed-key validation. Resolved in PR2 scope before PR3 wiring. PR4 blocker found and resolved: debug report gates previously used truthiness for `WP_DEBUG`; both now require strict `WP_DEBUG === true`. Verify warning W2 found that `WIZARD_REVIEW_ENABLED` used loose `(bool)` casting; resolved with explicit true/false token parsing. PR5 QA feedback found copy calibration needed more customer clarity and stricter service grounding; addressed through compact harness/reviewer prompt changes. PR6 QA feedback found remaining repeated praise, section-angle overlap, abstract openings, and missing differentiator risk; addressed without hardcoding vertical-specific services.

## Remaining Tasks

None — all 22 tasks complete (18 code/static + 4 manual WP Admin accepted by user).

## Workload / PR Boundary

- Mode: chained PR slice (`feature-branch-chain`)
- Current work unit: PR6 Content Polish Calibration
- Boundary: starts from PR1-PR5 applied/reviewed and PR5 verified PASS WITH WARNINGS; ends with compact contractor-agnostic lexical variety, section-angle separation, and missing-differentiator prompt calibration only. No UI/assets, staging, commit, push, or PR creation.
- Rollback: revert PR6 prompt/spec calibration in `inc/wizard/class-ai-content-harness.php`, `inc/wizard/class-ai-content-reviewer.php`, and PR6 OpenSpec progress updates.
