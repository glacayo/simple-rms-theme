# Delta for wizard-page-generation

> Baseline: NOT published. The only `wizard-page-generation` spec lives in the active change `wizard-user-friendly-content-flow`. Because no `openspec/specs/wizard-page-generation/spec.md` exists yet, this delta uses ADDED only — a MODIFIED block would have no requirement to replace at archive time.
> Ordering: `wizard-user-friendly-content-flow` MUST archive before this change. These requirements are additive to `Page Creation and Update` and `Step State Persistence` and contradict neither.

## ADDED Requirements

### Requirement: Internal Page Template Assignment at Shell Creation

When a generated page's type has an internal blueprint, the wizard MUST persist that blueprint's `_wp_page_template` value at shell creation time, in the same write that creates or updates the page, so the first render uses that template. Pages whose type has no blueprint MUST keep their current template behavior unchanged.

#### Scenario: Blueprinted shell carries its template

- GIVEN a row whose type maps to an internal blueprint
- WHEN Generate Pages creates the shell
- THEN `_wp_page_template` is set in the creating write and the first load renders that template

#### Scenario: Existing page updated keeps a single record

- GIVEN a row whose slug matches an existing page
- WHEN Generate Pages updates it
- THEN the blueprint template is applied to that page and no duplicate page is created

#### Scenario: Unblueprinted page unchanged

- GIVEN a row whose type has no internal blueprint
- WHEN Generate Pages creates the shell
- THEN no internal blueprint template is assigned

### Requirement: Shell Creation Does Not Build Sections

Generate Pages MUST NOT write `page_sections` rows, run internal blueprint layouts, or generate posts. Section content for internal pages MUST be produced only by the internal page builder step.

#### Scenario: No sections written at shell creation

- GIVEN Generate Pages runs for a blueprinted page type
- WHEN the shell is created
- THEN `page_sections` remains empty for that page
