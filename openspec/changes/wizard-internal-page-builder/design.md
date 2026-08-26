# Design: Wizard Internal Page Builder

## Technical Approach

One optional 9th step (`internal-page-builder`) drives a static blueprint registry, reusing `Content_Builder::build_page()` in `section_only` mode. Its `META_INPUT_WHITELIST` already accepts `_wp_page_template`, so template assignment needs no builder change, and `page_sections` is already located on `page == all`, so no ACF field or `acf-json` sync is required. Five templates convert to the proven `pages/landing-page.php` loop; `home.php` is added for `page_for_posts`.

## Architecture Decisions

| # | Decision | Rejected | Rationale |
|---|---|---|---|
| 1 | One `Internal_Page_Blueprints` registry + one `Step_Internal_Page_Builder` | Six builders | Product intent; a Landing clone (~2294 lines) breaks the 400-line budget |
| 2 | Extract `Section_Assembler` from the identical `section_data`/`placeholder_copy`/`service_rows` pair in Home (`:423`) and Landing (`:2158`); both delegate | Copy a third time | Already byte-identical, so delegation is behavior-preserving and destabilizes neither builder |
| 3 | Plan in `state.internal_pages`; one page per `process`; plain `execute_step` under the global fence | Clone/parameterize `Landing_Run_Orchestrator` (1042 lines, open verify task 4.1) | One page ≤ Home's existing single-request AI load, so no lease is needed; `response_success_from_status()` already treats `running` as success |
| 4 | No fence exception: acquire the legacy lock like Home/Generate/IA | Landing's start/process bypass | Bounded work cannot outlive the lock; the existing `finally` releases lock before fence |
| 5 | Provenance in non-autoloaded option `rms_wizard_placeholder_provenance` | Store in `rms_wizard_state` | Mirrors `Canonical_Section_Store`; `State_Manager`'s `array_replace_recursive` corrupts list data |
| 6 | Contact map stays chrome outside the loop, gated on `company_google_maps_url` | Invent a map layout | `contact-map` is not one of the 27 `page_sections` layouts; it already renders "Map unavailable" |
| 7 | Services uses real `services-v1` + `cta-v2`; `services-page` kept but unreferenced | Make the demo ACF-driven | Meets the spec with existing layouts; retaining the file keeps rollback trivial |
| 8 | Per-page Yoast excluded — no `seo` key passed to `build_page()` | Reuse `Yoast_Meta_Writer` | No approved spec requires it; adding it would silently expand scope |

## Data Flow

    wizard.ts ──POST /steps/internal-page-builder/run──► Rest_Controller
       ▲                                                       ▼
       │ running → re-dispatch          Step_Controller::execute_step
       │                                (fence acquire → legacy lock)
       │                                                       ▼
       │            Internal_Page_Blueprints ──► Step_Internal_Page_Builder::run()
       │                                    plan: state.internal_pages
       │                                                       ▼
       │    next pending page ─► Section_Assembler ─┬─► Canonical_Section_Store
       │                                            ├─► Harness + Provider + Reviewer
       │                                            └─► Placeholder_Provenance_Store
       └── state ◄─ Content_Builder::build_page(section_only, _wp_page_template)

## File Changes

All PHP classes live in `inc/wizard/`.

| File | Action | Description |
|---|---|---|
| `class-internal-page-blueprints.php` | Create | Fixed map: type → template, layouts, `PAGE_*`, canonical policy |
| `class-step-internal-page-builder.php` | Create | Plan, one-page process, retry, skip-all, legacy/overwrite gates |
| `class-section-assembler.php` | Create | Shared assembly extracted from Home/Landing |
| `class-placeholder-provenance-store.php` | Create | Record, query, queue, `sync()`, `is_placeholder_payload()` |
| `class-step-home-page-builder.php`, `class-step-landing-page-builder.php` | Modify | Delegate to `Section_Assembler` |
| `class-step-controller.php` | Modify | Step added to `REQUIRED_STEPS` + `DISPATCHABLE_STEPS`, dispatch case |
| `class-state-manager.php` | Modify | `internal_pages` default |
| `class-step-generate-pages.php` | Modify | Blueprint `_wp_page_template` at shell creation; no sections |
| `class-ai-content-harness.php` | Modify | Layer 2 for ABOUT/SERVICE/CONTACT/BLOG; add `PAGE_PROJECTS`/`PAGE_TESTIMONIALS` |
| `wizard-init.php` | Modify | Step label, description, panel; `acf/save_post` → `sync()` |
| `templates/page-sections-loop.php` | Create | Shared loop partial |
| `pages/{about-us,services,contact-us,projects}.php` | Modify | Chrome + loop partial |
| `pages/testimonials.php` | Modify | Repaired valid PHP + loop |
| `home.php` | Create | Posts-index chrome + WP loop + empty state |
| `header.php` | Modify | Defer `src/scss/templates/{layout}.scss` from stored rows |
| `src/ts/admin/wizard.ts`, `src/scss/admin/` | Modify | Step config, cards, re-dispatch, styles |

