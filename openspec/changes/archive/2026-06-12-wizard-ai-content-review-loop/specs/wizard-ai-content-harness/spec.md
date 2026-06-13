# Delta for Wizard AI Content Harness

## ADDED Requirements

### Requirement: Customer Content Calibration

The harness MUST keep prompt calibration compact while steering generated copy toward customers and property owners first. Technical language MAY be used, but it MUST be paired with a clear customer benefit. Body copy SHOULD lead with a concrete benefit, outcome, or customer concern before explaining method. Copy SHOULD avoid repeating the same generic praise adjectives or phrases across the page. Each section SHOULD have a distinct job or angle such as process, result, customer experience, trust, service overview, or CTA. Headings and subheadings SHOULD favor concrete service/search-intent language from `company_services` when applicable and MUST NOT repeatedly lean on abstract quality promises. Generated copy MUST NOT mention services that are not present in `company_services`.

#### Scenario: Technical language translated into customer value

- GIVEN a contractor service has technical process details available
- WHEN the harness composes the generation prompt
- THEN the AI is instructed to explain those details through customer benefits
- AND not to open paragraphs with abstract method-first language

#### Scenario: Services remain grounded in client data

- GIVEN `company_services` does not include a specific service
- WHEN the harness composes service or SEO-related section prompts
- THEN that service is not introduced as an available service
- AND headings favor services/search intent present in `company_services`

#### Scenario: Sections avoid repeated praise and overlapping angles

- GIVEN prior sections already use the same praise language or value promise
- WHEN the harness composes the generation prompt for another section
- THEN the AI is instructed to vary the wording
- AND to give the section a distinct job or angle

### Requirement: Editorial Constants Exposure

The harness MUST expose its per-layout word-count ranges and structural rules (paragraph
counts, field roles, eyebrow/headline/CTA limits) as public PHP class constants or public
static getter methods. These MUST serve as the single source of truth for any consuming
class — specifically `AI_Content_Reviewer`. The harness MUST NOT maintain a separate
private copy that diverges from what the reviewer references. The reviewer MUST NOT
hardcode independent numeric word-count values; all numeric constraints it uses MUST be
derived from harness constants.

#### Scenario: Reviewer reads word-count constants from harness

- GIVEN `AI_Content_Reviewer` assembles the critique prompt for `about-us`
- WHEN the prompt string is built
- THEN word-count ranges (e.g., headline 6–12 w, body 50–60 w per paragraph) come from
  `AI_Content_Harness` public constants
- AND no equivalent numeric values are hardcoded inside `AI_Content_Reviewer`

#### Scenario: Single update propagates to both prompt and reviewer

- GIVEN a word-count rule for `hero-v1` headline changes from 6–12 to 8–14 words
- WHEN the harness constant is updated in one place
- THEN both the Layer 3 prompt and the reviewer critique prompt reflect the new range
  without any additional change to reviewer source code

#### Scenario: Harness still composes prompts independently

- GIVEN a caller requests Layer 3 for any layout
- WHEN the harness builds the prompt using its own constants
- THEN the existing prompt-composition behavior is unchanged; constant exposure is additive
