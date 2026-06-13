# Proposal: Wizard AI Content Review Loop

## Intent

The wizard generates each home-page section with a **single AI pass** (generate → decode → validate → save). There is no review, critique, or rewrite. Mediocre output (generic copy, AI-speak, repetition across sections, unsupported claims) reaches ACF and ships as-is. This change inserts a **diagnose-then-rewrite** review loop between `decode_json_content()` and `validate_fields()` so content is improved against research-backed guardrails before it is ever saved.

## Scope

### In Scope
- A dedicated `AI_Content_Reviewer` service (Approach 2) encapsulating critique → diagnose → targeted-rewrite.
- Integration in `Step_Home_Page_Builder::generate_section_overrides()`, before `validate_fields()`/save.
- A quality diagnosis taxonomy that classifies failures **before** any rewrite (no blind retries).
- Cross-section semantic-repetition check: section N compared against sections 1..N-1 of the same page.
- A reviewer prompt (L-critique) anchored to harness constants and primary research sources.
- Dev-only structured quality report; **bounded** production logging (pass/fail + iteration count only).
- Bounded iteration budget (default 2 passes) with fallback to original content on failure/timeout.

### Out of Scope
- Any wizard UI for v1 (toggles, strictness controls) — backend/programmatic only. UI deferred to v2 unless minimal dev reporting forces it.
- Review for other page builders (about/services) — reviewer is reusable but not wired this change.
- Cost/token estimation, native WP AI APIs, provider changes, automated test runner setup.
- Relying on `Wizard ai harness prompt guide.md` / `wizard-prd.html` (untracked/excluded).

## Capabilities

### New Capabilities
- `wizard-ai-content-reviewer`: critique/diagnose/rewrite loop service — taxonomy scoring, cross-section comparison, iteration budget, fallback, bounded logging.

### Modified Capabilities
- `wizard-home-page-builder`: section generation becomes multi-pass; reviewer invoked between decode and `validate_fields()`/save.
- `wizard-ai-content-harness`: expose editorial rules/word-count constants for the reviewer to reference (single source of truth); no contradictory hardcoded values.

## Approach

New `inc/wizard/class-ai-content-reviewer.php`, injected into `Step_Home_Page_Builder` via constructor. Reuses `AI_Provider::generate()` — no provider changes, runtime stays in the WP theme over the WP HTTP API custom adapter. Provider config stays tested in WP admin; no keys in chat.

Loop shape:

```
generate → decode → review(decoded, layout, prior_sections) → diagnose → rewrite (tailored) → decode → validate_fields → save
                              ↑________________ up to N=2 passes _______________|
```

`review()` returns a per-section verdict (pass/fail + diagnoses). Only failed sections with a diagnosis are rewritten, with a prompt **tailored to that diagnosis**. Word-count: soft-nudge pass 1, harder pass 2; **±2–6 word tolerance allowed when it improves naturalness**. Beyond N passes → accept content, flag in dev report. Layouts without fillable fields skip review.

### Guardrail basis (primary sources only)

| Category | Anchor |
|----------|--------|
| People-first / helpful content | Google Helpful Content |
| No spam / keyword stuffing | Google Spam Policies |
| Trust / E-E-A-T, no unsupported claims | Google SQRG; Google LSA Policies |
| Plain language / scannability | NNGroup (Plain Language; Be Succinct) |

Secondary sources (Yoast, Hemingway, Copyblogger) are **optional heuristics, not gates** — not cited as authority.

### Quality diagnosis taxonomy

