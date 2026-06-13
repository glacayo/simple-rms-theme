# Design: Wizard AI Content Review Loop

## Technical Approach

Add `inc/wizard/class-ai-content-reviewer.php` (final class, namespace `Inc\Wizard`), injected into `Step_Home_Page_Builder` via its existing nullable-constructor DI pattern. The reviewer runs between `decode_json_content()` and `validate_fields()` inside `generate_section_overrides()`. It reuses `AI_Provider_Registry::make_provider()->generate()` (45s timeout in `Ollama_Provider` untouched). No UI, no REST/JS changes, no new Vite entries, no CSS impact, no new ACF fields or acf-json sync.

## Architecture Decisions

| Decision | Options | Tradeoff | Chosen |
|----------|---------|----------|--------|
| Harness constant exposure | (a) public array constants; (b) public static getters | Constants are clunky for per-layout lookup + key normalization; getters can normalize and filter | **(b)** `public static function get_editorial_rules( string $layout ): array` backed by one `private const EDITORIAL_RULES` (single source). `layout_rules()` heredocs get numeric tokens (`{{headline_range}}`, etc.) substituted via existing `strtr` pattern so prompt and reviewer read the same values |
| Reviewer shape | trait in builder vs dedicated service | Service is unit-testable in isolation, reusable for about/services later | **Dedicated `AI_Content_Reviewer`** |
| Repetition detection v1 | static regex vs AI critique | Regex misses paraphrase; AI handles semantics | **AI critique with pattern examples**; regex fallback deferred |
| Word-count check v1 | PHP counter gate vs prompt-level instruction | PHP counting of HTML/WYSIWYG is brittle | **Critique prompt enforces ranges + ±2–6 tolerance**; verdict JSON carries flags |
| Kill switch | option vs constant | Constant = zero UI, instant rollback | `WIZARD_REVIEW_ENABLED` (defined+false → bypass) |

## Reviewer Contract

```php
public function review( string $layout, array $decoded, array $prior_sections, array $ai_config ): array
// returns: [ 'payload' => array, 'status' => 'pass|rewritten|fallback|skipped|budget_exhausted',
//            'iterations' => int, 'report' => ?array ]  // report only when WP_DEBUG
```

