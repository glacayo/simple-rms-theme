# Verification Report: Wizard AI Prompt Guide Parity

## Change

- **Name:** wizard-ai-prompt-guide-parity
- **Mode:** openspec
- **Date:** 2026-06-10

---

## Completeness Table

| Artifact | Exists | Status |
|----------|--------|--------|
| proposal.md | ✅ | Read |
| specs/wizard-ai-content-harness/spec.md | ✅ | Read |
| specs/wizard-ai-prompt-guide/spec.md | ✅ | Read |
| specs/wizard-home-page-builder/spec.md | ✅ | Read |
| design.md | ✅ | Read |
| tasks.md | ✅ | Read |
| apply-progress.md | ✅ | Read |

---

## Task Completion

| Task | Checked | Status |
|------|---------|--------|
| 1.1 Rewrite get_layer1() and get_layer2() | ✅ | DONE |
| 1.2 Update get_layer3() and layout_rules() | ✅ | DONE |
| 1.3 Preserve product decisions | ✅ | DONE |
| 2.1 Add TEXT_REPEATER_FIELDS and get_text_repeater_fields() | ✅ | DONE |
| 2.2 Expand FILLABLE_FIELDS/BLOCKED_FIELDS | ✅ | DONE |
| 2.3 Extend validate_fields() and sanitize_allowed_value() | ✅ | DONE |
| 3.1 Update placeholder_copy() for repeaters | ✅ | DONE |
| 3.2 Update section_data() merge flow | ✅ | DONE |
| 4.1 Revise guide doc to match shipped contracts | 🔲 | DEFERRED — guide file prepared locally but excluded from commit scope per user request |
| 4.2 Update guide field classifications | 🔲 | DEFERRED — guide file prepared locally but excluded from commit scope per user request |
| 5.1 Run php -l and npm build check | ✅ | THIS REPORT |
| 5.2 Manual local wizard/runtime checks | 🔲 | Manual — see warnings below |

Runtime implementation tasks (Phase 1–3) are checked in `tasks.md`. Phase 4 guide synchronization is deferred because `Wizard ai harness prompt guide.md` is intentionally excluded from this commit scope. Phase 5 verification tasks are addressed by this report where automated checks are available.

---

## Build / Syntax Evidence

| Command | Result |
|---------|--------|
| `php -l inc/wizard/class-ai-content-harness.php` | ✅ No syntax errors detected |
| `php -l inc/wizard/class-step-home-page-builder.php` | ✅ No syntax errors detected |
| `php -l inc/wizard/class-flexible-content-layouts.php` | ✅ No syntax errors detected |
| `php -l inc/wizard/class-content-builder.php` | ✅ No syntax errors detected |
| `npm run build` | ✅ Passed after TS/SCSS wizard hydration changes and indentation fix |

---

## Spec Compliance Matrix

### Spec: wizard-ai-content-harness

| Requirement / Scenario | Evidence | Status |
|------------------------|----------|--------|
| **Per-Layout Editorial Rules** | `layout_rules()` method (lines 184–387) returns heredoc strings for every layout with fillable fields, including word counts, paragraph structure, field roles, and `gallery-grid` returning `{}` | ✅ COMPLIANT |
| Layout receives editorial rules in prompt (about-us) | `about-us` rules include P1/P2/P3 purpose, 50–60 word constraint, subheadline rule | ✅ COMPLIANT |
| Layout with no fillable fields gets no rules (gallery-grid) | `gallery-grid` rules: "Return an empty JSON object: {}." | ✅ COMPLIANT |
| **Text Repeater Enablement** | `TEXT_REPEATER_FIELDS` const (lines 89–96) maps all 6 layouts | ✅ COMPLIANT |
| FAQ text repeater enabled (faq-v1 with 5 items) | `faq_v1_faqs` → `[faq_question, faq_answer]` in `TEXT_REPEATER_FIELDS`; `faq_v1_faqs` in `FILLABLE_FIELDS`; `{{item_count}}` resolved in `layout_rules` | ✅ COMPLIANT |
| Factual repeater stays blocked (testimonials-v1) | `testimonials_v1_items` in `BLOCKED_FIELDS` for `testimonials-v1` | ✅ COMPLIANT |
| Slider images stay blocked while text enabled | `slide_bg_image`, `slide_cta_url` in `BLOCKED_FIELDS`; `slider_slides` → `[slide_subheadline, slide_headline, slide_text, slide_cta_text]` in `TEXT_REPEATER_FIELDS` | ✅ COMPLIANT |
| **Versioned Prompt Contracts** | All three layers are PHP heredoc/nowdoc — no filesystem reads | ✅ COMPLIANT |
| Prompts delivered without guide file | `get_layer1()`, `get_layer2()`, `get_layer3()` return PHP-encoded data with no file I/O | ✅ COMPLIANT |
| Layer 1 includes editorial standards | `get_layer1()` contains headlines word counts, paragraph density, CTA conventions, no-invention guardrails | ✅ COMPLIANT |
| Layer 2 defaults to PAGE_HOME | `get_layer2()` defaults `PAGE_HOME`; non-Home logs warning and falls back | ✅ COMPLIANT |

