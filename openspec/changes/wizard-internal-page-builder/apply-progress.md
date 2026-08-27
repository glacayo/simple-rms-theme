# Apply Progress: wizard-internal-page-builder

**Change**: wizard-internal-page-builder
**Mode**: Standard (`strict_tdd: false`)
**Latest work unit**: Phase 6 Blog index on `feat/internal-page-blog-index`
**Date**: 2026-08-27
**Delivery**: auto-chain / force-chained
**Cumulative**: 13/18 tasks complete

## Local chain

| Unit | Branch | Tip | Parent |
|---|---|---|---|
| PR3A | `feat/internal-page-placeholder-provenance` | `ffe3d957e7d99c90b16447047a87e3a8d20a5fd3` | tracker `4b8b717` |
| PR3B | `feat/internal-page-about-core` | `842376aefb4976f56ec72f85d54fc3c1e9a9df1d` | PR3A `ffe3d95` |
| PR3C | `feat/internal-page-about-recovery` | `a3c5b8aad368149093bfa85e5c411454d3563d4a` | PR3B `842376a` |
| PR4A | `feat/wizard-stable-page-types` | `f69777219aba6004dc3fc92ac7241ced7127692c` | tracker `4ae9ced` |
| PR4B | `feat/internal-page-template-rendering` | `42dc3f1367b6829907abed709cf30f8ccfb346f2` | PR4A `f697772` |
| PR5A | `feat/internal-page-remaining-types` | `54f268e71e42985cac6a7438554f5da2b0c8e981` | tracker `85bc1bd` |
| PR5B | `fix/internal-page-provenance-sync` | `7d1e997f013bc80385cfa8c3fbfd85a07f0c921d` | PR5A `54f268e` |
| PR6 | `feat/internal-page-blog-index` | uncommitted on tracker `c077e96` | tracker `c077e96` |

PR3A commits: `303c90d` store+harness, then `ffe3d95` `test(wizard): verify provenance option autoload` (no amend).

## Completed Tasks (cumulative)

- [x] 1.1–1.2 Blueprint registry + `internal_pages` default
- [x] 2.1–2.2 Section assembler + Home/Landing delegate
- [x] 3.1 About builder + harness (core PR3B, recovery PR3C). Builder does **not** acquire the mutation fence; Phase 8 controller/dispatch owns it.
- [x] 3.2 Provenance `record`/`query`/`queue` (PR3A)
- [x] 4.1 Shared `templates/page-sections-loop.php`
- [x] 4.2 About/Services/Contact/Projects use the loop
- [x] 4.3 Generate Pages assigns blueprint `_wp_page_template`; no sections
- [x] 5.1 Remaining ready types + provenance sync service
- [x] 5.2 Testimonials template + shell assignment
- [x] 6.1 `home.php` posts-index chrome + Blog shell assignment
- [x] 6.2 Header stored-layout assets; builder plan includes Blog

## Remaining Tasks

- [ ] 7.1–8.3

## Work Unit Evidence (PR3A)

| Evidence | Result |
|---|---|
| Diff vs tracker (both commits) | store + harness; **+255 / −0 = 255** |
| Focused test | `php -l` store and harness, 2/2 pass, PHP 8.2.29 |
| Runtime | `php tests/wizard-placeholder-provenance-harness.php` → **4 scenarios pass** (record, query-by-page, queue, **autoload=false**) |
| Rollback | Revert/delete those two files |

## Work Unit Evidence (PR3B)

| Evidence | Result |
|---|---|
| Diff vs PR3A | builder + builder harness; **+400 / −0 = 400** |
| Runtime | builder harness **7 core scenarios**; Home SEO **9/9** |
| Rollback | Delete builder + builder harness |

## Work Unit Evidence (PR3C)

| Evidence | Result |
|---|---|
| Diff vs PR3B | **+78 / −13 = 91** |
| Runtime | builder **10/10**; provenance **4/4**; Home SEO **9/9**; integration **8/8**; Landing **N/A** (shared Home/Landing/assembler untouched) |
| Rollback | Revert overwrite/convert/retry/fail isolation |

Production bytes were not changed by the autoload correction (test stub instrumentation only).

