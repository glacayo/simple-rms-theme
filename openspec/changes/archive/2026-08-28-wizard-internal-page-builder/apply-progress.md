# Apply Progress: wizard-internal-page-builder

**Change**: wizard-internal-page-builder
**Mode**: Standard (`strict_tdd: false`)
**Latest work unit**: Phase 9 post-verify correction on `fix/internal-page-runtime-coverage`
**Date**: 2026-08-28
**Delivery**: auto-chain / force-chained / feature-branch-chain
**Cumulative**: 18/18 original tasks complete; 9.1-9.10 correction tasks complete

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
| PR8A | `feat/internal-page-builder-core` | #60 `e00977e0df22a70f5fa2bb53b17a2fcc33815869` merged `d2a02903f5c6ff063daf38a15e41207f56fca907` | tracker `c5b02eb` |
| PR8B | `feat/internal-page-builder-controller` | #61 `84c40d1e62afb826447339dc0192314d3e6d1e82` merged `d18b0b2e6931e33f9173075cb801ced4a46d3703` | PR8A `e00977e` |
| PR8C | `feat/internal-page-builder-ui` | #62 `7a921173de2b5f55a7b5af5a3c6f39ba586e3f66` merged `eed44ace018db2c8057df83655c9ed335928ba84` | PR8B `84c40d1` |
| PR8D | `feat/internal-page-builder-activation-final` | #63 `818845e1e4a087fbf8bf459171e2b8021f9d18cd` merged `7296d1f150a976e8927c6004ebb8d349d63a2f09` | PR8C `7a92117` |

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
- [x] 9.1 Grandfathered completion contract (verify rows 50/51/53)
- [x] 9.2 Stable identity + GET-no-write preview plan
- [x] 9.3 Runtime coverage for remaining 19 verify rows
- [x] 9.4 One-to-one mapping + server-side mapping confirmation
- [x] 9.5 Causal mapping rejection proofs (Home/Blog remap, missing/non-page object, subset/superset confirmation) with zero writes
- [x] 9.6 Confirmed exact-set mapping success proof (no content/template/ACF/canonical/count/log writes)
- [x] 9.7 Independent accessible mapping confirmation dialog + executable client/markup tests

## Remaining Tasks

- Independent sdd-verify (not run in this apply). Failed verify-report.md preserved. Do not archive.

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
| Diff vs tracker `c5b02eb` (prod+test, exclude OpenSpec) | controller **+47/−6=53**; builder **+121/−16=137**; fence **+82/−1=83**; wizard-init **+62/−0=62**; SCSS **+15/−0=15**; TS **+214/−0=214**; builder harness **+155/−8=163**; bootstrap **185**; activation harness **135**; required-step harness **45**; UI harness **103**. **Total 1195** (merged as PR8A–PR8D) |
| PR8A invariants | #60 product `e00977e` merge `d2a0290`; **383**/400; 3 files — builder, fence, builder harness |
| PR8B hidden dispatch | #61 product `84c40d1` merge `d18b0b2`; **369**/400; 3 files — controller (no REQUIRED_STEPS ninth), activation bootstrap, activation harness |
| PR8C dormant UI | #62 product `7a92117` merge `eed44ac`; **391**/400; 4 files — wizard-init (no `$steps`/`$descriptions` keys), wizard.ts (no `steps` entry), wizard.scss, UI harness |
| PR8D atomic activation | #63 product `818845e` merge `7296d1f`; **52**/400; 4 files — REQUIRED_STEPS ninth, wizard-init `$steps`+`$descriptions`, TS `steps` entry, required-step harness |
| Focused test | `php -l` controller, builder, fence, wizard-init, bootstrap, activation, required-step, UI, builder harnesses; `tsc --noEmit` **0** |
| Runtime | Independent product+packaging validation **PASS, no warnings**. builder **19/19**; activation **8/8**; required-step **2/2**; UI **2/2**; fence **4/4**; `tsc --noEmit` **0**; Layer 2 **5/5**; hook **9/9**; provenance **6/6**; templates **11/11**; Blog **7/7**; Landing **293/0**; ACF **11/11**; Home SEO **9/9**; integration **8/8**. Live visual admin boundary remains Local 502/untrusted HTTPS (external; not claimed passed). |

