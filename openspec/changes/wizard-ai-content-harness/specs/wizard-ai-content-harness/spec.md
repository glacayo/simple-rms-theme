# Wizard AI Content Harness Specification

## Purpose

A versioned PHP service layer that encodes 3-layer prompt contracts, enforces field
allowlists/blocklists for all 27 ACF Home layouts, filters client context to harness-approved
fields, and validates AI responses before saving. Designed for Home Page Builder now;
architecture MUST be extensible to future page generators without structural changes.

---

## Requirements

### Requirement: Versioned Prompt Contracts

The harness MUST encode Layer 1 (global editorial system prompt), Layer 2 (page-type
context), and Layer 3 (per-layout section contract) as PHP class constants or method
return values. The harness MUST NOT read from any external file, markdown guide, or
runtime-resolved path. The untracked guide document is reference-only and MUST be
deleted after integration.

#### Scenario: Prompts delivered without guide file present

- GIVEN the markdown guide file is absent from the filesystem
- WHEN any harness prompt method is called
- THEN all three layers are returned from PHP-encoded data without error or warning

#### Scenario: Layer 2 defaults to PAGE_HOME

- GIVEN no page type argument is passed to the Layer 2 method
- WHEN the method executes
- THEN the PAGE_HOME context string is returned

---

### Requirement: Extensible Page-Type Architecture

The harness MUST define page-type identifiers (PAGE_HOME, PAGE_ABOUT, PAGE_SERVICE,
PAGE_LANDING, PAGE_BLOG, PAGE_CONTACT) as named constants. Only PAGE_HOME MUST be
implemented in this slice. Unknown or unimplemented page types MUST fall back to
PAGE_HOME with a logged warning rather than throwing.

#### Scenario: Unsupported page type falls back gracefully

- GIVEN `get_layer2('PAGE_BLOG')` is called before PAGE_BLOG is implemented
- WHEN the method executes
- THEN PAGE_HOME context is returned and a warning is logged

---

### Requirement: Client Context Filtering

The harness MUST expose `get_harness_context(array $client_data): array` that returns
ONLY approved client fields. Media, color, social link, and internal identifier fields
MUST be excluded from the returned array regardless of what the caller passes in.

#### Scenario: Non-approved fields stripped

- GIVEN `$client_data` includes `logo_url` and `brand_color`
- WHEN `get_harness_context()` is called
- THEN the returned array does not contain `logo_url` or `brand_color`

---

### Requirement: Missing Required Client Data Blocking

The harness MUST expose `validate_required_context(array $client_data): array` that
returns a list of missing required field keys. Callers MUST call this method before
making any AI request. If the returned list is non-empty, generation MUST be blocked
and a user-visible warning MUST be shown.

#### Scenario: Missing required field returns warning list

- GIVEN `$client_data` has no `company_name` value
- WHEN `validate_required_context()` is called
- THEN `['company_name']` is returned in the missing list

#### Scenario: All required fields present allows generation

- GIVEN `$client_data` contains all required fields
- WHEN `validate_required_context()` is called
- THEN an empty array is returned

---

### Requirement: Field Allowlist/Blocklist Enforcement

The harness MUST expose `get_fillable_fields(string $layout_key): array` and
`get_blocked_fields(string $layout_key): array` for all 27 ACF Home layouts.
After decoding an AI response, every key NOT present in `get_fillable_fields()` for
that layout MUST be stripped. Blocked field keys MUST be stripped regardless of whether
they overlap with the fillable list.

#### Scenario: Extra and blocked keys stripped after decode

- GIVEN the AI returns `{ "hero_title": "...", "hero_bg_image": "...", "invented_key": "..." }` for layout `hero`
- WHEN field validation runs (`hero_bg_image` is blocked, `invented_key` is unknown)
- THEN only `{ "hero_title": "..." }` is retained; the other two keys are stripped

#### Scenario: Invented badge data rejected

- GIVEN the AI returns `{ "about_badge_years": "25", "about_headline": "We deliver." }` for `about-us`
- WHEN field validation runs (`about_badge_years` is blocked)
- THEN only `{ "about_headline": "We deliver." }` is saved

---

### Requirement: No-Invention Editorial Constraint

Layer 1 MUST instruct the AI that it MUST NOT generate: URLs, shortcodes, image paths,
icon class names, boolean values, select option values, numeric statistics, geographic
or date data, testimonial content, customer names, star ratings, or real project/gallery
labels. Field validation (allowlist/blocklist) acts as the enforcement backstop for
any constraint violation the AI ignores.

#### Scenario: URL field rejected at validation

- GIVEN the AI returns `{ "cta_v1_button_url": "https://example.com", "cta_v1_headline": "Call us" }` for `cta-v1`
- WHEN field validation runs (`cta_v1_button_url` is blocked)
- THEN only `{ "cta_v1_headline": "Call us" }` is saved

#### Scenario: Testimonial repeater rejected

- GIVEN the AI returns `{ "testimonials_v1_items": [...], "testimonials_v1_headline": "What clients say" }` for `testimonials-v1`
- WHEN field validation runs (`testimonials_v1_items` is blocked)
- THEN only `{ "testimonials_v1_headline": "What clients say" }` is saved
