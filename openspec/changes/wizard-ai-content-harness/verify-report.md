# Verification Report: Wizard AI Content Harness

**Change:** `wizard-ai-content-harness`  
**Mode:** openspec  
**Date:** 2026-06-08  
**Verifier:** sdd-verify (automated)

---

## Completeness Table

| Dimension | Artifact | Status |
|-----------|----------|--------|
| Proposal | `proposal.md` | Present |
| Specs (3) | `specs/wizard-ai-content-harness/spec.md`, `specs/wizard-home-page-builder/spec.md`, `specs/wizard-ai-configuration/spec.md` | Present |
| Design | `design.md` | Present |
| Tasks | `tasks.md` | Present |
| Implementation | All 8 target files | Present |
| Apply Progress | `apply-progress.md` | Present |

**Task completion: 17/17 (all checked).**

---

## Build / Type-Check / Syntax Evidence

| Check | Command | Result |
|-------|---------|--------|
| PHP syntax — `class-ai-content-harness.php` | `php -l` | **PASS** — No syntax errors |
| PHP syntax — `class-step-home-page-builder.php` | `php -l` | **PASS** — No syntax errors |
| PHP syntax — `class-flexible-content-layouts.php` | `php -l` | **PASS** — No syntax errors |
| PHP syntax — `class-ai-provider.php` | `php -l` | **PASS** — No syntax errors |
| PHP syntax — `class-ollama-provider.php` | `php -l` | **PASS** — No syntax errors |
| PHP syntax — `class-client-data-fields.php` | `php -l` | **PASS** — No syntax errors |
| PHP syntax — `wizard-init.php` | `php -l` | **PASS** — No syntax errors |
| TypeScript / SCSS build | `npm run build` (`tsc && vite build`) | **PASS** — 52 modules transformed, no errors |

---

## Spec Compliance Matrix

### Capability: `wizard-ai-content-harness`

| Spec Requirement | Scenario | Implementation Evidence | Status |
|------------------|----------|------------------------|--------|
| **Versioned Prompt Contracts** | Prompts delivered without guide file | `AI_Content_Harness::get_layer1()` returns hardcoded editorial system prompt; `get_layer2()` returns page context; `get_layer3()` composes layout-specific prompt with placeholder substitution. No filesystem reads. | **PASS** |
| **Layer 2 defaults to PAGE_HOME** | No page type argument | `get_layer2(string $page_type = self::PAGE_HOME)` — default parameter is `PAGE_HOME`. Only `PAGE_HOME` content is implemented; other types log warning and fall back. | **PASS** |
| **Extensible Page-Type Architecture** | Unsupported page type fallback | `PAGE_HOME`, `PAGE_ABOUT`, `PAGE_SERVICE`, `PAGE_LANDING`, `PAGE_BLOG`, `PAGE_CONTACT` constants defined. `get_layer2()` checks `self::PAGE_HOME !== $page_type` → logs warning → returns PAGE_HOME content. | **PASS** |
| **Client Context Filtering** | Non-approved fields stripped | `APPROVED_CONTEXT_FIELDS` allowlist includes `company_name`, `company_services`, etc. `get_harness_context()` iterates only approved keys; `sanitize_context_value()` strips keys containing `url`, `logo`, `color`, `social`, `slug`, or `id`. | **PASS** |
| **Missing Required Client Data Blocking** | Missing `company_name` returns warning | `REQUIRED_CONTEXT_FIELDS = ['company_name']`; `validate_required_context()` returns `['company_name']` when missing/empty. `Step_Home_Page_Builder::run()` calls this before AI calls and returns `WP_Error` with the spec message. | **PASS** |
| **All required fields present** | Empty array returned | When all required fields have values, `validate_required_context()` returns `[]`, allowing generation. | **PASS** |
| **Field Allowlist/Blocklist Enforcement** | Extra and blocked keys stripped | `FILLABLE_FIELDS` maps all 27 layouts to approved editorial-copy keys. `BLOCKED_FIELDS` maps all layouts to blocked keys (URLs, images, repeaters, stats, etc.). `validate_fields()` iterates decoded JSON, keeps only keys in fillable AND not in blocked. | **PASS** |
| **Invented badge data rejected** | `about_badge_years` blocked | `BLOCKED_FIELDS['about-us']` includes `about_badge_years`. Verified present in code. | **PASS** |
| **URL field rejected** | `cta_v1_button_url` blocked | `BLOCKED_FIELDS['cta-v1']` includes `cta_v1_button_url`. Verified present. | **PASS** |
| **Testimonial repeater rejected** | `testimonials_v1_items` blocked | `BLOCKED_FIELDS['testimonials-v1']` includes `testimonials_v1_items` and sub-fields. Verified present. | **PASS** |
| **No-Invention Editorial Constraint** | Layer 1 prompt content | `get_layer1()` returns a prompt explicitly prohibiting URLs, shortcodes, images, statistics, testimonials, ratings, project labels, etc. Field validation acts as enforcement backstop. | **PASS** |

