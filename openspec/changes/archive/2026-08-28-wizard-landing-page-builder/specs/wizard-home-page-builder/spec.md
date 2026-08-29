# Delta for wizard-home-page-builder

## ADDED Requirements

### Requirement: Canonical First-Write on Home Success

After a successful Home Page Builder run, the step MUST copy each produced reusable section payload into the canonical store using first-write semantics (`set_if_empty()`), skipping the keyword-driven layouts `hero` and `seo-content`. Re-runs MUST NOT overwrite existing canonical content; the canonical store MUST be read-only on re-run. The step MUST update the state summary to record which layouts now have a canonical payload. This behavior is additive: the existing generation, review, validation, and save flow MUST remain unchanged.

#### Scenario: First Home run seeds empty canonical layouts

- GIVEN a successful Home run produces `about-us` and `services` payloads and the canonical store is empty for them
- WHEN the step finalizes
- THEN `set_if_empty()` stores both as canonical entries and the state summary records them

#### Scenario: Home re-run does not clobber canonical

- GIVEN canonical payloads already exist for `about-us` and `services`
- WHEN a later Home run regenerates those sections
- THEN the canonical store entries are unchanged

#### Scenario: Keyword layouts are skipped

- GIVEN a Home run produces `hero` and `seo-content` payloads
- WHEN canonical first-write runs
- THEN neither `hero` nor `seo-content` is written to the canonical store
