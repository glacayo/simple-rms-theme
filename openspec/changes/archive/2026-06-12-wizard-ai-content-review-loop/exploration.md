# Exploration: Wizard AI Content Review Loop

## Current State

The wizard generates AI section content via a single-pass generate → validate → save pipeline. There is **no review, critique, or rewrite loop** anywhere in the codebase. Each section gets one AI call; if it fails, the section falls back to placeholder text.

### Flow (as-is)

```
Step_Home_Page_Builder::run()
  └── foreach section row:
        └── generate_section_overrides()
              ├── validate_required_context()   ← blocks if company_name missing
              ├── compose system = L1 + L2       ← AI_Content_Harness
              ├── compose user   = L3             ← AI_Content_Harness
              ├── AI_Provider_Registry::make_provider()->generate()  ← single call, no retry
              ├── if fail → return [] → placeholder copy
              ├── decode_json_content()           ← strips markdown fences, json_decode
              └── harness->validate_fields()      ← strips non-fillable keys, sanitizes
        └── section_data()                        ← merges AI copy into ACF field structure
        └── prepare_image_fallbacks()
  └── Content_Builder::build_page()               ← update_field('page_sections', ...) — FINAL SAVE
```

### Key insight

The **gap** is between `decode_json_content()` and `validate_fields()` — that's where content exists in structured form but hasn't been saved yet. A review/rewrite loop inserted there would intercept content before it touches ACF.

## Research: Quality Rubric & Guardrail Framework

### What exists today

The harness (`AI_Content_Harness`) already encodes generation guardrails in `get_layer1()` (no-invention, editorial standards, output rules), `get_layer2()` (page context, tone), and `layout_rules()` (per-layout word counts, paragraph structure, field roles). OpenSpec specs (`wizard-ai-content-harness`, `wizard-ai-prompt-guide`) document these contracts. What is **missing**:

- A formal quality rubric the reviewer can score against
- A diagnosis taxonomy to classify failures
- A dedicated reviewer/critique prompt (L-critique)
- Cross-section semantic repetition checks (same idea rephrased across sections)
- Dev-only quality reporting (structured, not production-logged)

### Authoritative sources (primary — normative)

These sources carry high confidence and should inform reviewer prompt design and quality criteria:

