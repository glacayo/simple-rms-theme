# Issue #125 — Breadcrumb Theme Settings

## Goal
Replace the hardcoded breadcrumb hero placeholder with operator-controlled image, solid, or gradient backgrounds and a sanitized text color, without changing the slim landing breadcrumb.

## Constraints
- No third-party placeholder fallback.
- Global Theme Settings only; no per-page override.
- Image mode shows the dark overlay only for a usable image URL.
- Colors and gradient direction must be allowlisted/sanitized before CSS output.
- `templates/breadcrumb-slim.php` remains byte-unchanged and visually compact.
- Keep each reviewed slice at or below 400 changed lines.

## Tasks
- [x] **I125-01 — Define schema and sanitization contract.** Added the Breadcrumb Theme Settings fields, strict style resolver/sanitizers, JSON parity, and 80 focused assertions; independently verified and approved through native review.
- [x] **I125-02 — Render and style the breadcrumb hero.** Replaced the placeholder with scoped CSS variables, type-specific classes/overlay behavior, text-color styling, and slim regression coverage; completed live verification, native review, chain merge, main synchronization, and post-merge checks.

## Planned Chain

| Slice | Responsibility | Expected files | Budget |
|---|---|---|---:|
| 1 — Schema/helpers | ACF fields, strict style resolution, JSON parity, focused harness | `inc/acf-theme-options.php`, `acf-json/group_rms_theme_settings.json`, `tests/breadcrumb-theme-settings-schema-harness.php` | 399 / 400 |
| 2 — Render/styles | Full template, breadcrumb SCSS, render/slim regression harness, ODD evidence | `templates/breadcrumb.php`, `src/scss/templates/breadcrumb.scss`, `tests/breadcrumb-theme-settings-render-harness.php`, this file | 242 / 400 |

## Evidence
- Baseline: `main` at `52d332c236935ac897be6bfc3950a06afc5756c2`.
- Tracker branch: `feat/issue-125-breadcrumb-settings` at `91fd463f9c7da27f0b4529cfb55a6943159274ff`.
- Slice 1: `f23a38c7122051b2f0a17717af0e1738ee78c662`, 399 lines across three paths; focused harness 80/80 and relevant regressions PASS; native lineage `review-30d0bf212232f019` approved and acknowledged at revision `sha256:ed544ba0be17792cb5160b11a90e237e343b1388c0f1232cdd980fb07ff4b992`.
- Slice 2: `5e91bb3cfdf8fffda5a6a3ce151f17bea6654071`; render harness 28/28, schema harness 80/80, internal-page template/render and landing render harnesses PASS; PHP lint, diff check, production build, byte-identical slim template, and independent contrast checks PASS.
- Slice 2 native review: lineage `review-af15d1b6a83b8706` approved and acknowledged at revision `sha256:abae91b6b6644299f7868934362acf1cecaa2bd3135eb4999d987ff3b507b158`.
- Live verification: gradient mode rendered on the private Contact page at desktop/mobile with HTTP 200, valid TLS, correct scoped variables, no overlay/placeholder; empty image mode emitted neither image URL nor overlay. Database restored from rollback and cleanup HTTP 200. Evidence: `/home/glacayom/backups/simple-rms-theme/issue-125-live-20260919T054334Z`.
- Delivery: child PRs #140 and #141 merged into tracker #139; tracker merged to `main` as `3a67ba43d1e4bdb8c8935b1ba924a0d33c010835`; issue #125 closed.
- Post-merge: schema 80/80, render 28/28, production build, ref parity, clean tree, artifact scan, and database rollback state PASS.
