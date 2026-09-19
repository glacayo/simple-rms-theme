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
- [ ] **I125-02 — Render and style the breadcrumb hero.** Replace the placeholder with scoped CSS variables, type-specific classes/overlay behavior, text-color styling, slim regression coverage, live verification, review, chain publication/merge, and synchronized `main`.

## Planned Chain

| Slice | Responsibility | Expected files | Budget |
|---|---|---|---:|
| 1 — Schema/helpers | ACF fields, strict style resolution, JSON parity, focused harness | `inc/acf-theme-options.php`, `acf-json/group_rms_theme_settings.json`, `tests/breadcrumb-theme-settings-schema-harness.php` | 399 / 400 |
| 2 — Render/styles | Full template, breadcrumb SCSS, render/slim regression harness, ODD evidence | `templates/breadcrumb.php`, `src/scss/templates/breadcrumb.scss`, `tests/breadcrumb-theme-settings-render-harness.php`, this file | 239 / 400 |

## Evidence
- Baseline: `main` at `52d332c236935ac897be6bfc3950a06afc5756c2`.
- Tracker branch: `feat/issue-125-breadcrumb-settings` at `91fd463f9c7da27f0b4529cfb55a6943159274ff`.
- Slice 1: `f23a38c7122051b2f0a17717af0e1738ee78c662`, 399 lines across three paths; focused harness 80/80 and relevant regressions PASS; native lineage `review-30d0bf212232f019` approved and acknowledged at revision `sha256:ed544ba0be17792cb5160b11a90e237e343b1388c0f1232cdd980fb07ff4b992`.
- Slice 2 pre-review: render harness 28/28, schema harness 80/80, internal-page template/render and landing render harnesses PASS; PHP lint, diff check, production build, and byte-identical slim template PASS.
