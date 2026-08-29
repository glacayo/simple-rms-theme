# Delta for wizard-ai-content-harness

> Baseline: `openspec/specs/wizard-ai-content-harness/spec.md` (published). The MODIFIED block below copies that published requirement in full.
> Ordering: the active `wizard-landing-page-builder` delta ADDs `PAGE_LANDING` Layer 2 and the active `wizard-ai-content-harness` change owns the page-type constant list. Both are additive to this delta; no requirement here replaces theirs.

## MODIFIED Requirements

### Requirement: Versioned Prompt Contracts

The harness MUST encode Layer 1 (global editorial system prompt), Layer 2 (page-type context), and Layer 3 (per-layout section contract) as PHP class constants or method return values. The harness MUST NOT read from any external file, markdown guide, or runtime-resolved path. Layer 1 MUST include full editorial standards: paragraph rules, headline/subheadline/eyebrow word counts, CTA text conventions, body copy density, FAQ pair structure, and services copy guidelines. Layer 2 for PAGE_HOME MUST include page purpose, tone, and section-ordering guidance from the guide. Layer 2 MUST return a dedicated context block for every implemented page type, and MUST fall back to PAGE_HOME only when no page type is supplied or the supplied type is unimplemented. Layer 3 MUST include per-layout field descriptions, word-count constraints, and structural rules.

(Previously: Layer 2 was specified for PAGE_HOME only, so every other page type resolved to the Home context.)

#### Scenario: Prompts delivered without guide file present

- GIVEN the markdown guide file is absent from the filesystem
- WHEN any harness prompt method is called
- THEN all three layers are returned from PHP-encoded data without error

#### Scenario: Layer 1 includes editorial standards

- GIVEN a caller requests the Layer 1 prompt
- WHEN `get_layer1()` returns
- THEN the output includes headline word-count ranges, paragraph density rules, CTA conventions, and no-invention constraints

#### Scenario: Layer 2 defaults to PAGE_HOME

- GIVEN no page type argument is passed to Layer 2
- WHEN the method executes
- THEN the PAGE_HOME context with purpose and tone guidance is returned

#### Scenario: Implemented page type does not fall back

- GIVEN an implemented internal page type is passed to Layer 2
- WHEN the method executes
- THEN that page type's own context is returned instead of PAGE_HOME

## ADDED Requirements

### Requirement: Internal Page Type Contexts

The harness MUST implement Layer 2 contexts for `PAGE_ABOUT`, `PAGE_SERVICE`, `PAGE_CONTACT`, and `PAGE_BLOG`, each encoding that page's purpose, tone, and section-ordering guidance. The `PAGE_BLOG` context MUST describe posts-index chrome only and MUST NOT instruct the model to write post bodies. Keyword placeholders MUST NOT be injected for these page types.

#### Scenario: About context returned

- GIVEN Layer 2 is requested for `PAGE_ABOUT`
- WHEN the method executes
- THEN the About purpose, tone, and ordering guidance are returned and no warning is logged

#### Scenario: Blog context covers index chrome only

- GIVEN Layer 2 is requested for `PAGE_BLOG`
- WHEN the method executes
- THEN the context describes index chrome and does not request post bodies

#### Scenario: Internal types stay keyword-neutral

- GIVEN any internal page type and any layout
- WHEN Layer 3 composes the prompt
- THEN no keyword placeholders are injected

### Requirement: Projects and Testimonials Page Types

The harness MUST define `PAGE_PROJECTS` and `PAGE_TESTIMONIALS` page-type identifiers with their own Layer 2 contexts. Project, gallery, media, and testimonial-item fields MUST remain blocked for these page types, so only headline-level and descriptive copy is fillable.

#### Scenario: Testimonials headlines fillable, items blocked

- GIVEN Layer 3 is composed for a testimonials layout under `PAGE_TESTIMONIALS`
- WHEN the fillable list is built
- THEN headline fields are fillable and testimonial item repeaters remain blocked

#### Scenario: Projects gallery stays blocked

- GIVEN Layer 3 is composed for a gallery layout under `PAGE_PROJECTS`
- WHEN the fillable list is built
- THEN no gallery, media, or project-label field is fillable
