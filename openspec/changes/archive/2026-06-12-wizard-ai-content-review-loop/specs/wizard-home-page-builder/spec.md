# Delta for Wizard Home Page Builder

## MODIFIED Requirements

### Requirement: AI-Assisted Section Content

For each selected section the wizard MUST compose prompts using `AI_Content_Harness`:
Layer 1 (global editorial system prompt) + Layer 2 (PAGE_HOME context) form the `system`
message; Layer 3 (layout-specific contract) with `{{item_count}}` replaced by the row's
item-count value and `{{client_json}}` replaced by the output of `get_harness_context()`
form the `user` message. The AI provider MUST receive `system` and `user` as separate
parameters. Before any AI request, `validate_required_context()` MUST be called; if
required fields are missing the step MUST block with a warning and MUST NOT call the
provider. The AI response MUST be decoded; for layouts with fillable fields the decoded
payload MUST be passed to `AI_Content_Reviewer::review()` together with the layout key and
the accumulated prior-section payloads (sections 1..N-1 of the current run). The
reviewer's returned payload MUST replace the decoded payload; if the reviewer returns a
fallback on failure or timeout, the original decoded payload MUST be used. The final
payload MUST be validated against `get_fillable_fields()` for the layout; any key outside
the allowlist MUST be stripped before saving. For newly enabled text repeaters (slider
slides, FAQ items, vision/mission cards/reasons, cta-v3 stat labels), the builder MUST
decode the repeater-shaped JSON and merge it into the ACF field structure using existing
repeater handling patterns. Layouts without an explicit field mapping MUST use
`build_generic_section()` constrained to harness-approved fillable fields only.

(Previously: Single-pass generate → decode → validate → save with no review step. The
reviewer and cross-section context accumulation did not exist.)

#### Scenario: AI fills content for a known layout

- GIVEN `state.ai_config` is set and the provider is reachable
- WHEN the step runs for `about-us`
- THEN the AI response is validated against fillable fields and only approved keys are stored

#### Scenario: AI fills content for an unmapped layout

- GIVEN `state.ai_config` is set and the layout key has no explicit field mapping
- WHEN the step processes the section
- THEN `build_generic_section()` uses `get_fillable_fields()` to constrain which
  sub_fields are populated

#### Scenario: AI call fails for one section

- GIVEN the AI provider returns an error for a specific section
- WHEN the step continues processing remaining sections
- THEN the failed section is saved with placeholder text, the error is logged, and the
  step DOES NOT abort

#### Scenario: Missing required client data blocks generation

- GIVEN `validate_required_context()` returns `['company_name']`
- WHEN the admin submits the Home Page Builder step
- THEN the wizard blocks with a warning and no AI request is made

#### Scenario: Item count injected into repeater prompt

- GIVEN the admin set item count to 5 for a `faq-v1` row
- WHEN the harness composes the user message
- THEN `{{item_count}}` is replaced with `5`

#### Scenario: Newly enabled repeater decoded and merged

- GIVEN the AI returns `{ "faq_v1_faqs": [{"faq_question": "...", "faq_answer": "..."}] }`
  for `faq-v1`
- WHEN the builder processes the response
- THEN the repeater array is decoded and merged into the ACF field structure

#### Scenario: Extra AI response keys stripped before save

- GIVEN the AI returns keys outside the fillable list
- WHEN field validation runs
- THEN only fillable keys are saved; all others are stripped

#### Scenario: Reviewer invoked between decode and validate

- GIVEN `about-us` decoded payload is ready and `has_fillable_fields()` returns true
- WHEN the step calls `AI_Content_Reviewer::review()`
- THEN the reviewer's returned payload replaces the decoded payload
- AND `validate_fields()` runs on the reviewer output, not the raw decoded output

#### Scenario: Reviewer fallback preserves original content

- GIVEN the reviewer's AI critique call times out
- WHEN the reviewer returns the original decoded payload as fallback
- THEN the builder uses the original decoded payload and proceeds to `validate_fields()`
- AND no error is thrown; save is not blocked

#### Scenario: Prior sections passed as repetition context

- GIVEN sections 1 and 2 have been generated and the builder is processing section 3
- WHEN `AI_Content_Reviewer::review()` is called for section 3
- THEN the payloads from sections 1 and 2 are passed as prior-section context
- AND the reviewer can detect semantic repetition against them

#### Scenario: No UI changes in v1

- GIVEN the admin triggers the Home Page Builder step
- WHEN the step runs including all review passes
- THEN no additional UI elements, progress indicators, or review-result controls are
  rendered; the admin sees the same wizard interface as before

## ADDED Requirements

### Requirement: Section Context Accumulation

For each full-page generation run the builder MUST maintain an ordered list of decoded
(post-review) section payloads indexed by processing order. Before invoking the reviewer
for section N, the builder MUST pass sections 1..N-1 from this list. After the reviewer
returns, the accepted payload for section N MUST be appended to the list. The list MUST be
scoped to a single `run()` invocation and MUST NOT persist across requests.

#### Scenario: Context list grows per section processed

- GIVEN a run processes 5 sections
- WHEN section 3 is reviewed
- THEN the context list passed to the reviewer contains exactly 2 entries (sections 1 and 2)
- AND after review, section 3 is appended so the list has 3 entries

#### Scenario: Context list does not persist across requests

- GIVEN a first wizard run completes with 5 sections
- WHEN the admin triggers a second run
- THEN the context list starts empty; no payloads from the first run are present
