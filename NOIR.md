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
| 2026-04-18 | Build the Contact Us internal page |
| 2026-04-20 | Build the Blog page template |

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

---

> Today's task for **2026-04-18** is described below.

---

## Main Task — 2026-04-17
Build the **Contact Us internal page**.

### Goal
The AI must convert `pages/contact-us.php` from a blank stub into a complete internal page composed of:
1. Breadcrumb
2. Two-column contact info + form section
3. Full-width Google Maps embed section
4. Footer

---

## Detailed Instructions

### 1. Build the page template in `pages/contact-us.php`

It must render in this order:

```php
<?php
/*
 * Template Name: Contact Us
 */

get_header();

get_template_part('templates/breadcrumb');
get_template_part('templates/contact-info');
get_template_part('templates/contact-map');

get_footer();
```

---

### 2. Create `templates/contact-info.php`

This template renders a **two-column layout** inside a `.container`:

#### Left column — Company Info
- Phone numbers
- Email addresses
- Physical address
- Free Estimate Available
- Speak English and Spanish
- Schedule (business hours)
- Covered Area
- Payment Methods accepted

#### Right column — Contact Form
Fields:
- Name
- Email
- Phone
- Zip Code
- Services of Interest (select or text)
- Brief of Your Project (textarea)
- Submit button

Use hardcoded content. No ACF, no plugin dependency.

---

### 3. Create `templates/contact-map.php`

This template renders a **full-width Google Maps embed**:

- Width: 100%
- Height: 550px
- Use a standard `<iframe>` Google Maps embed
- The section must be full-width (outside or ignoring `.container` constraints)
- Use a placeholder embed URL (any real-looking Google Maps embed)

---

### 4. Create SCSS files

#### `src/scss/sections/contact-info.scss`
- Two-column layout on desktop (info + form)
- Single column on mobile
- Clean contractor-style aesthetics
- Form inputs should be styled consistently with the theme
- BEM naming

#### `src/scss/sections/contact-map.scss`
- Full-width section, no horizontal padding
- Map iframe fills the container: `width: 100%; height: 550px;`
- BEM naming

---

### 5. Register CSS in Vite
Update `vite.config.ts` and register:

```ts
'contact-info': path.resolve(__dirname, 'src/scss/sections/contact-info.scss'),
'contact-map':  path.resolve(__dirname, 'src/scss/sections/contact-map.scss'),
```

---

### 6. Ensure CSS loads on the Contact Us page
Detect `is_page_template('pages/contact-us.php')` in `header.php` and load both CSS files in a **deferred / non-render-blocking way** (below-the-fold content).

Also ensure the breadcrumb CSS is loaded here if not already generalized.

---

## Expected Deliverables
- `pages/contact-us.php`
- `templates/contact-info.php`
- `templates/contact-map.php`
- `src/scss/sections/contact-info.scss`
- `src/scss/sections/contact-map.scss`
- `vite.config.ts` (modified)
- `header.php` (modified for CSS loading)

---

## Definition of Done
- Contact Us page renders correctly
- Breadcrumb appears
- Two-column layout: info left, form right
- Form fields are all present and styled
- Google Maps iframe renders full-width at 550px height
- CSS is correctly loaded (deferred)
- Page is visually coherent with the rest of the theme

---

## Constraints
- No Gutenberg / block theme patterns
- No ACF
- Hardcoded content is acceptable
- Respect BEM
- Respect deferred CSS approach
- Keep consistent with current theme architecture

---

> Today's task for **2026-04-20** is described below.

---

## Main Task — 2026-04-20
Build the **Blog page template**.

### Goal
The AI must create a fully functional WordPress blog listing page using a custom page template. Single-column layout, no sidebar. Displays up to 10 posts per page with pagination.

---

## Detailed Instructions

### 1. Build the page template in `pages/blog.php`

```php
<?php
/*
 * Template Name: Blog
 */

get_header();

get_template_part('templates/breadcrumb');
get_template_part('templates/blog-listing');

get_footer();
```

---

### 2. Create `templates/blog-listing.php`

This template must:
- Query posts using `WP_Query` with `posts_per_page: 10` and support for pagination via `paged`
- Render each post as a **card** in a single-column layout

#### Each post card must display (in this order):
1. **Featured image** — 900×500, use `get_the_post_thumbnail()` with a registered size or `wp_get_attachment_image_src`. If no image, show a placeholder (`https://placehold.co/900x500`)
2. **Title** — `<h2>` linking to the single post
3. **Meta row** — date, author, and tags (all on the same line, separated visually)
4. **Excerpt** — `get_the_excerpt()`, trimmed and readable
5. **Read More button** — links to `get_permalink()`

#### After the loop:
- Render **pagination** using `paginate_links()` or the theme's existing pagination helper if one exists
- Pagination must be responsive and styled

#### WP_Query example structure:
```php
$paged = get_query_var('paged') ? get_query_var('paged') : 1;
$query = new WP_Query([
    'post_type'      => 'post',
    'posts_per_page' => 10,
    'paged'          => $paged,
]);
```

---

### 3. Create `src/scss/sections/blog-listing.scss`

#### Layout rules:
- Single column, centered, max-width consistent with the theme's `.container`
- No sidebar
- Cards stacked vertically with generous spacing between them

#### Card design rules:
- Featured image: full width of the card, `900×500` ratio (use `aspect-ratio: 9/5` or fixed height), `object-fit: cover`
- `h2` title: large, bold, linked (no underline by default, underline on hover)
- Meta row: small font, muted color, inline items (date · author · tags)
- Excerpt: readable line-height, max 3–4 lines if needed
- Read More button: styled consistently with other CTAs in the theme (use existing button styles if available)
- Card should have subtle separation (bottom border or spacing — no heavy shadow needed)

#### Pagination:
- Centered below the listing
- Active page highlighted
- Clean, minimal styling consistent with the theme

#### Responsive:
- Mobile: full width, image scales naturally
- No breakpoint changes needed for layout (already single column)

---

### 4. Register CSS in Vite
Update `vite.config.ts`:

```ts
'blog-listing': path.resolve(__dirname, 'src/scss/sections/blog-listing.scss'),
```

---

### 5. Ensure CSS loads on the Blog page
In `header.php`, detect `is_page_template('pages/blog.php')` and load `blog-listing.css` in a **deferred / non-render-blocking way**.

Also ensure breadcrumb CSS is loaded here if not already generalized.

---

## Expected Deliverables
- `pages/blog.php`
- `templates/blog-listing.php`
- `src/scss/sections/blog-listing.scss`
- `vite.config.ts` (modified)
- `header.php` (modified for CSS loading)

---

## Definition of Done
- Blog page renders with up to 10 posts per page
- Each card shows: featured image (900×500), h2 title, meta (date · author · tags), excerpt, Read More button
- Pagination renders and works correctly
- Layout is single column, no sidebar
- CSS is correctly loaded (deferred)
- Page is visually coherent with the rest of the theme

---

## Constraints
- No Gutenberg / block theme patterns
- No ACF
- Use real WordPress functions (`WP_Query`, `get_the_excerpt()`, `get_the_date()`, `get_the_author()`, `get_the_tags()`, `paginate_links()`)
- Respect BEM
- Respect deferred CSS approach
- Keep consistent with current theme architecture