### Spec: wizard-ai-prompt-guide

| Requirement / Scenario | Evidence | Status |
|------------------------|----------|--------|
| **Guide-Harness Synchronization** | Deferred. `Wizard ai harness prompt guide.md` was prepared locally but is intentionally not staged for this commit per user request. | ⚠️ DEFERRED |
| Harness change triggers guide update | Deferred guide-sync work is documented in `tasks.md` and this report. | ⚠️ DEFERRED |
| Guide-harness contradiction resolved | Deferred until the guide documentation slice is committed. Runtime PHP remains the source of truth. | ⚠️ DEFERRED |
| **Product Decision Documentation** | Deferred until the guide documentation slice is committed. Runtime product decisions are encoded in PHP prompts/rules. | ⚠️ DEFERRED |
| Deviation documented with rationale | Deferred until the guide documentation slice is committed. | ⚠️ DEFERRED |
| **Field Classification Accuracy** | Deferred for the guide file; runtime classification is verified through `FILLABLE_FIELDS`, `BLOCKED_FIELDS`, and `TEXT_REPEATER_FIELDS`. | ⚠️ DEFERRED |
| Guide matches harness for newly enabled repeaters | Deferred until the guide documentation slice is committed. | ⚠️ DEFERRED |

### Spec: wizard-home-page-builder

| Requirement / Scenario | Evidence | Status |
|------------------------|----------|--------|
| AI fills content for known layout | `section_data()` merges validated `copy` into ACF structure via `section_value()` with fillable-field filtering | ✅ COMPLIANT |
| AI fills content for unmapped layout | `build_generic_section()` constrained by `get_fillable_fields()` (via harness) — not modified but still functions correctly | ✅ COMPLIANT |
| AI call fails — fallback | `placeholder_copy()` + `placeholder_repeater_rows()` produce row-shaped placeholders for text repeaters, scalar placeholders for other fields | ✅ COMPLIANT |
| Missing required context blocks generation | `validate_required_context()` called in `run()`; returns missing fields; WP_Error returned with status 400 | ✅ COMPLIANT |
| Item count injected into repeater prompt | `get_layer3()` receives `$item_count`; `layout_rules()` resolves `{{item_count}}` via `strtr` | ✅ COMPLIANT |
| Newly enabled repeater decoded and merged | `validate_fields()` strips unknown sub-keys per `TEXT_REPEATER_FIELDS` allowlist; `section_data()` passes validated arrays through `section_value()` which recursively handles arrays | ✅ COMPLIANT |
| Extra AI response keys stripped | `validate_fields()` checks `fillable` and `blocked` flip-arrays; unknown keys dropped | ✅ COMPLIANT |

---

## Correctness Table

| Implementation Decision | Spec Reference | Implementation | Status |
|------------------------|----------------|----------------|--------|
| Lean context (company_name, company_covered_areas, company_services) | Proposal §Scope, spec harness §Versioned Prompt Contracts | `APPROVED_CONTEXT_FIELDS` = `['company_name', 'company_covered_areas', 'company_services']` | ✅ CORRECT |
| `about_badge_label` fillable | Proposal §Approach, spec harness, guide Product Decisions | In `FILLABLE_FIELDS['about-us']` | ✅ CORRECT |
| `video_v1_video_title` fillable | Proposal §Approach, spec harness, guide Product Decisions | In `FILLABLE_FIELDS['video-v1']` | ✅ CORRECT |
| `about_text` 3 paragraphs × 50–60 words | Design §Architecture, spec harness §Per-Layout Editorial Rules | `layout_rules('about-us')`: "exactly three HTML paragraphs using <p> tags. Each paragraph must contain 50 to 60 words." | ✅ CORRECT |
| Badges as local directory/profile links | Proposal §Approach, spec harness, guide | `layout_rules('badges')`: "Frame local directories or platforms where the business has a public profile" | ✅ CORRECT |
| Service names from Client Data only | Design §Architecture, spec builder, guide | `service_rows()` in builder sources `client_data['company_services']`; harness Layer 3 instructs AI not to return `service_title`/`service_name` | ✅ CORRECT |
| `slide_bg_image` and `slide_cta_url` blocked | Spec harness §Text Repeater Enablement | In `BLOCKED_FIELDS['slider']` | ✅ CORRECT |
| `stat_number` blocked | Spec harness §Text Repeater Enablement | In `BLOCKED_FIELDS['cta-v3']` | ✅ CORRECT |
| `card_highlight` blocked | Spec harness §Text Repeater Enablement | In `BLOCKED_FIELDS['vision-mission-v1']` | ✅ CORRECT |
| `{{item_count}}` resolved in layout_rules (not strtr single-pass) | Design §Architecture | `layout_rules()` uses explicit `strtr` with `['{{item_count}}' => (string) max(0, $item_count)]` | ✅ CORRECT |
| Repeater placeholder fallback produces row-shaped arrays | Design §Data Flow, spec builder | `placeholder_repeater_rows()` builds `item_count` rows of `{sub_field: value}` objects | ✅ CORRECT |
| Guide version bumped to 1.2 | Task 4.1 | Deferred: guide file is intentionally not staged in this commit per user request | ⚠️ DEFERRED |

