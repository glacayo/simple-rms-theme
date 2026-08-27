# Apply Progress: wizard-internal-page-builder

**Change**: wizard-internal-page-builder
**Mode**: Standard (`strict_tdd: false`)
**Latest work unit**: Phase 8 packaging — 4-PR safe chain with 8-case hidden dispatch vs 2-case REQUIRED_STEPS delta
**Date**: 2026-08-27
**Delivery**: auto-chain / force-chained
**Cumulative**: 18/18 tasks complete

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
| PR6 | `feat/internal-page-blog-index` | published on tracker `3d776c6` | tracker `c077e96` |
| PR7A | `feat/internal-page-ai-layer2` | #58 `473c2e991edd389186c89b11827adcf3cbd898e5` merged `12a195587cce307b9fda61efde100d3508597e4b` | tracker `3d776c6` |
| PR7B | `feat/internal-page-provenance-hook` | #59 `7c8128029e9223d94b8fb52a2772516b533cef70` merged `ec14fed194d4e8018cc326f50201047912634264` | PR7A `473c2e9` |
| PR8 | `feat/internal-page-builder-activation` | uncommitted on tracker `c5b02eb` | tracker `c5b02eb` |

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
- [x] 7.1 Layer 2 for About/Services/Contact/Blog + `PAGE_PROJECTS`/`PAGE_TESTIMONIALS`; blocked gallery/item fields
- [x] 7.2 `Placeholder_Provenance_Store::register()` on `acf/save_post`(20, args 1); field-object empty-save completeness; runtime reentrancy
- [x] 8.1 Internal Page Builder step UI: cards by stable type, skip-all, overwrite/convert confirmation, edit link
- [x] 8.2 Controller `REQUIRED_STEPS`+`DISPATCHABLE_STEPS`+dispatch; fence owned by `execute_step`
- [x] 8.3 Identity mismatch rejected; failed pages do not block next pending; step stays incomplete while any page is failed; Home/Landing regression

## Remaining Tasks

- None. Do not archive until independent validation.

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

## Work Unit Evidence (Phase 7)

| Evidence | Result |
|---|---|
| Diff vs tracker `3d776c6` (prod+test) | **+442 / −11 = 453** (over 400 — merged as PR7A then PR7B) |
| PR7A 7.1 AI Layer 2 | #58 product `473c2e9` merge `12a1955`; **+113 / −10 = 123**/400; 3 files — harness, blueprints, `tests/wizard-ai-layer2-harness.php` |
| PR7B 7.2 provenance hook | #59 product `7c81280` merge `ec14fed`; **+329 / −1 = 330**/400; 4 files — store, wizard-init `register()`, landing fake+runtime register, hook harness |
| Focused test | `php -l` **7/7**: AI harness, blueprints, provenance store, wizard-init, landing orchestrator, Layer 2 harness, hook harness |
| Runtime | Independent revalidation **PASS, no warnings**. Layer 2 **5/5**; hook **9/9** (runtime `register()`, empty-save completeness, nested reentrancy); provenance **6/6**; builder **16/16**; templates **11/11**; blog-index **7/7**; Landing **293/0** (bootstrap asserts `register()` via fake `add_action`) |
| Rollback | PR7A: revert Layer 2/`PAGE_PROJECTS`/`PAGE_TESTIMONIALS` + delete Layer 2 harness. PR7B: drop `register()`/`handle_acf_save_post()`/`complete_page_sections_snapshot()` + wizard-init call; restore landing require/`add_action` fake; delete hook harness |

`get_layer2()` for Home/Landing is unchanged. Unknown type falls back to `PAGE_HOME` with a warning. Blocked collections stay empty. `wizard-init.php` calls `Placeholder_Provenance_Store::register()` which `add_action( 'acf/save_post', [ self::class, 'handle_acf_save_post' ], 20, 1 )`. Hook and Landing harnesses call that same method through fake `add_action` (no source grep). Valid field object `value === false` → `sync( $id, [] )` once, page-scoped. Read failure/missing value/malformed nonempty/non-page/autosave/revision no-op. Nested `handle_acf_save_post()` during `update_option` persist returns false, one write, no cross-page mutation. Independent revalidation proved runtime registration, Landing path, causal reentrancy, and empty-vs-read-failure. Footer/palette **N/A**.

Regressions: ACF-inactive **11/11**; Home SEO **9/9**; integration **8/8**.

## Work Unit Evidence (Phase 8)