### Capability: `wizard-home-page-builder`

| Spec Requirement | Scenario | Implementation Evidence | Status |
|------------------|----------|------------------------|--------|
| **Section Selection UI** | Layout picker exposes all ACF layouts | `wizard-init.php` renders `<select>` from `Flexible_Content_Layouts::get_layouts()` which reads all 27 layouts from `acf-json`. Quick-start common sections also available. | **PASS** |
| **Common layouts as quick-start** | Common sections offer | `rms_wizard_render_home_page_builder_form()` provides common-section quick-start buttons via `data-wizard-add-common-home-sections`. | **PASS** |
| **Admin removes section row** | Row removal | `data-wizard-remove-home-section` button on each row; `removeHomeSectionRow` in TS removed row and reindexes. | **PASS** |
| **Same layout added twice** | Duplicate layout allowed | Row template has no uniqueness constraint; `addHomeSectionRow` appends without duplicate check. | **PASS** |
| **No sections on submit** | Block with message | `collectHomePageBuilderPayload` in TS checks `sections.length === 0` → throws `'Select at least one section for the Home page'`. PHP side also checks `$sections === []` → `WP_Error`. | **PASS** |
| **Repeater item count input** | Numeric input per row | TS renders `data-wizard-home-section-count-wrap` (hidden for non-repeater) with `data-wizard-home-section-item-count` number input. PHP `selected_section_rows` extracts `item_count`. Layout defaults in both TS and PHP match. | **PASS** |
| **AI-Assisted Section Content** | Harness system/user prompts composed | `generate_section_overrides()` composes `$system = get_layer1() . get_layer2(PAGE_HOME)` and `$prompt = get_layer3()`. Provider called with system as 4th arg. | **PASS** |
| **Missing required data blocks generation** | WP_Error before AI call | `run()` calls `validate_required_context()` → non-empty `$missing` → `WP_Error('rms_wizard_home_required_client_data_missing', ...)` with spec message. No AI request made. | **PASS** |
| **AI call fails for one section** | Placeholder, no abort | `generate_section_overrides()` returns `[]` on failure (logger logs warning). `section_data()` uses placeholder copy. Step does NOT abort. | **PASS** |
| **Item count injected into prompt** | `{{item_count}}` replacement | `get_layer3()` replaces `'{{item_count}}'` with `(string) max(0, $item_count)`. `selected_section_rows` passes user-set `item_count`. | **PASS** |
| **Extra keys stripped before save** | `validate_fields()` called | `generate_section_overrides()` calls `$this->harness->validate_fields($section_key, $decoded)` before returning. `section_data()` also filters against `fillable_fields`. | **PASS** |
| **Services sourced from `company_services.service_name` only** | Service rows | `service_rows()` iterates `$client_data['company_services']` and uses `$service['service_name']` for the name/title field. AI output can only supply description copy. No invented service names. | **PASS** |

### Capability: `wizard-ai-configuration`