`generic_copy` · `semantic_repetition` · `unsupported_claims` · `keyword_stuffing` · `filler_content` · `missing_trust_signal` · `intent_mismatch` · `ai_speak` · `guardrail_gap`. Each diagnosis maps to a targeted rewrite directive (full definitions in `exploration.md`).

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `inc/wizard/class-ai-content-reviewer.php` | New | Review/diagnose/rewrite service + taxonomy + L-critique prompt |
| `inc/wizard/class-step-home-page-builder.php` | Modified | Inject reviewer into `generate_section_overrides()` before validate/save |
| `inc/wizard/class-ai-content-harness.php` | Modified | Expose editorial/word-count constants for reviewer reference |
| `inc/wizard/class-logger.php` | Modified | Bounded production log; structured dev-only quality report |
| `inc/wizard/class-state-manager.php` | Possibly | Review-status flags if needed |
| `openspec/specs/wizard-home-page-builder/spec.md` | Modified | Multi-pass generation scenario |
| `openspec/specs/wizard-ai-content-harness/spec.md` | Modified | Constant-exposure requirement |
| Vite entry points | None | No new entry points (no UI in v1) |

## Review Workload Forecast (force-chained)

Logic-heavy PHP across reviewer + integration + reporting will exceed the **400-line** budget in a single PR. Session strategy is `force-chained`, so delivery splits into **3 chained slices**:

| Slice | Scope | Est. lines |
|-------|-------|-----------|
| 1 | `AI_Content_Reviewer` skeleton: taxonomy constants, L-critique prompt, scoring contract (no integration) | ~250–350 |
| 2 | Integration into `generate_section_overrides()`: iteration budget, diagnosis-tailored rewrite, timeout fallback | ~200–300 |
| 3 | Cross-section comparison (N vs 1..N-1) + dev quality report + bounded production logging | ~150–250 |

- **400-line budget risk: High**
- **Chained PRs recommended: Yes**
- **Decision needed before apply: No** (force-chained already resolved)

Each slice has autonomous scope, manual verification (`php -l`, harness contract check), and a clean rollback (slice 1 is dead code until slice 2 wires it).

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Token cost doubles (~14 calls/run) | High | One-time setup tool; document; iteration cap N=2 |
| Critique prompt drifts from harness rules | Med | Reviewer MUST reference harness constants, not hardcoded values |
| Review AI hallucinates "corrections" | Med | No-invention guardrails in L-critique; `validate_fields()` still final gate |
| Timeout (45s × passes) | Med | Respect existing 45s timeout; fall back to original content, never extend |
| No automated tests | High | Manual audit only; keep reviewer unit-testable in isolation for future runner |
| Production log snowball | Med | Hard cap: pass/fail + iteration count only; no scores/diagnoses/tokens in prod |

## Rollback Plan

Reviewer is additive and isolated. To revert: remove the reviewer call from `generate_section_overrides()` (or short-circuit via a constant `WIZARD_REVIEW_ENABLED = false`) — the pipeline returns to single-pass generate→validate→save with no data migration. Delete `class-ai-content-reviewer.php` and revert harness constant exposure. ACF save boundary is unchanged, so no saved content is affected by rollback.

## Open Decisions

- **Strictness escalation**: soft-nudge pass 1 / harder pass 2 is the recommendation — confirm at spec time.
- **L-critique encoding**: PHP heredoc constants (matching L1/L2/L3) with an optional WP filter for advanced override — confirm.
- **Repetition detection**: AI-driven critique with explicit pattern examples; static regex list as fallback — confirm scope for v1.

## Success Criteria

- [ ] A failed section is **diagnosed** (taxonomy) before any rewrite; no blind retries occur.
- [ ] Reviewer compares each section against prior sections on the page; paraphrased duplicates are flagged.
- [ ] Word-count deviations within ±2–6 words pass when naturalness improves; larger deviations flagged.
- [ ] Production logging is bounded (pass/fail + iteration count); dev report is richer and dev-only.
- [ ] Iteration budget (N=2) is enforced; timeout/failure falls back to original content, never blocks save.
- [ ] Runtime stays in the WP theme via the existing provider adapter; no native WP AI APIs; no keys in chat.
- [ ] Delivered as 3 chained PRs, each under the 400-line budget.
