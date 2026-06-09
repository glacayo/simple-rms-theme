# Tasks: Wizard AI Content Harness

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 550-850 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 harness/provider → PR 2 builder → PR 3 UI/checks |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Harness + provider BC | PR 1 | Base main. |
| 2 | Builder validation + no-invention cleanup | PR 2 | Base PR 1. |
| 3 | UI item counts + verification | PR 3 | Base PR 2. |

## Phase 1: PR 1 Harness and Provider

- [x] 1.1 Create `inc/wizard/class-ai-content-harness.php` with `PAGE_*`, `get_layer1()`, `get_layer2()`, `get_layer3()`, `get_harness_context()`, `validate_required_context()`, `get_fillable_fields()`, `get_blocked_fields()`, `validate_fields()`.
- [x] 1.2 Encode Layer 1/2/3 prompts in PHP only; include no-invention rules, `{{item_count}}`, `{{client_json}}`, and no guide-file reads.
- [x] 1.3 Map all 27 Home layouts in `inc/wizard/class-ai-content-harness.php`; allow editorial copy only and block URLs, media, selects, shortcodes, testimonials, ratings, stats, badges, galleries, projects, and invalid fallback fields.
- [x] 1.4 Add optional `$system = ''` support to `inc/wizard/class-ai-provider.php::generate()` while preserving existing 3-argument callers.
- [x] 1.5 Update `inc/wizard/class-ollama-provider.php` to send `[system,user]` messages when `$system` exists; otherwise keep legacy single user message.

## Phase 2: PR 2 Builder and Data Rules

- [x] 2.1 Approved-context keys and required-data checks live in `inc/wizard/class-ai-content-harness.php` (`APPROVED_CONTEXT_FIELDS`, `REQUIRED_CONTEXT_FIELDS`, `validate_required_context`, `get_harness_context`). `class-client-data-fields.php` was intentionally not modified; no coupling needed.
- [x] 2.2 Refactor `inc/wizard/class-step-home-page-builder.php` to block before AI calls when required data is missing and show the spec warning.
- [x] 2.3 Replace flat prompts in `inc/wizard/class-step-home-page-builder.php` with harness system/user prompts per `{layout,item_count}` row.
- [x] 2.4 Validate decoded AI JSON through `AI_Content_Harness::validate_fields()` before saving; strip unknown/blocked keys and preserve failed-section placeholders.
- [x] 2.5 Remove invented fallbacks in `inc/wizard/class-step-home-page-builder.php`: badge years, testimonials, ratings, gallery labels, default URLs, service lists, duration, and radius.
- [x] 2.6 Update services-v1/v2/v3 in `inc/wizard/class-step-home-page-builder.php`: repeater names must come only from `company_services.service_name`; AI may write descriptions and section copy only.
- [x] 2.7 Constrain `inc/wizard/class-flexible-content-layouts.php::build_generic_section()` to harness fillable fields and remove URL/duration/radius defaults.

## Phase 3: PR 3 UI, Payload, and Verification

- [x] 3.1 Add numeric item-count controls to `inc/wizard/wizard-init.php` row templates with layout defaults and payload names.
- [x] 3.2 Update `src/ts/admin/wizard.ts` to collect `sections[] = [{layout,item_count}]`, block empty selections, and show missing-data warnings.
- [x] 3.3 Style count controls and harness warnings in `src/scss/admin/wizard.scss` without touching ACF JSON.
- [x] 3.4 Run `php -l` on all changed PHP files: harness, provider, Ollama, client data, Home Builder, layouts, and `wizard-init.php`.
- [x] 3.5 Run `npm run build` or `npx tsc --noEmit`; manually verify service-name sourcing and blocked-field stripping scenarios.
