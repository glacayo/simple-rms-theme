## Exploration: Wizard AI Content Harness

### Current State

The wizard currently has a working 7-step flow (`wizard-user-friendly-content-flow` change, already implemented) with AI content generation in `Step_Home_Page_Builder`. The AI provider stack (`AI_Provider`, `Ollama_Provider`, `AI_Provider_Registry`) sends single-prompt requests — no `system`/`user` message separation. The harness guide (`Wizard ai harness prompt guide.md`) is an untracked root document defining a structured 3-layer prompt composition protocol for 27 ACF layouts + schema, with strict editorial rules and no-invention constraints.

**The current `generate_section_overrides()` in `class-step-home-page-builder.php:159-178`** constructs a single flat prompt string:

```php
$prompt = sprintf(
    "Return compact JSON copy for the %s section of a contractor Home page. Use keys like headline, subheadline, text, cta_text, cta_url, items. Client data JSON: %s",
    $section_key,
    (string) wp_json_encode($client_data)
);
$result = AI_Provider_Registry::make_provider($provider)->generate($model, $prompt, [...]);
```

This has NO harness integration — no system/user message split, no Layer 1-3 composition, no page-type context, no item_count for repeaters, no field-level do-not-fill lists, and no keyword passing for landing/blog pages.

### What the Harness Guide Prescribes

| Component | Current Status | Required |
|-----------|---------------|----------|
| **Layer 1 (Global System)** | Not used | Static 1074-character system prompt defining role, editorial standards, output format |
| **Layer 2 (Page Context)** | Not used | One of 6 page-type templates (PAGE_HOME, PAGE_ABOUT, PAGE_SERVICE, PAGE_LANDING, PAGE_BLOG, PAGE_CONTACT) selected per request |
| **Layer 3 (Section Layout)** | Not used | 27 layout-specific prompts with exact field contracts, do-not-fill lists, item_count, and {{client_json}} placeholder |
| **Client JSON filtering** | Raw full `$client_data` sent | Guide specifies "always included", "included when available", and "never included" field lists |
| **System/User message split** | Single `user` message only | Provider must accept `system` + `user` as separate fields |
| **Repeatable items** | Hardcoded defaults (1 slide, 3 services) | `{{item_count}}` variable injected at runtime |
| **Keyword injection** | None | `PAGE_LANDING` and `PAGE_BLOG` require `{{keyword}}` replacement |
| **Do-not-fill fields** | Mixed — some hardcoded empty, some generated incorrectly | Explicit per-layout field exclusion lists |

### Affected Areas

#### Files Requiring Modification

| File | Current state | Required change |
|------|--------------|-----------------|
| `inc/wizard/class-ollama-provider.php` | Single `user` message at line 43-53 | Add optional `$system` param; send `messages[]` with both `system` and `user` roles |
| `inc/wizard/class-ai-provider.php` | `generate(string $model, string $prompt, array $context)` | Change signature to `generate(string $model, string $system, string $user, array $context)` — or add `$system` as optional second param with BC fallback |
| `inc/wizard/class-step-home-page-builder.php` | `generate_section_overrides()` builds ad-hoc prompt; `section_data()` has explicit mappings for 7 layouts only; fallback `build_generic_section()` via `Flexible_Content_Layouts` | Replace prompt construction with harness-guided composition: Layer1 + Layer2(pagetype) → system, Layer3(layout) + filtered client JSON → user. Update `section_data()` mappings to match exact ACF field names from harness. Remove hardcoded defaults that violate no-invention (badge_years, testimonial data). Add `item_count` and `keyword` injection. |
| `inc/wizard/class-flexible-content-layouts.php` | `build_generic_section()` populates ALL sub_fields with defaults; `generic_text()` invents values for non-string types | Add `ai_fillable_fields()` method that reads harness layout definition to determine which fields AI is allowed to fill for non-explicit layouts. Add `ai_blocked_fields()` mirror. |
| `inc/wizard/class-client-data-fields.php` | Returns all wizard-safe theme settings fields | Add harness-aware filtering: `get_harness_context()` that returns only the "always included" + "included when available" fields per the harness reference |
| `inc/wizard/wizard-init.php` | Step list complete; no harness loading | Add harness prompt file reader (without committing the file). Load Layer 1-3 from `Wizard ai harness prompt guide.md` into a `Harness_Prompt_Store` service |
| `inc/wizard/` | No `Harness_Prompt_Store` class | **NEW**: `class-harness-prompt-store.php` — parses the markdown guide, exposes `get_layer1()`, `get_layer2(page_type)`, `get_layer3(layout_key)`, `get_blocked_fields(layout_key)`, `get_fillable_fields(layout_key)`, editorial rules constants |
| `acf-json/group_rms_page_sections.json` | 27 flexible content layouts | No change needed — field names already match harness layout keys |

