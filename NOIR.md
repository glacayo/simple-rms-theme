# NOIR.md

## Instruction
> **This document contains a daily task schedule.**
> Each day has a date and a task block assigned to it.
> **The AI must ONLY execute the task whose date matches today.**
> If today does not match any task date, do nothing and inform the user that there is no task assigned for today.

## Task Schedule
| Date | Task |
|------|------|
| 2026-04-21 | Build the single post page template |

> Today's task for **2026-04-21** is described below.

---

## Project Context
- Project: `simple-rms-theme`
- Stack: WordPress classic theme + Vite + SCSS + TypeScript
- Architecture: reusable templates, modular SCSS by section/layout, deferred CSS for below-the-fold content, critical CSS only where needed
- Current active header: `header-one`
- Current active footer: `footer-v2`
- Breadcrumb template already exists as: `templates/breadcrumb.php`

---

## Main Task — 2026-04-21
Build the **single post page template** (`single.php`).

### Goal
Create the WordPress single post template. Single-column layout, no sidebar. Fully dynamic using WordPress template tags. The CSS must cover every possible content scenario (bold, italic, underline, lists, tables, images, etc.) so the post content is always formatted correctly.

---

## Detailed Instructions

### 1. Modify `templates/breadcrumb.php` — hide H1 on single posts

Inside the breadcrumb template, add a condition so the `<h1>` is **NOT rendered** when the current page is a single post.

```php
<?php if ( ! is_single() ) : ?>
    <h1>...</h1>
<?php endif; ?>
```

Only the H1 should be hidden. The breadcrumb trail itself should still render normally on single posts.

---

### 2. Create `single.php` at the theme root

This is the WordPress standard template for single posts. It must render in this order:

```php
<?php
get_header();

get_template_part('templates/breadcrumb');
get_template_part('templates/single-post');

get_footer();
```

---

### 3. Create `templates/single-post.php`

This template renders the full single post using WordPress functions. It must display:

1. **Featured image** — full width, using `the_post_thumbnail('full')`
2. **Post title** — `<h1>` using `the_title()`
3. **Meta row** — date, author, and tags on the same line:
   - Date: `get_the_date()`
   - Author: `get_the_author()`
   - Tags: `get_the_tags()` — render as a comma-separated list of tag names
4. **Post content** — `the_content()` — this renders all the dynamic content
5. **Post navigation** — previous and next post links at the bottom

#### Post navigation must use:
```php
the_post_navigation([
    'prev_text' => '&laquo; %title',
    'next_text' => '%title &raquo;',
]);
```

#### Template structure reference:
```php
<?php while ( have_posts() ) : the_post(); ?>

    <article class="single-post">

        <div class="single-post__featured-image">
            <?php if ( has_post_thumbnail() ) : ?>
                <?php the_post_thumbnail('full'); ?>
            <?php endif; ?>
        </div>

        <div class="single-post__header">
            <h1 class="single-post__title"><?php the_title(); ?></h1>
            <div class="single-post__meta">
                <span><?php echo get_the_date(); ?></span>
                <span><?php echo get_the_author(); ?></span>
                <?php
                $tags = get_the_tags();
                if ( $tags ) {
                    $tag_names = wp_list_pluck($tags, 'name');
                    echo '<span>' . implode(', ', $tag_names) . '</span>';
                }
                ?>
            </div>
        </div>

        <div class="single-post__content">
            <?php the_content(); ?>
        </div>

        <nav class="single-post__navigation">
            <?php
            the_post_navigation([
                'prev_text' => '&laquo; %title',
                'next_text' => '%title &raquo;',
            ]);
            ?>
        </nav>

    </article>

<?php endwhile; ?>
```

---

### 4. Seed content via WP-CLI

#### Step 1 — Check for an existing published post:
```bash
wp post list --post_status=publish --post_type=post --fields=ID,post_title
```

- If a published post exists (including the default "Hello world!" sample post), use it.
- If no published post exists, create one:

```bash
wp post create \
  --post_title="The Complete Guide to Roof Maintenance" \
  --post_status=publish \
  --post_type=post \
  --post_author=1
```

