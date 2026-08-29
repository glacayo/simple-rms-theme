# Wizard Canonical Sections Specification

## Purpose

A dedicated first-write store for neutral, reusable section content shared across the Home page and all landings. It keeps reusable copy consistent, prevents accidental overwrites, and supports per-landing overrides without leaking keywords.

## Requirements

### Requirement: Dedicated Canonical Store

Canonical reusable section content MUST be persisted in a dedicated option `rms_wizard_canonical_sections`, separate from the wizard state option. The wizard state MUST hold only a small summary (which layouts have a canonical payload and a `generated_at` timestamp), not the full payloads. The store MUST be lazy-loaded on demand rather than read on every state access.

#### Scenario: Payload stored in dedicated option

- GIVEN a reusable section is canonicalized
- WHEN the payload is saved
- THEN it is written to `rms_wizard_canonical_sections`, not the wizard state option

#### Scenario: State keeps only a summary

- GIVEN a canonical payload exists for `about-us`
- WHEN the wizard state is read
- THEN it exposes only a summary entry (has-payload + timestamp), not the full copy

### Requirement: First-Write Semantics

The store MUST write a layout's canonical payload only when it is empty for that layout (`set_if_empty()`). Automatic re-runs MUST NOT overwrite existing canonical content. Overwriting MUST require an explicit user replace action (`replace()`) gated by a confirmation affordance; no automatic replace is permitted.

#### Scenario: First write populates an empty layout

- GIVEN no canonical payload exists for `services`
- WHEN a run produces a `services` payload
- THEN `set_if_empty()` stores it as the canonical entry

#### Scenario: Re-run does not overwrite canonical

- GIVEN a canonical payload already exists for `services`
- WHEN a later run regenerates `services`
- THEN the canonical entry is unchanged

#### Scenario: Explicit replace overwrites after confirmation

- GIVEN a canonical payload exists and the admin confirms a replace action
- WHEN `replace()` is invoked
- THEN the canonical entry is overwritten with the new payload

### Requirement: Reusable Section Neutrality

Canonical content MUST remain keyword-neutral. Reusable layouts include About, Services, FAQ, Mission, Vision, Why Choose Us, Testimonials, and similar shared sections — every layout except `hero` and `seo-content`. Testimonials MUST participate in the canonical store like any other reusable layout. The keyword-driven layouts `hero` and `seo-content` MUST NOT be stored as canonical content.

#### Scenario: Testimonials are canonical/reusable

- GIVEN a `testimonials-v1` section is produced
- WHEN it is processed for canonicalization
- THEN it participates in the canonical store like any other reusable layout

#### Scenario: Keyword layouts excluded from store

- GIVEN a run produces `hero` and `seo-content` payloads
- WHEN canonicalization runs
- THEN neither `hero` nor `seo-content` is written to the canonical store

### Requirement: Per-Landing Override

A per-landing `override_canonical` checkbox MUST allow regenerating a reusable section for that landing only. Override results MUST be written to the landing's own sections only and MUST NOT overwrite canonical content. Overrides MUST stay keyword-neutral. If an override regeneration returns an empty or invalid payload, the landing MUST fall back to the canonical copy and log the failure, and MUST NOT persist placeholders.

#### Scenario: Override writes landing-only

- GIVEN a landing row for `about-us` with `override_canonical = true`
- WHEN the section is regenerated
- THEN the result is stored in that landing's sections only and the canonical store is untouched

#### Scenario: Override failure falls back to canonical

- GIVEN an override regeneration returns an empty payload and canonical `about-us` exists
- WHEN the step handles the failure
- THEN the landing uses the canonical copy and the failure is logged

#### Scenario: Override stays keyword-neutral

- GIVEN a reusable section is regenerated via override
- WHEN the prompt is composed
- THEN no keyword context is injected for that section

---

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

---

### Requirement: Placeholder Payloads Excluded From Canonical

A generated payload whose fields hold placeholder values MUST NOT be written to the canonical store, so placeholder text never becomes shared reusable content.

#### Scenario: Placeholder payload is not canonicalized

- GIVEN a reusable layout generated with placeholder field values
- WHEN canonicalization runs
- THEN the canonical entry for that layout stays empty
