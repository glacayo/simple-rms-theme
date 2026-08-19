# Wizard Page Generation Specification

## Purpose

A guided wizard step that lets the admin build a custom list of pages to generate, assigns Home and Blog roles per row, and creates or updates those pages in WordPress using AI-generated content derived from Client Data.

---

## Requirements

### Requirement: Custom Page Row UI

The wizard MUST present a dynamic, row-based page builder. Each row MUST contain an editable title field and an editable slug field. The admin MUST be able to add any number of custom page rows and remove any existing row before submitting the step. At least one row MUST exist before the step can be submitted.

#### Scenario: Admin adds a custom page row

- GIVEN the Generate Pages step is open
- WHEN the admin clicks "Add page"
- THEN a new row with empty title and slug fields is appended to the row list

#### Scenario: Admin removes a row

- GIVEN one or more rows exist
- WHEN the admin clicks "Remove" on a row
- THEN that row is removed from the list

#### Scenario: No rows on submit

- GIVEN zero rows exist in the list
- WHEN the admin submits the step
- THEN the wizard displays a validation error and does not proceed

---

### Requirement: Common Pages Quick-Start

The wizard MUST provide an "Add common pages" action that populates the row list with a predefined set of template rows (e.g. Home, About, Services, Blog, Contact). This action MUST be treated as a convenience shortcut only — it populates rows that the admin MAY subsequently edit or remove. It MUST NOT lock the admin into a fixed page set.

#### Scenario: Admin uses common pages shortcut

- GIVEN the row list is empty or partially filled
- WHEN the admin clicks "Add common pages"
- THEN template rows for the common set are appended, and the admin can edit, remove, or add further rows freely

#### Scenario: Common pages do not replace existing rows

- GIVEN the admin has already added custom rows
- WHEN the admin clicks "Add common pages"
- THEN template rows are appended without removing the existing rows

---

### Requirement: Home and Blog Role Assignment

The wizard MUST require the admin to designate exactly one row as **Home** via a radio control on that row. Blog designation is optional; the admin MAY mark at most one row as **Blog** using a separate radio control. These designations MUST be enforced before the step completes. Neither the Home nor the Blog role is tied to a specific page title or slug — the admin assigns the role to whichever row they choose.

#### Scenario: Valid Home assignment

- GIVEN one or more rows exist with titles and slugs
- WHEN the admin selects a row's Home radio and submits
- THEN that row's slug is sent as `home_slug` in the step payload

#### Scenario: Blog role assigned to a custom row

- GIVEN the admin marks any row's Blog radio
- WHEN the step completes
- THEN that row's slug is sent as `blog_slug` and WordPress reading settings are updated to use it as the posts page

#### Scenario: Blog role not assigned

- GIVEN the admin does not mark any row as Blog
- WHEN the step completes
- THEN no `blog_slug` is sent and WordPress reading settings are not changed

#### Scenario: Home role missing on submit

- GIVEN no row has its Home radio selected
- WHEN the admin submits
- THEN the wizard displays an error: "Please mark one page as Home" and blocks completion

#### Scenario: Duplicate slugs on submit

- GIVEN two or more rows share the same slug value
- WHEN the admin submits
- THEN the wizard displays a validation error identifying the duplicate and blocks completion

---

### Requirement: Destructive Page Cleanup

Before creating or updating wizard-selected pages, the wizard MUST display a confirmation warning informing the admin that all existing WordPress pages outside the current wizard selection will be permanently deleted. The admin MUST explicitly confirm before the step proceeds. Upon confirmation, the wizard MUST delete all non-selected pages so that only the wizard-selected pages remain as the active page set after the step completes.

#### Scenario: Warning displayed before page destruction

- GIVEN the admin has added rows and submits the Generate Pages step
- WHEN WordPress has existing pages not included in the current wizard row set
- THEN the wizard displays: "Existing pages not in your selection will be permanently deleted. This cannot be undone."
- AND the step does not proceed until the admin explicitly confirms

#### Scenario: Admin confirms and cleanup runs

- GIVEN the admin confirms the destructive warning
- WHEN the step runs
- THEN all existing WordPress pages outside the wizard row set are deleted
- AND only the wizard-defined pages are created or updated and remain as the active page set

#### Scenario: Admin cancels the warning

- GIVEN the warning is displayed
- WHEN the admin cancels or dismisses it
- THEN no pages are deleted or created and the wizard remains on the Generate Pages step

---

### Requirement: Page Creation and Update

The wizard MUST create each page defined in the row list via the wizard page-building flow. If a page with the same slug already exists, the wizard MUST update it rather than create a duplicate. Each page MUST be published.

#### Scenario: New pages created

- GIVEN rows with slugs that do not exist in WordPress
- WHEN the step runs
- THEN each page is created with `post_status = publish` and its ID stored in `state.created_posts`

#### Scenario: Existing page updated

- GIVEN a row whose slug matches an existing WordPress page
- WHEN the step runs
- THEN the existing page's content is updated and no duplicate is created

---

### Requirement: AI-Assisted Page Content

The wizard SHOULD use the configured AI provider to generate initial page body content for each page in the row list, using Client Data (company name, services, location) as context. If no AI provider is configured or the call fails, the wizard MUST fall back to minimal placeholder content and log the failure.

#### Scenario: AI content generated successfully

- GIVEN an AI provider is configured in wizard state
- WHEN the Generate Pages step runs for a row
- THEN AI-generated text is used as the `post_content` and saved with the page

#### Scenario: AI provider unavailable

- GIVEN no AI provider is configured or the provider returns an error
- WHEN the step runs
- THEN each page is created with placeholder content, the failure is logged, and the step still completes

---

### Requirement: Step State Persistence

The wizard MUST persist the list of created page IDs, slugs, Home/Blog role assignments, and step completion status to `wp_options` after the step completes. On reload, previously created pages MUST be surfaced to later steps (Menu Setup, Home Page Builder).

#### Scenario: State available to subsequent steps

- GIVEN the Generate Pages step completed successfully
- WHEN the Menu Setup step loads
- THEN it reads `state.generated_pages` and renders the page list for menu assignment