Activation tests that need the ninth required step live only in `tests/wizard-internal-page-required-step-harness.php`. The 8-case activation harness must pass on both the PR8B tree (8 required steps) and the final tree (9 required steps).

### Published 4-PR chain (merged on tracker `7296d1f`)

PR8A–PR8C omitted `internal-page-builder` from `REQUIRED_STEPS` and wizard `$steps` / client `steps` until PR8D.

| PR | Base | Files / hunks | +/− | Churn | Intermediate | Command | Rollback |
|---|---|---|---|---|---|---|---|
| **PR8A** | tracker `c5b02eb` | `class-step-internal-page-builder.php`, `class-wizard-mutation-fence.php`, `tests/wizard-internal-page-builder-harness.php` | 121/16 + 82/1 + 155/8 | **383** | Builder is inert: no REST dispatch, `complete()` still 8 steps | `php tests/wizard-internal-page-builder-harness.php` → **19/19** | revert those 3 |
| **PR8B** | PR8A | `class-step-controller.php` **excluding** the REQUIRED_STEPS comment rewrite and `'internal-page-builder'` array element (**+45/−4=49**); `tests/wizard-internal-page-activation-bootstrap.php` (**185**); `tests/wizard-internal-page-activation-harness.php` (**135**) | 45/4 + 185/0 + 135/0 | **369** | Hidden `execute_step('internal-page-builder')`; not in REQUIRED_STEPS; `complete()` still 8 steps; capability + REST nonce + completed `423` | `php tests/wizard-internal-page-activation-harness.php` → **8/8**; `php tests/wizard-mutation-fence-harness.php` → **4/4** | revert controller hunk; delete bootstrap + activation harness |
| **PR8C** | PR8B | `wizard-init.php` **excluding** `$steps`/`$descriptions` keys (**+60/−0**); `wizard.ts` **excluding** the `steps` array entry (**+213/−0**); `wizard.scss` (**+15/−0**); `tests/wizard-internal-page-ui-harness.php` (**103**) | 60+213+15+103 | **391** | Form renderer + TS helpers + SCSS exist; sidebar/`steps` array omit the ninth slug so it is not in the normal sequence; `complete()` still 8 steps; UI harness calls the form renderer directly | `php tests/wizard-internal-page-ui-harness.php` → **2/2**; `npx tsc --noEmit` → **0**; activation **8/8** still | revert those 4 files |
| **PR8D** | PR8C | REQUIRED_STEPS comment+element (**+2/−2=4**); wizard-init `$steps`+`$descriptions` keys (**+2/−0**); TS `steps` array entry (**+1/−0**); `tests/wizard-internal-page-required-step-harness.php` (**45**) | 2/2 + 2/0 + 1/0 + 45/0 | **52** | Ninth step required and visible together; explicit Complete proven | `php tests/wizard-internal-page-required-step-harness.php` → **2/2**; activation **8/8**; UI **2/2** | revert those 4 hunks/files |

Packaging hunk map (executed):
- PR8B controller: take DISPATCHABLE entry, skip-all flag, identity status restore, `case 'internal-page-builder'`, alias, `authorize_internal_builder`. Leave the REQUIRED_STEPS comment and `'internal-page-builder',` element for PR8D.
- PR8C wizard-init: take `rms_wizard_render_internal_page_builder_form()` and the `elseif ( 'internal-page-builder' === $slug )` branch. Leave the two `$steps` / `$descriptions` keys for PR8D.
- PR8C `wizard.ts`: take helpers, `runStep` branch, `collectPayload`. Leave `{ slug: 'internal-page-builder', label: 'Internal Page Builder' }` for PR8D.

Newest-first rollback: PR8D → PR8C → PR8B → PR8A.

## Work Unit Evidence (Phase 9 correction)