#### Step 2 — Update the post content with rich HTML:

Use `wp post update <ID> --post_content="..."` to set the post body.

The content must be **at least 1000 words of Lorem Ipsum** and must include ALL of the following inline and block elements so the CSS for every scenario is validated:

- `<strong>` — bold text
- `<em>` — italic text
- Text in ALL CAPS (hardcoded uppercase words in the lorem ipsum)
- `<u>` — underlined text
- `<s>` — strikethrough text
- `<a href="#">` — inline link
- `<ol>` with `<li>` — ordered list
- `<ul>` with `<li>` — unordered list
- `<table>` with `<thead>`, `<tbody>`, `<tr>`, `<th>`, `<td>` — at least 3 columns, 3 rows
- `<img>` full width — `<img src="https://placehold.co/1200x500" style="width:100%">`
- `<img>` small square floated right — `<img src="https://placehold.co/200x200" style="float:right; margin: 0 0 1rem 1rem;">`
- `<img>` small square floated left — `<img src="https://placehold.co/200x200" style="float:left; margin: 0 1rem 1rem 0;">`

> The purpose of this rich content is to ensure the SCSS covers every WordPress content formatting scenario.

---

### 5. Create `src/scss/sections/single-post.scss`

This stylesheet must cover the full single post layout AND all content formatting scenarios inside `.single-post__content`.

#### Layout:
- Single column, centered, max-width consistent with `.container`
- No sidebar
- Featured image: full width, `object-fit: cover`
- Meta row: small font, muted color, inline items separated by `·`
- H1 title: large, bold, proper spacing below

#### Content typography — `.single-post__content` must style:
- `p` — readable font size, line-height, margin between paragraphs
- `strong` — bold
- `em` — italic
- `u` — underline
- `s` — strikethrough with muted color
- `a` — colored link, underline on hover
- `ul`, `ol` — proper indentation, list-style visible
- `li` — spacing between items
- `table` — full width, bordered, alternating row background, `th` styled differently from `td`
- `img` — max-width: 100%, height: auto, display: block (for full-width images)
- `img[style*="float:right"]` or `.alignright` — float right with margin
- `img[style*="float:left"]` or `.alignleft` — float left with margin
- Clearfix after floated images if needed

#### Post navigation:
- `.single-post__navigation` — flex row, space-between
- Previous link aligned left, next link aligned right
- Styled consistently with theme link/button styles

#### Responsive:
- On mobile, floated images should stop floating and go full width
- Table should be horizontally scrollable on small screens

---

### 6. Register CSS in Vite
Update `vite.config.ts`:

```ts
'single-post': path.resolve(__dirname, 'src/scss/sections/single-post.scss'),
```

---

### 7. Ensure CSS loads on single posts
In `header.php`, detect `is_single()` and load `single-post.css` in a **deferred / non-render-blocking way**.

Also ensure breadcrumb CSS is loaded here if not already generalized.

---

## Expected Deliverables
- `single.php` (theme root)
- `templates/single-post.php`
- `templates/breadcrumb.php` (modified — hide H1 on single posts)
- `src/scss/sections/single-post.scss`
- `vite.config.ts` (modified)
- `header.php` (modified for CSS loading)
- Post content updated via WP-CLI with rich HTML

---

## Definition of Done
- `single.php` renders the post correctly
- Breadcrumb shows on single post but WITHOUT the H1
- Featured image renders full width
- H1 title, meta row (date · author · tags), and content all render
- Post content includes: bold, italic, uppercase, underline, strikethrough, links, ordered list, unordered list, table, full-width image, float-right image, float-left image
- All content elements are properly styled by the SCSS
- Previous/Next post navigation renders at the bottom
- CSS is correctly loaded (deferred)
- Page is visually coherent with the rest of the theme

---

## Constraints
- No Gutenberg / block theme patterns
- No ACF
- **Use real WordPress functions for all dynamic content** (`the_title()`, `the_content()`, `the_post_thumbnail()`, `get_the_date()`, `get_the_author()`, `get_the_tags()`, `the_post_navigation()`)
- Respect BEM
- Respect deferred CSS approach
- Keep consistent with current theme architecture
