# Design: Wizard Landing Page Builder

## Technical Approach

Add `landing-page-builder` as the **8th step** (after menu setup) mirroring `Step_Home_Page_Builder`, dispatched via existing `POST /rms-wizard/v1/steps/{step}/run` (no new routes). Reusable copy lives in first-write `Canonical_Section_Store`, with **lazy canonical bootstrap** before landing generation when the store is empty (existing completed/unlocked sites). Keyword copy (`hero`, `seo-content`) comes from `AI_Content_Harness` with distinct `PAGE_LANDING` Layer 2 and keyword-injected Layer 3. Pages are created only in this step via `Content_Builder::build_page()` with whitelist `meta_input`. Unlock is reversible via `Wizard_Unlock_Controller` (`unlock`/`relock` POST pseudo-steps only; state remains existing GET `/state`). After each upsert, **final-state** menu + robots sync: SEO landings get idempotent `Menu_Builder::append_page_items()`; Ads get `remove_page_items` + noindex (type flips reconcile both sides). **Menu Setup re-runs MUST** merge eligible landings and run the same final-state menu reconciliation after destructive menu replacement. Per-landing Yoast title/metadesc are written when Yoast is active. **Data-loss guard:** `Step_Generate_Pages::delete_unselected_pages()` must never delete landing pages (see Architecture Decisions).

## Architecture Decisions

