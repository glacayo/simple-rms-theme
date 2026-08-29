# Proposal: Wizard Internal Page Builder

## Intent

Wizard shells get no `_wp_page_template` or `page_sections`. Only Home and Landing have builders, so the six internal page types ship as bare `page.php` or demo markup. Add ONE blueprint-driven builder, not six.

## Scope

### In Scope
- Optional 9th step `internal-page-builder`, unlockable on completed sites
- Fixed blueprint per type: template, layouts, harness `PAGE_*`, canonical policy
- Builds only `state.generated_pages`; resumable per page; failures isolated
- Convert five internal templates to `page_sections` loops, repair `pages/testimonials.php`, retire `services-page`, add `home.php`
- Preserve user edits; regeneration and legacy conversion need confirmation
- Public placeholders for missing facts, with provenance and replacement

### Out of Scope
- Post generation, free-form blueprints, Landing/Home redesign, menu rebuild, config edits

## Capabilities

### New Capabilities
- `wizard-internal-page-builder`: step gating, blueprint registry, resumable run
- `wizard-internal-page-templates`: render contract, template assignment, Testimonials repair
- `wizard-placeholder-provenance`: placeholder tracking, replacement, no labeling

### Modified Capabilities
- `wizard-ai-content-harness` (published): Layer 2 for `PAGE_ABOUT`/`PAGE_SERVICE`/`PAGE_CONTACT`/`PAGE_BLOG`, new Projects and Testimonials types, Home fallback unchanged
- `wizard-page-generation` (delta-only): assign template at shell creation
- `wizard-canonical-sections` (delta-only): internal pages copy rows, `replace()` stays explicit
- `wizard-controlled-unlock` (delta-only): new step must not invalidate completed sites

## Approach

Reuse `Content_Builder`, `AI_Content_Harness`, `Canonical_Section_Store`, `Wizard_Mutation_Fence`; extract a shared section-assembly helper; never clone `Step_Landing_Page_Builder` (~2294 lines). Chained PRs under 400 lines.

## Affected Areas

| Area | Impact | Description |
|---|---|---|
| `inc/wizard/class-step-internal-page-builder.php`, `class-internal-page-blueprints.php` | New | Step, resumable run, registry |
| `class-step-controller.php`, `class-state-manager.php`, `class-step-generate-pages.php` | Modified | Registration, `internal_pages`, template assignment |
| `class-ai-content-harness.php` | Modified | Layer 2 page types |
| `pages/{about-us,services,contact-us,projects,testimonials}.php`, `home.php` | Modified, New | Flexible loops, posts-index chrome |
| `header.php`, `src/ts/admin/wizard.ts`, `src/scss/`, `acf-json/` | Modified | Assets, step UI, styles, fields |

Vite entry points: none added or removed.

## Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Unmarked public placeholders deceive visitors, get indexed, go stale (accepted against architect advice) | High | No labeling or blocking; track per-field provenance and replacement queue |
| Sections written while template stays hardcoded, so content stays invisible | High | Convert each template in its page type's slice |
| New step invalidates completed sites | Medium | Optional, unlockable, skip-all; completion recomputed |
| Reruns clobber post-wizard ACF edits | Medium | Mutation fence, skip-unchanged, confirm before overwrite |
| Siblings share controller, state, canonical, fence and delta-only capabilities | Medium | Additive edits only, stack deltas there, re-verify before archive |

## Rollback Plan

Revert chained PRs newest-first; each reverts alone. Drop the step from `REQUIRED_STEPS`, discard `state.internal_pages`, restore `pages/*.php`, delete `home.php`. Stored rows are inert once templates revert; canonical store untouched.

## Dependencies

- ACF Pro flexible content; existing harness, canonical store, fence
- Configured AI provider for Layer 2
- `wizard-landing-page-builder` verification task 4.1 still open

## Success Criteria

- Five internal types render `page_sections`, none fall back to `page.php`
- `pages/testimonials.php` passes `php -l` and renders rows
- Posts index renders `home.php` chrome, zero posts generated
- Completed sites stay complete and can skip-all
- Rerun over edited sections changes nothing without confirmation
- Placeholders queryable by provenance and clearable
- Each PR under 400 lines; `tsc --noEmit` and `php -l` clean