## Work Unit Evidence (PR4A)

| Evidence | Result |
|---|---|
| Diff vs tracker | **+292 / −14 = 306** |
| Commits | `f9dcc21` identity; `7d8a6e0` stable-type lookup; `f697772` `test(wizard): align page type contract count` |
| Focused test | `npx tsc --noEmit` 0; `php -l` generate-pages, internal builder, both harnesses |
| Runtime | client **5/5**; PHP contract **5/5**; builder **13/13** |
| Rollback | Revert `f697772`, then `7d8a6e0`, then `f9dcc21` |

`our-company` + `type:about` is stored on Generate Pages result/state. About builder locates and processes that shell. Unknown `type` on slug `about` is not coerced. Legacy `about-us` without type still matches.

## Work Unit Evidence (PR4B)

| Evidence | Result |
|---|---|
| Diff vs PR4A | **+337 / −33 = 370** |
| Commits | `b3c2b17` templates; `42dc3f1` `docs(wizard): clarify shell-ready blueprint types` |
| Focused test | `php -l` loop, 4 templates, generate-pages, content-builder, blueprints, harness |
| Runtime harness | template **11/11**; builder **13/13** |
| Rollback | Revert `42dc3f1` then `b3c2b17` |

`all()` docblock restored onto `all()`; `shell_ready_types()` keeps its own note. Traversal still rejected before locate. Landing **293/0**.

Regressions: ACF-inactive **11/11**; Home SEO **9/9**; integration **8/8**.

Scratch preserved: `scratch-phase4-corrected-uncommitted`.

## Work Unit Evidence (PR5A)

| Evidence | Result |
|---|---|
| Diff vs tracker | **+204 / −60 = 264** |
| Commit | `54f268e` `feat(wizard): build remaining internal page types` |
| Runtime | builder **16/16**; template **11/11**; provenance **4/4** (no sync yet) |
| Rollback | Revert `54f268e` |

Remaining types via `generated_pages[].type`; Testimonials PHP+loop+shell; Blog deferred. `placeholder_fields()` so company_services/canonical facts are not recorded. Landing **293/0**.

## Work Unit Evidence (PR5B)

| Evidence | Result |
|---|---|
| Diff vs PR5A | **+216 / −4 = 220** |
| Commits | `aaae5ad` harden sync; `7d1e997` `test(wizard): cover duplicate placeholder sync` |
| Runtime | provenance **6/6**; builder **16/16**; template **11/11** |
| Rollback | Revert `7d1e997` then `aaae5ad` |

Sync matches layout+field+canonical hash (multiset); duplicate identical occurrences consume one remaining row; reorder reindexes; malformed no-op; empty snapshot page-scoped; extra process after complete is complete/no-op. 7.2 wires existing sync.

Regressions: ACF **11/11**; Home SEO **9/9**; integration **8/8**. Landing not re-run on PR5B (assembler unchanged in this slice).

Scratch: `scratch-phase5-hardened-full`.

## Work Unit Evidence (Phase 6)

| Evidence | Result |
|---|---|
| Diff vs tracker `c077e96` (prod+test) | **+265 / −133 = 398** (under 400) |
| Focused test | `php -l` home.php, blog-v1, loop, header, boundary, blueprints, builder, footer harness, 2 harnesses |
| Runtime | blog-index **7/7**; template **11/11**; builder **16/16**; footer variants **5/5**; palette **9/9** |
| Rollback | Delete `home.php` + blog-index harness; revert blog-v1 chrome-only, loop post-id, header internal-template gating, `rms_page_section_layouts()`, Blog `shell_ready_types`/`READY_TYPES`, template harness Blog assertions |

`home.php` reads chrome from `page_for_posts`. `blog-v1.php` is headline + CTA only when both text and URL are non-empty after `esc_url`. Footer harness stubs `is_home()` false. Internal assets load only on ready page templates + `is_home()`. Landing **N/A**.

Regressions: ACF-inactive **11/11**; Home SEO **9/9**; integration **8/8**. Footer **5/5**; palette **9/9**.

## Status

13/18 complete. Phase 6 uncommitted on `feat/internal-page-blog-index`, ready for independent validation.