#### Files Requiring Review (No Changes Expected)

| File | Reason |
|------|--------|
| `inc/wizard/class-ai-provider-registry.php` | Already resolves providers; no harness awareness needed |
| `inc/wizard/class-ai-credential-store.php` | Encryption/retrieval unchanged |
| `inc/wizard/class-content-builder.php` | `prepare_image_fallbacks()` and `build_page()` unchanged; receives prepared section data from Home Page Builder |
| `inc/wizard/class-state-manager.php` | May need new state keys for `home_page_pagetype` if page type becomes selectable per-section rather than hardcoded to HOME |
| `inc/wizard/class-step-generate-pages.php` | Uses AI for page-level content generation; would benefit from harness Layer 2 page-type selection but is out of scope for this change (Home Page Builder only) |
| `inc/wizard/class-rest-controller.php` | Step dispatch already generic; no harness-specific changes needed |

### Complete ACF Layout → Harness Layout Mapping

All 27 ACF flexible content layouts match the harness guide's 27 layout sections. Field names are 1:1 compatible:

| ACF Layout Key | Harness Section | AI-Fillable Fields (from harness) | Blocked Fields |
|---------------|-----------------|----------------------------------|----------------|
| `hero` | hero | `hero_title`, `hero_description` | `hero_bg_image`, `hero_reviews_label`, `hero_form_shortcode` |
| `slider` | slider | `slider_slides[].slide_subheadline`, `slider_slides[].slide_headline`, `slider_slides[].slide_text`, `slider_slides[].slide_cta_text` | `slider_slides[].slide_bg_image`, `slider_slides[].slide_cta_url` |
| `about-us` | about-us | `about_headline`, `about_subheadline`, `about_text` | `about_image`, `about_badge_years`, `about_badge_label` |
| `area-coverage-v1` | area-coverage-v1 | `area_eyebrow`, `area_headline`, `area_description`, `area_cta_text` | `area_radius`, `area_cities`, `area_cta_url`, `area_map_image` |
| `badges` | badges | `badges_label` | `badges_items` (entire repeater) |
| `blog-v1` | blog-v1 | `blog_headline`, `blog_cta_text` | `blog_cta_url` |
| `contact-info` | contact-info | `contact_info_headline`, `contact_info_intro` | `contact_info_form_shortcode` |
| `cta-v1` | cta-v1 | `cta_v1_headline`, `cta_v1_text`, `cta_v1_button_text` | `cta_v1_button_url` |
| `cta-v2` | cta-v2 | `cta_v2_headline`, `cta_v2_text`, `cta_v2_primary_text`, `cta_v2_secondary_text` | `cta_v2_primary_url`, `cta_v2_secondary_url` |
| `cta-v3` | cta-v3 | `cta_v3_headline`, `cta_v3_button_text`, `cta_v3_stats[].stat_label` | `cta_v3_button_url`, `cta_v3_stats[].stat_number` |
| `faq-v1` | faq-v1 | `faq_v1_headline`, `faq_v1_subheadline`, `faq_v1_faqs[].faq_question`, `faq_v1_faqs[].faq_answer` | none (all fillable) |
| `faq-v2` | faq-v2 | `faq_v2_headline`, `faq_v2_subheadline`, `faq_v2_faqs[].faq_question`, `faq_v2_faqs[].faq_answer` | none (all fillable) |
| `gallery-grid` | gallery-grid | **none** — return `{}` | all fields factual |
| `portfolio-v1` | portfolio-v1 | `portfolio_v1_headline`, `portfolio_v1_subheadline` | `portfolio_v1_projects` (entire repeater) |
| `portfolio-v2` | portfolio-v2 | `portfolio_v2_headline`, `portfolio_v2_subheadline` | `portfolio_v2_projects` (entire repeater) |
| `portfolio-v3` | portfolio-v3 | `portfolio_v3_headline`, `portfolio_v3_subheadline` | `portfolio_v3_filters`, `portfolio_v3_projects` |
| `seo-content` | seo-content | `seo_headline`, `seo_subheadline`, `seo_text` | `seo_image`, `seo_modifier`, `seo_bg_style`, `seo_bg_image` |
| `services-v1` | services-v1 | `services_v1_headline`, `services_v1_subheadline`, `services_v1_services[].service_title`, `services_v1_services[].service_text`, `services_v1_cta_text` | `services_v1_bg_image`, `services_v1_cta_url` |
| `services-v2` | services-v2 | `services_v2_headline`, `services_v2_subheadline`, `services_v2_services[].service_title`, `services_v2_services[].service_text`, `services_v2_cta_text` | `services_v2_services[].service_image`, `services_v2_cta_url` |
| `services-v3` | services-v3 | `services_v3_headline`, `services_v3_subheadline`, `services_v3_services[].service_name`, `services_v3_services[].service_overlay_text`, `services_v3_cta_text` | `services_v3_services[].service_image`, `services_v3_cta_url` |
| `testimonials-v1` | testimonials-v1 | `testimonials_v1_headline`, `testimonials_v1_subheadline` | `testimonials_v1_items` (entire repeater) |
| `testimonials-v2` | testimonials-v2 | `testimonials_v2_headline`, `testimonials_v2_subheadline` | `testimonials_v2_items` (entire repeater) |
| `testimonials-v3` | testimonials-v3 | `testimonials_v3_headline`, `testimonials_v3_subheadline` | `testimonials_v3_items` (entire repeater) |
| `video-v1` | video-v1 | `video_v1_headline`, `video_v1_subheadline`, `video_v1_description`, `video_v1_cta_text` | `video_v1_poster`, `video_v1_video_url`, `video_v1_duration`, `video_v1_video_title`, `video_v1_cta_url` |
| `video-v2` | video-v2 | `video_v2_headline`, `video_v2_subheadline` | `video_v2_videos` (entire repeater) |
| `vision-mission-v1` | vision-mission-v1 | `vm_v1_eyebrow`, `vm_v1_headline`, `vm_v1_intro`, `vm_v1_cards[].card_title`, `vm_v1_cards[].card_text`, `vm_v1_cta_text` | `vm_v1_cards[].card_highlight`, `vm_v1_cta_url` |
| `vision-mission-v2` | vision-mission-v2 | `vm_v2_eyebrow`, `vm_v2_headline`, `vm_v2_vision_text`, `vm_v2_mission_text`, `vm_v2_reasons[].reason_text`, `vm_v2_cta_text` | `vm_v2_cta_url` |
| `schema` (Theme Settings) | schema | `schema_short_description` | 11 blocked fields (business_type, price_range, founder, date, coordinates, service_areas, links, images) |

