# Wizard Home Page Builder Specification

## Requirements

### Requirement: Section Selection UI

The wizard MUST present a dynamic section builder in which the admin can add, remove, and order section rows using all ACF Flexible Content layouts available in `acf-json/group_rms_page_sections.json`. Common layouts (slider, cta-v1, about-us, services-v1, gallery-grid, testimonials-v1, contact-info) MAY be surfaced as quick-start actions but MUST NOT be the only selectable source. The admin MAY add the same layout key more than once. At least one section row MUST be present before the step can complete.

(Previously: wizard presented only 7 fixed toggleable card checkboxes; no dynamic row builder, no full layout discovery.)

#### Scenario: Layout picker exposes all ACF layouts

- GIVEN the Home Page Builder step is opened
- WHEN the UI renders
- THEN a layout picker lists all layouts discovered from `group_rms_page_sections.json`, not only the 7 common ones

#### Scenario: Common layouts offered as quick-start actions

- GIVEN the layout picker is visible
- WHEN the admin activates a common-section quick-start action (e.g. "Add Slider")
- THEN the matching layout key is appended as a new ordered row in the section list

#### Scenario: Admin removes a section row

- GIVEN two or more section rows are present
- WHEN the admin removes one row
- THEN that row is deleted and the remaining rows preserve their original order

#### Scenario: Same layout added twice

- GIVEN `slider` is already a section row
- WHEN the admin adds `slider` again
- THEN a second `slider` row is appended without error

#### Scenario: No sections on submit

- GIVEN no section rows are present
- WHEN the admin submits the step
- THEN the wizard displays: "Select at least one section for the Home page" and blocks completion

---

### Requirement: Layout Discovery from ACF JSON

`Flexible_Content_Layouts` MUST read all available flexible content layouts from `acf-json/group_rms_page_sections.json` at runtime. If the JSON is unreadable or contains no `page_sections` flexible field, the class MUST fall back to the 7 common hardcoded layouts. The backend MUST NOT restrict accepted layout keys to the common 7.

#### Scenario: Non-common layout key accepted

- GIVEN `group_rms_page_sections.json` contains a layout with key `custom-band`
- WHEN the payload includes `custom-band`
- THEN the backend accepts it, delegates to `build_generic_section()`, and saves the section without error

#### Scenario: ACF JSON unreadable — hardcoded fallback

- GIVEN `group_rms_page_sections.json` is missing or unreadable
- WHEN the UI or backend requests available layouts
- THEN the 7 common hardcoded layouts are used and the step continues without error

---

### Requirement: AI-Assisted Section Content

For each selected section the wizard MUST compose prompts using `AI_Content_Harness`: Layer 1 (global editorial system prompt) + Layer 2 (PAGE_HOME context) form the `system` message; Layer 3 (layout-specific contract) with `{{item_count}}` replaced by the row's item-count value and `{{client_json}}` replaced by the output of `get_harness_context()` form the `user` message. The AI provider MUST receive `system` and `user` as separate parameters. Before any AI request, `validate_required_context()` MUST be called; if required fields are missing the step MUST block with a warning and MUST NOT call the provider. The AI response MUST be decoded; for layouts with fillable fields the decoded payload MUST be passed to `AI_Content_Reviewer::review()` together with the layout key and the accumulated prior-section payloads (sections 1..N-1 of the current run). The reviewer's returned payload MUST replace the decoded payload; if the reviewer returns a fallback on failure or timeout, the original decoded payload MUST be used. The final payload MUST be validated against `get_fillable_fields()` for the layout; any key outside the allowlist MUST be stripped before saving. For newly enabled text repeaters (slider slides, FAQ items, vision/mission cards/reasons, cta-v3 stat labels), the builder MUST decode the repeater-shaped JSON and merge it into the ACF field structure using existing repeater handling patterns. Layouts without an explicit field mapping MUST use `build_generic_section()` constrained to harness-approved fillable fields only.

(Previously: Single-pass generate → decode → validate → save with no review step. The reviewer and cross-section context accumulation did not exist.)

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
- THEN no additional UI elements, progress indicators, or review-result controls are rendered; the admin sees the same wizard interface as before

---

### Requirement: Section Context Accumulation

For each full-page generation run the builder MUST maintain an ordered list of decoded (post-review) section payloads indexed by processing order. Before invoking the reviewer for section N, the builder MUST pass sections 1..N-1 from this list. After the reviewer returns, the accepted payload for section N MUST be appended to the list. The list MUST be scoped to a single `run()` invocation and MUST NOT persist across requests.