| Source | Relevance |
|--------|-----------|
| [Google Search Central — Creating Helpful Content](https://developers.google.com/search/docs/fundamentals/creating-helpful-content) | People-first content principles, self-assessment questions |
| [Google Search Essentials — Spam Policies](https://developers.google.com/search/docs/essentials/spam-policies) | Keyword stuffing, auto-generated content, scraped/thin content |
| [Google Search Quality Rater Guidelines (PDF)](https://services.google.com/fh/files/misc/hsw-sqrg.pdf) | E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness), YMYL, page quality rating |
| [Google Local Services Ads Policies](https://support.google.com/adspolicy/answer/6245891) | Truthfulness for local service businesses, no unsupported claims |
| [NNGroup — Plain Language for Experts](https://www.nngroup.com/articles/plain-language-experts) | Plain language improves comprehension even for domain experts |
| [NNGroup — Be Succinct: Writing for the Web](https://www.nngroup.com/articles/be-succinct-writing-for-the-web) | Scannability, conciseness, inverted pyramid |

### Secondary sources (optional inspiration — not normative)

Medium-confidence sources that may inform reviewer heuristics but are NOT binding authority:

- Yoast SEO readability analysis (Flesch Reading Ease, passive voice, transition words) — useful as optional readability signals, not as quality gate
- Hemingway App grade-level heuristics — same caveat
- Copyblogger/Moz content templates — structural inspiration only

### Research-backed guardrail categories

The reviewer prompt (L-critique) should enforce these categories, each anchored to a primary source:

| Category | Source anchor | What it checks |
|----------|--------------|----------------|
| **People-first / helpful content** | Google Helpful Content | Does the section answer a real visitor need, or is it written for search engines? Is it original and adds value beyond what other pages say? |
| **Trust / E-E-A-T** | Google SQRG §2.3–2.6 | Does the content demonstrate experience, expertise, authoritativeness, and trustworthiness without inventing credentials? |
| **No spam / keyword stuffing** | Google Spam Policies | Are keywords naturally integrated? No repeated phrases, no hidden text patterns, no auto-generated gibberish. |
| **Local service truthfulness** | Google LSA Policies | No invented service areas, pricing, guarantees, licenses, or certifications. Claims must be supportable from client data. |
| **Plain language / scannability** | NNGroup | Short sentences, active voice, no jargon, scannable structure. Headlines and CTAs are direct. |
| **Objective, non-marketese tone** | Google SQRG + NNGroup | No exaggerated claims ("best in the world", "unmatched"), no hype, no empty superlatives. |
| **No unsupported claims** | Google LSA + SQRG | No invented reviews, ratings, testimonials, pricing, service areas, statistics, or customer names. |

### Diagnosis taxonomy

The reviewer classifies each flagged issue into one of these diagnoses. This taxonomy drives the rewrite prompt and quality report:

| Diagnosis | Definition | Example trigger |
|-----------|-----------|----------------|
| `generic_copy` | Content could apply to any business; no specific value proposition | "We provide quality service to our customers" |
| `semantic_repetition` | Same idea rephrased across sections or within a section | Headline and subheadline say the same thing with different words |
| `unsupported_claims` | Factual assertion not backed by client data | "Serving the Tri-State area since 1998" when no year or area in context |
| `keyword_stuffing` | Unnatural keyword density or forced insertion | "Our roofing company provides roofing services for roofing needs" |
| `filler_content` | Words that add length but no information | "When it comes to your home, you want the best" |
| `missing_trust_signal` | Section that should build trust but lacks any trust element | About-us section with no differentiation or credibility marker |
| `intent_mismatch` | Section doesn't fulfill its page-role purpose | CTA section that describes services instead of driving action |
| `ai_speak` | Recognizable LLM stylistic tics | "dependable service", "clear communication", "peace of mind", hedging patterns |
| `guardrail_gap` | Content violates a harness guardrail that the generation prompt should have prevented | Invented URL, blocked field returned, service name not from client data |

### Product decisions (from conversation)

These decisions are settled and should be encoded in proposal/spec/design:

1. **Diagnose before rewrite**: When output is mediocre, the reviewer MUST first classify the failure using the diagnosis taxonomy above. The rewrite prompt is then tailored to the specific diagnosis, not a generic "make it better." This prevents the rewrite loop from burning tokens on unfocused corrections.

2. **Quality report is development-only**: The structured quality report (diagnosis counts, per-section scores, token usage) is a development diagnostic tool. Production logging MUST be minimal and bounded — log only pass/fail status and iteration count per section. No per-field scores, no diagnosis breakdowns, no token estimates in production logs.

3. **Cross-section comparison**: The reviewer compares each section against other already-generated sections on the same page to detect semantic repetition. The first section has no comparator; section N is compared against sections 1..N-1.

4. **Word-count tolerance**: Minor deviations of ±2–6 words from harness editorial rules are acceptable when the deviation demonstrably improves naturalness or quality. The reviewer should flag only significant deviations (>6 words) or deviations that indicate filler/stuffing.

## Affected Areas

| File | Role | How Affected |
|------|------|-------------|
| `inc/wizard/class-step-home-page-builder.php` | **Primary insertion point** — `generate_section_overrides()` method (lines 222–256) | New review step injected between AI response decode and field validation |
| `inc/wizard/class-ai-content-harness.php` | Prompt contracts, field allowlists/blocklists, validation | MAY need new review-prompt methods (L4 critique prompt) or a `validate_quality()` method for word counts, naturalness checks |
| `inc/wizard/class-ai-provider.php` | Abstract provider interface | Unchanged — review calls reuse same `generate()` contract |
| `inc/wizard/class-ai-provider-registry.php` | Provider factory | Unchanged |
| `inc/wizard/class-ollama-provider.php` | Concrete Ollama Cloud provider | Unchanged — review uses same API |
| `inc/wizard/class-content-builder.php` | `save_page_sections()` | Unchanged — save boundary stays the same |
| `inc/wizard/class-state-manager.php` | Wizard state persistence | May need new state flags for review status |
| `inc/wizard/class-logger.php` | Structured logging | New log entries for review passes and rewrite decisions |
| `src/ts/admin/wizard.ts` | Frontend wizard UI | MAY need review-configuration controls (iterations, strictness toggle) |
| `openspec/specs/wizard-ai-content-harness/spec.md` | Harness spec | WILL need new requirements for quality review prompts |
| `openspec/specs/wizard-home-page-builder/spec.md` | Builder spec | WILL need modified scenario for multi-pass generation |

## Approaches

### 1. Inline Review in `generate_section_overrides()` (Minimal)

Add a `review_and_rewrite()` private method directly in `Step_Home_Page_Builder` that takes the decoded AI response, sends it to a second AI call with a critique prompt, and returns rewritten content.

- **Pros**: Minimal new files, no dependency wiring changes, fast to implement
- **Cons**: Bloats an already 415-line class, untestable in isolation, couples review logic to builder, hard to reuse for non-home pages
- **Effort**: Low

### 2. Dedicated `AI_Content_Reviewer` Class (Recommended)

New class `class-ai-content-reviewer.php` that encapsulates the review/critique/rewrite loop. Injected into `Step_Home_Page_Builder` via constructor. Called between `decode_json_content()` and `validate_fields()`.

- **Pros**: Clean separation of concerns, testable in isolation, reusable for future page builders (about, services, etc.), keeps harness focused on prompt contracts
- **Cons**: New file, new dependency wiring, slightly more ceremony
- **Effort**: Medium

### 3. Extended `AI_Content_Harness` with Review Layer

Add `get_layer4_critique()` and `validate_quality()` methods to the existing harness. The harness becomes the single source for all prompt contracts AND quality enforcement.

- **Pros**: Single class for all AI prompt contracts, no new file dependencies
- **Cons**: Harness is already 566 lines; adding critique prompts, word-count validators, and rewrite logic pushes it past SRP; harder to test review independently
- **Effort**: Medium

## Recommendation

**Approach 2: Dedicated `AI_Content_Reviewer` class**, with the review loop called from `generate_section_overrides()` (inline integration, not a new step).

### Why

1. **Separation of concerns**: Generation, review, validation, and persistence are distinct responsibilities. The harness handles prompt contracts; the reviewer handles quality enforcement.
2. **Reusability**: Future wizard steps (service pages, about pages) will need the same review loop.
3. **Testability**: Reviewer can be unit-tested with mock AI responses without spinning up the full builder.
4. **Token budget control**: The reviewer class can own iteration limits, early-exit criteria, and cost tracking — logic that doesn't belong in the harness.

### Proposed loop shape

```
generate → decode → review(decoded, layout, client_context) → rewrite → decode → validate_fields → save
                                                    ↑______________________________|
                                                    (up to N iterations)
```

Review prompt (L-critique) would check:
- Word counts against harness editorial rules per field
- Repetitive AI phrasing (hedging, filler patterns like "dependable service", "clear communication")
- No-invention guardrail violations (invented statistics, URLs, service names, locations)
- Naturalness score vs. templated tone

### Iteration budget

Recommend 2 review passes max (generate → review → rewrite → final review). Beyond 2 passes, accept the content and flag in logs. This keeps token costs predictable.

## Risks

- **Token cost doubling**: Each review pass doubles the AI call count per section. With 7 common sections × 2 calls = 14 calls per wizard run. Acceptable given the wizard is a one-time setup tool, but must be documented.
- **Review loop divergence**: If the critique prompt diverges from harness editorial rules over time, the AI may rewrite against contradictory constraints. Solution: review prompt MUST reference harness constants, not hardcoded values.
- **Hallucination in review**: The critique AI could itself hallucinate corrections, inventing "better" facts. Mitigation: review prompt must include no-invention guardrails identical to L1, and final validation still runs `validate_fields()`.
- **Timeout risk**: 45s timeout per call × 2 review passes = potential ~2 min per section. Solution: review loop must respect the existing 45s timeout and not extend it; failures fall back to original content.
- **Provider contract dependency**: Review loop reuses `AI_Provider::generate()`. If providers change the contract (e.g., streaming), the reviewer breaks. Low risk — abstract class is stable.
- **No automated tests**: The project has no PHP test runner. Review logic quality depends entirely on manual audit. Higher risk than usual for logic-heavy code.

## Open Questions

These MUST be answered before proposal/spec:

1. **Iteration budget**: How many review/rewrite passes before accepting content? (Recommend: 2)
2. **Strictness**: Should word-count violations be hard-block (reject content) or soft-nudge (rewrite with weaker prompt)? (Recommend: soft for pass 1, hard for pass 2)
3. **UI visibility**: Should the admin see review pass results in the wizard log? (Recommend: yes — log each pass with pass/fail status)
4. **Per-section skip**: Should layouts without fillable fields (gallery-grid, testimonials) still go through review? (Recommend: no — skip review for `!has_fillable_fields()`)
5. **Review prompt encoding**: Should critique prompts be PHP heredoc constants (like L1/L2/L3) or configurable via WordPress filter? (Recommend: constants in `AI_Content_Reviewer`, matching harness pattern; filter for advanced users)
6. **Cost tracking**: Should the wizard track total AI calls and estimated token cost? (Recommend: log call count + model; skip cost estimation for v1)
7. **What counts as "repetitive AI phrasing"?** Should this be a static pattern list (regex) or an AI-driven critique? (Recommend: AI-driven critique with explicit pattern examples in the prompt; static list as fallback)
8. **Should review loop be toggleable from wizard UI?** (Recommend: not for v1 — always-on with iteration count as a PHP constant; UI toggle in v2)

## Ready for Proposal

**YES**, but only after answering the 8 open questions above. The architecture direction is clear, the insertion point is well-defined, and the existing provider/harness/validation/save contracts are stable enough to build on.

### Suggested next phase

1. **Answer open questions** with the user (iteration budget, strictness, UI visibility)
2. **sdd-propose**: Create proposal.md defining scope, approach (Option B), and rollback plan
3. **sdd-spec**: Write delta specs for `wizard-ai-content-harness` (review prompts) and `wizard-home-page-builder` (multi-pass generation)
4. **sdd-design**: Sequence diagram for generate→review→rewrite→validate→save; class diagram for `AI_Content_Reviewer`