| Decision | Alternative rejected | Chosen + Rationale |
|---|---|---|
| Landing render path | Keep hardcoded `get_template_part()` — `get_sub_field()` null outside loop; inject breadcrumb on every hero. | Mirror `front-page.php`: flexible ACF loop + hardcoded fallback. **Inject `breadcrumb-slim` once after the first Hero row only** when loop-rendering (not an ACF layout; not repeated for later hero rows; preserves current landing template semantics). |
| Template meta at insert | Post-insert `update_post_meta` — first render wrong. | **Rescope** `Content_Builder::build_page()`: optional whitelist-only `meta_input` pass-through for `_wp_page_template` and `rms_landing_type` only (sanitized; no arbitrary keys). |
| Canonical payload shape | Raw AI JSON. | Full prepared ACF row (`acf_fc_layout`, fallbacks); landings copy 1:1 into `page_sections`. |
| Keyword injection | New harness method. | Extend signature: `get_layer3( string $layout, int $item_count, array $client_context, string $page_type = self::PAGE_HOME, array $keywords = [] )`. Existing Home Builder calls remain valid. Placeholders only when `PAGE_LANDING` AND layout ∈ {`hero`,`seo-content`}; server drops empties, clamps to 10. |
| Layer 2 landing | Fall through unsupported-type warning. | `get_layer2(PAGE_LANDING)` returns a **distinct landing context block**; unsupported-type warning must **not** fire for `PAGE_LANDING`. |
| Unlock transport | New REST route — forbidden. | **POST pseudo-steps only:** `unlock` and `relock` on the existing run route. **Do not** treat `state` as a run-route pseudo-step — state is already served by existing **GET** `/rms-wizard/v1/state`. `execute_step()` **completed-gate allowlist: `unlock`, `relock` only** → dispatch to `Wizard_Unlock_Controller`. Unlock/relock **must bypass** `set_current_step()` and `set_step_status()` (short-circuit before normal status writes) to prevent pseudo-step pollution. Options: `rms_wizard_unlocked_at`, `rms_wizard_unlocked_by` (separate; not one combined option). `is_completed()` false while unlocked; never touch `rms_wizard_completed`. |
| Menu eligibility | Pollute `generated_pages`; call `wp_update_nav_menu_item()` ad hoc from the landing step; append-only (duplicates on re-run; stale items after type flip); optional Menu Setup merge (landings dropped on destructive re-run). | Keep landing step **8th after menu setup**. After each successful landing upsert, run **final-state menu + robots reconciliation** (not append-only): (1) **SEO → eligible:** `Menu_Builder::append_page_items()` adds the page **idempotently** (skip if already present); (2) **Ads / ineligible:** `Menu_Builder::remove_page_items()` removes that page from configured menus; (3) **type flip SEO→Ads:** remove from menus + write noindex meta + ensure `wp_robots` applies; (4) **type flip Ads→SEO:** clear noindex meta + append if menu exists and page is menu-eligible. Re-running SEO landings **must not** duplicate menu items. Ads never menu-eligible. `state.landing_pages` rows carry stable `id`/`landing_key`, `slug`, `landing_type`, `menu_eligible` (seo `true`, ads `false`). **Menu Setup re-run (mandatory):** existing Menu Setup **destructively replaces** menus. If SEO landings already exist, a re-run would drop landing menu items unless reconciled. Therefore `Step_Menu_Setup` **MUST** (not optional): (a) **merge** all `state.landing_pages` with `menu_eligible === true` into its slug/id pool before build; (b) **exclude** Ads / `menu_eligible === false`; (c) immediately after menu replacement, run the **same final-state menu reconciliation** used by the landing step — eligible SEO landings present **idempotently**, Ads **removed**. Primary per-upsert sync remains in the landing step; Menu Setup reconciliation is the **required safety net** for unlock/re-run of step 7 after landings exist. |
| Lazy canonical bootstrap | Assume canonical exists only after future Home Builder success; landing generation fails or invents empty reusables on older completed sites. | Before any landing section assembly, Landing Builder **MUST ensure** required reusable layouts exist in `Canonical_Section_Store`. **Bootstrap source order (first success wins, first-write only via `set_if_empty`):** (1) existing `state.home_sections` prepared reusable rows if available and valid; (2) else current Home page ACF `page_sections` reusable rows if present and valid; (3) else generate **neutral** reusable sections (same path as Home Builder reusable generation without keyword injection) and first-write them. Seed `state.canonical_sections` summary when writes succeed. If bootstrap **cannot** produce the required reusable content after all sources, **block** landing generation for rows that need those layouts and surface an **actionable UI error** (e.g. re-run Home Builder or fix Home page sections) — do not create landings with empty/invalid reusable payloads. Keyword layouts (`hero`, `seo-content`) are never bootstrapped from canonical; they always come from the harness. |
| Per-landing Yoast SEO | Title/metadesc omitted or only noindex. | When Yoast is **active**, for **each** created/updated landing write `_yoast_wpseo_title` and `_yoast_wpseo_metadesc` derived from `primary_keyword` + `landing_type` (via `Yoast_Meta_Writer` or step helper). When Yoast is **absent**, **skip** title/metadesc writes and **log** once per run (do not fail the step for missing Yoast). Ads still require noindex path below. |
| Ads noindex | Yoast alone lost if disabled. | Extend `Yoast_Meta_Writer` (or small step helper) to write `_yoast_wpseo_meta-robots-noindex` = `1`; **read-back must equal `1`** or block completion. Plus `wp_robots` filter in always-loaded `wizard-init.php`. |
| Ads sitemap | No exclusion. | Scoped exclusion of `rms_landing_type=ads` from WP/Yoast sitemaps when those APIs are available. |
| Completion / 0 landings | Ambiguous empty complete; required-step list drift. | Empty `landings[]` is a **valid no-op completion** only when the UI explicitly allows skip (skip/empty confirmed). If the user submits rows, every non-skipped row requires non-empty sanitized `primary_keyword` (server + UI reject otherwise). Add `landing-page-builder` to the **single source of truth** for required steps (prefer one shared constant/list consumed by both `REQUIRED_STEPS` and `maybe_mark_completed()`). If two lists must remain temporarily, **both** must be updated in the same change and asserted equal in a smoke check — never edit one without the other. |
| Primary keyword | Optional. | Server and UI reject a landing row with empty sanitized `primary_keyword` unless the row is skipped/disabled. |
| Rerun / stable identity | Blind re-create; payload rows without stable identity; slug fallback collides with standard pages. | Each payload row may carry optional stable **`id`** (WP post ID) and/or **`landing_key`** hydrated from `state.landing_pages` on UI load. **Match order:** (1) `id` if present and still a landing in state, (2) else `landing_key` if present, (3) else slug. **Preflight (reject before any write):** (a) **slug fallback** may only resolve to an existing page that has post meta `rms_landing_type` (seo\|ads) — never claim a non-landing page; (b) **reject** slug collisions with unmatched/non-landing pages (e.g. standard generated slugs like `services`); (c) **reject** duplicate non-empty `id` values within the payload; (d) **reject** duplicate non-empty `landing_key` values within the payload; (e) reject duplicate slugs within payload and against other landing-state entries (including rename collisions). **UI duplicate-row:** when the user duplicates a landing row, clear `id` and assign a **new** `landing_key` (never copy identity). On valid slug rename for a matched landing: update `post_name` and the corresponding `state.landing_pages` entry. |
| Replace canonical | Unspecified confirm. | Reuse `rms_wizard_render_confirmation_modal`. Server accepts replace only when `replace_canonical[layout] === true` **and** explicit confirmation flag/token from the modal is present. |
| Step_Generate_Pages reverse-overlap | Assume landings are safe because they bypass generate-pages creation. | **DATA-LOSS RISK (explicit):** after unlock/rerun, `Step_Generate_Pages::delete_unselected_pages()` deletes every page whose slug is **not** in the selected standard-page slug set. Landing pages are **not** standard slugs → they would be **hard-deleted** without a guard. **Mitigation (required):** modify `delete_unselected_pages()` to **exclude any page that has post meta `rms_landing_type`** (seo\|ads) from the deletion candidate set. Prefer meta exclusion over slug-pool merge: landings remain identifiable even if `state.landing_pages` is stale or empty. Optionally also merge slugs from `state.landing_pages` into the protected pool as defense-in-depth, but meta exclusion is the primary contract. Landings are still **created only** in `Step_Landing_Page_Builder`; reading settings untouched. |

