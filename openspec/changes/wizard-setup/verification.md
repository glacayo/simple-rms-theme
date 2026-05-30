# Verification: Wizard Setup

## Scope

This PR 5 verification pass covers Phase 5 tasks 5.1 through 5.6 for the `wizard-setup` change.

No PHPUnit, Composer, or project test harness is configured in this theme repository, so verification is split into:

- source-level acceptance evidence from the implemented wizard files,
- automated syntax/build checks that are feasible in this workspace,
- a concise manual runtime checklist for checks that require a live WordPress admin, database, plugins, and HTTP/plugin-install side effects.

## Automated Checks Run

| Check | Command | Result |
|---|---|---|
| Wizard PHP syntax | `php -l "inc/wizard/wizard-init.php" && php -l "inc/wizard/class-logger.php" && php -l "inc/wizard/class-state-manager.php" && php -l "inc/wizard/class-step-dependencies.php" && php -l "inc/wizard/class-step-acf-import.php" && php -l "inc/wizard/class-step-client-data.php" && php -l "inc/wizard/class-ai-adapter.php" && php -l "inc/wizard/class-content-builder.php" && php -l "inc/wizard/class-yoast-meta-writer.php" && php -l "inc/wizard/class-step-controller.php" && php -l "inc/wizard/class-rest-controller.php"` | Passed. No syntax errors detected in all wizard PHP files. |
| Theme asset build | `npm run build` | Passed. `tsc` and `vite build` completed successfully, including `dist/assets/admin/wizard.*.css` and `dist/assets/admin/wizard-js.*.js`. |

## Phase 5 Verification Matrix

### 5.1 Unauthorized access is blocked

**Source evidence**

- `inc/wizard/wizard-init.php` registers the admin page with the `manage_options` capability.
- `rms_wizard_render_admin_page()` also checks `current_user_can( 'manage_options' )` and calls `wp_die()` before rendering the UI.
- `Inc\Wizard\Rest_Controller::permission_callback()` delegates to `Step_Controller::can_access()`.
- `Step_Controller::execute_step()` and `Step_Controller::complete()` return `WP_Error` with status `403` when the current user lacks `manage_options`.

**Manual runtime checklist**

- Log in as a non-admin role without `manage_options`.
- Navigate to `/wp-admin/themes.php?page=rms-setup-wizard`.
- Expected: WordPress denies access and the wizard UI is not rendered.
- Attempt `GET /wp-json/rms-wizard/v1/state` and `POST /wp-json/rms-wizard/v1/steps/dependencies/run` with that user.
- Expected: REST access is denied by the permission callback.

### 5.2 Dependencies are checked/installed correctly via TGMPA

**Source evidence**

- `inc/tgmpa.php` marks ACF PRO, Classic Editor, and Yoast SEO as `required => true`.
- `Step_Dependencies::get_status()` reads TGMPA's required plugins and reports per-plugin `installed` and `active` states.
- `Step_Dependencies::install_missing()` installs missing required plugins, activates installed inactive plugins, logs per-plugin results, and marks the step complete only when all required plugins are active.

**Manual runtime checklist**

- In a staging WordPress admin, deactivate or remove one required dependency.
- Run the wizard Dependencies step with install enabled.
- Expected: the step result reports each required plugin with `installed` and `active` booleans.
- Expected: missing plugins are installed through TGMPA/WordPress upgrader and inactive installed plugins are activated.
- Expected: the dependency step is `complete` only when ACF PRO, Classic Editor, and Yoast SEO are active; otherwise it is `failed`.

### 5.3 ACF JSON import skip/log behavior on conflicts

**Source evidence**

- `Step_ACF_Import::import()` scans `acf-json/*.json`.
- The repository contains `acf-json/group_rms_theme_settings.json` and `acf-json/group_rms_page_sections.json`.
- Existing field groups are detected through `acf_get_field_group()` or `get_posts()` for `acf-field-group` posts.
- Conflicts are appended to the result as `reason => 'skipped: already exists'` and logged as `ACF field group skipped: already exists.`.
- The import continues after conflicts and only fails the step when invalid JSON/import failures are recorded.

**Manual runtime checklist**

