# Wizard Landing Page Builder Specification

## Purpose

The 8th wizard step generates N WordPress landing pages from `pages/landing-page.php`, governing per-landing keywords, landing type, indexability, menu eligibility, and Yoast meta.

## Requirements

### Requirement: Landing Page Generation

The step MUST create one independent WordPress page per landing using `pages/landing-page.php`, setting `_wp_page_template` at insert time via `wp_insert_post` `meta_input` (never a `save_post`/`post_updated` hook) so the first render is correct. Landings MUST NOT receive Home/Blog reading settings.

#### Scenario: First render uses landing template

- GIVEN the admin requests a new landing
- WHEN the step creates the page
- THEN `_wp_page_template` is `pages/landing-page.php` at insert time and the first load renders it

#### Scenario: N landings created independently

- GIVEN the admin requests three landings in one run
- WHEN the step runs
- THEN three separate pages are created, each with its own sections and metadata

### Requirement: Landing Keyword Governance

Each landing MUST carry one primary keyword and 0–10 subkeywords; empty MUST mean 0, and the server MUST clamp the count to 10 and drop empties. Only `hero` and `seo-content` MUST consume keyword context; all other reusable sections, including override rows, MUST stay keyword-neutral.

#### Scenario: Keyword sections consume the keyword

- GIVEN a landing with a primary keyword and 3 subkeywords
- WHEN the step generates `hero` and `seo-content`
- THEN those sections receive the primary keyword and subkeywords

#### Scenario: Reusable section stays neutral

- GIVEN the same landing includes an `about-us` section
- WHEN the step generates it
- THEN no keyword context is injected

#### Scenario: Subkeyword count is bounded

- GIVEN 12 subkeywords with 2 blanks, or an empty field
- WHEN the server processes them
- THEN empties are dropped and the count is at most 10 (0 when empty)

### Requirement: Landing Type and Menu Eligibility

Each landing MUST persist `rms_landing_type` post meta (`seo`|`ads`) at insert time. SEO landings MUST be indexable and menu-eligible by default; Ads landings MUST be orphan/campaign pages, noindex by default and never auto-added to a menu. `menu_eligible` MUST default to `true` for `seo` and `false` for `ads`; menu setup MUST include only eligible landings.

#### Scenario: SEO landing is indexable and menu-eligible

- GIVEN a landing with `landing_type = seo`
- WHEN it is generated
- THEN `rms_landing_type` is `seo`, `menu_eligible` is `true`, and it may join the menu

#### Scenario: Ads landing is orphan and excluded from menu

- GIVEN a landing with `landing_type = ads`
- WHEN it is generated
- THEN `rms_landing_type` is `ads`, `menu_eligible` is `false`, and it is not auto-added to any menu

### Requirement: Ads Landing Noindex Enforcement

For `ads` landings the step MUST apply noindex via Yoast/post meta where available AND a `wp_robots` filter scoped to BOTH `_wp_page_template = pages/landing-page.php` AND `rms_landing_type = ads`. It MUST read the meta back and MUST NOT complete the landing if it is missing. SEO landings MUST NOT be noindexed by this filter.

#### Scenario: Ads landing is noindex with double protection

- GIVEN an `ads` landing is generated
- WHEN it finalizes
- THEN noindex meta is written and the scoped `wp_robots` filter emits noindex for it

#### Scenario: Missing noindex blocks completion

- GIVEN an `ads` landing whose noindex meta failed to persist
- WHEN the step reads the meta back
- THEN the landing is not marked complete and the failure is logged

#### Scenario: SEO landing is not noindexed

- GIVEN an `seo` landing on the same template
- WHEN the `wp_robots` filter runs
- THEN it does not emit noindex because `rms_landing_type` is not `ads`

### Requirement: Per-Landing Yoast Meta

When Yoast is active (`is_plugin_active('wordpress-seo/wordpress-seo.php')` OR `defined('WPSEO_VERSION')`) the step MUST generate and write a per-landing meta title and description from the primary keyword and landing type. When Yoast is absent it MUST skip, log a notice, and still complete the landing.

#### Scenario: Yoast active writes meta

- GIVEN Yoast is active
- WHEN a landing is generated
- THEN a meta title and description are written for that landing

#### Scenario: Yoast absent skips and logs

- GIVEN neither Yoast check passes
- WHEN a landing is generated
- THEN meta generation is skipped, a notice is logged, and the landing still completes