## Data Flow

```
wizard.ts (collectLandingPageBuilderPayload)
    │ POST /rms-wizard/v1/steps/landing-page-builder/run
    ▼
Rest_Controller → Step_Controller (lock; completed allowlist unlock|relock only)
    → Step_Landing_Page_Builder | Wizard_Unlock_Controller
    ├─ preflight: reject dup non-empty id/landing_key; reject slug vs non-landing pages;
    │    slug fallback only if target has rms_landing_type; match id → landing_key → slug
    ├─ lazy canonical bootstrap (if store missing required reusables):
    │    state.home_sections → else Home page_sections → else neutral generate
    │    → set_if_empty + state.canonical_sections; fail with actionable UI error if still missing
    ├─ hero/seo-content → Harness PAGE_LANDING + keywords → Provider → Reviewer → validate_fields()
    ├─ reusable rows → Canonical_Section_Store (copy; override → neutral regen; fallback on empty/invalid)
    ├─ Content_Builder::build_page( whitelist meta_input )
    ├─ Yoast (if active): per-landing _yoast_wpseo_title + _yoast_wpseo_metadesc from primary_keyword + landing_type; else skip+log
    ├─ final-state type sync (every update, not append-only):
    │    SEO: clear noindex; Menu_Builder::append_page_items idempotent (no dupes)
    │    Ads: write noindex + read-back; Menu_Builder::remove_page_items from configured menus
    │    SEO→Ads / Ads→SEO flips apply both menu + robots sides
    ├─ ads sitemap exclusion hooks as applicable
    └─ state.landing_pages (id, landing_key, slug, …) → response → UI

Menu Setup re-run (mandatory when landings exist):
    Step_Menu_Setup
      → MUST merge state.landing_pages where menu_eligible === true into slug/id pool
      → MUST exclude Ads / menu_eligible === false
      → destructive menu replace (existing behavior)
      → MUST run final-state menu reconciliation immediately after replace:
           eligible SEO landings → append_page_items idempotent
           Ads → remove_page_items

Separate (existing): GET /rms-wizard/v1/state — not a run-route pseudo-step.

Generate-pages guard (unlock/rerun path):
    Step_Generate_Pages::delete_unselected_pages()
      → skip candidates with rms_landing_type meta (required)
      → optional: also protect slugs in state.landing_pages
```

