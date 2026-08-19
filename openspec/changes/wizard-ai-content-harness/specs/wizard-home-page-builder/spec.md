# Delta for Wizard Home Page Builder

## MODIFIED Requirements

### Requirement: Section Selection UI

The wizard MUST present a dynamic section builder in which the admin can add, remove,
and order section rows using all ACF Flexible Content layouts available in
`acf-json/group_rms_page_sections.json`. Common layouts (slider, cta-v1, about-us,
services-v1, gallery-grid, testimonials-v1, contact-info) MAY be surfaced as quick-start
actions but MUST NOT be the only selectable source. The admin MAY add the same layout
key more than once. Each section row for a layout that contains repeater fields MUST
display a numeric item-count input pre-filled with a layout-specific default (e.g. 3
for services, 4 for FAQs, 2 for slides). The admin MUST be able to edit the item count
before submitting. At least one section row MUST be present before the step can complete.

(Previously: no item-count input per row; item counts were hardcoded constants in PHP.)

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

#### Scenario: Repeater item count input shown per row

- GIVEN the admin adds a `services-v1` section row
- WHEN the row renders
- THEN a numeric item-count input appears pre-filled with `3` (default)
- AND the admin can change the value before submitting

---

### Requirement: AI-Assisted Section Content

For each selected section the wizard MUST compose prompts using `AI_Content_Harness`:
Layer 1 (global editorial system prompt) + Layer 2 (PAGE_HOME context) form the
`system` message; Layer 3 (layout-specific contract) with `{{item_count}}` replaced
by the row's item-count value and `{{client_json}}` replaced by the output of
`get_harness_context()` form the `user` message. The AI provider MUST receive `system`
and `user` as separate parameters. Before any AI request, `validate_required_context()`
MUST be called; if required fields are missing the step MUST block with a warning and
MUST NOT call the provider. The AI response MUST be decoded and validated against
`get_fillable_fields()` for the layout; any key outside the allowlist MUST be stripped
before saving. Layouts without an explicit field mapping MUST use `build_generic_section()`
constrained to harness-approved fillable fields only.

(Previously: single flat prompt with raw unfiltered client data; hardcoded item counts;
no harness layers; no missing-data check; no field validation; `build_generic_section()`
populated ALL sub_fields including blocked ones.)

#### Scenario: AI fills content for a known layout

- GIVEN `state.ai_config` is set and the provider is reachable
- WHEN the step runs for a known layout (e.g. `about-us`)
- THEN the AI response is validated against the layout's fillable fields and only approved keys (e.g. `about_headline`, `about_subheadline`, `about_text`) are stored

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
- THEN the wizard displays: "Missing required client data: company_name. Complete your client profile before generating." and no AI request is made

#### Scenario: Item count injected into repeater prompt

- GIVEN the admin set item count to 5 for a `faq-v1` row
- WHEN the harness composes the user message for that section
- THEN `{{item_count}}` in the Layer 3 template is replaced with `5`

#### Scenario: Extra AI response keys stripped before save

- GIVEN the AI returns `{ "cta_v1_button_url": "https://ex.com", "cta_v1_headline": "Call us" }` for `cta-v1`
- WHEN field validation runs
- THEN only `{ "cta_v1_headline": "Call us" }` is saved; `cta_v1_button_url` is stripped
