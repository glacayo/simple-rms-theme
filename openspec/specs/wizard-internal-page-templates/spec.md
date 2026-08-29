# Wizard Internal Page Templates Specification

## Purpose

The render contract for wizard-built internal pages: flexible `page_sections` output, template assignment, a repaired Testimonials template, and configurable posts-index chrome.

## Requirements

### Requirement: Flexible Section Rendering

The About, Services, Contact, Projects, and Testimonials templates MUST render by looping the `page_sections` flexible content field instead of hardcoded section sequences. A layout with no matching render partial MUST be skipped without a fatal error. An empty or missing `page_sections` value MUST render the page frame without error.

#### Scenario: Stored sections render in order

- GIVEN the About page has three stored `page_sections` rows
- WHEN the page renders
- THEN all three rows render in their stored order

#### Scenario: Unknown layout is skipped safely

- GIVEN a stored row whose layout has no render partial
- WHEN the page renders
- THEN that row is skipped and the remaining rows still render

#### Scenario: Empty sections render without error

- GIVEN a page whose `page_sections` value is empty
- WHEN the page renders
- THEN the page frame renders and no PHP error is emitted

### Requirement: Page Template Assignment

Each built internal page MUST carry the `_wp_page_template` value declared by its blueprint, persisted so the first render after the build uses that template. A built internal page MUST NOT fall back to the default `page.php` renderer.

#### Scenario: First render uses the blueprint template

- GIVEN the builder completes the Contact page
- WHEN the page is loaded for the first time
- THEN `_wp_page_template` is the blueprint template and that template renders

#### Scenario: Built page never falls back to page.php

- GIVEN any internal page marked `complete`
- WHEN its template is resolved
- THEN the blueprint template is used, not `page.php`

### Requirement: Testimonials Template Repair

The Testimonials template MUST be a syntactically valid PHP template that passes `php -l`, and MUST render stored testimonial rows through the flexible section contract.

#### Scenario: Template passes syntax check

- GIVEN the Testimonials template file
- WHEN `php -l` runs against it
- THEN it reports no syntax errors

#### Scenario: Testimonial rows render

- GIVEN a Testimonials page with stored testimonial rows
- WHEN the page renders
- THEN the stored rows are output

### Requirement: Configurable Posts Index Chrome

The posts index assigned via `page_for_posts` MUST render through a theme `home.php` template that outputs configurable index chrome plus the WordPress posts loop. The wizard MUST NOT create, generate, or modify any post. When no posts exist, the index MUST render its chrome and an empty state.

#### Scenario: Index renders chrome and loop

- GIVEN a blog index page assigned as `page_for_posts`
- WHEN the index is loaded
- THEN `home.php` renders the configured chrome and the posts loop

#### Scenario: No posts are generated

- GIVEN the builder completes the Blog index blueprint
- WHEN the run finishes
- THEN the post count is unchanged

#### Scenario: Empty index renders an empty state

- GIVEN the site has zero published posts
- WHEN the index is loaded
- THEN the chrome renders and an empty state is shown without error

### Requirement: Services Independent of Static Demo Markup

The Services page MUST render its content from stored `page_sections` rows and MUST NOT depend on the static demo `services-page` partial for its content.

#### Scenario: Services renders stored rows

- GIVEN a Services page with stored `page_sections` rows
- WHEN the page renders
- THEN the stored rows are output and no static demo service content appears