Home step delta: on success, prepared reusable rows → `set_if_empty()` + state summary (forward path for new runs). Existing completed sites without `rms_wizard_canonical_sections` rely on **lazy bootstrap** in Landing Builder (above).

### `wp_robots` guard sequence

Early return unless **all** of: `is_page()`, valid queried object ID, page template is `pages/landing-page.php`, and `rms_landing_type === ads`. Then force noindex.

## File Changes

| File | Action | Description |
|---|---|---|
| `inc/wizard/class-step-landing-page-builder.php` | Create | 8th step: identity preflight; **lazy canonical bootstrap** (`home_sections` → Home `page_sections` → neutral generate; actionable error if still missing); rows, keywords, sections, meta, final-state menu+robots sync, slug upsert |
| `inc/wizard/class-canonical-section-store.php` | Create | `get/has/set_if_empty/replace/summary`; lazy load; used by Home first-write and Landing bootstrap |
| `inc/wizard/class-wizard-unlock-controller.php` | Create | `unlock()`/`relock()`; writes `rms_wizard_unlocked_at` / `rms_wizard_unlocked_by` |
| `inc/wizard/class-step-controller.php` | Modify | Shared required-steps source (or dual-list keep-in-sync); dispatch; completed-gate allowlist **`unlock`/`relock` only** (not `state`); unlock/relock bypass status writes |
| `inc/wizard/class-state-manager.php` | Modify | New state defaults (`landing_pages` with id/landing_key); unlock-aware `is_completed()` |
| `inc/wizard/class-ai-content-harness.php` | Modify | PAGE_LANDING Layer 2 distinct block; Layer 3 signature + keywords |
| `inc/wizard/class-step-home-page-builder.php` | Modify | Canonical first-write; required list |
| `inc/wizard/class-step-menu-setup.php` | Modify | **Mandatory** merge of `menu_eligible === true` landings into slug/id pool; exclude Ads; after destructive menu replace, run final-state menu reconciliation (idempotent SEO append + Ads remove) |
| `inc/wizard/class-menu-builder.php` | Modify | `append_page_items` (idempotent); `remove_page_items`; shared landing menu reconciliation helper used by landing step + Menu Setup |
| `inc/wizard/class-step-generate-pages.php` | Modify | **`delete_unselected_pages()`:** exclude pages with `rms_landing_type` meta (data-loss guard); optional protect `state.landing_pages` slugs |
| `inc/wizard/class-content-builder.php` | Modify | Whitelist `meta_input` only: `_wp_page_template`, `rms_landing_type` |
| `inc/wizard/class-yoast-meta-writer.php` | Modify | Per-landing title + metadesc from keyword/type when Yoast active; ads noindex write path (or equivalent small helper); skip+log when Yoast absent |
| `inc/wizard/wizard-init.php` | Modify | Registry, form, unlock notice, `wp_robots`, ads sitemap exclusion |
| `src/ts/admin/wizard.ts` | Modify | Collector (optional `id`/`landing_key`); duplicate-row clears `id` + new `landing_key`; skip/empty; keyword validation; unlock; replace modal |
| `src/scss/admin/wizard.scss` | Modify | Landing/unlock admin styles only |
| `pages/landing-page.php` | Modify | Flexible loop + inject `breadcrumb-slim` once after first Hero row only |

No new ACF fields/layouts. Untracked docs untouched. State remains GET `/state` only — not a POST run pseudo-step.

## Interfaces / Contracts

