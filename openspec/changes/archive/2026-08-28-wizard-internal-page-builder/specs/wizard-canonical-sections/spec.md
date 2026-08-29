# Delta for wizard-canonical-sections

> Baseline: NOT published. The only `wizard-canonical-sections` spec lives in the active change `wizard-landing-page-builder`. Because no `openspec/specs/wizard-canonical-sections/spec.md` exists yet, this delta uses ADDED only — a MODIFIED block would have no requirement to replace at archive time.
> Ordering: `wizard-landing-page-builder` MUST archive before this change. These requirements are additive to `First-Write Semantics` and `Reusable Section Neutrality` and contradict neither.

## ADDED Requirements

### Requirement: Internal Page Canonical Copy

When an internal page build produces a reusable layout that already has a canonical payload, the builder MUST copy that payload into the page's own `page_sections` rows and MUST leave the canonical entry unchanged. When no canonical payload exists for that layout, the builder MAY first-write the generated payload via `set_if_empty()`. Internal page builds MUST NOT call `replace()`; overwriting canonical content MUST remain an explicit, separately confirmed user action.

#### Scenario: Existing canonical payload is copied

- GIVEN a canonical `about-us` payload exists
- WHEN the builder builds the About page
- THEN the page rows receive a copy and the canonical entry is unchanged

#### Scenario: Empty canonical layout is first-written

- GIVEN no canonical payload exists for a reusable layout
- WHEN the builder generates that layout
- THEN `set_if_empty()` stores it as the canonical entry

#### Scenario: Internal build never auto-replaces canonical

- GIVEN a canonical payload exists and the admin has confirmed a page overwrite
- WHEN the builder rebuilds that page
- THEN the page rows are replaced and the canonical entry is still unchanged

### Requirement: Placeholder Payloads Excluded From Canonical

A generated payload whose fields hold placeholder values MUST NOT be written to the canonical store, so placeholder text never becomes shared reusable content.

#### Scenario: Placeholder payload is not canonicalized

- GIVEN a reusable layout generated with placeholder field values
- WHEN canonicalization runs
- THEN the canonical entry for that layout stays empty
