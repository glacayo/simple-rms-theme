# Wizard Setup Specification

## Purpose

A theme-owned admin wizard that guides an administrator through a one-time, multi-step setup: installing required plugins, importing ACF data, collecting client info, generating AI content, creating WordPress content, and locking the site as configured.

---

## Requirements

### Requirement: Access Control

Only a user with the `manage_options` capability MAY access or execute the wizard.

#### Scenario: Unauthorized access attempt

- GIVEN a logged-in user without `manage_options`
- WHEN they navigate to the wizard admin page
- THEN WordPress returns a permissions error and the wizard UI is not rendered

---

### Requirement: Dependency Installation

The wizard MUST ensure ACF PRO, Classic Editor, and Yoast SEO are installed and active before proceeding past the dependencies step. These plugins MUST be marked `required: true` in TGMPA.

#### Scenario: All dependencies satisfied

- GIVEN all required plugins are installed and active
- WHEN the wizard loads the dependencies step
- THEN it marks the step as complete and enables the next step

#### Scenario: Missing required plugin

- GIVEN one or more required plugins are not active
- WHEN the admin triggers installation
- THEN the wizard installs and activates each missing plugin via TGMPA and reports per-plugin success or failure

---

### Requirement: ACF JSON Import

The wizard MUST import ACF field group definitions from `acf-json/` during the designated import step. If a field group already exists, the wizard MUST skip it and log the conflict.

#### Scenario: Clean import

- GIVEN no ACF field groups exist in the database
- WHEN the ACF import step runs
- THEN all field groups from `acf-json/` are registered and the step is marked complete

#### Scenario: Conflict on existing group

- GIVEN a field group with the same key already exists
- WHEN the ACF import step runs
- THEN the wizard skips that group, logs "skipped: already exists", and continues without error

---

### Requirement: Client Data Collection

The wizard MUST collect company information from the admin (name, phones, emails, social links, branding colors, logos) and persist it to ACF theme options (`rms-theme-settings`).

#### Scenario: Valid client data submitted

- GIVEN the admin fills all required client data fields
- WHEN the step is submitted
- THEN values are saved to ACF theme options and the step is marked complete

---

### Requirement: AI Content Generation via Custom Adapter

The wizard MUST generate content through a custom adapter that calls provider APIs (OpenAI / Google / Anthropic) using `wp_remote_request()`. Native WordPress AI APIs MUST NOT be used. On failure the adapter MUST retry with exponential backoff (max 3 attempts) before marking a section as failed.

#### Scenario: Successful generation

- GIVEN a valid API key and a reachable provider endpoint
- WHEN the wizard requests content for a section
- THEN the adapter returns generated text and caches it in a transient keyed to the wizard session

#### Scenario: Provider failure with retry

- GIVEN the provider returns a 5xx error
- WHEN the adapter retries up to 3 times and all fail
- THEN the section is marked failed, an error is logged, and the admin can manually retry that section

---

### Requirement: Content and Yoast Metadata Creation

The wizard MUST programmatically create WordPress pages, populate ACF flexible-content fields, upload or insert media placeholders, and write Yoast SEO title and description meta for each created page.

#### Scenario: Full content creation

- GIVEN AI content is available for all sections
- WHEN the content creation step runs
- THEN pages exist in WordPress, ACF fields are populated, and Yoast meta (`_yoast_wpseo_title`, `_yoast_wpseo_metadesc`) is set on each page

---

### Requirement: Autosave and Resume

The wizard MUST persist step progress and generated content to `wp_options` after each step completion. On reload, it MUST resume from the last saved step.

#### Scenario: Session resume after interruption

- GIVEN the admin closed the browser mid-wizard
- WHEN they return to the wizard admin page
- THEN the wizard loads the last incomplete step with previously entered data intact

---

### Requirement: Completion Lock

After all steps succeed the wizard MUST write a completion flag to `wp_options` and prevent re-execution. A `define('RMS_WIZARD_FORCE', true)` constant in `wp-config.php` MUST bypass the lock for developer use.

#### Scenario: Lock prevents re-run

- GIVEN the wizard completion flag is set
- WHEN an admin navigates to the wizard page
- THEN a locked summary screen is shown and no step is executable

#### Scenario: Force bypass active

- GIVEN `RMS_WIZARD_FORCE` is `true` in `wp-config.php`
- WHEN an admin navigates to the wizard page
- THEN the wizard renders normally, ignoring the completion lock
