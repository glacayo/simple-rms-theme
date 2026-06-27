# Debug and Operations

This page lists the switches, logs, and checks maintainers use while developing or debugging the theme.

## Debug flags

Add these constants in `wp-config.php` when needed.

| Constant | Default | Use |
|----------|---------|-----|
| `WP_DEBUG` | Usually `false` | Enables development-only detail. The AI quality report is only available when `WP_DEBUG === true`. |
| `RMS_WIZARD_FORCE` | Undefined / false | Allows rerunning a completed wizard locally. |
| `WIZARD_REVIEW_ENABLED` | Enabled when undefined | Enables/disables the AI content review loop. |

## Rerun the wizard locally

Use this only in local/dev environments:

```php
define( 'RMS_WIZARD_FORCE', true );
```

## Disable the AI review loop

Use this as a rollback/diagnostic switch if the reviewer causes provider issues, timeout pressure, or unexpected rewrites:

```php
define( 'WIZARD_REVIEW_ENABLED', false );
```

The parser treats these as disabled values:

- `false`
- `0`
- `'0'`
- `'false'`
- `'off'`
- `'no'`

Undefined means enabled by default.

## Wizard state and logs

The wizard stores state and logs in `wp_options`.

| Option | Purpose |
|--------|---------|
| `rms_wizard_state` | Current step, generated references, AI config metadata, selected sections, locks. |
| `rms_wizard_log` | Last 500 structured wizard log entries. |
| `rms_wizard_completed` | Completion lock. |

The visible log section in WP Admin is titled **Recent log entries**. It appears near the bottom of the wizard panel, after the global actions.

## Inspect logs with WP-CLI

```bash
wp option get rms_wizard_log --format=json
```

Production AI review log entries should stay bounded to:

- `section`
- `status`
- `iterations`

When `WP_DEBUG === true`, the log context may include the development quality `report`.

## Clear logs with WP-CLI

```bash
wp option update rms_wizard_log '[]' --format=json
```

## Manual verification checklist

Use this after AI prompt/reviewer changes:

- [ ] Run Home generation with valid AI credentials.
- [ ] Confirm generated sections save successfully.
- [ ] Confirm services mentioned are present in `company_services`.
- [ ] Confirm section headings are concrete and scannable.
- [ ] Confirm repeated praise/adjectives are reduced.
- [ ] Confirm each section has a distinct angle.
- [ ] Confirm `WIZARD_REVIEW_ENABLED=false` bypasses the reviewer and generation still works.
- [ ] Compare `WP_DEBUG=false` logs against `WP_DEBUG=true` logs.

## Build and deployment notes

This project uses Node/Vite at build time only.

```bash
npm install
npm run build
```

For cPanel-style hosting, deploy the theme with compiled `dist/` assets. Do not require Node.js on production hosting.

## Available automated checks

There is no PHPUnit/E2E harness yet. Current checks are:

```bash
php -l inc/wizard/class-ai-content-harness.php
php -l inc/wizard/class-ai-content-reviewer.php
php -l inc/wizard/class-step-home-page-builder.php
npm run build
```

Run `npm run build` when assets, TypeScript, SCSS, or Vite config change.
