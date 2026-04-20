# NOIR.md

## Instruction
> **This document contains a daily task schedule.**
> Each day has a date and a task block assigned to it.
> **The AI must ONLY execute the task whose date matches today.**
> If today does not match any task date, do nothing and inform the user that there is no task assigned for today.

## Task Schedule
| Date | Task |
|------|------|
| 2026-04-20 | Build the Blog page template |

> Today's task for **2026-04-20** is described below.

---

## Project Context
- Project: `simple-rms-theme`
- Stack: WordPress classic theme + Vite + SCSS + TypeScript
- Architecture: reusable templates, modular SCSS by section/layout, deferred CSS for below-the-fold content, critical CSS only where needed
- Current active header: `header-one`
- Current active footer: `footer-v2`
- Breadcrumb template already exists as: `templates/breadcrumb.php`

---

## Main Task — 2026-04-20
Build the **Blog page template**.

### Goal
Create a WordPress blog listing page using a custom page template. Single-column layout, no sidebar. Displays 10 hardcoded post cards followed by a hardcoded pagination UI.

> **IMPORTANT: ALL content must be hardcoded HTML. Do NOT use WP_Query, get_the_excerpt(), get_the_date(), get_the_author(), get_the_tags(), paginate_links(), or any other WordPress function inside the templates. Pure static HTML only.**

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
- Render **10 hardcoded post cards** in a single-column layout
- Render a **hardcoded pagination UI** at the bottom (e.g. « 1 2 3 … Next »)
- **NO WordPress loop, NO WP_Query, NO template tags** — pure static HTML

#### Each post card must display (in this order):
1. **Featured image** — 900×500, hardcoded `<img>` using `https://placehold.co/900x500`
2. **Title** — `<h2>` with a hardcoded `<a href="#">` link
3. **Meta row** — hardcoded date, author, and tags (all on the same line, separated visually)
4. **Excerpt** — hardcoded short paragraph (~25–35 words)
5. **Read More button** — hardcoded `<a href="#">` styled as a button

#### After the 10 cards:
- Render a **hardcoded pagination UI** — e.g. `« 1 2 3 … Next »`
- Pagination must be responsive and styled
- It does NOT need to be functional — just the visual design

---

### 3. Create `src/scss/sections/blog-listing.scss`

#### Layout rules:
- Single column, centered, max-width consistent with the theme's `.container`
- No sidebar
- Cards stacked vertically with generous spacing between them

#### Card design rules:
- Featured image: full width of the card, `900×500` ratio (`aspect-ratio: 9/5`), `object-fit: cover`
- `h2` title: large, bold, linked (no underline by default, underline on hover)
- Meta row: small font, muted color, inline items (date · author · tags)
- Excerpt: readable line-height, max 3–4 lines
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
- Blog page renders with 10 hardcoded post cards
- Each card shows: featured image (900×500 placeholder), h2 title, meta (date · author · tags), excerpt, Read More button
- Hardcoded pagination UI renders at the bottom
- Layout is single column, no sidebar
- CSS is correctly loaded (deferred)
- Page is visually coherent with the rest of the theme

---

## Constraints
- No Gutenberg / block theme patterns
- No ACF
- **ALL content is hardcoded HTML — no WordPress template tags or functions inside templates**
- **No WP_Query, no get_the_excerpt(), no get_the_date(), no get_the_author(), no get_the_tags(), no paginate_links()**
- Respect BEM
- Respect deferred CSS approach
- Keep consistent with current theme architecture