### Current Violations to Fix

| Violation | Current Code | Harness Rule |
|-----------|-------------|--------------|
| `about_badge_years: '25'` hardcoded default | `class-step-home-page-builder.php:217` | No-invention: "Do not invent years in business" |
| `about_badge_label: 'Years Of Experience'` hardcoded | `class-step-home-page-builder.php:218` | No-invention: badge label would imply factual claim |
| Testimonial AI generation: `company_name + ' delivered exactly what they promised...'` | `class-step-home-page-builder.php:298` | No-invention: "Do not invent testimonials, quotes, customer names, or star ratings" |
| Testimonial author `'Happy Customer'`, role `'Homeowner'` invented | `class-step-home-page-builder.php:298` | No-invention: customer names must not be invented |
| Testimonial stars default `5` | `class-step-home-page-builder.php:307` | No-invention: "Do not invent star ratings" |
| `slide_bg_image: ''` saved explicitly | `class-step-home-page-builder.php:200` | Harness says `slide_bg_image` is blocked field — should not be in JSON at all; image fallback handles empty later |
| `slide_cta_url: '#contact'` default | `class-step-home-page-builder.php:205` | Harness says blocked — factual URL, not AI-generated |
| `gallery_items` populated from services (6 items) | `class-step-home-page-builder.php:235-241` | Harness says gallery-grid has NO AI-fillable fields; labels are factual |
| `build_generic_section()` fills ALL sub_fields | `class-flexible-content-layouts.php:93-102` | No notion of which fields AI can/cannot fill for unmapped layouts |
| Single prompt string for all sections | `class-step-home-page-builder.php:162-167` | Harness requires system/user message split across 3 layers |
| Raw client data JSON sent unfiltered | `class-step-home-page-builder.php:165` | Harness specifies exact subset of client fields |
| Item count hardcoded (1 slide, 3 services) | `class-step-home-page-builder.php:198-207, 276-286` | Harness expects `{{item_count}}` passed at runtime |
| No page type awareness | `class-step-home-page-builder.php:163` | Harness has 6 page contexts; Home Page Builder should inject `PAGE_HOME` |