| Evidence | Result |
|---|---|
| Diff vs tracker `c5b02eb` (prod+test, exclude OpenSpec) | controller **+47/−6=53**; builder **+121/−16=137**; fence **+82/−1=83**; wizard-init **+62/−0=62**; SCSS **+15/−0=15**; TS **+214/−0=214**; builder harness **+155/−8=163**; bootstrap **185**; activation harness **135**; required-step harness **45**; UI harness **103**. **Total 1195** |
| Focused test | `php -l` controller, builder, fence, wizard-init, bootstrap, activation, required-step, UI, builder harnesses; `tsc --noEmit` **0** |
| Runtime | builder **19/19**; activation **8/8** (hidden dispatch, independent of REQUIRED_STEPS); required-step **2/2**; UI **2/2**; fence **4/4**; Layer 2 **5/5**; hook **9/9**; provenance **6/6**; templates **11/11**; blog **7/7**; Landing **293/0**; ACF **11/11**; Home SEO **9/9**; integration **8/8** |

Activation tests that need the ninth required step live only in `tests/wizard-internal-page-required-step-harness.php`. The 8-case activation harness must pass on both the PR8B tree (8 required steps) and the final tree (9 required steps).

### Safe 4-PR chain (feature-branch-chain from `c5b02eb`)

Do **not** add `internal-page-builder` to `REQUIRED_STEPS` or to the wizard `$steps` / client `steps` arrays before PR8D.

| PR | Base | Files / hunks | +/− | Churn | Intermediate | Command | Rollback |
|---|---|---|---|---|---|---|---|
| **PR8A** | tracker `c5b02eb` | `class-step-internal-page-builder.php`, `class-wizard-mutation-fence.php`, `tests/wizard-internal-page-builder-harness.php` | 121/16 + 82/1 + 155/8 | **383** | Builder is inert: no REST dispatch, `complete()` still 8 steps | `php tests/wizard-internal-page-builder-harness.php` → **19/19** | revert those 3 |
| **PR8B** | PR8A | `class-step-controller.php` **excluding** the REQUIRED_STEPS comment rewrite and `'internal-page-builder'` array element (**+45/−4=49**); `tests/wizard-internal-page-activation-bootstrap.php` (**185**); `tests/wizard-internal-page-activation-harness.php` (**135**) | 45/4 + 185/0 + 135/0 | **369** | Hidden `execute_step('internal-page-builder')`; not in REQUIRED_STEPS; `complete()` still 8 steps; capability + REST nonce + completed `423` | `php tests/wizard-internal-page-activation-harness.php` → **8/8**; `php tests/wizard-mutation-fence-harness.php` → **4/4** | revert controller hunk; delete bootstrap + activation harness |
| **PR8C** | PR8B | `wizard-init.php` **excluding** `$steps`/`$descriptions` keys (**+60/−0**); `wizard.ts` **excluding** the `steps` array entry (**+213/−0**); `wizard.scss` (**+15/−0**); `tests/wizard-internal-page-ui-harness.php` (**103**) | 60+213+15+103 | **391** | Form renderer + TS helpers + SCSS exist; sidebar/`steps` array omit the ninth slug so it is not in the normal sequence; `complete()` still 8 steps; UI harness calls the form renderer directly | `php tests/wizard-internal-page-ui-harness.php` → **2/2**; `npx tsc --noEmit` → **0**; activation **8/8** still | revert those 4 files |
| **PR8D** | PR8C | REQUIRED_STEPS comment+element (**+2/−2=4**); wizard-init `$steps`+`$descriptions` keys (**+2/−0**); TS `steps` array entry (**+1/−0**); `tests/wizard-internal-page-required-step-harness.php` (**45**) | 2/2 + 2/0 + 1/0 + 45/0 | **52** | Ninth step required and visible together; explicit Complete proven | `php tests/wizard-internal-page-required-step-harness.php` → **2/2**; activation **8/8**; UI **2/2** | revert those 4 hunks/files |

Hunk map (practical `git add -p`):
- PR8B controller: take DISPATCHABLE entry, skip-all flag, identity status restore, `case 'internal-page-builder'`, alias, `authorize_internal_builder`. Leave the REQUIRED_STEPS comment and `'internal-page-builder',` element for PR8D.
- PR8C wizard-init: take `rms_wizard_render_internal_page_builder_form()` and the `elseif ( 'internal-page-builder' === $slug )` branch. Leave the two `$steps` / `$descriptions` keys for PR8D.
- PR8C `wizard.ts`: take helpers, `runStep` branch, `collectPayload`. Leave `{ slug: 'internal-page-builder', label: 'Internal Page Builder' }` for PR8D.

Newest-first rollback: PR8D → PR8C → PR8B → PR8A.

## Status

18/18 complete. Phase 8 uncommitted on `feat/internal-page-builder-activation` @ tracker `c5b02eb`. Combined prod+test **1195**. Ready for independent packaging validation. No archive.
