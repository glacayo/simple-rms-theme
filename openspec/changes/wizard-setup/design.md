# Design: Wizard Setup

## Technical Approach

Add a theme-owned admin wizard as a hybrid module: a procedural entry point (`inc/wizard/wizard-init.php`) loaded by `functions.php`, with internal logic in namespaced `Inc\Wizard\*` classes. The wizard orchestrates plugin dependencies, ACF JSON import, client data collection, AI content generation, page/media creation, Yoast metadata writes, autosave/resume, and a completion lock.

## Architecture Decisions

| Decision | Choice | Alternatives | Rationale |
|---|---|---|---|
| Module style | Hybrid: procedural loader + namespaced classes | Pure procedural; full Composer/PSR-4 | Matches existing theme patterns while keeping internals testable and organized |
| Autoloading | `spl_autoload_register()` in `wizard-init.php` | Composer autoload | No new tooling; single-line registration |
| State storage | `wp_options` (`rms_wizard_state`, `rms_wizard_log`, `rms_wizard_completed`) | Custom DB table; CPT | Simple, atomic, no schema changes |
| Section cache | Transients (`rms_wizard_section_{key}`) | Postmeta on scratch post | Auto-expire; designed for temp data |
| AI adapter | Custom HTTP layer via `wp_remote_request()` | Wait for native WP AI API | Native API does not exist; we control retries/backoff |
| Yoast handling | Make `required: true` in TGMPA | Graceful degradation without Yoast | Simplifies spec; PRD assumes Yoast always present |
| Admin actions | REST endpoints | `admin-post.php` | Modern WordPress pattern; cleaner JSON responses for step actions |
| Admin assets | New Vite entries (`src/ts/admin/wizard.ts`, `src/scss/admin/wizard.scss`) | Inline admin styles | Keeps build pipeline consistent; HMR works in dev |
| Content generation | Synchronous chunked per-section | WP-Cron background job | PRD wants real-time progress; cron is unreliable for UI feedback |
| Placeholder fallback | Bundle a local theme placeholder image | Depend only on `placehold.co` | Avoids broken media when the external placeholder service is unavailable |

## Data Flow

```
Admin UI (WP Admin)
    ↓ REST request
Wizard_Controller (Inc\Wizard\Controller)
    ↓ reads/writes
State_Manager  ──→  wp_option: rms_wizard_state
    ↓
Step handlers (Dependencies, ACF_Import, Client_Data, AI_Generation, Content_Creation)
    ↓
AI_Adapter ──→ wp_remote_request() ──→ Provider APIs
    ↓
Content_Builder ──→ wp_insert_post(), update_field(), add_post_meta()
    ↓
Yoast_Meta_Writer ──→ _yoast_wpseo_title / _yoast_wpseo_metadesc
```

## File Changes

| File | Action | Description |
|---|---|---|
| `functions.php` | Modify | Add `require_once inc/wizard/wizard-init.php` |
| `inc/tgmpa.php` | Modify | Set Yoast SEO `required: true` |
| `inc/wizard/wizard-init.php` | Create | Procedural loader: autoloader, admin menu, assets enqueue |
| `inc/wizard/class-state-manager.php` | Create | Read/write `rms_wizard_state`, `rms_wizard_log`, lock flag |
| `inc/wizard/class-step-controller.php` | Create | Route step actions, enforce capability, handle resume |
| `inc/wizard/class-rest-controller.php` | Create | Register REST routes for wizard step actions and state reads |
| `inc/wizard/class-step-dependencies.php` | Create | Check/install required plugins via TGMPA wrappers |
| `inc/wizard/class-step-acf-import.php` | Create | Import `acf-json/` files; skip existing groups; log conflicts |
| `inc/wizard/class-step-client-data.php` | Create | Save client info to ACF theme options (`rms-theme-settings`) |
| `inc/wizard/class-ai-adapter.php` | Create | Provider abstraction with retry/backoff (max 3) |
| `inc/wizard/class-content-builder.php` | Create | Create pages, populate flexible content, upload media or placeholders |
| `inc/wizard/class-yoast-meta-writer.php` | Create | Write Yoast post meta on created pages |
| `inc/wizard/class-logger.php` | Create | Append structured entries to `rms_wizard_log` |
| `src/ts/admin/wizard.ts` | Create | Step navigation, AJAX calls, progress UI, retry per section |
| `src/scss/admin/wizard.scss` | Create | Wizard admin styles |
| `assets/images/wizard-placeholder.svg` | Create | Local fallback placeholder when external placeholder service fails |
| `vite.config.ts` | Modify | Add `admin/wizard` entry for build |

## Interfaces / Contracts

```php
// AI adapter contract
interface Inc\Wizard\AI_Adapter_Interface {
    public function generate( string $prompt, array $context ): array;
    // returns ['success'=>bool,'content'=>string,'error'=>string|null]
}

// State shape (stored in wp_option)
$rms_wizard_state = [
    'current_step' => 'dependencies', // string slug
    'step_status'  => [
        'dependencies' => 'complete', // pending | running | complete | failed
        // ...
    ],
    'client_data'  => [ /* sanitized inputs */ ],
    'generated'    => [ 'section_key' => 'transient_key' ],
    'created_posts'=> [ /* post_id list */ ],
    'logs'         => 'rms_wizard_log', // separate option key
];
```

## Testing Strategy

| Layer | What to Test | Approach |
|---|---|---|
| Unit | `State_Manager`, `AI_Adapter` retry logic, `Logger` formatting | PHPUnit or WP_Mock if available; otherwise manual test harness |
| Integration | Step handlers in isolation (ACF import, client data save, content creation) | Run on staging site with real ACF + Yoast |
| E2E | Full wizard flow from fresh install to locked completion | Manual admin walkthrough; verify posts, meta, options, lock |

## Migration / Rollout

No data migration needed. On first run, the wizard creates fresh content. Rollback: revert theme files, manually delete wizard-created posts/media, remove `rms_wizard_*` options and `rms_wizard_section_*` transients.

## Open Questions

None. The wizard will bundle a local placeholder fallback and use REST endpoints for admin actions.