| Spec Requirement | Scenario | Implementation Evidence | Status |
|------------------|----------|------------------------|--------|
| **System/User Provider Message Interface** | System message sent as separate role | `Ollama_Provider::generate()` builds `$messages` array. When `$system` is non-empty after trim: `[{role:'system',content:$system}, {role:'user',content:$prompt}]`. | **PASS** |
| **Empty system falls back to single message** | Legacy behavior preserved | When `trim($system) === ''`: `$messages = [{role:'user', content:$prompt}]`. Identical to pre-harness behavior. | **PASS** |
| **Existing callers unaffected** | 3-arg call still works | `AI_Provider::generate()` signature: `generate($model, $prompt, $context = [], $system = '')`. Default `$system = ''` preserves backward compatibility. | **PASS** |

---

## Correctness Table

| Design Decision | Implementation Matches? | Notes |
|-----------------|------------------------|-------|
| Prompt storage: PHP class constants/methods | **YES** — `AI_Content_Harness::get_layer1/2/3()` return class-encoded strings; no filesystem reads | |
| Page-type extensibility: `PAGE_*` constants, unknown → PAGE_HOME + log | **YES** — 6 constants defined; `get_layer2()` logs warning and falls back to PAGE_HOME for unknown types | |
| Allowlist source: hand-curated maps | **YES** — `FILLABLE_FIELDS` and `BLOCKED_FIELDS` are private const maps covering all 27 layouts | |
| Provider signature: optional 4th param `$system` | **YES** — `AI_Provider::generate($model, $prompt, $context = [], $system = '')` | |
| Repeater counts: user-set per row via UI | **YES** — `item_count` input per section row in form (wizard-init + wizard.ts); PHP-side defaults as back-compatible fallback | |
| Client data filtering: harness-approved keys only | **YES** — `APPROVED_CONTEXT_FIELDS` allowlist + `sanitize_context_value()` strips url/logo/color/social/slug/id keys | |
| Services: names from `company_services.service_name` only | **YES** — `service_rows()` slices from `$client_data['company_services']`, uses `$service['service_name']` for name field. AI provides description only via `$contract['description']`. | |
| `build_generic_section()` constrained to fillable | **YES** — creates `AI_Content_Harness` instance, gets `get_fillable_fields()`, skips sub_fields not in fillable | |
| No runtime dependency on guide file | **YES** — no filesystem reads of any markdown guide in any implementation file | |

---

## Design Coherence Table

| Design Element | Implementation Aligned? | Evidence |
|----------------|------------------------|----------|
| `AI_Content_Harness` as new service class | **YES** | `class-ai-content-harness.php` with `final class AI_Content_Harness` containing all specified methods |
| `Step_Home_Page_Builder` as thin orchestrator | **YES** | Validates context → composes prompts → calls provider → decodes → validates → saves. Flat prompt and invented switch fallbacks removed. |
| `Flexible_Content_Layouts::build_generic_section()` constrained | **YES** | Uses `AI_Content_Harness::get_fillable_fields()` to filter, removes URL/duration/radius defaults |
| Ollama dual-message when system non-empty | **YES** | `Ollama_Provider::generate()` conditionally prepends system message |
| UI item-count controls per section row | **YES** | Template `data-wizard-home-section-row-template` includes `data-wizard-home-section-count-wrap` with `data-wizard-home-section-item-count` |
| Harness warning banner for missing client data | **YES** | `wizard-init.php`: `[data-wizard-home-harness-warning]` notice. `wizard.ts`: `setHomeHarnessWarning()` / `missingHomeBuilderClientData()` |

---

## Runtime Spec Scenario Coverage

**No PHPUnit test harness exists for this project.** Spec scenarios have been verified through source code inspection and static analysis only. The following table marks each scenario accordingly.

