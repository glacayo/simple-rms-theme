# Apply Progress: wizard-landing-page-builder

**Mode**: Standard  
**Batch**: Phase 1 final cleanup before PR prep  
**Date**: 2026-07-20

## Completed Tasks (cumulative)

- [x] 1.1 Canonical section store
- [x] 1.2 State manager + step controller foundation
- [x] 1.3 Unlock controller + admin wiring
- [x] 1.4 Completed-gate allowlist for unlock/relock only

Phase 2+ tasks remain incomplete and were not started in this batch.

## Review Follow-up Fixes (cumulative)

| Finding | Severity | Fix |
|---------|----------|-----|
| `landing-page-builder` required before runtime | BLOCKER | Removed from active `REQUIRED_STEPS`; 7-step completion remains valid via shared `get_required_steps()` |
| Canonical store nondeterministic persistence | WARNING | Cache mutates only after successful `update_option()` or verified equal full post-state entry |
| Unlock/relock nondeterministic persistence | WARNING | Post-state option equality checks; fail + rollback on unlock; fail if still unlocked after relock |
| Phase 1 unlock exposes destructive reruns | WARNING | `CONTROLLED_UNLOCK_ENABLED = false`; no unlock admin-post registration until Phase 2 deletion guard; REST unlock returns 503; relock kept for stale cleanup |
| `RMS_WIZARD_FORCE` UI ambiguity | WARNING | Explicit force-unlocked notice; suppress unlock messaging under force mode |
| Premature `landing_page_builder` alias pollutes state | WARNING | Removed Phase 1 alias; `is_dispatchable_step()` rejects unknown/unimplemented steps **before** status writes |
| Stale unlock marker bypasses lock | BLOCKER | `has_unlock_marker()` vs effective `is_unlocked()`; lock ignores markers while controlled unlock disabled |
| View duplicates unlock source of truth | WARNING | `wizard-init.php` trusts only `$state` from `get_resume_state()` |
| Untracked local docs risk commit | WARNING | `.gitignore` entries for local docs |
| Step status stuck `running` on throw | WARNING | catch marks progress steps `failed`; logs if failed-status write itself fails |
| Dead `assert_required_steps_parity()` | WARNING | Removed; shared `get_required_steps()` is the single list |
| Duplicate completion-flag helper | WARNING | Removed `Wizard_Unlock_Controller::has_completion_flag()`; use `State_Manager::has_completion_flag()` |
| Tautological unlock `$at_ok`/`$by_ok` | WARNING | Simplified to post-state equality only |
| Canonical success ignored `generated_at` | WARNING | Full entry compare (payload + generated_at) after reload |
| Unlock/relock failures silent | WARNING | Logger records persist failures + residual rollback markers |
| Duplicate execute_step success shape | INFO | Single success return path after dispatch |

## Files Changed (this batch)

| File | Action | What Was Done |
|------|--------|---------------|
| `inc/wizard/class-wizard-unlock-controller.php` | Modified | SoT completion flag; simplified post-state checks; failure/rollback logging |
| `inc/wizard/class-canonical-section-store.php` | Modified | Full-entry post-state verification including `generated_at` |
| `inc/wizard/class-step-controller.php` | Modified | Deduped success return; log failed-status write failure on throw |
| `inc/wizard/wizard-init.php` | Modified | Register unlock admin-post only when enabled; relock notice always; keep gated unlock UI for Phase 2 |
| `openspec/changes/wizard-landing-page-builder/apply-progress.md` | Modified | This progress note |

## Intentionally not changed

- **Unlock UI branch behind `$unlock_ui_enabled`**: left in place (dead in Phase 1) so Phase 2 task 2.6 can flip `CONTROLLED_UNLOCK_ENABLED` without reintroducing the form. Risk of removing it outweighs PR-size gain.
- **REST `/steps/unlock/run` route**: still dispatchable, but `unlock()` hard-gates with 503 while disabled — keeps a clear unavailable contract instead of pretending the step is unknown.
- **Phase 2/3/4 landing runtime / TS / template**: out of Phase 1 scope.
- **No commit/push**: per apply instructions.

## Verification

Automated behavioral tests are **unavailable** by project config (`openspec/config.yaml`: `strict_tdd: false`, testing section unavailable).  
Phase 1 relies on **PHP syntax (`php -l`) + 4R fresh review + later manual runtime verification (Phase 4)**. Do not pretend unit/integration tests exist.

```
php -l inc/wizard/class-wizard-unlock-controller.php
php -l inc/wizard/class-canonical-section-store.php
php -l inc/wizard/class-step-controller.php
php -l inc/wizard/wizard-init.php
```

## Status

Phase 1 final cleanup complete. Ready for PR prep / re-verify of Phase 1 slice.  
Do **not** mark Phase 2 tasks complete. Do not start Phase 2 in this batch.
