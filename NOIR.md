# NOIR.md

## Instruction
> **This document contains a daily task schedule.**
> Each day has a date and a task block assigned to it.
> **The AI must ONLY execute the task whose date matches today.**
> If today does not match any task date, do nothing and inform the user that there is no task assigned for today.

## Task Schedule
| Date | Task |
|------|------|
| 2026-04-16 | Build the Services internal page |

> Today's task for **2026-04-16** is described below.

---

## Purpose
This document defines the **next planned development block** for the WordPress theme. It is intended to be read by an AI agent that will execute the tasks exactly as described.

The AI must follow the architecture and conventions already established in this project.

---

## Project Context
- Project: `simple-rms-theme`
- Stack: WordPress classic theme + Vite + SCSS + TypeScript
- Architecture: reusable templates, modular SCSS by section/layout, deferred CSS for below-the-fold content, critical CSS only where needed
- Current active header: `header-one`
- Current active footer: `footer-v2`
- Breadcrumb template already exists as: `templates/breadcrumb.php`
- Services page template stub already exists as: `pages/services.php`

---

## Main Task for the Next Session
Build the **Services internal page**.

### Goal
The AI must convert `pages/services.php` from a blank template into a complete internal page composed of:
1. Breadcrumb
2. Services section
3. CTA section
4. Footer

---

## Detailed Instructions

### 1. Build the page template in `pages/services.php`
The file must become a proper WordPress page template.

It must render in this order:
1. `get_header()`
2. `templates/breadcrumb.php`
3. A new services page section template (to be created)
4. Any existing CTA variation (AI may choose one already available in the project)
5. `get_footer()`

### Expected structure
```php
<?php
/*
 * Template Name: Services
 */

get_header();

get_template_part('templates/breadcrumb');
get_template_part('templates/services-page');
get_template_part('templates/cta-v2'); // or another existing CTA variant

get_footer();
```

> The AI may choose a different CTA variant if it believes another one fits better visually, but it must use an **existing CTA template already present in the theme**.

---

### 2. Create the Services section template
Create a reusable template file:

```txt
templates/services-page.php
```

#### Requirements
- Display **up to 10 services**
- Present them as **cards / boxes**
- **3 cards per row** on desktop
- Cards must remain **symmetrical** with neighboring cards
- Each card must include:
  - image placeholder `400x200`
  - service title
  - text of **maximum ~30 words**
- Each card must have a **shadow**
- Layout must be fully responsive

#### Suggested content model for each card
- Image: `https://placehold.co/400x200`
- Title: service name
- Text: short description

The section should also include:
- one main section headline (`h2`)
- optional short intro paragraph if needed

---

### 3. Create the SCSS for the services page section
Create:

```txt
src/scss/sections/services-page.scss
```

#### Design rules
- Premium contractor-style layout
- 3-column card grid on desktop
- 2 columns on tablet if needed
- 1 column on mobile
- Cards should be equal height or visually aligned
- Strong spacing and clean hierarchy
- Use existing design tokens only
- Use BEM naming

#### Card design rules
- image on top
- content below
- rounded corners
- shadow
- equal visual height
- max 30-word text block

---

### 4. Register CSS in Vite
Update `vite.config.ts` and register:

```ts
'services-page': path.resolve(__dirname, 'src/scss/sections/services-page.scss'),
```

---

### 5. Ensure CSS loads on the Services page
This is IMPORTANT.

Because this project is modular, calling a template from a page **does not automatically load its SCSS**.

The AI must make sure `services-page.scss` is actually loaded on the Services page.

The preferred strategy is:
- detect `is_page_template('pages/services.php')` in `header.php`
- load `services-page.scss`
- because it is **below the fold content**, this CSS should be loaded in a **deferred / non-render-blocking way**

If the AI decides to use the existing breadcrumb on the Services page, it must also ensure the breadcrumb CSS works there.
If the current breadcrumb CSS is only loaded for `pages/about-us.php`, the AI must generalize or extend the conditional so the breadcrumb also works correctly on the Services page.

---

## Content Requirements for the Services Section
The AI may choose the exact service names, but they should be realistic contractor services such as:
- Roof Installation
- Roof Repair
- Roof Replacement
- Roof Inspection
- Gutter Installation
- Emergency Services
- Storm Damage Repair
- Leak Detection
- Skylight Installation
- Preventive Maintenance

Descriptions must be short and scannable.

---

## Reuse Rules
The AI should reuse existing project pieces where appropriate:
- breadcrumb template: `templates/breadcrumb.php`
- an existing CTA template (choose the best fit)
- footer currently active via `footer.php`
- existing `.container`
- existing grid utilities if useful
- existing variables and mixins

The AI must NOT reinvent global systems if they already exist.

---

## Constraints
- Do not use Gutenberg / block theme patterns
- Do not use ACF yet
- Hardcoded content is acceptable for now
- Use the current project architecture
- Respect BEM
- Respect deferred CSS approach for below-the-fold content
- Keep the implementation consistent with the rest of the theme

---

## Important Observation
There is a known architectural consideration in this project:

> **Calling a modular template in a page does not guarantee its SCSS is loaded automatically.**

So when building the Services page, the AI must explicitly handle CSS loading for:
- breadcrumb
- services-page section
- any other section it inserts if not already globally loaded

This is the most important thing to avoid broken layouts.

---

## Expected Deliverables
The AI should complete at least these files:
- `pages/services.php`
- `templates/services-page.php`
- `src/scss/sections/services-page.scss`
- `vite.config.ts` (modified)
- `header.php` (modified if needed for CSS loading)

---

## Definition of Done
The task is complete when:
- Services page template is fully rendered
- Breadcrumb appears correctly
- Services cards render as 3-per-row on desktop
- Cards are symmetrical and responsive
- A CTA is present after the services section
- CSS is correctly loaded for the page
- The page is visually coherent with the theme

---

## Notes for the AI
- If something is ambiguous, prioritize consistency with the current theme system
- Prefer reuse over reinvention
- The final implementation should feel production-oriented, not demo-like