#### Scenario: Context list grows per section processed

- GIVEN a run processes 5 sections
- WHEN section 3 is reviewed
- THEN the context list passed to the reviewer contains exactly 2 entries (sections 1 and 2)
- AND after review, section 3 is appended so the list has 3 entries

#### Scenario: Context list does not persist across requests

- GIVEN a first wizard run completes with 5 sections
- WHEN the admin triggers a second run
- THEN the context list starts empty; no payloads from the first run are present

---

### Requirement: Image Placeholder Fallback

For every image field within a selected section the wizard MUST use `Content_Builder::prepare_image_fallbacks()` to substitute the bundled `wizard-placeholder.svg` when no real image is available. Real images MUST NOT be required to complete this step.

#### Scenario: Image field left empty

- GIVEN a section's image sub-field has no value after AI generation
- WHEN `prepare_image_fallbacks()` runs
- THEN the field is set to the URL of `wizard-placeholder.svg` and the section saves without error

#### Scenario: Placeholder does not block publish

- GIVEN all section image fields use the placeholder
- WHEN the Home page is viewed on the frontend
- THEN the page renders with placeholder images and no PHP errors

---

### Requirement: ACF Flexible Content Persistence

The Home page content and all its sections MUST be updated through the same wizard page-building flow used to create pages, ensuring consistent page metadata and section persistence. Partial or isolated writes to individual sections MUST NOT be used; all sections MUST be assembled and saved as a single operation in the order collected from the payload. The Home page MUST be identified by `state.home_page_slug`. If the Home page does not exist the step MUST return an error.

#### Scenario: Sections saved in payload order

- GIVEN the Home page exists (ID from `state.created_posts`) and the payload contains three section rows
- WHEN the step completes
- THEN `get_field('page_sections', $home_page_id)` returns all three layouts in the same order they were submitted

#### Scenario: Home page not found

- GIVEN `state.home_page_slug` has no matching WordPress page
- WHEN the step attempts to save
- THEN the wizard returns an error: "Home page not found. Regenerate pages before building the Home page." and does not save partial data

---

### Requirement: Dependency on IA Generation Step

The Home Page Builder step MUST NOT execute AI calls if `state.ai_config` is absent or incomplete. The wizard MUST surface a blocking error directing the admin to complete the IA Generation step first.

#### Scenario: IA Generation step not complete

- GIVEN `state.ai_config` is missing or has no provider
- WHEN the admin submits the Home Page Builder step
- THEN the wizard returns an error: "AI configuration required. Complete the IA Generation step first." and does not proceed

---

### Requirement: Step Completion and Final State

After all sections are saved the wizard MUST mark the `home-page-builder` step as complete in `state.step_status`. This is the final step; completing it MUST trigger the wizard completion lock (`rms_wizard_completed = true`) to prevent re-execution.

#### Scenario: Wizard locked after last step

- GIVEN all seven steps are marked complete
- WHEN the Home Page Builder step succeeds
- THEN `rms_wizard_completed` is set to `true` and the admin sees the completion summary screen

---

### Requirement: Canonical First-Write on Home Success

After a successful Home Page Builder run, the step MUST copy each produced reusable section payload into the canonical store using first-write semantics (`set_if_empty()`), skipping the keyword-driven layouts `hero` and `seo-content`. Re-runs MUST NOT overwrite existing canonical content; the canonical store MUST be read-only on re-run. The step MUST update the state summary to record which layouts now have a canonical payload. This behavior is additive: the existing generation, review, validation, and save flow MUST remain unchanged.

#### Scenario: First Home run seeds empty canonical layouts

- GIVEN a successful Home run produces `about-us` and `services` payloads and the canonical store is empty for them
- WHEN the step finalizes
- THEN `set_if_empty()` stores both as canonical entries and the state summary records them

#### Scenario: Home re-run does not clobber canonical

- GIVEN canonical payloads already exist for `about-us` and `services`
- WHEN a later Home run regenerates those sections
- THEN the canonical store entries are unchanged

#### Scenario: Keyword layouts are skipped

- GIVEN a Home run produces `hero` and `seo-content` payloads
- WHEN canonical first-write runs
- THEN neither `hero` nor `seo-content` is written to the canonical store
