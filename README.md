# Simple RMS Theme

High-performance classic WordPress theme built with **Vite**, **SCSS**, and **TypeScript**.

## Stack

- WordPress classic theme
- Vite 8
- SCSS modular architecture
- TypeScript for interactive components
- TGMPA for plugin dependency management

## Core goals

- Performance-first architecture
- Critical CSS only for above-the-fold content
- Deferred CSS for below-the-fold sections
- Reusable section-based templates ready for future ACF integration

## Current architecture

### Base styling

Located in `src/scss/base/`:

- `_variables.scss`
- `_mixins.scss`
- `_grid.scss`
- `_utilities.scss`
- `_reset.scss`
- `_typography.scss`

Main critical stylesheet entry:

- `src/scss/main.scss`

## Vite entry strategy

This theme does **not** use one giant CSS bundle for all sections.

- `main.scss` = base critical CSS
- each reusable section/layout has its own SCSS entry point
- selected JS interactions also have dedicated TS entry points

Examples:

- `hero.scss`
- `slider.scss`
- `about-us.scss`
- `services-v1.scss`
- `portfolio-v2.scss`
- `testimonials-v3.scss`
- `blog-v1.scss`
- `area-coverage-v1.scss`
- `vision-mission-v1.scss`
- `vision-mission-v2.scss`
- `seo-content.scss`
- `header-two.scss`
- `footer-v2.scss`

## Active front page composition

Current `front-page.php` renders:

1. Slider
2. Badges / directories
3. About Us
4. Services V1
5. CTA V2
6. Portfolio V2
7. Testimonials V3
8. Blog V1
9. Area Coverage V1
10. Vision / Mission V1
11. Vision / Mission V2
12. SEO Content reusable section

## Active layout components

- Header: `templates/header-two.php`
- Footer: `templates/footer-v2.php`

## Reusable section templates available

Inside `templates/`:

- `hero.php`
- `slider.php`
- `badges.php`
- `about-us.php`
- `services-v1.php`
- `services-v2.php`
- `services-v3.php`
- `cta-v1.php`
- `cta-v2.php`
- `cta-v3.php`
- `portfolio-v1.php`
- `portfolio-v2.php`
- `portfolio-v3.php`
- `testimonials-v1.php`
- `testimonials-v2.php`
- `testimonials-v3.php`
- `blog-v1.php`
- `area-coverage-v1.php`
- `vision-mission-v1.php`
- `vision-mission-v2.php`
- `seo-content.php`
- `header-one.php`
- `header-two.php`
- `footer-v2.php`

## Interactive components

Inside `src/ts/`:

- `header-two-menu.ts` — mobile menu for header v2
- `slider.ts` — slider autoplay + dot navigation
- `lightbox.ts` — image modal for portfolio
- `portfolio-filter.ts` — category filter for portfolio v3

## CSS loading strategy

Handled in `header.php` + `inc/vite-integration.php`:

- **Critical CSS inline**
  - `src/scss/main.scss`
  - `src/scss/sections/hero.scss`

- **Eager loaded above the fold**
  - slider
  - badges

- **Deferred below the fold**
  - about-us
  - services variants
  - cta variants
  - portfolio variants
  - testimonials variants
  - blog-v1
  - area-coverage-v1
  - vision-mission-v1
  - vision-mission-v2
  - seo-content
  - footer-v2
  - shared lightbox CSS

## TGMPA plugin dependencies

Configured in `inc/tgmpa.php`.

### Required

- Advanced Custom Fields PRO (bundled in `inc/plugins/advanced-custom-fields-pro.zip`)
- Classic Editor

### Recommended

- Yoast SEO
- Contact Form 7
- WP Fastest Cache
- Wordfence Security

## Important files

- `functions.php` — main bootstrap
- `inc/setup.php` — theme setup, menus, image sizes, walkers
- `inc/optimize.php` — WordPress cleanup/performance tweaks
- `inc/vite-integration.php` — Vite ↔ WordPress bridge
- `inc/tgmpa.php` — plugin dependency registration
- `vite.config.ts` — all CSS/JS entry points
- `header.php` — critical/deferred asset loading + active header template
- `footer.php` — active footer template
- `AGENT.md` — project rules and architecture philosophy

## Development

### Install

```bash
npm install
```

### Dev mode

```bash
npm run dev
```

Open the site through your LocalWP/WordPress domain, **not** through `localhost:3000`.

- WordPress page = real site
- Vite = asset server / HMR

### Production build

```bash
npm run build
```

> Note: on Windows, if your npm scripts still use `rm`, adjust them to PowerShell-compatible commands.

## Conventions

- BEM naming for components and sections
- `@use` instead of `@import`
- explicit image dimensions for CLS control
- above-the-fold CSS inline only where it matters
- below-the-fold sections loaded deferred

## License

ISC

## Author

**Geovanny Lacayo** — [github.com/glacayo](https://github.com/glacayo)
