# Wizard Internal Page Builder Specification

## Purpose

One optional, blueprint-driven step that fills ACF `page_sections` for internal pages the wizard already generated.

## Requirements

### Requirement: Optional Blueprint-Driven Step

The wizard MUST expose exactly one optional internal page builder step with a skip-all action that completes it without mutation. Fixed, non-composable blueprints MUST exist for About, Services, Contact, Projects, Testimonials, and Blog index, each declaring template, default layouts, harness page type, and canonical policy.

#### Scenario: Skip-all completes without mutation

- GIVEN the step is open
- WHEN the admin chooses skip-all
- THEN the step completes and no page or canonical row is written

#### Scenario: Blueprint supplies the build contract

- GIVEN a generated About page
- WHEN the step builds it
- THEN the About blueprint's template, layouts, and harness page type are used

### Requirement: Scope Limited to Generated Pages

The step MUST build only pages in `state.generated_pages` whose type has a blueprint. It MUST NOT create or delete pages, generate posts, or rebuild menus.

#### Scenario: Missing shell is not created

- GIVEN Projects was never selected in Generate Pages
- WHEN the step runs
- THEN no Projects page is created and it is reported unavailable

#### Scenario: Unblueprinted page is untouched

- GIVEN a generated page with no blueprint
- WHEN the step lists buildable pages
- THEN that page is neither offered nor mutated

### Requirement: Resumable Plan with Failure Isolation

The step MUST persist a per-page plan with status `pending`, `complete`, `failed`, or `skipped`, and MUST resume an interrupted run without rebuilding completed pages. A page failure MUST NOT abort the run or block other pages, MUST record a reason, and a retry MUST target only `failed` and `pending` pages.

#### Scenario: Run resumes after interruption

- GIVEN a run interrupted after two of four pages
- WHEN the admin resumes
- THEN those two are not rebuilt and the remaining two are processed

#### Scenario: One page fails, others complete

- GIVEN four buildable pages and the Services build fails
- WHEN the run finishes
- THEN Services is `failed` with a reason and the others are `complete`

#### Scenario: Retry skips completed pages

- GIVEN three `complete` pages and one `failed` page
- WHEN the admin retries
- THEN only the failed page is rebuilt

### Requirement: Atomic Mutation Under the Fence

Every mutating run MUST execute under the existing wizard mutation fence and release the state lock on success and on failure. A page MUST become `complete` only after its sections, template, and status persist; a partial write MUST leave it `failed`.

#### Scenario: Lock released after failure

- GIVEN a page build fails during persistence
- WHEN the run returns
- THEN the state lock is released and that page is `failed`

#### Scenario: Rerun of a complete page is a no-op

- GIVEN a page already `complete` with unchanged inputs
- WHEN the run processes it
- THEN no write occurs and the status stays `complete`

### Requirement: Edit Preservation and Explicit Overwrite

The step MUST NOT overwrite existing `page_sections` or legacy body content without an explicit user action; regeneration MUST require an explicit overwrite choice. When a page holds legacy content the build would replace, the wizard MUST require a conversion confirmation naming that page.

#### Scenario: Post-wizard edits survive a rerun

- GIVEN an editor changed sections on the built About page
- WHEN the step runs again without an overwrite choice
- THEN the stored sections are unchanged

#### Scenario: Explicit overwrite regenerates

- GIVEN the admin confirms overwrite for Contact
- WHEN the run processes Contact
- THEN its sections are regenerated and replaced

#### Scenario: Unconfirmed legacy conversion is skipped

- GIVEN Services holds legacy body content and no conversion confirmation
- WHEN the run processes Services
- THEN the page is unchanged and reported as `skipped`
