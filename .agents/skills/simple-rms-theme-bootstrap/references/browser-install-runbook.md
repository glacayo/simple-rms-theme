# Browser Install Runbook

Tool-agnostic. Use whichever browser automation surface the developer selected and installed (Playwright, Chrome DevTools MCP, a browser MCP server, or equivalent). The descriptions below are capabilities, not API syntax — map each to your tool's primitives.

Canonical source: `https://github.com/glacayo/simple-rms-theme`. Release index: `https://github.com/glacayo/simple-rms-theme/releases`. Use the explicit release URL/tag approved by the human; never silently substitute another release.

## Capability map

| Capability | Why |
|---|---|
| Navigate to URL | Reach WP Admin pages and the frontend. |
| Find element by text/selector/css/xpath | Locate buttons, fields, rows, status labels. |
| Fill input / select option / click | Drive forms. |
| Read text/attribute from element | Confirm status, values, URLs. |
| Screenshot | Evidence at every checkpoint. |
| Read current URL | Confirm navigation/result. |
| Wait for element/network idle | Avoid racing WP Admin JS hydration. |
| Read console errors | Detect JS/REST failures. |
| Upload file | Theme/plugin ZIP install (if not using WP-CLI). |

If your tool lacks any capability above, stop and ask the developer to choose a tool that has it, or fall back to a manual checkpoint (human performs the action and returns a screenshot/URL).

## Evidence rule

Every claimed action MUST be backed by at least one of: screenshot, DOM snippet, or current URL. State in the report which evidence was captured and where it is stored. Never claim success without evidence.

## Checkpoints

### CP-1 — WordPress root detected
- Evidence: path listing showing `wp-content/themes/` exists.
- Confirm `wp-content/themes/simple-rms-theme` does not already contain uncommitted/local changes (if it exists).

### CP-2 — Theme ZIP installed
- Action: upload ZIP via Appearance → Themes → Add New → Upload, or unzip into `wp-content/themes/`.
- Evidence: screenshot of the theme card present in Appearance → Themes with "Activate" available.
- Verify the installed directory contains `dist/` (runtime assets). If missing, STOP — the install is not runnable; see Decision Gate G1.

### CP-3 — Theme activated
- Action: click Activate.
- Evidence: screenshot showing "Simple RMS Theme" as the active theme (Appearance → Themes shows "Active").
- Verify URL is now the site front-end and the theme header renders.

### CP-4 — Wizard dependencies step
- Navigate to Appearance → Setup Wizard (`wp-admin/themes.php?page=rms-setup-wizard`).
- Run the `dependencies` step (button `data-wizard-run-step="dependencies"`, or REST `POST /rms-wizard/v1/steps/dependencies/run`).
- Evidence: per-plugin status from `GET /rms-wizard/v1/state` showing every required plugin `installed: true, active: true`.
- Required: ACF Pro, Classic Editor, Yoast SEO, Contact Form 7.
- If ACF Pro fails: use the bundled `inc/plugins/advanced-custom-fields-pro.zip` or a developer-supplied licensed ZIP. Never download from unofficial sources.

### CP-5 — ACF Pro license gate (human-only)
- The agent MAY install and activate the ACF Pro plugin.
- Navigate to the official ACF license/updates screen in WP Admin. Do not focus, read, capture, or fill the license field.
- STOP and ask the human to enter and activate the license. Wait; do not continue in another wizard tab.
- After the human says it is done, verify visible DOM/status text reports the license as active/valid and capture evidence that excludes the key.
- If status is inactive, invalid, expired, or cannot be verified, report `BLOCKED: ACF_LICENSE_NOT_ACTIVE` and wait. Never bypass this gate.

### CP-6 — ACF import step
- Run `acf-import`.
- Evidence: state shows `acf-import` complete; `acf-json/group_rms_theme_settings.json` fields render in the client-data form (the form fieldsets appear).

### CP-7 — Client data step
- Before running, complete `references/client-intake.md` with the human and get explicit fact-sheet confirmation.
- Fill the client-data form fields (or REST `POST /rms-wizard/v1/steps/client-data/run` with the confirmed payload).
- Evidence: state shows `client-data` complete; screenshot of saved values.

### CP-8 — Generate pages step
- Confirm page list + Home/Blog roles with the human.
- The human MUST check the destructive-action confirmation checkbox (`data-wizard-destructive-confirm="generate-pages"`).
- Evidence: state shows `generate-pages` complete; list of created page URLs.

### CP-9 — Menu setup step
- Confirm primary + mobile menu selections with the human.
- Human MUST check the destructive confirmation checkbox.
- Evidence: state shows `menu-setup` complete; `primary_menu_id` and `mobile_menu_id` present in state; menu locations `primary` and `mobile` assigned.

### CP-10 — IA generation step
- The HUMAN enters the AI provider, API key, and tests/loads models in WP Admin. The agent never sees the key.
- Evidence: state shows `ia-generation` complete; `AI_Credential_Store` status shows a saved encrypted key (masked status, never the key itself).

## Agent assistance boundary

STOP after CP-10. Return the confirmed fact ledger, evidence, current wizard state, and the human-only continuation below. Do not click, submit, or automate CP-11 through CP-13 unless the human makes a new explicit request that changes this boundary.

### CP-11 — Home page builder step (human-only)
- Confirm section layouts + item counts with the human.
- Evidence: state shows `home-page-builder` complete; Home page flexible content has the selected sections.

### CP-12 — Landing page builder step (human-only)
- For each landing confirm type (SEO/Ads), primary keyword, subkeywords (max 10), sections, and canonical-replace decision.
- Evidence: state shows `landing-page-builder` complete; landing pages exist with correct `rms_landing_type` meta.

### CP-13 — Wizard complete (human-only)
- Click "Complete wizard" (`data-wizard-complete`) or REST `POST /rms-wizard/v1/complete`.
- Evidence: state shows completion lock set; site is read-only.

## Failure handling

- On any REST 4xx/5xx, capture the response body and the wizard log (`wp option get rms_wizard_log --format=json` via WP-CLI if available).
- If a step fails, use the step "Retry" button or re-run the REST call after correcting input. Do not skip ahead without resolving.
- Capture browser console errors at every checkpoint.
