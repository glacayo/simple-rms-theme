# Wizard AI Prompt Guide Specification

## Purpose

A reference document (`Wizard ai harness prompt guide.md`) intended to stay aligned with shipped PHP harness contracts, encoding editorial rules, field classifications, and product decisions for all ACF layouts. The guide is not read at runtime and may be updated in a separate documentation change when explicitly excluded from the implementation commit.

## Requirements

### Requirement: Guide-Harness Synchronization

The guide SHOULD reflect the shipped PHP harness contracts for all three prompt layers. When a harness change modifies editorial rules, allowlists, or blocklists, the guide update MUST be either included in the same documentation change or explicitly deferred when the guide file is excluded from commit scope. The guide MUST NOT contain instructions that contradict shipped PHP behavior. Product criteria win over guide prose on any conflict.

#### Scenario: Harness change triggers guide update

- GIVEN a change modifies Layer 3 editorial rules for any layout
- WHEN the change is completed
- THEN `Wizard ai harness prompt guide.md` reflects the updated rules or the deferred guide-sync work is documented

#### Scenario: Guide-harness contradiction resolved

- GIVEN the guide says a field is "MUST NOT FILL" but the harness treats it as fillable
- THEN the guide MUST be updated to match the harness

### Requirement: Product Decision Documentation

Intentional deviations MUST be documented in the guide with rationale when the guide-sync documentation change ships. Documented deviations: lean context (`company_name`, `company_covered_areas`, `company_services` only), `about_badge_label` fillable, `video_v1_video_title` fillable, `about_text` 50–60 words per paragraph, badges framed as local directory/profile links, service names sourced only from `company_services.service_name`.

#### Scenario: Deviation documented with rationale

- GIVEN `about_badge_label` is fillable contrary to strict no-invention rules
- WHEN a reader checks the guide
- THEN the guide explicitly states the decision and its rationale

### Requirement: Field Classification Accuracy

For each ACF layout the guide MUST list fillable fields, blocked fields, and editorial constraints matching `get_fillable_fields()` and `get_blocked_fields()`.

#### Scenario: Guide matches harness for newly enabled repeaters

- GIVEN the harness enables text fields in `slider_slides` as fillable
- WHEN a reader checks the guide's slider section
- THEN those fields are listed as AI-fillable with editorial constraints