```php
// Payload (wizard.ts → step)
[
  'landings' => [ [
    'id' => int|null,                // optional; WP post ID hydrated from state.landing_pages
    'landing_key' => string|null,    // optional stable key hydrated from state (survives slug rename)
    'title', 'slug', 'landing_type', // seo|ads
    'primary_keyword',               // required unless row skipped
    'subkeywords' => string[],
    'sections' => [ [ 'layout', 'override_canonical' => bool ] ],
    'skipped' => bool,               // optional; skipped rows ignored
  ] ],
  'replace_canonical' => [ '{layout}' => bool ],
  'confirm_replace_canonical' => bool|string, // modal flag/token required with any replace true
  'skip_all' => bool,                // explicit UI skip → 0-landing no-op complete
]

// Harness
get_layer3( string $layout, int $item_count, array $client_context,
            string $page_type = self::PAGE_HOME, array $keywords = [] ): ...
get_layer2( /* PAGE_LANDING */ ): // distinct landing context; no unsupported warning

// Canonical_Section_Store
set_if_empty( string $layout, array $row ): bool;
replace( string $layout, array $row ): bool; // only if replace_canonical[layout] && confirm
get( string $layout ): array;  has( string $layout ): bool;

// Menu_Builder — final-state reconciliation helpers (not append-only)
append_page_items( int $menu_id, array $page_ids ): array;
// Idempotent: skip page_ids already present as menu items; never duplicate.
remove_page_items( int $menu_id, array $page_ids ): array;
// Remove nav items pointing at those page IDs (when landing becomes Ads/ineligible).
// Shared helper (preferred): reconcile_landing_menu_items( menus, state.landing_pages )
//   — eligible SEO present idempotently; Ads removed. Called from:
//   (1) Step_Landing_Page_Builder after each upsert
//   (2) Step_Menu_Setup immediately after destructive menu replacement (MANDATORY)

// Step_Menu_Setup re-run contract (MANDATORY — not optional)
// 1. Merge state.landing_pages where menu_eligible === true into slug/id pool before build.
// 2. Exclude Ads / menu_eligible === false from pool.
// 3. After menu replacement: run final-state menu reconciliation (same as landing step).

// Canonical_Section_Store — lazy bootstrap (Landing Builder, before generation)
// ensure_canonical_reusables(): if required layouts missing:
//   (1) seed from state.home_sections if valid
//   (2) else seed from Home page ACF page_sections if valid
//   (3) else generate neutral reusable rows and set_if_empty
//   On total failure: return error → step surfaces actionable UI message; do not build landings
//   needing missing layouts. Keyword layouts (hero, seo-content) never come from bootstrap.

// Content_Builder meta_input whitelist (sanitized values only)
// _wp_page_template, rms_landing_type

// Step_Generate_Pages::delete_unselected_pages()
// MUST skip any page with post meta rms_landing_type (seo|ads).
// Optional defense-in-depth: also protect slugs listed in state.landing_pages.

// Unlock options (separate) — POST run pseudo-steps: unlock, relock only
// rms_wizard_unlocked_at, rms_wizard_unlocked_by
// State: existing GET /rms-wizard/v1/state (not a run pseudo-step)

// Completion gate — single source of truth for required steps
// Prefer one shared list/constant used by REQUIRED_STEPS and maybe_mark_completed().
// If dual lists remain, both updated together + asserted equal (prevent drift).

// Yoast per landing (when Yoast active)
// _yoast_wpseo_title, _yoast_wpseo_metadesc ← from primary_keyword + landing_type
// When Yoast absent: skip title/metadesc writes + log; do not fail step for missing Yoast
// Ads: _yoast_wpseo_meta-robots-noindex = 1 (write+read-back); SEO: clear noindex meta
// wp_robots filter always applies for ads landings (wizard-init.php)

state.landing_pages[]    = [ 'id', 'landing_key', 'slug', 'landing_type', 'menu_eligible', 'keywords' ];
state.canonical_sections = [ '{layout}' => [ 'has_payload', 'generated_at' ] ];
```