**CSS strategy**: breadcrumb stays inline critical; `header.php` replaces its hardcoded per-template lists with a loop over the page's stored `page_sections` layouts, deferring `section-{layout}` async — otherwise generated rows render unstyled. No new Vite entry points; per-layout SCSS already exists.

## Interfaces / Contracts

```php
// Internal_Page_Blueprints::all()
[ 'about' => [ 'template'=>'pages/about-us.php', 'canonical'=>'copy',
    'layouts'=>['about-us','vision-mission-v2'], 'page_type'=>PAGE_ABOUT ] ]

// state.internal_pages
[ 'about' => [ 'post_id'=>12, 'layouts'=>[], 'updated_at'=>'',
    'status'=>'pending|complete|failed|skipped', 'reason'=>'' ] ]

// option rms_wizard_placeholder_provenance (autoload=false)
[ 12 => [ '0:about_headline' => [ 'layout'=>'about-us', 'row'=>0,
    'field'=>'about_headline', 'reason'=>'missing_client_fact',
    'value_hash'=>'sha1', 'written_at'=>'UTC' ] ] ]
```

Payload: `{ action: 'start'|'process', skip_all?, retry_failed?, overwrite[], convert_legacy[] }`. Empty `page_sections` plus non-empty `post_content` is `legacy`; unconfirmed → `skipped`. `sync()` runs on `acf/save_post` (priority 20), dropping entries whose current value no longer hashes to `value_hash` — replacement needs no rerun and never touches siblings. `is_placeholder_payload()` gates `set_if_empty()` and factual-context composition, so placeholders never become canonical or client facts.

## Testing Strategy

No unit runner exists (`testing.runner.available: false`); custom harnesses are the pattern.

| Layer | What | Approach |
|---|---|---|
| Unit-equivalent | Blueprint contract, plan transitions, provenance record/query/clear, canonical copy vs first-write | New `tests/wizard-internal-page-builder-harness.php` and `tests/wizard-placeholder-provenance-harness.php`, stubs per `wizard-mutation-fence-harness.php` |
| Contract | Template assignment, no post generation | Source assertions in the same harness |
| Integration | Lock release on failure, resume, retry, failure isolation, `running` re-dispatch | Extend `scripts/test-landing-run-orchestrator.php` fakes |
| Render | Loop order, unknown layout skipped, empty sections/index | `php -l` all templates + manual audit |
| Types | Admin step flow | `tsc --noEmit` |

## Threat Matrix

N/A — no shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary. All five rows (documentation-like paths, repository selection, commit state, push state, PR commands) are N/A: this adds one REST-dispatched step, already guarded by `manage_options`, nonce, and the mutation fence.

## Migration / Rollout

No data migration. Completed sites keep `rms_wizard_completed`; the step is reachable only through `Wizard_Unlock_Controller`, and skip-all satisfies it. Delivery is `force-chained` in ~8 slices under 400 lines: templates → registry/state → About backend → remaining blueprints → Testimonials → `home.php` → admin UI → harness Layer 2 and tests.

**Rollback**: revert slices newest-first — drop the step from both step lists, discard `state.internal_pages`, delete the provenance option, restore `pages/*.php`, delete `home.php`. Stored rows are inert once templates revert; the canonical store is never written by a rollback path.

## Open Questions

- [ ] None blocking. Ordering: `wizard-user-friendly-content-flow` and `wizard-landing-page-builder` must archive first — the `wizard-page-generation`, `wizard-canonical-sections`, and `wizard-controlled-unlock` deltas lack a published baseline.
