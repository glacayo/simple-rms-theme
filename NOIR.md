# NOIR.md

## Instruction
> **This document contains a daily task schedule.**
> Each day has a date and a task block assigned to it.
> **The AI must ONLY execute the task whose date matches today.**
> If today does not match any task date, do nothing and inform the user that there is no task assigned for today.

## Task Schedule
| Date | Task |
|------|------|
| 2026-04-23 | Replace all icon placeholders with inline SVGs + build Thank You page |

> Today's task for **2026-04-23** is described below.

---

## Project Context
- Project: `simple-rms-theme`
- Stack: WordPress classic theme + Vite + SCSS + TypeScript
- Templates live in: `templates/`
- Page templates live in: `pages/`
- **Reference implementation for SVG icons:** `templates/contact-info.php` and `templates/header-three.php` — these files already use correct inline SVGs. Use them as the style and size reference for all other icons.

---

## Main Task — 2026-04-23
Two tasks for today:
1. **Replace all icon placeholders with real inline SVGs** across all templates
2. **Build the Thank You page** (`pages/thank-you.php`)

---

## TASK 1 — SVG Icons

### Rules
- Use **inline SVG only** — no icon fonts, no FontAwesome, no img tags
- All icons must use `width="20" height="20"` (or match the size already used in `contact-info.php` and `header-three.php`)
- Use `aria-hidden="true"` on decorative icons
- Use `fill="currentColor"` so icons inherit text color
- Source icons from the **Heroicons** set (https://heroicons.com) or any clean open-source SVG set — solid/filled style preferred
- Do NOT add new SCSS for icons unless absolutely needed — inline SVG with `fill="currentColor"` inherits color from the parent

---

### Icon Fixes by File

#### `templates/services-v1.php`
- **Problem:** 6 service feature items contain the literal text `ICON` instead of an SVG
- **Fix:** Replace each `ICON` text with an appropriate inline SVG
- **Suggested icons per item:**
  - Roof Installation → `home` or `building`
  - Roof Repair → `wrench`
  - Emergency Services → `bolt` or `exclamation-triangle`
  - Gutter Installation → `adjustments`
  - Storm Damage → `cloud` or `lightning-bolt`
  - Inspection → `magnifying-glass` or `eye`

#### `templates/header-one.php`
- **Problem:** Social media links use text labels `FB`, `TW`, `IG`, `LK` instead of SVG icons
- **Fix:** Replace each text label with the correct inline SVG
  - `FB` → Facebook SVG icon
  - `TW` → X (Twitter) SVG icon
  - `IG` → Instagram SVG icon
  - `LK` → LinkedIn SVG icon

#### `templates/header-two.php`
- **Problem:** Social media links use text labels `FB`, `TW`, `IG`, `LK`
- **Fix:** Same as header-one — replace with inline SVGs for Facebook, X, Instagram, LinkedIn

#### `templates/header-three.php`
- **Problem:** Mobile menu social links use text labels `FB`, `TW`, `IG`, `LK` (desktop already has SVGs)
- **Fix:** Replace mobile social text labels with inline SVGs — match the desktop SVGs already in the same file

#### `templates/footer-v2.php`
- **Problem:** Social media links use text labels `FB`, `TW`, `IG`, `LK`
- **Fix:** Replace with inline SVGs for Facebook, X, Instagram, LinkedIn

#### `templates/portfolio-v1.php`
- **Problem:** Overlay hover uses `⊕` Unicode character as the view/zoom icon
- **Fix:** Replace `⊕` with an inline SVG — suggested: `plus-circle` or `magnifying-glass-plus`

#### `templates/portfolio-v2.php`
- **Problem:** Overlay hover uses `⊕` Unicode character
- **Fix:** Same as portfolio-v1 — replace with inline SVG

#### `templates/portfolio-v3.php`
- **Problem:** Overlay hover uses `🔍` emoji as the zoom icon
- **Fix:** Replace emoji with inline SVG — suggested: `magnifying-glass`

#### `templates/testimonials-v1.php`
- **Problem:** Star ratings use `★★★★★` Unicode characters
- **Fix:** Replace with 5 inline SVG star icons (`star` solid/filled)

#### `templates/testimonials-v2.php`
- **Problem:** Star ratings use `★★★★★` Unicode characters
- **Fix:** Same — replace with 5 inline SVG star icons

#### `templates/testimonials-v3.php`
- **Problem:** Star ratings use `★★★★★` Unicode characters
- **Fix:** Same — replace with 5 inline SVG star icons

#### `templates/testimonials.php`
- **Problem:** Star ratings use `★★★★★` Unicode characters
- **Fix:** Same — replace with 5 inline SVG star icons

#### `templates/hero.php`
- **Problem:** Prev/next slider arrows use `«` and `»` Unicode characters
- **Fix:** Replace with inline SVG chevron-left and chevron-right icons

#### `templates/blog-listing.php`
- **Problem:** Read More buttons have an eye/view icon — this is incorrect for a "Read More" CTA
- **Fix:** Replace the eye icon with an `arrow-right` inline SVG, or simply remove the icon entirely if no icon was intended

---

## TASK 2 — Thank You Page

### Goal
Build the Thank You page that is shown after a form submission. It uses the standard site header and footer and is a fully normal WordPress page (not an orphan landing page).

### 1. Build `pages/thank-you.php`

```php
<?php
/*
 * Template Name: Thank You Page
 */

get_header();

get_template_part('templates/breadcrumb');
get_template_part('templates/thank-you');
get_template_part('templates/blog-v1');

get_footer();
```

### 2. Create `templates/thank-you.php`

A simple, centered confirmation section. Hardcoded HTML. No WordPress functions needed.

#### Content:
- A large checkmark SVG icon (success green color)
- `<h1>` — "Thank You!"
- `<p>` — "Your message has been received. We will get back to you within 24 hours."
- A button linking back to the homepage: "Back to Home" — use `.btn` class

#### Design:
- Centered layout (text-align: center)
- Generous vertical padding
- Icon should be large (~80px) and green (`$color-success` or a hardcoded green if the variable doesn't exist)
- Clean, minimal — no card, no background, just centered content

### 3. Create `src/scss/templates/thank-you.scss`

```scss
.thank-you {
  // centered section, padding, icon size, heading, paragraph, button spacing
}
```

Use existing variables only. BEM naming.

### 4. Register CSS in Vite
Update `vite.config.ts`:

```ts
'thank-you': path.resolve(__dirname, 'src/scss/templates/thank-you.scss'),
```

### 5. Ensure CSS loads on the Thank You page
In `header.php`, detect `is_page_template('pages/thank-you.php')` and load `thank-you.css` deferred.

### 6. Create the WordPress page via WP-CLI and assign the template

```bash
wp post create \
  --post_type=page \
  --post_title="Thank You" \
  --post_status=publish \
  --post_author=1

wp post meta update <ID> _wp_page_template "pages/thank-you.php"
```

---

## Expected Deliverables
- `templates/services-v1.php` — ICON text replaced with SVGs
- `templates/header-one.php` — social SVGs
- `templates/header-two.php` — social SVGs
- `templates/header-three.php` — mobile social SVGs
- `templates/footer-v2.php` — social SVGs
- `templates/portfolio-v1.php` — overlay SVG
- `templates/portfolio-v2.php` — overlay SVG
- `templates/portfolio-v3.php` — overlay SVG
- `templates/testimonials.php` — star SVGs
- `templates/testimonials-v1.php` — star SVGs
- `templates/testimonials-v2.php` — star SVGs
- `templates/testimonials-v3.php` — star SVGs
- `templates/hero.php` — arrow SVGs
- `templates/blog-listing.php` — arrow-right or no icon on Read More
- `pages/thank-you.php`
- `templates/thank-you.php`
- `src/scss/templates/thank-you.scss`
- `vite.config.ts` (modified)
- `header.php` (modified)

---

## Definition of Done
- Zero text labels, Unicode characters, or emoji used as icons anywhere in the templates
- All icons are inline SVG with `fill="currentColor"` and `aria-hidden="true"`
- Social icons render correctly in all headers and footer
- Star ratings render as SVG stars in all testimonial variants
- Portfolio overlays use SVG icons
- Thank You page renders with header, breadcrumb, confirmation section, blog section, and footer
- Thank You WordPress page exists and has the template assigned
- CSS loads correctly (deferred)

---

## Constraints
- Inline SVG only — no icon fonts, no FontAwesome, no external img
- Use `fill="currentColor"` on all SVG paths
- Use `aria-hidden="true"` on decorative icons
- Hardcoded content is acceptable
- Respect BEM
- Respect deferred CSS approach
- Keep consistent with current theme architecture
