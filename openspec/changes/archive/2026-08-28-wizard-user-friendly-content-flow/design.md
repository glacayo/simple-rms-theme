# Design: Wizard User-Friendly Content Flow

## Technical Approach

Extend the existing step-per-class architecture with three new step services: `Step_Generate_Pages`, `Step_Menu_Setup`, and `Step_Home_Page_Builder`. Replace the raw JSON `content-creation` step entirely. Keep the current `ai-generation` step as a configuration-only step (provider, model, API key) with no content generation. The actual AI calls move into utilities consumed by `Step_Generate_Pages` and `Step_Home_Page_Builder`, using Client Data as context. `Content_Builder` continues to create/update pages and save ACF flexible content; a new `Menu_Builder` handles WordPress menu creation and location assignment. Both page generation and menu setup perform destructive cleanup of existing data, gated by explicit UI confirmation.

## Architecture Decisions

| Decision | Option | Tradeoff | Choice |
|---|---|---|---|
| Step decomposition | 3 new step services (pages, menu, home builder) | More files, but clean separation and testability | **Chosen** |
| | Consolidate into 1 megastep | Fewer files, but harder to resume and test | Rejected |
| AI step role | Config-only `ai-generation` | Requires decoupling AI calls from step status; clearer flow | **Chosen** |
| | Keep AI as standalone content gen | Duplicates content generation between steps | Rejected |
| Home page update | Reuse `Content_Builder::build_page()` on existing Home ID | Ensures consistent page meta and section saving | **Chosen** |
| | Direct ACF `update_field` | Faster, but skips page meta consistency | Rejected |
| Menu candidate scope | Only wizard-generated pages | Simpler state dependency; avoids stale pages | **Chosen** |
| | Include all existing WordPress pages | More flexible, but mixes wizard and pre-existing data | Rejected |
| Destructive page cleanup | Delete non-selected pages on confirmation | Guarantees active page set matches wizard selection | **Chosen** |
| | Merge with existing pages | Safer, but leaves stale/orphan pages | Rejected |
| Destructive menu cleanup | Delete all existing menus + clear locations before wizard menus | Guarantees theme locations contain only wizard menus | **Chosen** |
| | Update/merge into existing menus | Safer, but leaves stale menu structures and locations | Rejected |

## Data Flow

```
User UI ──► REST /steps/{step}/run ──► Step_Controller::dispatch_step()
                                           │
    ┌─────────────────────────────────────┼─────────────────────────────────────┐
    ▼                                     ▼                                     ▼
Step_Generate_Pages            Step_Menu_Setup               Step_Home_Page_Builder
    │                                     │                                     │
    ├─► Confirm destructive warning         ├─► Confirm destructive warning         ├─► AI provider (client context)
    │       │                             │       │                             │       │
    ├─► AI provider (client context)      │       ├─► delete all existing menus   ▼
    │       │                             │       │       & clear locations       sections[]
    │       ▼                             │       │                             │
    │   page content                      │       ├─► Menu_Builder                  │
    │       │                             │       ├─► wp_create_nav_menu          │
    ▼       ▼                             │       ├─► wp_update_nav_menu_item     ▼
Content_Builder::build_page()       state.menu_config                       Content_Builder::build_page()
    │                                     │                                (Home post ID)
    ▼                                     ▼                                     ▼
state.generated_pages               primary/mobile menus                ACF page_sections
```

## File Changes

| File | Action | Description |
|---|---|---|
| `inc/wizard/class-step-generate-pages.php` | Create | Select pages, generate AI content with client context, assign Home/Blog roles, delete non-selected pages after confirmation. |
| `inc/wizard/class-step-menu-setup.php` | Create | Map generated pages into primary/mobile menu items; delete existing menus and clear locations after confirmation. |
| `inc/wizard/class-step-home-page-builder.php` | Create | Select ACF layouts, AI-fill sections, save to Home page via `Content_Builder::build_page()`. |
| `inc/wizard/class-menu-builder.php` | Create | Wrap `wp_create_nav_menu`, `wp_update_nav_menu_item`, `set_theme_mod`; also wrap menu/location deletion. |
| `inc/wizard/wizard-init.php` | Modify | Expand `$steps` to 7, add panel markup for new steps, update `$required`, add destructive confirmation dialogs. |
| `inc/wizard/class-step-controller.php` | Modify | Dispatch new slugs; make `ai-generation` config-only; update `complete()` required list; wire destructive confirmation flags. |
| `inc/wizard/class-content-builder.php` | Modify | Ensure `build_page()` supports section-only updates for the Home post; verify `prepare_image_fallbacks()` is public if needed. |
| `src/ts/admin/wizard.ts` | Modify | Update `steps` array; extend `collectPayload()` for new form shapes; implement confirm modal flow before destructive POSTs. |
| `src/scss/admin/wizard.scss` | Modify | Add styles for page picker, section checklist, menu assignment UI, and confirmation modal. |

## Interfaces / Contracts

```php
// REST payload examples (validated in step services)
// generate-pages
['pages' => [['slug'=>'about','generate'=>true,'role'=>'']], 'home_slug'=>'home', 'blog_slug'=>'blog', 'confirm_cleanup'=>true]
// menu-setup
['primary' => [1,2], 'mobile' => [1,2,3], 'confirm_cleanup'=>true]
// ia-generation
['provider'=>'ollama','api_key'=>'...','model'=>'llama3']
// home-page-builder
['sections' => ['slider','cta-v1','about-us','services-v1','gallery-grid','testimonials-v1','contact-info']]
```

## Testing Strategy

| Layer | What to Test | Approach |
|---|---|---|
| Unit | `Step_Generate_Pages` page creation + cleanup, `Menu_Builder` item insertion + deletion, `Step_Home_Page_Builder` section assembly | Mocked AI provider and state manager |
| Integration | REST endpoints for new step slugs; confirm destructive confirmation flag is required | `WP_REST_Server` test cases |
| E2E | Full 7-step UI progression including confirmation modals | Manual / browser automation |

## Migration / Rollout

No data migration required. Wizard state is option-based; existing completed wizards stay locked. Use `RMS_WIZARD_FORCE` to rerun in development. No breaking changes to stored ACF or posts.

## Open Questions

None.

## Delivery Slicing Recommendation

Because the estimate exceeds 400 lines and the strategy is `ask-always`, ask the user before finalizing the PR whether to split into chained PRs. Suggested slices:
1. **Backend services** — New PHP step classes (`Step_Generate_Pages`, `Step_Menu_Setup`, `Menu_Builder`, `Step_Home_Page_Builder`) and state updates.
2. **Controller wiring** — `Step_Controller` dispatch, `complete()` logic, `Rest_Controller` no-op (already generic), destructive confirmation wiring.
3. **Frontend UI** — `wizard-init.php` panels, `wizard.ts` payload collection and confirmation modals, `wizard.scss` styles.
