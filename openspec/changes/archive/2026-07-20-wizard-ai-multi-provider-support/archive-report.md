# Archive Report: Wizard AI Multi-Provider Support

**Change**: `wizard-ai-multi-provider-support`
**Archived to**: `openspec/changes/archive/2026-07-20-wizard-ai-multi-provider-support/`
**Archive date**: 2026-07-20
**Mode**: OpenSpec
**Verdict**: PASS WITH WARNINGS

## Specs Synced

| Domain | Action | Details |
|--------|--------|---------|
| wizard-ai-providers | Created | New capability spec copied to `openspec/specs/wizard-ai-providers/spec.md` (no existing main spec to merge into) |

## Archive Contents

- proposal.md ✅
- exploration.md ✅
- specs/wizard-ai-providers/spec.md ✅
- design.md ✅
- tasks.md ✅ (14/15 tasks complete; 1 intentionally deferred)
- verify-report.md ✅
- archive-report.md ✅

## Accepted Warnings (from verify-report)

1. **GENERATION_EVIDENCE_USER_MANUAL**: End-to-end generation accepted from user manual confirmation; assistant did not independently reproduce after wizard lock.
2. **RUNTIME_ROLE_TEST_SKIPPED**: Lower-privileged browser session not executed; source gates inspected instead.
3. **ANTHROPIC_DISABLED_EXTERNAL**: Anthropic selectable availability intentionally off due external billing/payment issue (minimum deposit + payment processor failure). Code is ready.

## Accepted Future Work (deferred, not a v1 blocker)

- **Task 4.3b** (Anthropic real-key smoke): Intentionally unchecked. Anthropic is implemented in `class-anthropic-provider.php` and `make_provider( 'anthropic' )` but absent from default `list_providers()` / selectable UI. Re-enable when a usable Anthropic key is available: re-add to `list_providers()`, run real-key model-list/generation smoke, then mark task 4.3b complete.

## Source of Truth Updated

- `openspec/specs/wizard-ai-providers/spec.md` — new capability spec reflecting enabled selectable providers (OpenAI, Google Gemini, OpenRouter, plus existing Ollama Cloud) and Anthropic as implemented-but-disabled pending future smoke.

## SDD Cycle Complete

✅ Yes — all active v1 implementation tasks are complete, enabled providers are verified, and the change has been fully planned, implemented, verified, and archived.