- Ensure ACF is active.
- Pre-create or keep an existing field group with key `group_rms_theme_settings` or `group_rms_page_sections`.
- Run the ACF Import step.
- Expected: the conflicting group appears under `skipped` with `skipped: already exists`.
- Expected: a warning log entry is written and other JSON groups continue importing.

### 5.4 AI Adapter retry/backoff on simulated failures

**Source evidence**

- `AI_Adapter` calls providers via `wp_remote_request()`.
- `AI_Adapter::MAX_ATTEMPTS` is `3`.
- Failed attempts are logged as warnings.
- Backoff uses `sleep( 2 ** ( $attempt - 1 ) )` between attempts, producing 1s then 2s delays before the third/final attempt.
- After all attempts fail, the adapter logs an error and returns `success => false`, empty content, the final error, and `attempts => 3`.
- Successful responses cache generated content into an `rms_wizard_section_*` transient when `session_id` and `section_key` are provided.

**Manual runtime checklist**

- Use a staging-safe endpoint that consistently returns HTTP 500, or temporarily route the provider endpoint to a controlled failing endpoint.
- Run the AI Generation step with a valid prompt.
- Expected: the result returns `success: false`, `attempts: 3`, and a provider error.
- Expected: wizard logs contain three failed attempt warnings and one final retry-limit error.
- Then use an endpoint returning a 2xx response with supported content shape.
- Expected: the result returns generated content and writes the section transient when context includes `session_id` and `section_key`.

### 5.5 Content, ACF flexible fields, and Yoast meta are created

**Source evidence**

- `Content_Builder::build_pages()` creates or updates WordPress pages via `wp_insert_post()` / `wp_update_post()`.
- `Content_Builder::save_page_sections()` writes flexible-content data to `page_sections` through ACF `update_field()` when available, with post meta fallback.
- Empty image fields are filled with the bundled placeholder URL from `assets/images/wizard-placeholder.svg`.
- `Yoast_Meta_Writer::write()` stores `_yoast_wpseo_title` and `_yoast_wpseo_metadesc`.
- Created post IDs are persisted in `rms_wizard_state.created_posts`, and the step is complete only when all provided pages are created.

**Manual runtime checklist**

- Ensure ACF and Yoast SEO are active.
- Run the Content Creation step with sample Pages JSON:

```json
[
  {
    "title": "Wizard Verification Page",
    "slug": "wizard-verification-page",
    "content": "Generated verification content.",
    "sections": [
      {
        "acf_fc_layout": "hero",
        "hero_title": "Verified Hero",
        "hero_bg_image": ""
      }
    ],
    "seo": {
      "title": "Verified SEO Title",
      "description": "Verified SEO description."
    }
  }
]
```

- Expected: a page is created or updated with the supplied title/slug/content.
- Expected: `page_sections` is populated with the flexible-content payload and the empty image field receives the local placeholder URL.
- Expected: `_yoast_wpseo_title` and `_yoast_wpseo_metadesc` are set on the page.

### 5.6 State/lock storage and Developer Force Bypass

**Source evidence**

- `State_Manager` stores wizard state in `rms_wizard_state`, logs in `rms_wizard_log`, and completion lock in `rms_wizard_completed`.
- Named execution locks are stored under `rms_wizard_state.locks` with expiry metadata.
- `Step_Controller::complete()` only writes the completion flag after all required steps are complete.
- `Step_Controller::execute_step()` blocks step execution with HTTP status `423` when the wizard is completed.
- `State_Manager::is_completed()` returns `false` when `RMS_WIZARD_FORCE` is defined as `true`, allowing developer reruns despite the stored completion flag.

**Manual runtime checklist**

- Complete all wizard steps and run Complete Wizard.
- Expected: `rms_wizard_completed` is stored as true and the admin page shows the locked summary state.
- Attempt to run a step while locked.
- Expected: the REST response is blocked as already complete.
- Add `define( 'RMS_WIZARD_FORCE', true );` in `wp-config.php` on a local/staging site.
- Expected: the wizard renders normally and step execution is allowed despite `rms_wizard_completed` being true.

## Result

Phase 5 is backed by source evidence, successful static/build checks, and manual runtime checklists for WordPress-dependent behaviors that cannot be safely executed without a live staging admin and plugin/database side effects.
