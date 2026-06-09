# Apply Progress: Wizard AI Content Harness

## Slice

- Workload mode: chained PR slice
- Chain strategy: feature-branch-chain / tracker branch `feature/wizard-setup`
- Current work unit: Phase 3 — UI, payload, and verification
- Review budget: 400 changed lines

## Completed Tasks

- [x] 1.1 Created `inc/wizard/class-ai-content-harness.php` with page constants, prompt methods, context filtering, required-context validation, field contracts, and response validation.
- [x] 1.2 Encoded Layer 1/2/3 prompts in PHP only, with no guide-file runtime reads and placeholder replacement for `{{item_count}}` and `{{client_json}}`.
- [x] 1.3 Added field allowlists/blocklists for all 27 Home layouts, blocking non-editorial fields and preserving the user decision that service names must come from `company_services.service_name`.
- [x] 1.4 Added optional `$system = ''` provider signature support without breaking existing three-argument callers.
- [x] 1.5 Updated Ollama provider request messages to send `[system,user]` when a system prompt is present and the legacy single user message otherwise.
- [x] 2.1 Used harness-approved client context and required-data validation before generation; media, colors, social links, IDs, and other non-approved data stay out of prompts.
- [x] 2.2 Blocked Home section generation before provider calls when required client data is missing, returning the spec warning message.
- [x] 2.3 Replaced the flat Home Builder prompt with harness Layer 1 + Layer 2 system prompts and Layer 3 per-layout user prompts using `{layout,item_count}` rows.
- [x] 2.4 Validated decoded AI JSON through `AI_Content_Harness::validate_fields()` before saving and kept safe placeholder copy for failed sections.
- [x] 2.5 Removed invented Home Builder fallbacks for badge years, testimonials, ratings, gallery items/labels, default URLs, fallback service names, duration, and radius.
- [x] 2.6 Seeded `services-v1`, `services-v2`, and `services-v3` repeaters only from `company_services.service_name`; AI output can only supply description-style row copy and section-level copy.
- [x] 2.7 Constrained generic layout building to harness fillable fields and removed URL/duration/radius/select default invention paths.
- [x] 3.1 Added numeric item-count controls to Home section row templates, including repeater detection, layout-specific defaults, and `sections[index][layout|item_count]` payload names.
- [x] 3.2 Updated wizard TypeScript to collect `sections[] = [{layout,item_count}]`, block empty Home section submissions, and show missing required Client Data warnings in the Home Builder UI.
- [x] 3.3 Styled Home section item-count controls and the harness warning notice without changing ACF JSON.
- [x] 3.4 Ran PHP syntax checks for the harness/provider/builder/layout/UI PHP files.
- [x] 3.5 Ran the project build and manually verified service-name sourcing plus blocked-field stripping paths from the Phase 2 implementation.

## Verification

- `php -l "inc/wizard/class-ai-content-harness.php"` — passed, no syntax errors.
- `php -l "inc/wizard/class-ai-provider.php"` — passed, no syntax errors.
- `php -l "inc/wizard/class-ollama-provider.php"` — passed, no syntax errors.
- `php -l "inc/wizard/class-client-data-fields.php"` — passed, no syntax errors.
- `php -l "inc/wizard/class-step-home-page-builder.php"` — passed, no syntax errors.
- `php -l "inc/wizard/class-flexible-content-layouts.php"` — passed, no syntax errors.
- `php -l "inc/wizard/wizard-init.php"` — passed, no syntax errors.
- `npm run build` — passed; `tsc` and `vite build` completed successfully.
- Manual verification: `Step_Home_Page_Builder::service_rows()` still sources service names only from `client_data.company_services[].service_name`; `AI_Content_Harness::validate_fields()` still strips keys outside allowlists and all blocked keys before saving.

## Notes

- Phase 2 used PHP-side item-count parsing/defaults so harness prompts can receive `{layout,item_count}` without adding Phase 3 UI controls.
- Phase 3 now sends row objects with explicit `item_count`; PHP-side defaults remain as the back-compatible fallback for older string-only payloads.
- Untracked reference files (`Wizard ai harness prompt guide.md`, `wizard-prd.html`, `wizard-ai-harness-context.md`) were not read or modified.
