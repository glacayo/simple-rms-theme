# Delta for Wizard Home Page Builder

## MODIFIED Requirements

### Requirement: AI-Assisted Section Content

For each selected section the wizard MUST compose prompts using `AI_Content_Harness`: Layer 1 (global editorial system prompt) + Layer 2 (PAGE_HOME context) form the `system` message; Layer 3 (layout-specific contract) with `{{item_count}}` replaced by the row's item-count value and `{{client_json}}` replaced by the output of `get_harness_context()` form the `user` message. The AI provider MUST receive `system` and `user` as separate parameters. Before any AI request, `validate_required_context()` MUST be called; if required fields are missing the step MUST block with a warning and MUST NOT call the provider. The AI response MUST be decoded and validated against `get_fillable_fields()` for the layout; any key outside the allowlist MUST be stripped before saving. For newly enabled text repeaters (slider slides, FAQ items, vision/mission cards/reasons, cta-v3 stat labels), the builder MUST decode the repeater-shaped JSON and merge it into the ACF field structure using existing repeater handling patterns. Layouts without an explicit field mapping MUST use `build_generic_section()` constrained to harness-approved fillable fields only.

(Previously: No repeater decoding for slider, FAQ, vision-mission, or cta-v3 layouts; those repeater fields were fully blocked by the harness.)

#### Scenario: AI fills content for a known layout

- GIVEN `state.ai_config` is set and the provider is reachable
- WHEN the step runs for `about-us`
- THEN the AI response is validated against fillable fields and only approved keys are stored

#### Scenario: AI fills content for an unmapped layout

- GIVEN `state.ai_config` is set and the layout key has no explicit field mapping
- WHEN the step processes the section
- THEN `build_generic_section()` uses `get_fillable_fields()` to constrain which sub_fields are populated

#### Scenario: AI call fails for one section

- GIVEN the AI provider returns an error for a specific section
- WHEN the step continues processing remaining sections
- THEN the failed section is saved with placeholder text, the error is logged, and the step DOES NOT abort

#### Scenario: Missing required client data blocks generation

- GIVEN `validate_required_context()` returns `['company_name']`
- WHEN the admin submits the Home Page Builder step
- THEN the wizard blocks with a warning and no AI request is made

#### Scenario: Item count injected into repeater prompt

- GIVEN the admin set item count to 5 for a `faq-v1` row
- WHEN the harness composes the user message
- THEN `{{item_count}}` is replaced with `5`

#### Scenario: Newly enabled repeater decoded and merged

- GIVEN the AI returns `{ "faq_v1_faqs": [{"faq_question": "...", "faq_answer": "..."}] }` for `faq-v1`
- WHEN the builder processes the response
- THEN the repeater array is decoded and merged into the ACF field structure

#### Scenario: Extra AI response keys stripped before save

- GIVEN the AI returns keys outside the fillable list
- WHEN field validation runs
- THEN only fillable keys are saved; all others are stripped
