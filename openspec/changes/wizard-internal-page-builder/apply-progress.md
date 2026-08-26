# Apply Progress: wizard-internal-page-builder

**Change**: wizard-internal-page-builder
**Mode**: Standard (`strict_tdd: false`)
**Latest work unit**: PR2C Landing section-assembler delegation
**Date**: 2026-08-26
**Delivery**: auto-chain / feature-branch-chain
**Cumulative**: 4/18 tasks complete

Phase 2 was split locally into three chained work units after the full extraction was validated, so each review slice stays under 400 authored lines.

## Local chain

| Unit | Branch | Commit | Base |
|---|---|---|---|
| PR2A | `refactor/wizard-section-assembler-core` | `5d3d99cf3b9cd3832fde86404f0dec41fee9b4b3` | `origin/feat/internal-page-builder` `3d5947fd64b85d79749c902590d72fe21b6c2c52` |
| PR2B | `refactor/home-section-assembler` | `4c58330979f615f38595105b4aafaf02834c5d4c` | PR2A `5d3d99cf3b9cd3832fde86404f0dec41fee9b4b3` |
| PR2C | `refactor/landing-section-assembler` | `c0bf57c0c4b9eccfb20c4a04a54c44278aefbf6d` | PR2B `4c58330979f615f38595105b4aafaf02834c5d4c` |

Scratch branch `refactor/wizard-section-assembler` remains at `d437909` with the original uncommitted full extraction preserved.

## Completed Tasks (cumulative)

### Phase 1: Blueprint Registry & State Shape
- [x] 1.1 Create `inc/wizard/class-internal-page-blueprints.php`
- [x] 1.2 Modify `inc/wizard/class-state-manager.php`: `internal_pages` default

### Phase 2: Section Assembler Extraction (split)
- [x] 2.1 Create `inc/wizard/class-section-assembler.php` — delivered in PR2A
- [x] 2.2 Home + Landing delegate — Home in PR2B, Landing in PR2C

## Remaining Tasks

- [ ] 3.1–8.3 later phases

## Work Unit Evidence (PR1) — independently revalidated

| Evidence | Result |
|---|---|
| Focused test | PHP lint both PR1 production files, **2/2 pass**, PHP 8.2.29 |
| Runtime harness | **N/A** — no runtime boundary. Existing regression `php tests/wizard-integration-truth-harness.php` → **8 scenarios pass** |
| Rollback | Delete blueprint registry; revert `INTERNAL_PAGE_ENTRY` and empty `internal_pages` default |

## Work Unit Evidence (PR2A)

| Evidence | Result |
|---|---|
| Diff vs tracker | `inc/wizard/class-section-assembler.php` only; **+169 / −0 = 169** (<400) |
| Focused test | `php -l inc/wizard/class-section-assembler.php` → exit 0, `No syntax errors detected`, PHP 8.2.29 |
| Runtime harness | **N/A** — assembler is unused until PR2B/PR2C; no runtime boundary |
| Rollback | Delete `inc/wizard/class-section-assembler.php` |

## Work Unit Evidence (PR2B)

| Evidence | Result |
|---|---|
| Diff vs PR2A | `inc/wizard/class-step-home-page-builder.php` only; **+5 / −134 = 139** (<400) |
| Focused test | `php -l inc/wizard/class-step-home-page-builder.php` → exit 0 |
| Runtime / equivalence | Home-vs-assembler `section_data` **10/10 PASS**. `php tests/wizard-home-seo-targeting-harness.php` → **9 scenarios pass**, exit 0 |
| Rollback | Revert Home builder delegation; restore inlined Home assembly methods |

## Work Unit Evidence (PR2C)

| Evidence | Result |
|---|---|
| Diff vs PR2B | `inc/wizard/class-step-landing-page-builder.php` + `scripts/test-landing-run-orchestrator.php` (one require line); **+32 / −158 = 190** (<400) |
| Focused test | `php -l` Landing builder and landing harness → exit 0 both |
| Runtime / equivalence | Landing-vs-assembler `section_data` **10/10 PASS**. `php scripts/test-landing-run-orchestrator.php` → **293 passed, 0 failed**. `php tests/wizard-integration-truth-harness.php` → **8 scenarios pass** |
| Rollback | Revert Landing builder delegation and the one harness loader line |

## Cumulative production parity

These SHA256 values are of the **current working-tree bytes** (Windows checkout may be CRLF). They are **not** Git blob SHA1s. Blob SHA1s identify the committed LF objects and must not be mixed with working-tree SHA256.

Working-tree SHA256 (recomputed 2026-08-26 on `refactor/landing-section-assembler`):

| File | Working-tree SHA256 |
|---|---|
| `inc/wizard/class-section-assembler.php` | `3E825526117F03791F6DCF6BC88441AC5770B5E53C11ABC887BB1C10C1F1BB0D` |
| `inc/wizard/class-step-home-page-builder.php` | `2CB41F318061977D9376B79C34F18F0462E297A61CAAC71B0CB830A1823B41BC` |
| `inc/wizard/class-step-landing-page-builder.php` | `6583681B223956C15EBF90CAC0F09EFDF21EBB1E54232280D97EAB3514256B98` |
| `scripts/test-landing-run-orchestrator.php` | `52816E3544265D3D3D1BD549B1EDD4ED950CA319FFFFCAC09219D9C830F65194` |

Committed Git blob SHA1 (`git hash-object` == `HEAD:<path>`, LF in the object store):

| File | Git blob SHA1 |
|---|---|
| `inc/wizard/class-section-assembler.php` | `24d1fb0a4a5c3cac48abe7e5da9cc2f8ae6fb389` |
| `inc/wizard/class-step-home-page-builder.php` | `70d52fdd1f88bbfa7264fffab7204afbd6e1c1db` |
| `inc/wizard/class-step-landing-page-builder.php` | `8d986cb3c6935a5947f594bb0aadf6cae5f42591` |
| `scripts/test-landing-run-orchestrator.php` | `e0e3fe3dd2ac92f9030e3958936de804e07f2938` |

Home and orchestrator working-tree SHA256 match the prior record. Assembler and Landing working-tree SHA256 were stale (LF goldens vs CRLF checkout) and are corrected above.

Cumulative vs tracker: **+206 / −292 = 498** across the three slices. Each slice is independently under 400.

## Deviations from Design

None in product behavior. Constructors unchanged. Assembler is constructed from the existing harness. `placeholder_repeater_rows` Home/Landing were semantically identical; assembler uses the Home form.

## Workload / PR Boundary

- Mode: chained PR slices (feature-branch-chain)
- No push, PR, issue, merge, amend, or force
- This apply-progress file is uncommitted on PR2C

## Status

4/18 tasks complete. PR2A, PR2B, and PR2C are independently ready for read-only validation/publication.
