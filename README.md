# Simple RMS Theme

A modern WordPress theme built with Vite for blazing-fast development and optimized production builds.

## Features

- **Vite 8 Integration** — Lightning-fast HMR during development, optimized production bundles
- **TypeScript** — Type-safe JavaScript with full TS support
- **SCSS** — Modular stylesheets with Sass compilation
- **Critical CSS Inlining** — Automatic above-the-fold CSS injection for perfect PageSpeed scores
- **Image Optimization** — Built-in image compression via `vite-plugin-image-optimizer` (Sharp-based)
- **Live Reload** — Automatic browser refresh on PHP, CSS, and JS changes
- **ACF Ready** — ACF JSON field group sync support

## Project Structure

```
simple-rms-theme/
├── src/
│   ├── ts/
│   │   └── main.ts              # Main TypeScript entry point
│   └── scss/
│       └── sections/
│           └── hero.scss        # Section-specific styles
├── inc/
│   └── vite-integration.php     # Vite ↔ WordPress bridge (manifest reader, HMR, critical CSS)
├── templates/
│   ├── hero.php                 # Hero section template
│   ├── services.php             # Services section
│   ├── projects.php             # Projects showcase
│   ├── testimonials.php         # Testimonials section
│   ├── cta.php                  # Call-to-action section
│   └── content-section.php      # Generic content section
├── pages/
│   ├── about-us.php             # About Us page template
│   ├── contact-us.php           # Contact page
│   ├── services.php             # Services page
│   ├── projects.php             # Projects page
│   ├── testimonials.php         # Testimonials page
│   ├── landing-pages.php        # Landing pages template
│   └── thank-you.php            # Thank you / confirmation page
├── acf-json/                    # ACF field group JSON sync
├── dist/                        # Production build output (gitignored)
├── functions.php                # Theme setup and enqueues
├── header.php                   # Site header with critical CSS injection
├── footer.php                   # Site footer
├── front-page.php               # Front page template
├── index.php                    # Default template (post archive)
├── single.php                   # Single post template
├── page.php                     # Default page template
├── archive.php                  # Archive template
├── search.php                   # Search results template
├── 404.php                      # 404 error page
├── style.css                    # Theme metadata (WordPress requirement)
├── vite.config.ts               # Vite configuration
├── tsconfig.json                # TypeScript configuration
└── package.json                 # Node dependencies
```

## Requirements

- **Node.js** 18+
- **npm** or **yarn**
- **WordPress** 6.0+
- **Local dev server** (LocalWP, Laragon, MAMP, etc.)

## Getting Started

### 1. Install dependencies

```bash
npm install
```

### 2. Development mode

```bash
npm run dev
```

This starts the Vite dev server on `http://localhost:3000` with:
- Hot Module Replacement (HMR) for instant updates
- Live reload on PHP file changes
- A `hot` file marker that tells WordPress to load assets from the dev server

### 3. Production build

```bash
npm run build
```

Generates optimized assets in `dist/` with:
- Minified JS and CSS bundles
- Content-hashed filenames for cache busting
- A `manifest.json` that WordPress uses to resolve asset paths
- Optimized images

## Vite ↔ WordPress Integration

The `inc/vite-integration.php` class handles the bridge:

- **Development**: Assets load directly from `http://localhost:3000` when the `hot` file exists
- **Production**: Assets resolve via `dist/.vite/manifest.json` with hashed filenames
- **Critical CSS**: The hero section CSS is inlined in `<head>` on the front page to eliminate render-blocking and achieve 100% PageSpeed scores
- **Module Scripts**: Main JS loads as `<script type="module">` for modern browser support

## Adding Entry Points

To add a new JS or CSS entry point, edit `vite.config.ts`:

```ts
rollupOptions: {
  input: {
    main: path.resolve(__dirname, 'src/ts/main.ts'),
    'hero-section': path.resolve(__dirname, 'src/scss/sections/hero.scss'),
    // Add new entries here
    'new-section': path.resolve(__dirname, 'src/scss/sections/new-section.scss'),
  },
}
```

Then use `get_critical_css()` or `get_asset()` in your PHP templates to load them.

## Code Standards

- **PHP**: 4-space indentation ([PSR-12](https://www.php-fig.org/psr/psr-12/))
- **JS/TS/CSS/SCSS**: 2-space indentation
- **Line endings**: LF
- **Charset**: UTF-8

## License

ISC

## Author

**Geovanny Lacayo** — [github.com/glacayo](https://github.com/glacayo)
