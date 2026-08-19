# Wizard AI Content Reviewer Specification

## Purpose

Dedicated critique/diagnose/rewrite service inserted between `decode_json_content()` and
`validate_fields()` in the home page builder. Enforces research-backed quality guardrails
via a fixed taxonomy before any content reaches ACF. Has no UI in v1.

---

## Requirements

### Requirement: Diagnosis-First Review

The reviewer MUST classify every failed section using the taxonomy below before issuing a
rewrite. A rewrite prompt MUST NOT be sent without a diagnosis code assigned first.

| Code | When triggered |
|------|----------------|
| `generic_copy` | Content applies to any business; no specific value proposition |
| `semantic_repetition` | Same idea rephrased across or within sections |
| `unsupported_claims` | Fact not present in client context (invented year, area, stat) |
| `keyword_stuffing` | Unnatural keyword density or forced phrase insertion |
| `filler_content` | Words that add length but no information |
| `missing_trust_signal` | Trust section contains no differentiation or credibility marker |
| `missing_differentiator` | Copy claims differentiation but no real differentiator is present in trusted context |
| `intent_mismatch` | Section does not fulfill its declared page-role purpose |
| `ai_speak` | LLM tics: "peace of mind", "clear communication", hedging patterns |
| `overtechnical_language` | Jargon-heavy or method-first copy that does not translate the method into customer benefit |
| `repetitive_wording` | Same generic praise adjectives or phrases repeat across current and prior sections |
| `section_angle_overlap` | Section repeats the same promise or page job instead of serving a distinct angle |
| `guardrail_gap` | Harness guardrail violated — blocked field returned, invented URL |

#### Scenario: Mediocre section diagnosed before rewrite

- GIVEN the reviewer evaluates a section and detects `ai_speak` patterns
- WHEN the diagnosis step completes
- THEN the rewrite prompt is tailored to `ai_speak`
- AND no rewrite call is dispatched without a diagnosis code

#### Scenario: Clean section accepted without rewrite

- GIVEN the reviewer finds no flags on a section
- WHEN scoring completes
- THEN the content is returned unchanged and no AI rewrite call is made

#### Scenario: Overtechnical language is diagnosed before rewrite

- GIVEN a section uses technical process language without a clear customer benefit
- WHEN the reviewer evaluates the section
- THEN `overtechnical_language` is assigned
- AND the rewrite directive keeps credibility while translating method into customer value

---

### Requirement: Guardrail Basis

The L-critique prompt MUST enforce quality against these primary sources:
Google Helpful Content (people-first, originality),
Google Spam Policies (no keyword stuffing),
Google SQRG §2.3–2.6 (E-E-A-T, no unsupported claims),
Google LSA Policies (local service truthfulness),
NNGroup Be Succinct / Plain Language (scannability, active voice).

Secondary sources (Yoast readability, Hemingway grade, Copyblogger) MAY inform heuristics
but MUST NOT function as quality gates.

#### Scenario: Unsupported claim caught

- GIVEN the AI generated a service area or year not present in client context
- WHEN the reviewer evaluates the section
- THEN `unsupported_claims` is assigned
- AND the rewrite directive instructs removal of the unverifiable fact

---

### Requirement: Iteration Budget and Fallback

The reviewer MUST cap review/rewrite passes at N=2 per section. Pass 1 MUST apply a
soft-nudge rewrite directive; pass 2 MUST apply a harder directive. After N passes the
last content version MUST be returned and flagged in the dev report. On any AI failure or
provider timeout the reviewer MUST return the original decoded content and MUST NOT
propagate an exception or block save.

#### Scenario: Budget exhausted — last version accepted

- GIVEN two review passes have completed and minor flags remain on a section
- WHEN the iteration cap is reached
- THEN the last content version is returned to the builder
- AND the dev report marks the section as `budget_exhausted`
- AND save proceeds without interruption

#### Scenario: Review AI call times out

- GIVEN the reviewer's AI critique call does not return within the provider timeout
- WHEN the timeout fires
- THEN the original decoded payload is returned
- AND no exception propagates up to the builder
- AND save is not blocked

---

### Requirement: Word-Count Tolerance

Deviations of ±2–6 words from harness editorial rules MUST pass when the deviation
demonstrably improves naturalness or quality. Deviations exceeding 6 words MUST be
flagged. The reviewer MUST NOT penalize quality improvements that fall within tolerance.

#### Scenario: Minor overrun accepted

- GIVEN a harness rule specifies 6–12 words for a headline and AI returns 14 natural words
- WHEN the reviewer scores the field
- THEN the field passes; no rewrite is triggered

#### Scenario: Significant overrun flagged

- GIVEN a harness rule specifies 50–60 words for a body paragraph and AI returns 78 words
- WHEN the reviewer scores the field
- THEN the field is flagged and the rewrite directive includes a conciseness instruction

---

### Requirement: Cross-Section Repetition Check

Section N MUST be compared against sections 1..N-1 already generated on the same page to
detect semantic paraphrase repetition. Section 1 has no prior comparator and MUST skip
the repetition check. Detected paraphrase MUST be assigned `semantic_repetition` and the
rewrite prompt MUST include the prior sections as negative context.

#### Scenario: Paraphrase detected across sections