### Approaches

1. **Full harness integration with new Harness_Prompt_Store** — Parse the markdown guide into a reusable PHP service. Modify `AI_Provider` interface to accept `system` + `user` messages. Rewrite `Step_Home_Page_Builder::generate_section_overrides()` to compose prompts from harness layers. Update all 7 explicit layouts to match harness field contracts. Add `Harness_Prompt_Store` to `Flexible_Content_Layouts` for ai-fillable/blocked field awareness in generic builder.
   - Pros: Complete alignment with the guide. All layouts become AI-capable. No-invention rules enforced at the field level. Extensible for future page types (Generate Pages step).
   - Cons: New service class. Provider interface change touches all provider implementations. Significant refactor of Home Page Builder. Needs comprehensive field mapping verification.
   - Effort: High

2. **Minimal harness: inline prompt constants only** — Hard-code Layer 1 and Layer 2 (PAGE_HOME only) in a new `Harness_Prompts` class with array constants for each layout. Keep single-prompt provider interface. Don't parse the markdown file; duplicate the prompt strings.
   - Pros: No file parsing. No provider interface change. Lower effort. Still gets structured prompts and no-invention rules.
   - Cons: Hardcoded prompts drift from source of truth. Must maintain manually. Only supports current 7+ common layouts. No extensibility to other page types.
   - Effort: Medium

3. **Hybrid: Parse harness for prompts, keep single message** — Parse the markdown guide to extract layout prompts, but concatenate system+user into a single message (with `### SYSTEM ###` / `### USER ###` delimiters or similar). Don't change the provider interface.
   - Pros: Harness is the single source of truth. Provider interface unchanged. Easy to test.
   - Cons: Models may behave differently with single combined message vs separated system/user messages. May reduce output quality on some providers. Not future-proof if providers require true separation.
   - Effort: Medium

### Recommendation

**Approach 1 — Full harness integration.** The harness guide is the authoritative specification for AI content generation. It was written for EXACTLY this integration point. The 3-layer composition (`system = L1 + L2`, `user = L3 + client_json`) is a deliberate design choice that Ollama, OpenAI, Anthropic, and all major providers support natively. Changing `AI_Provider::generate()` to accept `system` + `user` is a clean interface evolution with a clear BC path (default `$system = ''` → concatenate to `$user` if empty).