| Evidence | Result |
|---|---|
| Focused tests | identity 19/19; grandfather 3/3; controller persist-fail 1/1; render 7/7; Layer 1 2/2; REST nonce 1/1; builder 25/25; UI 2/2; client preview 17/17; map-only 6/6 |
| Runtime harness | Landing 293/0; ACF 11/11; Home SEO 9/9; integration 8/8; footer 5/5; palette 9/9; page-type PHP 5/5; page-type client 5/5; activation 8/8; required-step 2/2; fence 4/4; Layer 2 5/5; hook 9/9; provenance 6/6; templates 11/11; Blog 7/7; dependency 10/10; credential DOM 3/3; Home SEO client 14/14; integration client 1/1; landing client 7/7; client truth 8/8 |
| Build | `npx tsc --noEmit` 0; `npm run build` Vite 8.0.14, 57 modules |
| Rollback | Revert identity class, builder/controller/REST/harness/UI/TS preview helpers; delete Phase 9 test files |
| Failed verify | Preserved uncommitted `openspec/changes/wizard-internal-page-builder/verify-report.md` |

GET/state hydration does not persist generated_pages types or step_status. Legacy aliases persist type+post ID only on start/process. Custom slugs stay mapping_needed until an explicit map.

### Mapping warning closure (9.5–9.7)

| Evidence | Result |
|---|---|
| Home/Blog role remap rejection | `rms_wizard_internal_map_invalid`; zero state/page/ACF/canonical/log writes; post counts unchanged |
| Missing live object rejection | `rms_wizard_internal_map_invalid`; zero writes; post counts unchanged |
| Non-page object rejection | `rms_wizard_internal_map_invalid`; zero writes; post counts unchanged |
| Subset/superset confirmation rejection | `rms_wizard_internal_map_confirmation_required`; zero writes; post counts unchanged |
| Exact-set confirmed success | type persisted only; post content, template/meta, ACF rows, canonical store, page writes, logs, post counts all unchanged |
| Independent mapping dialog | distinct `data-wizard-internal-map-dialog` node + accept/cancel/Escape/focus behavior + exact-set payload; server authoritative; client 4/4 dialog scenarios + markup assertions |

### Live completed-site card model (9.8)

| Evidence | Result |
|---|---|
| Unique identity | per-page list keyed by unique generated `post_id`; five-shell fixture renders exactly five cards (3 resolved + 2 unresolved), never six blueprint placeholders |
| Action visibility matrix | resolved complete → regenerate only; resolved pending → no destructive action; unresolved → mapping select only; no regenerate/convert on mapping rows |
| Unavailable exclusion | blueprints with no live shell excluded from per-page list and progress; no duplicate unavailable cards |
| Progress | total counts unique eligible shell post IDs (5); complete counts only resolved complete (2); mapping_needed unresolved, not duplicate |
| Post-map collapse | after confirmed server map + refresh, unresolved card becomes exactly one resolved card for the same post id |
| Hidden semantics | `.rms-wizard-internal-card__action[hidden] { display: none; }` added; native `[hidden]` no longer overridden by `display: inline-flex` |
| Tests | client 13/13 (unique identity, exact total, action matrix, resolved-no-select, unresolved-map-only, unavailable exclusion, post-map collapse, duplicate-ID negative controls, hidden semantics); PHP identity 19/19 (preview payload post_id-keyed, resolved/unmapped disjoint, plan carried) |

### Mapping dialog cancel local no-op (9.9)

| Evidence | Result |
|---|---|
| Cancel/Escape finish plan | `planStepFinish({canceled:true})` → `reload:false`, empty status/notice; no GET fetch, no dispatch, no render, no panel change |
| Focus restoration | cancel and Escape both restore focus to the exact trigger element (client 2/2) |
| Confirm path | unchanged: exact confirmed map payload + normal response hydration; focus restored (client 1/1) |
| Form/context preservation | no `loadState()`/`render()` on cancel, so mapping selections and Internal Page Builder panel are preserved |
| Tests | client 16/16 total (3 new: cancel-finish-plan-local-noop, dialog-focus-restoration, confirm-path-unaffected) |