| Spec | Scenario | Runtime Test | Status |
|------|----------|-------------|--------|
| wizard-ai-content-harness | Prompts delivered without guide file | **NO RUNTIME TEST** — Source inspection confirms no FS reads | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-ai-content-harness | Layer 2 defaults to PAGE_HOME | **NO RUNTIME TEST** — Default parameter value verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-ai-content-harness | Unsupported page type fallback | **NO RUNTIME TEST** — Warning log code path verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-ai-content-harness | Non-approved fields stripped | **NO RUNTIME TEST** — Allowlist iteration verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-ai-content-harness | Missing required field returns warning | **NO RUNTIME TEST** — `validate_required_context()` logic verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-ai-content-harness | All required fields present → empty array | **NO RUNTIME TEST** — Verified by code reading | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-ai-content-harness | Extra/blocked keys stripped | **NO RUNTIME TEST** — `validate_fields()` allowlist+blocklist intersection verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-ai-content-harness | Invented badge data rejected | **NO RUNTIME TEST** — `BLOCKED_FIELDS['about-us']` includes `about_badge_years` | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-ai-content-harness | URL field rejected | **NO RUNTIME TEST** — `BLOCKED_FIELDS['cta-v1']` includes `cta_v1_button_url` | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-ai-content-harness | Testimonial repeater rejected | **NO RUNTIME TEST** — `BLOCKED_FIELDS['testimonials-v1']` includes `testimonials_v1_items` | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-home-page-builder | Layout picker exposes all layouts | **NO RUNTIME TEST** — Dynamic rendering from ACF JSON verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-home-page-builder | Common layouts as quick-start | **NO RUNTIME TEST** — Common sections button and data attribute verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-home-page-builder | Admin removes section row | **NO RUNTIME TEST** — `removeHomeSectionRow` + reindex verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-home-page-builder | Same layout added twice | **NO RUNTIME TEST** — No uniqueness guard, appends verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-home-page-builder | No sections on submit blocked | **NO RUNTIME TEST** — TS `sections.length === 0` throw + PHP WP_Error verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-home-page-builder | Repeater item count input per row | **NO RUNTIME TEST** — Number input with defaults verified in TS/PHP | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-home-page-builder | AI-assisted section content through harness | **NO RUNTIME TEST** — `generate_section_overrides()` composes via harness verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-home-page-builder | Missing client data blocks generation | **NO RUNTIME TEST** — `validate_required_context()` + WP_Error verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-home-page-builder | AI call fails → placeholder, no abort | **NO RUNTIME TEST** — Empty array return + logger warning verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-home-page-builder | Item count injected into prompt | **NO RUNTIME TEST** — `get_layer3()` `{{item_count}}` substitution verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-home-page-builder | Extra AI keys stripped before save | **NO RUNTIME TEST** — `validate_fields()` call + `section_data()` allowlist filter verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-home-page-builder | Services sourced from `company_services.service_name` | **NO RUNTIME TEST** — `service_rows()` iteration from client data verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-ai-configuration | System message separate role | **NO RUNTIME TEST** — Ollama messages array construction verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-ai-configuration | Empty system → single message | **NO RUNTIME TEST** — Conditional branch verified | ⚠️ MANUAL VERIFICATION REQUIRED |
| wizard-ai-configuration | Existing callers unaffected | **NO RUNTIME TEST** — Default `$system = ''` param verified | ⚠️ MANUAL VERIFICATION REQUIRED |

---

## Issues

### CRITICAL

None.

### WARNING

1. **No PHPUnit test harness exists.** All 25 spec scenarios have been verified through source code inspection and build/type-check evidence only. Runtime behavioral verification requires manual testing or future PHPUnit integration.

### SUGGESTION

1. Consider adding a PHPUnit test suite (or WP_UnitTestCase suite) for the harness core methods (`validate_fields`, `validate_required_context`, `get_harness_context`, `get_layer2` fallback) to enable automated regression coverage.
2. Consider end-to-end manual test of the full wizard flow: Client Data → IA Generation → Home Page Builder with sections → verify section content is saved with only fillable fields and service rows use client data names.

---

## Final Verdict

**PASS WITH WARNINGS**

All 17/17 tasks are marked complete. PHP syntax checks and the TypeScript/Vite build pass without errors. Source code inspection confirms every spec requirement and design decision is implemented correctly. The lack of a PHPUnit test harness prevents automated runtime scenario verification; all scenarios are marked for manual verification. No CRITICAL issues found.