Post meta: `rms_landing_type` (`seo|ads`), `_wp_page_template`, `_yoast_wpseo_title`, `_yoast_wpseo_metadesc` (when Yoast active), `_yoast_wpseo_meta-robots-noindex` (ads; read-back `1`; cleared when type becomes SEO).

**Rerun / identity preflight + match:**
1. **Reject** payload with duplicate non-empty `id` values or duplicate non-empty `landing_key` values.
2. **Match** existing `state.landing_pages` by **`id` first**, then **`landing_key`**, then **slug**.
3. **Slug fallback** may only match an existing WP page that has `rms_landing_type` post meta; otherwise treat as no match for update.
4. **Reject** slug collision with an unmatched/non-landing page (e.g. standard page `services`) — do not overwrite or “claim” non-landings.
5. Reject intra-payload duplicate slugs and collisions with other landing-state slugs (including rename collisions).
6. On match + valid slug rename: update `post_name` + state row.
7. **UI duplicate row:** clear `id`; mint a new `landing_key`.

**Landing-type final-state sync (every successful create/update AND after Menu Setup replace):**
| Transition / state | Menu | Robots / meta |
|---|---|---|
| SEO (create, re-run, or Ads→SEO) | `append_page_items` idempotent if menu exists + menu-eligible | Clear `_yoast_wpseo_meta-robots-noindex` |
| Ads (create, re-run, or SEO→Ads) | `remove_page_items` from configured menus | Write noindex `1` + read-back; `wp_robots` applies |
| Re-run same type | Reconcile to final state (no duplicate menu items; no leftover noindex on SEO) | Same |
| Menu Setup re-run (after destructive replace) | **MUST** re-apply same menu final state for all `state.landing_pages` (eligible SEO present idempotently; Ads removed) | Robots unchanged by Menu Setup (landing step owns meta) |

## Testing Strategy

No automated runner (`openspec/config.yaml`).

| Layer | What | Approach |
|---|---|---|
| Syntax/type | Modified PHP / TS | `php -l` per file; `tsc --noEmit` |
| Scenario | Spec scenarios | Manual: seo+ads, skip-all no-op, keyword reject, identity preflight (dup id/key reject, slug vs non-landing reject, slug fallback only with `rms_landing_type`), UI duplicate clears id+new key, rerun upsert by id/key/slug + rename, SEO↔Ads type flip (menu remove/append + noindex clear/set, no menu dupes on re-run), **Menu Setup re-run after SEO+Ads landings exist** (eligible SEO items restored idempotently; Ads absent from menus), **lazy canonical bootstrap** on completed site with empty `rms_wizard_canonical_sections` (seeds from home_sections → page_sections → neutral; actionable error if all sources fail), unlock/relock status (GET state separate), Yoast title/metadesc when active / skip+log when absent, sitemap ads exclusion, generate-pages does **not** delete landings after unlock, replace modal, required-steps list parity |
| Manual audit | Landing frontend | Template first-load, breadcrumb once after first hero only, `wp_robots`, DOM < 1500, lazy images |

## Migration / Rollout

No bulk data migration; additive options/state. Completed sites re-open via unlock.

**Existing completed / unlocked sites (pre-landing-builder):** may lack `rms_wizard_canonical_sections` because canonical first-write only ran after Home Builder success on *future* runs. **No backfill job required.** On first Landing Builder run, **lazy canonical bootstrap** (Architecture Decisions) seeds the store from `state.home_sections` → Home `page_sections` → neutral generate, then first-writes. If bootstrap fails, the step errors with an actionable UI message rather than creating broken landings.

**Menu Setup after landings:** sites that re-run Menu Setup post-landing must pick up the mandatory merge + final-state reconciliation (no separate migration).

Rollback: revert chained PRs; delete unlock options, `state.landing_pages`, and `rms_wizard_canonical_sections` if written. Delivery: chained PRs under 400 lines (`force-chained`).

## Open Questions

None — all prior open questions resolved above.
