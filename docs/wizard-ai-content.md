# Wizard and AI Content Flow

This page explains how the setup wizard and AI-generated Home page content work. It is for maintainers changing wizard behavior, AI prompts, provider code, or runtime verification.

## Wizard steps

The wizard is orchestrated by `Inc\Wizard\Step_Controller` and currently runs these required steps:

| Step | Purpose |
|------|---------|
| `dependencies` | Validate required/recommended plugins. |
| `acf-import` | Import/register ACF data. |
| `client-data` | Capture business/client context used by later steps. |
| `generate-pages` | Create selected WordPress pages. |
| `menu-setup` | Build menu assignments. |
| `ia-generation` | Store AI provider/model/key configuration. |
| `home-page-builder` | Generate and save Home page flexible content sections. |

State is stored in `wp_options` through `Inc\Wizard\State_Manager`.

## AI provider flow

The wizard uses the theme's custom AI provider abstraction:

- `inc/wizard/class-ai-provider.php` — provider contract.
- `inc/wizard/class-ai-provider-registry.php` — provider factory.
- `inc/wizard/class-ollama-provider.php` — Ollama Cloud provider.
- `inc/wizard/class-ai-credential-store.php` — encrypted/masked credential storage.

The project does **not** use native WordPress AI APIs. Do not put API keys in code, docs, commits, screenshots, or chat.

## Content generation flow

`Inc\Wizard\Step_Home_Page_Builder` builds Home sections with this backend flow:

```text
selected section
→ AI_Content_Harness builds prompts and allowed/blocked fields
→ provider generates JSON copy
→ JSON is decoded
→ AI_Content_Reviewer reviews and may rewrite copy
→ AI output is validated by AI_Content_Harness::validate_fields()
→ section data is merged into ACF structure
→ Content_Builder saves page sections
```

The reviewer runs after JSON decode and before final validation. If review fails, times out, or returns invalid data, the builder falls back to the original decoded AI payload and still runs final validation before saving.

## Prompt and quality responsibilities

| Component | Responsibility |
|-----------|----------------|
| `AI_Content_Harness` | Source of truth for generation prompts, fillable fields, blocked fields, text repeater rules, and shared editorial rules. |
| `AI_Content_Reviewer` | Diagnosis-first quality review, rewrite guidance, cross-section repetition checks, and dev-only quality report. |
| `Step_Home_Page_Builder` | Runtime orchestration, request-scoped prior section context, kill-switch handling, fallback, and bounded logging. |

## Current quality principles

- Write for customers/property owners first, contractors second.
- Technical language is allowed only when paired with a clear customer benefit.
- Paragraphs should lead with concrete benefit or outcome before method/explanation.
- Service-specific language must come from `company_services` or trusted client context.
- Do not invent services, credentials, years in business, guarantees, brands, licenses, bilingual service, special equipment, reviews, ratings, or proof.
- Avoid repeated generic praise across the page.
- Give each section a distinct job or angle: process, result, customer experience, trust, service overview, or CTA.
- The rules must remain contractor/home-services agnostic. Do not hardcode a vertical such as painting, landscaping, cleaning, pool building, or drilling.

## Review diagnoses

`AI_Content_Reviewer` can classify quality issues such as:

- `generic_copy`
- `semantic_repetition`
- `unsupported_claims`
- `keyword_stuffing`
- `filler_content`
- `missing_trust_signal`
- `intent_mismatch`
- `ai_speak`
- `guardrail_gap`
- `overtechnical_language`
- `repetitive_wording`
- `section_angle_overlap`
- `missing_differentiator`

When adding a diagnosis, update the taxonomy, rewrite directive, prompt contract, OpenSpec docs, and manual QA checklist together.

## Current limitations

- No PHPUnit or E2E test runner exists yet.
- Runtime behavior is verified manually in WP Admin.
- The quality reviewer depends on provider behavior and prompt adherence.
- `Wizard ai harness prompt guide.md` is a local reference file and is intentionally not a runtime dependency.
