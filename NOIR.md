# NOIR.md

## Instruction
> **This document contains a daily task schedule.**
> Each day has a date and a task block assigned to it.
> **The AI must ONLY execute the task whose date matches today.**
> If today does not match any task date, do nothing and inform the user that there is no task assigned for today.

## Task Schedule
| Date | Task |
|------|------|
| 2026-04-22 | Build the SEO Landing Page template |

> Today's task for **2026-04-22** is described below.

---

## Project Context
- Project: `simple-rms-theme`
- Stack: WordPress classic theme + Vite + SCSS + TypeScript
- Architecture: reusable templates, modular SCSS by section/layout, deferred CSS for below-the-fold content, critical CSS only where needed
- Current active header: `header-one`
- Current active footer: `footer-v2`
- Breadcrumb template already exists as: `templates/breadcrumb.php`

---

## Main Task — 2026-04-22
Build the **SEO Landing Page** page template.

### Goal
Create a WordPress page template for SEO-targeted landing pages. This is NOT an orphan landing page — it uses the standard site header and footer and is fully indexable. All sections must be assembled from **existing reusable templates already present in the project**. No new section templates need to be created.

---

## Page Structure

The page must render sections in this exact order:

1. `get_header()` — standard site header
2. **Hero section** — reuse `templates/hero.php`
3. **Slim breadcrumb** — reuse `templates/breadcrumb.php` but wrapped in a dedicated slim style (solid background, breadcrumb centered, no H1)
4. **Content section — text right, image left** — reuse `templates/seo-content.php` with `.seo-content--reverse` modifier
5. **Mission / Vision / Why Choose Us** — reuse `templates/vision-mission-v1.php`
6. **Badges** — reuse `templates/badges.php`
7. **Latest Projects** — reuse `templates/portfolio-v1.php`
8. **Content section — text left, image right** — reuse `templates/seo-content.php` (default, no reverse modifier)
9. **Testimonials** — reuse `templates/testimonials-v1.php`
10. **Content section — text right, image left** — reuse `templates/seo-content.php` with `.seo-content--reverse` modifier
11. `get_footer()` — standard site footer

---

## Detailed Instructions

### 1. Create `pages/landing-page.php`

```php
<?php
/*
 * Template Name: SEO Landing Page
 */

get_header();

get_template_part('templates/hero');
get_template_part('templates/breadcrumb');
get_template_part('templates/seo-content');        // text left, image right — default
get_template_part('templates/vision-mission-v1');
get_template_part('templates/badges');
get_template_part('templates/portfolio-v1');
get_template_part('templates/seo-content');        // text left, image right — default (second instance)
get_template_part('templates/testimonials-v1');
get_template_part('templates/seo-content');        // text right, image left — use reverse modifier

get_footer();
```

> **Important:** `get_template_part()` does not support passing modifier classes directly. The AI must handle the three `seo-content` variations by one of these approaches:
> - Create three thin wrapper template files (e.g. `templates/seo-content-reverse.php`) that include the section with the correct modifier class applied
> - Or duplicate the section HTML inline inside the page template with the correct modifier
> 
> Choose whichever approach is cleaner and more consistent with the existing project architecture.

---

### 2. Breadcrumb slim style

The breadcrumb on this landing page must appear as a **slim bar**:
- Solid background color (use an existing theme color token — dark or brand color)
- Breadcrumb trail centered horizontally
- Reduced vertical padding (slim, not full-height)
- No H1 rendered (add `is_page_template('pages/landing-page.php')` condition to the existing `is_single()` check in `templates/breadcrumb.php`)

If a dedicated `.breadcrumb--slim` modifier class does not exist yet, add it to the breadcrumb SCSS.

---

### 3. Ensure CSS loads on the Landing Page
In `header.php`, detect `is_page_template('pages/landing-page.php')` and load any CSS specific to this template in a **deferred / non-render-blocking way**.

All section CSS (hero, seo-content, vision-mission, badges, portfolio, testimonials) should already be loading globally or via their own conditionals. The AI must verify this and add any missing entries.

---

### 4. Create a WordPress page via WP-CLI and assign the template

#### Step 1 — Create the page:
```bash
wp post create \
  --post_type=page \
  --post_title="Roofing Services in Miami, FL" \
  --post_status=publish \
  --post_author=1
```

#### Step 2 — Assign the template to the page:
```bash
wp post meta update <ID> _wp_page_template "pages/landing-page.php"
```

Replace `<ID>` with the ID returned in Step 1.

#### Step 3 — Verify:
```bash
wp post list --post_type=page --fields=ID,post_title,post_status
```

---

## Expected Deliverables
- `pages/landing-page.php`
- `templates/breadcrumb.php` (modified — hide H1 on landing page template too)
- Any thin wrapper templates needed for `seo-content` variations (if that approach is chosen)
- `header.php` (modified for CSS loading if needed)
- WordPress page created and template assigned via WP-CLI

---

## Definition of Done
- Landing page renders all sections in the correct order
- Hero renders at the top
- Slim breadcrumb bar renders below the hero, centered, no H1
- Three `seo-content` sections render: reverse → default → reverse
- Vision/Mission/Why Choose Us section renders
- Badges section renders
- Latest Projects section renders
- Testimonials section renders
- Footer renders at the bottom
- A published WordPress page exists with this template assigned and is viewable in the browser
- CSS is correctly loaded

---

## Constraints
- Do NOT create new section designs — reuse ONLY existing templates
- No Gutenberg / block theme patterns
- No ACF
- Hardcoded content in sections is acceptable (already exists in reusable templates)
- Respect BEM
- Respect deferred CSS approach
- Keep consistent with current theme architecture
