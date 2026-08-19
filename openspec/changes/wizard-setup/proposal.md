# Proposal: Wizard Setup

## Intent

Build a theme-owned admin wizard that turns a fresh install into a configured contractor site with required plugins, ACF data, AI content, Yoast metadata, autosave/resume, and lock.

## Scope

### In Scope
- Add the wizard inside the theme, not a companion plugin.
- Use a hybrid module: procedural loader plus internal namespaced classes.
- Persist state, progress, logs, and locks in `wp_options`; cache generated sections in transients.
- Provide admin UI/assets for guided steps, retry, resume, and summary.
- Build custom AI adapters via the WordPress HTTP API.
- Install/activate dependencies via TGMPA and make Yoast SEO required.
- Import ACF JSON, create content, handle media/placeholders, and write Yoast metadata.

### Out of Scope
- Companion plugin.
- Native WordPress AI APIs.
- Global undo of all wizard-created content.
- Editing generated content inside the wizard.
- CRM/marketing platform integrations.

## Capabilities

### New Capabilities
- `wizard-setup`: Guided flow for dependencies, ACF import, client data, AI generation, content creation, autosave/resume, and lock.

### Modified Capabilities
- None; no existing OpenSpec capabilities are present.

## Approach

Add `inc/wizard/wizard-init.php` as the procedural entry loaded by `functions.php`. Internals use `Inc\Wizard\*` classes for state, steps, admin screens, AI adapters, content/media generation, Yoast metadata, dependencies, and logging. Store state in `wp_options`, cache sections in transients, and call providers through `wp_remote_request()`.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `functions.php` | Modified | Load wizard module. |
| `inc/wizard/` | New | Core, steps, adapters, admin handlers. |
| `inc/tgmpa.php` | Modified | Change Yoast SEO to required. |
| `acf-json/` | Modified | Source files for import step. |
| `src/` | New/Modified | Admin TS/SCSS assets. |
| WP data | Modified | State and generated content. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| AI failures | High | Retries, backoff, per-section resume. |
| Large option payload | Med | Split per-step if needed. |
| ACF conflicts | Med | Skip/update with reporting. |
| No global rollback | Med | Log entities and cleanup path. |

## Rollback Plan

Revert theme files. Manually delete wizard-created content/media, remove wizard options/transients, and restore Yoast required flag if needed.

## Dependencies

- `manage_options` admin access.
- TGMPA, ACF PRO, Classic Editor, Yoast SEO.
- Provider API key and internet.

## Success Criteria

- [ ] Wizard appears in WordPress admin and resumes saved progress.
- [ ] Required dependencies install/activate, including Yoast.
- [ ] AI adapters generate content and handle retryable failures.
- [ ] Content, ACF data, media/placeholders, and Yoast metadata are created.
- [ ] Completed wizard is locked from normal re-execution.
