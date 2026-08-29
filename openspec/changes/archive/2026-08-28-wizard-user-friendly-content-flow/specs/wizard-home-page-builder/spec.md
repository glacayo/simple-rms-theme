# Wizard Home Page Builder Specification

## Purpose

A guided wizard step that lets the admin dynamically build an ordered list of ACF flexible-content sections for the Home page from **all** available layouts, triggers AI content generation per section using Client Data as context, applies image placeholders where no image is provided, and saves the result to the Home page's `page_sections` ACF field.

---

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

For each selected section the wizard MUST call the configured AI provider (from `state.ai_config`) with a prompt that includes Client Data (company name, services, address, tone) as context and the target section's purpose as instruction. The response MUST be mapped to the section's ACF sub-field structure before saving. Known common layouts MUST use explicit richer field mappings; layouts without an explicit mapping MUST use `Flexible_Content_Layouts::build_generic_section()`, which introspects ACF sub_fields and merges AI copy with safe type-appropriate defaults.

#### Scenario: AI fills content for a known layout

- GIVEN `state.ai_config` is set and the provider is reachable
- WHEN the step runs for a known layout (e.g. `about-us`)
- THEN the AI response is parsed and stored in the matching ACF sub-fields (e.g. `about_headline`, `about_text`)

#### Scenario: AI fills content for an unmapped layout

- GIVEN `state.ai_config` is set and the layout key has no explicit field mapping
- WHEN the step processes the section
- THEN `build_generic_section()` introspects the layout's ACF sub_fields and populates each field with a safe default merged with any AI copy

#### Scenario: AI call fails for one section

- GIVEN the AI provider returns an error for a specific section
- WHEN the step continues processing remaining sections
- THEN the failed section is saved with placeholder text, the error is logged, and the step DOES NOT abort

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