### Map-only identity mapping metadata-only (9.10)

| Evidence | Result |
|---|---|
| Grandfathered completed site | map-only after unlock: completion flag preserved, step status untouched, ninth step stays grandfathered optional, "Wizard complete" kept |
| Fresh pending site | stays `pending`; current_step untouched; type persisted |
| Prior failed site | stays `failed` |
| Card counts | mapping all shells leaves plan entries and step status untouched; 0/N unchanged; no page writes |
| Builder result | distinct `action: mapped` + `page_types_assigned`; never `done`/`status`; no ACF/template/canonical/log/page writes |
| Controller | map-only detected before generic progress writes; capability/nonce/locks/fence still apply |
| Client outcome | specific "Page types assigned." status/notice; never "Step completed"/"completed successfully"; cards refreshed |
| Negative control | `action: start` with map payload still writes `running` + current_step — proving the map-only branch is what protects progress |
| Tests | map-only harness 6/6; client 17/17 (map-only-outcome-specific added) |

### Live runtime evidence (Playwright, authorized destructive scenarios)

Executed against `simple-rms-theme.local` only, each scenario preceded by identity proof (siteurl `http://simple-rms-theme.local`, `rms_wizard_state` present) and followed by restoration from the protected baseline `C:\Users\Geovanny Lacayo\AppData\Local\Temp\opencode\simple-rms-theme-pre-playwright-20260827-201455.sql` (SHA-256 `9F9D54CDAB30828E208EE64BF41EB1F5A69DEA616D1C810CA87AD18E031EEFC7`) plus invariant verification (state 17176, completed=1, log 95141, 274 posts / 7 pages / 2 posts, template meta 0).

| Scenario | Causal evidence | Result |
|---|---|---|
| S1 skip-all completes without mutation | `POST /steps/internal-page-builder/run {"skip_all":true}` → `{"skipped":true,"internal_pages":[],"canonical_wrote":false}`; step 9 → complete; notice "Internal pages skipped without changing pages."; state +144 (status entry only), no post/template/ACF writes | PASS |
| S2 confirmed exact-set mapping success | `{"map_pages":[{"post_id":410,"type":"about"}],"confirm_map":true,"confirm_map_types":["about"]}` → `{"action":"mapped","page_types_assigned":["about"],"internal_pages":[]}`; UI "Page types assigned."; step stays optional; Wizard complete preserved; state +169 (type only), no other writes | PASS |
| S3 mapping rejection proofs (6) | Home/Blog role remap, missing live object (99999), non-page object (post 1), superset/subset confirmation — all `400 rms_wizard_internal_map_invalid` / `rms_wizard_internal_map_confirmation_required`; zero type/mapping/step-status/page/template/ACF/canonical/log writes; only lock-release state normalization (internal_pages a:0:{} + landing_run N) | PASS |
| S4 mapping dialog cancel/Escape local no-op | Cancel and Escape both close dialog with zero new network requests; selection preserved (About stays selected); panel unchanged; no generic step-canceled status; DB byte-identical | PASS |
| S5 post-map refresh collapse (9.8) | After confirmed map + refresh, unresolved Who We Are (post 410) collapses to exactly one resolved About card (about-us, vision-mission-v2); resolved cards' mapping wrap hidden (display:none), unresolved keep select; progress stays "0 of 5"; no duplicate cards | PASS |

Final restoration after all scenarios verified byte-level: wizard state has no `internal_pages` key, no `type` keys, no `internal-page-builder` step status; browser shows Internal Page Builder optional, 0/5, Blog and Contact resolved, three mappings pending. Repository files untouched (only `.playwright-mcp/` evidence added). Independent final sdd-verify not run; failed verify-report.md preserved.

## Status

18/18 original + 9.1–9.10 correction complete on `fix/internal-page-runtime-coverage`. Failed verify report not overwritten. No archive. No commit.
