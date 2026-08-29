# Delta for wizard-ai-content-harness

## ADDED Requirements

### Requirement: Landing Page Type Context (Layer 2)

The harness MUST provide a `PAGE_LANDING` Layer 2 context block that encodes landing purpose (single conversion goal), tone, section-ordering guidance aligned with `pages/landing-page.php`, and ads-vs-seo intent branching. `get_layer2()` MUST continue to return the `PAGE_HOME` block by default and MUST return the `PAGE_LANDING` block only when the active page type is landing. Existing `PAGE_HOME` behavior MUST remain unchanged.

#### Scenario: Landing page type returns landing context

- GIVEN a caller requests Layer 2 with page type `PAGE_LANDING`
- WHEN `get_layer2()` executes
- THEN the landing context (purpose, tone, ordering, ads/seo intent) is returned

#### Scenario: Default page type stays Home

- GIVEN no page type or `PAGE_HOME` is passed
- WHEN `get_layer2()` executes
- THEN the existing PAGE_HOME block is returned unchanged

### Requirement: Landing Keyword Injection (Layer 3)

When the active page type is `PAGE_LANDING` AND the layout is `hero` or `seo-content`, `get_layer3()` MUST replace `{{primary_keyword}}` and `{{subkeywords}}` with the landing's keyword context, with subkeywords clamped to the 0–10 range. For any other layout, and for any non-landing page type, the harness MUST NOT inject keyword placeholders — including reusable rows regenerated via `override_canonical`. The existing `{{item_count}}` and `{{client_json}}` replacement behavior MUST remain unchanged.

#### Scenario: Hero on landing receives keyword context

- GIVEN page type `PAGE_LANDING` and layout `hero`
- WHEN `get_layer3()` composes the prompt
- THEN `{{primary_keyword}}` and `{{subkeywords}}` are replaced with the landing's keyword context

#### Scenario: Reusable layout never receives keyword

- GIVEN page type `PAGE_LANDING` and layout `about-us`
- WHEN `get_layer3()` composes the prompt
- THEN no keyword placeholders are injected

#### Scenario: Override row stays keyword-neutral

- GIVEN a reusable layout regenerated with `override_canonical = true`
- WHEN the harness composes the prompt
- THEN no keyword placeholders are injected

#### Scenario: Home page type unaffected

- GIVEN page type `PAGE_HOME` for any layout
- WHEN `get_layer3()` composes the prompt
- THEN no keyword placeholders are injected and item_count/client_json behavior is unchanged
