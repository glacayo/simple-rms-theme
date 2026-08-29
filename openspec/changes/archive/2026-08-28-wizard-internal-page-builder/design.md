# Design: Wizard Internal Page Builder

## Technical Approach

One optional 9th step (`internal-page-builder`) drives a blueprint registry and reuses `Content_Builder::build_page()` in `section_only` mode. Template meta is already whitelisted and `page_sections` already covers `page == all`. Five templates use the landing loop; `home.php` covers `page_for_posts`.

## Architecture Decisions

| # | Decision | Rejected | Rationale |
|---|---|---|---|
| 1 | One `Internal_Page_Blueprints` registry + one `Step_Internal_Page_Builder` | Six builders | Product intent; a Landing clone blows the 400-line budget |
| 2 | Extract `Section_Assembler` from the Home (`:423`) / Landing (`:2158`) `section_data`/`placeholder_copy`/`service_rows` pair; both delegate | Copy a third time | Semantically equivalent, not byte-identical. Landing lacked Home's no-fillable comment and inlined `(string)` casts in `placeholder_repeater_rows`. Assembler uses the Home form; runtime output is preserved |
| 3 | Plan in `state.internal_pages`; one page per `process`; plain `execute_step` under the global fence | Clone `Landing_Run_Orchestrator` (1042 lines, open verify 4.1) | One page ≤ Home's AI load, so no lease; `running` already counts as success |
| 4 | No fence exception: acquire the legacy lock like Home/Generate/IA | Landing start/process bypass | Work cannot outlive the lock; `finally` still releases lock before fence |
| 5 | Provenance in non-autoloaded option `rms_wizard_placeholder_provenance` | Store in `rms_wizard_state` | Same as canonical store; `array_replace_recursive` corrupts lists |
| 6 | Contact map stays chrome outside the loop, gated on `company_google_maps_url` | Invent a map layout | Not a `page_sections` layout; already renders "Map unavailable" |
| 7 | Services uses real `services-v1` + `cta-v2`; `services-page` kept but unreferenced | Make the demo ACF-driven | Existing layouts meet the spec; keep the file for rollback |
| 8 | Per-page Yoast excluded — no `seo` key passed to `build_page()` | Reuse `Yoast_Meta_Writer` | No approved spec; adding it would expand scope |

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

| File | Action | Description |
|---|---|---|
| `class-internal-page-blueprints.php` | Create | Type → template, layouts, `PAGE_*`, canonical policy |
| `class-step-internal-page-builder.php` | Create | Plan, one-page process, retry, skip-all, gates |
| `class-section-assembler.php` | Create | Shared Home/Landing assembly |
| `class-placeholder-provenance-store.php` | Create | Record, query, queue, `sync()` |
| `class-step-home-page-builder.php`, `class-step-landing-page-builder.php` | Modify | Delegate to assembler |
| `class-step-controller.php` | Modify | `REQUIRED_STEPS` + `DISPATCHABLE_STEPS` + dispatch |
| `class-state-manager.php` | Modify | `internal_pages` default |
| `class-step-generate-pages.php` | Modify | Blueprint template at shell create; no sections |
| `class-ai-content-harness.php` | Modify | Layer 2 ABOUT/SERVICE/CONTACT/BLOG; add PROJECTS/TESTIMONIALS |
| `wizard-init.php` | Modify | Step copy; `acf/save_post` → `sync()` |
| `templates/page-sections-loop.php` | Create | Shared loop partial |
| `pages/{about-us,services,contact-us,projects}.php` | Modify | Chrome + loop |
| `pages/testimonials.php` | Modify | Valid PHP + loop |
| `home.php` | Create | Index chrome + WP loop + empty state |
| `header.php` | Modify | Defer layout SCSS from stored rows |
| `src/ts/admin/wizard.ts`, `src/scss/admin/` | Modify | Step UI |

**CSS**: breadcrumb stays inline critical; `header.php` defers `section-{layout}` from stored rows. No new Vite entry.

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

Payload: `{ action: 'start'|'process', skip_all?, retry_failed?, overwrite[], convert_legacy[] }`. Empty `page_sections` plus body is `legacy`; unconfirmed → `skipped`. `acf/save_post`(20) `sync()` clears stale hashes. Placeholders never become canonical or client facts.

## Testing Strategy

No unit runner; custom harnesses.

| Layer | What | Approach |
|---|---|---|
| Unit-equivalent | Blueprint, plan, provenance, canonical copy vs first-write | New builder/provenance harnesses, fence-harness stubs |
| Contract | Template assignment, no post generation | Source assertions in those harnesses |
| Integration | Lock release, resume, retry, isolation, `running` re-dispatch | Extend landing-orchestrator fakes |
| Render | Loop order, unknown layout skip, empty index | `php -l` + manual audit |
| Types | Admin step flow | `tsc --noEmit` |

## Threat Matrix

N/A. REST step already gated by `manage_options`, nonce, and mutation fence.

## Migration / Rollout

No migration. Completed sites keep `rms_wizard_completed`; unlock + skip-all cover the new step. `force-chained` in ~8 slices under 400 lines.

**Rollback**: newest-first — drop the step, discard `state.internal_pages`, delete provenance, restore `pages/*.php`, delete `home.php`. Canonical store untouched.

## Open Questions

- [ ] None blocking. Archive sibling content-flow and landing-builder changes first; those deltas lack published baseline.