---

## Design Coherence Table

| Design Decision | Implementation Match | Status |
|-----------------|---------------------|--------|
| Heredoc/nowdoc prompt storage | All three layers use PHP heredoc (`<<<'PROMPT'`, `<<<'RULES'`) | ✅ COHERENT |
| `TEXT_REPEATER_FIELDS` const with layout→repeater→subkeys | Present as `private const TEXT_REPEATER_FIELDS` with exact layout/repeater/subkey mapping from design | ✅ COHERENT |
| `get_text_repeater_fields()` public method | Present (lines 434–438) | ✅ COHERENT |
| `placeholder_copy()` skips repeater keys, emits row-shaped placeholders | `placeholder_copy()` iterates fillable fields, skips `_services` and `$text_repeaters`, then builds repeater rows separately | ✅ COHERENT |
| Service descriptions handled by existing `SERVICE_DESCRIPTION_FIELDS` path | `sanitize_allowed_value()` checks `SERVICE_DESCRIPTION_FIELDS` before text repeater path; `service_rows()` in builder still merges from `client_data` | ✅ COHERENT |
| Blocked factual/media/testimonial/project repeaters remain blocked | `testimonials_*_items`, `portfolio_*_projects`, `gallery_items`, `badges_items`, `area_cities`, etc. all in `BLOCKED_FIELDS` | ✅ COHERENT |
| Guide never read at runtime | No filesystem reads in `AI_Content_Harness`; all prompt data from class constants/methods | ✅ COHERENT |

---

## Issues

### CRITICAL

None.

### WARNING

| # | Issue | Context |
|---|-------|---------|
| W1 | No PHPUnit harness exists — runtime spec scenario compliance cannot be verified by automated test | All spec scenarios in all three specs require a running WordPress instance with the wizard active. No test infrastructure exists for these scenarios. |
| W2 | Manual WP Admin verification required for runtime behavior | Tasks.md task 5.2 requires manual local wizard checks for `about-us`, `slider`, `faq-v1`, `vision-mission-v1`, `vision-mission-v2`, `cta-v3`, and AI-failure fallback. This verification report satisfies the structural/static analysis portion only. |

### SUGGESTION

| # | Suggestion | Context |
|---|-----------|---------|
| S1 | Add PHPUnit tests for `validate_fields()`, `get_text_repeater_fields()`, and `layout_rules()` | The harness logic is pure PHP and highly testable. Unit tests would close the runtime scenario coverage gap for future changes. |

---

## Manual Runtime Verification Requirements

Because no PHPUnit test harness exists, the following scenarios **must** be manually verified in a WP Admin instance:

1. **`about-us`**: Verify 3-paragraph `about_text` with 50–60 words per paragraph, `about_badge_label` editable, `about_image` and `about_badge_years` blocked.
2. **`slider`**: Verify `slider_slides` repeater with `slide_subheadline`, `slide_headline`, `slide_text`, `slide_cta_text` fillable; `slide_bg_image` and `slide_cta_url` blocked.
3. **`faq-v1`**: Verify `faq_v1_faqs` repeater with `faq_question`/`faq_answer` pairs; correct `item_count` resolution.
4. **`vision-mission-v1`**: Verify `vm_v1_cards` repeater with `card_title`/`card_text`; `card_highlight` blocked.
5. **`vision-mission-v2`**: Verify `vm_v2_reasons` repeater with `reason_text`.
6. **`cta-v3`**: Verify `cta_v3_stats` repeater with `stat_label`; `stat_number` blocked.
7. **AI-failure fallback**: Verify that when AI provider returns error, text repeater fields get row-shaped placeholders (not scalar strings).
8. **Service sections**: Verify `services-*` layouts source `service_name` from `client_data.company_services`, not from AI output.

---

## Final Verdict

**PASS WITH WARNINGS**

- Runtime implementation tasks (Phase 1–3) are complete and correct; Phase 4 guide synchronization is deferred because the guide file is excluded from this commit scope.
- Runtime harness and builder specs are compliant at the source-inspection level; guide-documentation compliance is deferred and documented above.
- Design coherence is confirmed across all architectural decisions.
- PHP syntax checks pass on all modified files.
- `npm run build` passed after the TS/SCSS wizard hydration changes.
- Warnings reflect deferred guide documentation and the absence of PHPUnit infrastructure; manual WP Admin runtime checks are still required.
- The two Phase 5 verification tasks (5.1 php/build checks and 5.2 manual runtime checks) are addressed by this report: 5.1 is satisfied; 5.2 requires manual WP Admin verification listed above.