Internals (all private): `critique()` → AI verdict JSON (per-field flags + diagnosis codes); `diagnose()` → validate codes against `DIAGNOSES` const (the taxonomy from the spec, including PR5 `overtechnical_language`); `rewrite()` → directive map `diagnosis => [soft, hard]`, pass 1 soft / pass 2 hard; `decode_json()` → same fence-stripping regex as builder (small local copy; builder's is private — note, do not extract this change). Constants: `MAX_PASSES = 2`, `PROMPT_CRITIQUE` nowdoc (filterable via `wizard_ai_content_reviewer_critique_prompt`), `REWRITE_DIRECTIVES`. Numeric ranges come ONLY from `AI_Content_Harness::get_editorial_rules()`.

Guards: `has_fillable_fields() === false` → `skipped`, no AI call. Rewrite output failing decode, or containing non-fillable keys (hallucination) → discard, keep previous version. Any provider error/timeout at any pass → return original decoded payload, `status: fallback`, never throw. `validate_fields()` stays the final gate after the reviewer.

## Data Flow

```
run() loop (per section row)                         $prior_payloads = []
  └─ generate_section_overrides(key, count, ctx, ai_config, $prior_payloads)
       generate → decode_json_content
         └─ reviewer->review(layout, decoded, prior, ai_config)
              critique ──fail──→ diagnose → rewrite(soft|hard) → decode ┐
                 ↑ ≤2 passes ───────────────────────────────────────────┘
              timeout/error → original decoded (fallback)
       → validate_fields(reviewer payload) → section_data → save
  └─ append accepted payload to $prior_payloads      (request-scoped only;
                                                      never touches State_Manager)
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `inc/wizard/class-ai-content-reviewer.php` | Create | Service + taxonomy + prompts + loop |
| `inc/wizard/class-ai-content-harness.php` | Modify | `EDITORIAL_RULES` const + `get_editorial_rules()`; tokenize heredoc numerics |
| `inc/wizard/class-step-home-page-builder.php` | Modify | Inject reviewer; prior-payload accumulation; wire review call |
| `inc/wizard/class-logger.php` | None/Minor | Existing `log()` suffices; bounded entries already capped at 500 in wp_options (not error_log) |

## PR5 Content Calibration

QA feedback after PR1-PR4 found that generated copy can be technically competent but too technical for customers, can repeat abstract quality concepts across headings, can open paragraphs abstractly, and can mention services not present in `company_services`. PR5 keeps calibration compact by placing broad customer-first rules in harness Layer 1, refining service/SEO layout guidance, and extending the existing reviewer taxonomy with `overtechnical_language` rather than adding brittle term blacklists.

The reviewer receives the trusted client context already passed by the builder and uses it only inside critique/rewrite prompt payloads. Production logging remains bounded to `section`, `status`, and `iterations`; no prompts, generated bodies, client JSON, diagnoses, or reports are added to production logs.

## PR6 Content Polish Calibration

Second QA confirmed the copy improved significantly, so PR6 is a narrow calibration slice rather than a broad rewrite. The remaining issues are repeated praise adjectives/phrases, overlap between section jobs, occasional abstract openings, and missing real differentiators.

The calibration stays contractor-agnostic: prompts MUST NOT prescribe vertical-specific services or examples. Service-specific language must come only from `company_services` or trusted client context. The reviewer adds `repetitive_wording`, `section_angle_overlap`, and `missing_differentiator` diagnoses. The missing-differentiator path may request or use trusted specificity, but it must not invent years, guarantees, brands, licenses, bilingual service, equipment, credentials, or proof.

## Logging Boundary

Builder logs via existing `Logger` (wp_options, satisfies "no PHP error log"). Production (`WP_DEBUG=false`): `['section', 'status', 'iterations']` only. Dev: full `report` (diagnoses, per-field flags, verdict) passed as context. Never log raw prompts, full content bodies, or keys.

## Testing Strategy (no PHPUnit available)

| Layer | What | How |
|-------|------|-----|
| Syntax | every touched PHP file | `php -l` (mandatory per config) |
| Static review | no hardcoded numerics in reviewer; fallback paths return arrays; all output through `validate_fields` | checklist per slice |
| Manual WP Admin | full run happy path; provider key removed mid-run (fallback); `WIZARD_REVIEW_ENABLED=false` bypass; repeated-section page (repetition flag in dev report); `WP_DEBUG` on/off log shape | Local site |

No `npm build` needed — no asset changes.

## PR Slices (force-chained, 400-line budget)

| # | Scope | Est. lines | Rollback |
|---|-------|-----------|----------|
| 1 | Harness: `EDITORIAL_RULES` + getter + heredoc tokenization | ~140–200 | revert file |
| 2 | Reviewer class (dead code until wired) | ~280–380 | delete file |
| 3 | Builder integration + accumulation + kill switch | ~120–180 | remove call / constant off |
| 4 | Dev report polish + bounded prod logging + manual audit notes | ~80–150 | revert |
| 5 | Content calibration: customer clarity, service-grounding, and overtechnical-language review | ~80–150 | revert prompt/spec calibration |
| 6 | Content polish calibration: lexical variety, section-angle separation, and missing-differentiator review | ~60–120 | revert PR6 prompt/spec calibration |

Refines proposal's 3-slice plan: reviewer class alone approaches budget, so harness work moved to its own slice. Risk per slice: Low/Medium.

## Migration / Rollout

No data migration; ACF save boundary unchanged. Rollout: dead code (slices 1–2) → wired (slice 3) behind `WIZARD_REVIEW_ENABLED`. Rollback: define constant false, or revert slice 3.

## Open Questions

- [ ] None blocking. Confirm at tasks time whether slice 4 merges into 3 if integration lands small.
