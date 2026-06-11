# Delta for Wizard AI Content Harness

## ADDED Requirements

### Requirement: Per-Layout Editorial Rules

Layer 3 MUST include layout-specific editorial rules: word counts, paragraph structure, and field roles for every layout with fillable fields. Rules MUST be encoded as PHP heredoc/nowdoc strings inside `layout_rules()` or equivalent, not read from external files.

| Constraint type | Examples |
|----------------|----------|
| Word counts | Headlines 6–12w, eyebrows 2–4w, CTA buttons 2–5w, body 50w+ |
| Paragraph structure | `about_text` exactly 3 paragraphs (P1 positioning, P2 differentiation, P3 trust+CTA), 50–60w each |
| Field roles | Badges = local directory/profile links; service descriptions = benefit-focused 40w+ |

#### Scenario: Layout receives editorial rules in prompt

- GIVEN the harness composes Layer 3 for `about-us`
- WHEN the prompt is built
- THEN it includes paragraph-structure guidance (P1/P2/P3 purpose), 50–60 word constraint, and subheadline rule

#### Scenario: Layout with no fillable fields gets no rules

- GIVEN `gallery-grid` has no fillable fields
- WHEN Layer 3 is composed
- THEN the editorial rules section is empty or instructs the AI to return `{}`

### Requirement: Text Repeater Enablement

The harness MUST enable guide-supported text repeaters where the builder already supports repeater decoding. Factual, media, testimonial, and project repeaters MUST remain blocked.

| Layout | Enabled repeater fields | Still blocked |
|--------|------------------------|---------------|
| `slider` | `slide_subheadline`, `slide_headline`, `slide_text`, `slide_cta_text` | `slide_image`, `slide_cta_url` |
| `faq-v1` | `faq_question`, `faq_answer` | — |
| `faq-v2` | `faq_question`, `faq_answer` | — |
| `vision-mission-v1` | `card_title`, `card_text` | `card_highlight` |
| `vision-mission-v2` | `reason_text` | — |
| `cta-v3` | `stat_label` | `stat_number` |

#### Scenario: FAQ text repeater enabled

- GIVEN the admin selects `faq-v1` with item count 5
- WHEN the harness builds the prompt
- THEN `faq_question` and `faq_answer` are in the fillable list
- AND the AI is instructed to return 5 Q&A pairs

#### Scenario: Factual repeater stays blocked

- GIVEN the admin selects `testimonials-v1`
- WHEN the harness builds the prompt
- THEN `testimonials_v1_items` remains in the blocked list

#### Scenario: Slider images stay blocked while text enabled

- GIVEN the admin selects `slider` with 4 slides
- WHEN the harness builds the prompt
- THEN `slide_headline`, `slide_text`, `slide_subheadline`, `slide_cta_text` are fillable
- AND `slide_image`, `slide_cta_url` remain blocked

## MODIFIED Requirements

### Requirement: Versioned Prompt Contracts

The harness MUST encode Layer 1 (global editorial system prompt), Layer 2 (page-type context), and Layer 3 (per-layout section contract) as PHP class constants or method return values. The harness MUST NOT read from any external file, markdown guide, or runtime-resolved path. Layer 1 MUST include full editorial standards: paragraph rules, headline/subheadline/eyebrow word counts, CTA text conventions, body copy density, FAQ pair structure, and services copy guidelines. Layer 2 for PAGE_HOME MUST include page purpose, tone, and section-ordering guidance from the guide. Layer 3 MUST include per-layout field descriptions, word-count constraints, and structural rules.

(Previously: Layers were single-sentence placeholders without editorial standards, word counts, or structural guidance.)

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