The `Harness_Prompt_Store` parsing the markdown means the guide remains the single source of truth — any future layout additions or editorial rule changes are updated in the markdown, not in PHP constants. The no-invention rules enforced at the field level guarantee compliance without relying on prompt instructions alone.

This approach future-proofs for: (a) multi-page-type generation when Generate Pages step gets harness integration, (b) new AI providers added via `rms_wizard_ai_providers` filter, (c) ACF layout additions that automatically get AI capability through generic builder.

### Risks

- **Provider interface change is breaking**: Existing `Ollama_Provider::generate()` only sends a `user` message. Adding `$system` param changes the method signature. Mitigation: make `$system` optional with default `''`. If `$system` is non-empty, send as separate message; if empty, fall back to single-message behavior. This preserves backward compatibility for any other caller.
- **Markdown parsing fragility**: The harness guide is human-written markdown. Parsing section headers, code blocks, and variable placeholders must be robust. A change to the markdown (added whitespace, section reordering) could break parsing. Mitigation: parse by known section delimiters (`## Layer 1`, `## Layer 2`, `## Layer 3`, `### \`layout\``). Include validation that all 27 expected layouts are found. Log warnings for parse failures but don't crash.
- **Harness guide not committed**: The root `Wizard ai harness prompt guide.md` is intentionally untracked and must NOT be committed. Mitigation: `Harness_Prompt_Store` reads from `get_template_directory() . '/Wizard ai harness prompt guide.md'`. If absent, fall back to hardcoded defaults. Add clear documentation that the harness file is a runtime dependency installed separately (not part of the theme repo). Add to `.gitignore` if not already present.
- **Field name mismatches**: Harness layout field names and ACF field names must match exactly for the mapping to work. Mitigation: verified during exploration — all 27 layouts have matching names. Write a validation test that cross-references `acf-json/group_rms_page_sections.json` layouts against harness layout sections on init and logs warnings for mismatches.
- **Client data field filtering**: The client data sent to the AI must be exactly what the harness expects. Mitigation: `Client_Data_Fields` already reads from ACF JSON. Add `get_harness_context()` that maps `company_name`, `company_covered_areas`, etc. to the exact keys the harness references. Filter out media/color/social fields.
- **JSON shape validation**: The AI response JSON must match the exact keys in the layout. If AI returns extra fields or wrong keys, they get silently dropped by `sanitize_by_type()` or worse, stored incorrectly. Mitigation: add post-decode validation in `decode_json_content()`: warn if returned keys don't match expected fillable fields, strip any that aren't in the allowed set.
- **Item count handling**: Repeater fields need `{{item_count}}` replaced with the actual desired count. Who sets the count? Mitigation: add a `item_counts` map to state or payload: `['slider' => 3, 'faq-v1' => 6, 'services-v1' => 4]`. Wizard UI lets user configure count per section. Default to sensible values (3 slides, 4 services, 6 FAQs). This is a UI concern for a follow-up change; initial implementation can use hardcoded defaults for MVP.
- **Performance**: Parsing a 1074-line markdown file on every section generation request is wasteful. Mitigation: `Harness_Prompt_Store` should cache parsed content in a static property (in-memory for the PHP request lifecycle). The file is read once per request at most, not once per section.

### Ready for Proposal

**Yes.** The harness guide alignment is a clean gap-filling exercise. The current AI generation is functional but primitive — it works by accident because models are forgiving. The harness brings rigor: exact field contracts, no-invention enforcement, and message separation. The exploration confirms that:

- All 27 ACF layouts have corresponding harness sections with matching field names
- The provider interface can accommodate system/user separation with optional backward compatibility
- Client data filtering maps cleanly to existing `Client_Data_Fields`
- The markdown guide is parseable and serves as the single source of truth

The orchestrator should proceed to `sdd-propose` with change name `wizard-ai-content-harness`.