- GIVEN sections 1–3 are already generated and section 4 repeats the same value
  proposition as section 2 in different words
- WHEN the reviewer evaluates section 4
- THEN `semantic_repetition` is assigned
- AND the rewrite prompt receives sections 1–3 payloads as negative examples

#### Scenario: First section skips repetition check

- GIVEN section 1 is being reviewed and no prior sections exist
- WHEN the reviewer initializes comparison context
- THEN no prior-section data is referenced and repetition check is skipped

---

### Requirement: Layout Skip Guard

Layouts where `has_fillable_fields()` returns false MUST bypass the reviewer entirely.
No L-critique AI call SHALL be made for such layouts.

#### Scenario: Non-fillable layout bypasses reviewer

- GIVEN `gallery-grid` has no fillable fields
- WHEN the builder invokes the reviewer for this layout
- THEN the reviewer returns the decoded payload unchanged with no AI call

---

### Requirement: L-Critique Prompt Contract

The L-critique prompt MUST be encoded as a PHP heredoc/nowdoc constant inside
`AI_Content_Reviewer`. It MUST reference word-count and structural rules from
`AI_Content_Harness` public constants as the single source of truth; no independent
hardcoded numeric values SHALL exist in the reviewer class. An optional WP filter
(`wizard_ai_content_reviewer_critique_prompt`) MAY allow advanced override of the prompt.

#### Scenario: L-critique references harness constants

- GIVEN the reviewer assembles the critique prompt for `about-us`
- WHEN the prompt string is built
- THEN word-count ranges and paragraph rules are read from `AI_Content_Harness` constants
- AND no separate hardcoded array of word-count values exists in the reviewer

### Requirement: Content Calibration Checks

The reviewer MUST detect excessive jargon, abstract paragraph openings, repeated generic praise language, overlapping section jobs/angles, service/SEO headings that should use `company_services` search-intent terms, and service mentions not grounded in `company_services`. These checks MUST reuse the existing bounded review loop; they MUST NOT add UI, asset changes, native WP AI APIs, API keys, or unbounded production logging.

#### Scenario: Abstract or repetitive headings are flagged

- GIVEN prior sections already use the same generic praise language or value promise
- WHEN the reviewer evaluates another heading with the same abstract concept
- THEN it assigns `repetitive_wording`, `section_angle_overlap`, `semantic_repetition`, or `generic_copy`
- AND the rewrite steers the heading toward a concrete service/search-intent angle when supported by `company_services`

#### Scenario: Unsupported service mention is flagged

- GIVEN `company_services` does not include a specific service
- WHEN generated copy mentions that service as an offered service
- THEN the reviewer assigns `unsupported_claims` or `guardrail_gap`
- AND the rewrite removes the unsupported service mention

### Requirement: Missing Differentiator Check

The reviewer MUST flag copy that lacks a real business differentiator when the section is meant to establish trust, choice, or positioning. The reviewer MAY ask the AI to make the copy more specific from trusted context, but it MUST NOT invent differentiators such as years in business, guarantees, brands, licenses, bilingual service, special equipment, credentials, awards, or other proof absent from trusted context.

#### Scenario: Differentiator is missing but not invented

- GIVEN a trust or positioning section only says the business is high quality
- WHEN trusted context does not include a concrete differentiator
- THEN the reviewer assigns `missing_differentiator` or `generic_copy`
- AND the rewrite uses supportable non-factual specificity or requests context-derived specificity
- AND it does not invent proof or credentials

---

### Requirement: Bounded Production Logging

In production (`WP_DEBUG === false`), each section review MUST log only: section key,
pass/fail status, and iteration count. Diagnosis codes, per-field scores, token estimates,
and any quality report data MUST NOT appear in production log entries.

#### Scenario: Production log entry is minimal

- GIVEN a section review completes in a production environment
- WHEN the logger writes the entry
- THEN the entry contains `section: layout-key`, `status: pass|fail`, and `iterations: N` only

---

### Requirement: Dev-Only Quality Report

When `WP_DEBUG === true`, the reviewer MUST emit a structured per-section quality report
containing: diagnosis codes, per-field pass/fail scores, iteration count, and overall
verdict. This report MUST NOT be emitted in production and MUST NOT write to the PHP or
server error log in either environment.

#### Scenario: Dev report available in debug mode

- GIVEN `WP_DEBUG` is true and a section review completes
- WHEN the reviewer finalizes the pass
- THEN a structured report with diagnosis codes and field scores is available via the
  designated dev-reporting channel

#### Scenario: Dev report absent in production

- GIVEN `WP_DEBUG` is false
- WHEN the reviewer completes any section review
- THEN no quality report is generated
- AND log entries contain only bounded section key + pass/fail + iteration count

---

### Requirement: Provider Reuse

The reviewer MUST reuse `AI_Provider::generate()` via the existing custom WP HTTP API
adapter. Native WordPress AI APIs (Jetpack AI, WP.com AI) MUST NOT be used. No API keys
SHALL appear in source code, logs, or chat.

#### Scenario: Reviewer dispatches through existing provider path

- GIVEN the reviewer issues a critique API call
- WHEN the call is dispatched
- THEN it travels through `AI_Provider_Registry::make_provider()->generate()`
- AND no alternative HTTP client or WP native AI endpoint is used
