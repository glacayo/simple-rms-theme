# Verification Checklist

Use as a staged checklist. The agent verifies installation through IA Generation, then stops. The human completes Home Builder, Landing Builder, and Wizard Complete. Every checked item needs an evidence reference.

## Theme and plugins

- [ ] Simple RMS Theme is the active theme (Appearance → Themes shows "Active").
- [ ] Theme directory contains built assets under `dist/` and the runtime manifest at `dist/.vite/manifest.json`.
- [ ] ACF Pro plugin active.
- [ ] ACF Pro license visibly reports active/valid after human activation; evidence excludes the license key.
- [ ] `acf-json` field groups imported (Custom Fields shows the Theme Settings + Page Sections groups).
- [ ] Classic Editor active.
- [ ] Yoast SEO active.
- [ ] Contact Form 7 active.
- [ ] (Optional) WP Fastest Cache active.
- [ ] (Optional) Wordfence Security active.

## Agent-assisted wizard steps (from `GET /rms-wizard/v1/state`)

- [ ] `dependencies` step status = `complete`; every required plugin `active: true`.
- [ ] `acf-import` step status = `complete`.
- [ ] `client-data` step status = `complete`; fact ledger matches saved Theme Settings values (spot-check `company_name`, phones, address, services).
- [ ] `generate-pages` step status = `complete`; all selected pages exist with correct URLs; Home and Blog roles assigned if selected.
- [ ] `menu-setup` step status = `complete`; `primary_menu_id` set and location `primary` assigned; `mobile_menu_id` set (or reuses primary).
- [ ] `ia-generation` step status = `complete`; encrypted credential status present (key never exposed).

## Human-only continuation

The agent must not execute these steps under this skill. The human may record evidence afterward:

- [ ] `home-page-builder` step status = `complete`; Home page flexible content has the human-approved sections with item counts.
- [ ] `landing-page-builder` step status = `complete` (or skipped with zero landings if the human chose to skip).
- [ ] Wizard completion lock set (`rms_wizard_completed`); site read-only.

## Generated pages and menu

- [ ] Each generated page is published and reachable (HTTP 200).
- [ ] Home page renders the selected sections in order.
- [ ] Primary menu contains exactly the human-selected pages.
- [ ] Mobile menu matches the selection (or equals primary).

## Home Builder

- [ ] Home page sections match the approved layout list and item counts.
- [ ] Services mentioned in generated copy exist in `company_services` (no invented services).
- [ ] Section headings are concrete and scannable; no repeated generic praise.

## Landing Builder — SEO vs Ads

- [ ] Each SEO landing: `rms_landing_type` = `seo`; indexable; appears in menu-eligible pool; included in sitemaps.
- [ ] Each Ads landing: `rms_landing_type` = `ads`; `noindex,nofollow` robots header present; NOT in any menu; excluded from core WP sitemap and Yoast sitemap.
- [ ] Each landing has a non-empty primary keyword and ≤10 subkeywords.
- [ ] Only Hero and SEO Content sections received keywords; reusable sections stay neutral/canonical.
- [ ] Canonical-replace decision honored (canonical store overwritten only if the human approved it).

## Front-end UX

- [ ] Accordion/FAQ sections (if present) expand/collapse via keyboard and click.
- [ ] Mobile menu opens and navigates.
- [ ] No browser console errors on home, a generated page, and a landing.
- [ ] Images have explicit width/height (CLS check); hero is not lazy-loaded.

## Logs and errors

- [ ] Wizard log (`wp option get rms_wizard_log --format=json`) has no `error` entries for completed steps.
- [ ] No PHP fatal/error in `wp-content/debug.log` (if `WP_DEBUG` is on).
- [ ] REST calls during the run did not return 4xx/5xx (or all were resolved).

## Secrets hygiene

- [ ] No API key, password, WP nonce, or credential appears in any screenshot, report, or memory/artifact.
- [ ] AI credential store shows masked status only.
