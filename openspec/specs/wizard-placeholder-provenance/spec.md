# Wizard Placeholder Provenance Specification

## Purpose

Tracking for placeholder values the builder writes when factual inputs are absent. Placeholders publish unmarked; provenance exists so owners can find and replace them, and so internal consumers never mistake them for client facts.

## Requirements

### Requirement: Unmarked Public Placeholders

When a blueprint field has no factual input available, the builder MAY write a placeholder value. Placeholder values MUST render publicly exactly like authored content: the public output MUST NOT add placeholder labels, badges, warnings, or markers, and MUST NOT hide or suppress the section. Placeholders MUST NOT block page completion, step completion, or wizard completion.

#### Scenario: Placeholder renders unmarked

- GIVEN a built page whose headline field holds a placeholder value
- WHEN a visitor loads the page
- THEN the value renders with no placeholder label, badge, or marker

#### Scenario: Placeholder does not block completion

- GIVEN a page built with three placeholder fields
- WHEN the run finishes
- THEN the page is `complete` and the step can complete

### Requirement: Per-Field Provenance Record

For every placeholder written, the wizard MUST persist a provenance entry identifying the page, the layout row, the field key, the reason the factual input was absent, and the write timestamp. Provenance MUST be queryable by page and by field.

#### Scenario: Provenance recorded per field

- GIVEN the builder writes placeholders for two fields on the Projects page
- WHEN the run finishes
- THEN two provenance entries exist, each naming its page, layout row, field key, reason, and timestamp

#### Scenario: Provenance queryable by page

- GIVEN provenance entries exist across several pages
- WHEN the wizard is queried for the Contact page
- THEN only that page's placeholder entries are returned

### Requirement: Replacement Queue and Clearing

The wizard MUST expose a replacement queue listing every field that still holds a placeholder. When a field's placeholder is replaced with a real value, its provenance entry MUST be cleared and the field MUST leave the queue. Replacement MUST NOT require rerunning the builder, and clearing one field MUST NOT clear any other.

#### Scenario: Queue lists outstanding placeholders

- GIVEN five placeholder fields exist across two pages
- WHEN the replacement queue is read
- THEN it lists all five fields with their pages and field keys

#### Scenario: Replacing a value clears its entry

- GIVEN a queued placeholder field
- WHEN a real value is saved for that field
- THEN its provenance entry is cleared and the queue no longer lists it

#### Scenario: Clearing one field leaves others queued

- GIVEN three queued placeholder fields on one page
- WHEN one is replaced
- THEN the other two remain queued with their provenance intact

### Requirement: Placeholders Are Never Internal Facts

Placeholder values MUST NOT be treated as verified client data by any internal consumer. They MUST NOT be written into client data, MUST NOT be stored as canonical reusable content, and MUST NOT be reused as factual context for later generation.

#### Scenario: Placeholder excluded from canonical store

- GIVEN a reusable section whose fields hold placeholder values
- WHEN canonicalization runs
- THEN that payload is not written to the canonical store

#### Scenario: Placeholder not reused as factual context

- GIVEN a page field holds a placeholder value
- WHEN a later generation composes factual context
- THEN the placeholder value is not supplied as client data
