# 🚀 Project: High-Performance WordPress Theme (Vite + TS + SASS)

## 📌 Project Summary
This is a WordPress theme built from scratch with an obsessive focus on loading speed (Performance First). The non-negotiable goal is **100/100 on Google PageSpeed Insights (PSI)**. It is not a headless theme; it is an optimized monolith that uses Vite as an orchestrator for modern assets.

**Core Stack:**
- **Build Tool:** Vite 8.x + TypeScript.
- **CSS:** SASS (Section-modular).
- **PHP:** Decoupled architecture by components/templates.
- **Data:** ACF Pro (Advanced Custom Fields) + Yoast SEO.

---

## 🛠 Execution Commands
- `npm install`: Installs dependencies.
- `npm run dev`: Starts the Vite development server (HMR) and creates the `hot` file.
- `npm run build`: Compiles assets for production, generates the `manifest.json`, and removes the `hot` file.

---

## 📂 Folder Structure & Responsibilities

### `/src` (Source Code)
- `ts/main.ts`: Global JS entry point. Only critical logic.
- `scss/base/`: Resets, color variables, typography, and mixins.
- `scss/templates/`: **Crucial.** Each design section (hero, services, etc.) has its own `.scss` file. These are registered as individual inputs in `vite.config.ts`.
- `images/`: Static theme assets (icons, decorations).

### `/inc` (PHP Business Logic)
- `vite-integration.php`: Class that connects PHP with Vite assets (HMR or Manifest).
- `optimize.php`: Scripts to remove WP bloat (emojis, dashicons, block-library).
- `setup.php`: Theme configuration (image sizes, menus, theme supports).

### `/templates` (HTML Fragments)
- Contains section partials. Each `.php` here must have a sibling `.scss` in `src/scss/templates/`.

### `/acf-json`
- Automatic synchronization of ACF Pro fields. **Do not edit manually.**
- The JSON is generated automatically from `inc/acf-flexible-content.php` using the script `scripts/generate-acf-json.php`.
- **Whenever you modify `inc/acf-flexible-content.php`, run `php scripts/generate-acf-json.php` to regenerate the JSON.**
- The JSON is NOT the source of truth — the PHP is. The JSON is just a snapshot for ACF sync.

### `/scripts`
- Development tools, not deployed to production.
- `generate-acf-json.php` — generates `acf-json/group_rms_page_sections.json` from PHP.

---

## 📏 Style & Code Guidelines

### 1. CSS & SASS (The "No-Blocking" Rule)
- **Methodology:** BEM (Block Element Modifier).
- **Critical CSS:** The Hero CSS MUST be inlined in the `<head>` using the `get_critical_css()` method.
- **Async CSS:** The rest of the sections are conditionally enqueued with `wp_enqueue_style`.
- **Forbidden:** Do NOT use massive `@import`s in a single global file. Keep files small (< 10kb gzipped).

### 2. JavaScript & TypeScript
- **Paradigm:** Vanilla JS only.
- **Forbidden:** jQuery or heavy frameworks. If reactivity is required, use Alpine.js (lightweight).
- **Performance:** All scripts must load with `type="module"` (asynchronous by nature).

### 3. Images & Media
- **CLS (Cumulative Layout Shift):** Every image MUST have explicit `width` and `height` attributes.
- **ACF Images:** Always use `wp_get_attachment_image( $id, 'size' )` to leverage native `srcset` and lazy loading.

---

## 🔄 Development Workflow for the Agent

When asked to create a new section (e.g., "Testimonials"):
1. **Create Styles:** Generate `src/scss/templates/testimonials.scss`.
2. **Register in Vite:** Add the new file to the `input` object in `vite.config.ts`.
3. **Create Template:** Generate `templates/testimonials.php` with semantic HTML markup.
4. **Vite Bridge:** In the page file (e.g., `front-page.php`), call the CSS using:
   - If Above the fold: `Vite_Icons_Integration::get_instance()->get_critical_css('src/scss/templates/testimonials.scss')`.
   - If Below the fold: `Vite_Icons_Integration::get_instance()->get_asset('src/scss/templates/testimonials.scss')` via `wp_enqueue_style`.

---

## 🔒 Security & SEO Considerations
- **Sanitization:** Always escape ACF outputs with `esc_html()`, `esc_attr()`, or `wp_kses()`.
- **Yoast SEO:** Do not overwrite titles or metas manually; use Yoast hooks.
- **Data Attributes:** Use `data-` attributes to pass information from PHP to JS, avoiding global `window.config` variables.

---

## 🧪 Testing Instructions (Performance Audit)
Before marking a task as complete, verify:
1. Does the total DOM have fewer than 1500 nodes?
2. Is there any blocking resource in the `<head>` other than the inline critical CSS?
3. Do images have `decoding="async"` and `loading="lazy"` (except the Hero)?
4. Is the TTFB stable (check Query Monitor for slow queries)?

---

## 🧩 Project Skills — Usage Guide

The project has 16 skills installed in `.agents/skills/`. **NOT all are relevant.** Before invoking a skill, consult this table.

### ⭐ Priority Skills (use ALWAYS when applicable)

| Skill | When to use | Why it matters |
|---|---|---|
| **core-web-vitals** | Every time you create or modify a section, template, or asset | The project goal is PSI 100/100. LCP, CLS, and INP are non-negotiable. |
| **seo** | When structuring HTML, metas, headings, URLs, structured data | SEO is a pillar of the project alongside performance. |
| **accessibility** | In all HTML markup, forms, navigation, contrast | WCAG compliance. Without accessibility, there is no serious project. |
| **vite** | When modifying `vite.config.ts`, entry points, plugins, builds | The Vite integration is the core of the build system. |
| **wp-performance** | When writing PHP, queries, hooks, enqueue, caching | Every line of PHP affects TTFB and server metrics. |
| **frontend-design** | When building visual components, layouts, UI | Design quality and visual consistency of the theme. |

### 🔧 Support Skills (use when applicable)

| Skill | When to use |
|---|---|
| **wordpress-router** | When you need to classify the repo or decide which workflow to follow |
| **wp-project-triage** | For structured project inspection and generating JSON reports |
| **wp-rest-api** | If we expose CPT or ACF data via REST API |
| **wp-wpcli-and-ops** | For WP-CLI operations, migrations, search-replace, deploy |
| **wp-plugin-development** | Only if we create a companion plugin (not currently planned) |
| **typescript-advanced-types** | For complex TypeScript logic (generic types, conditional types) |
| **nodejs-best-practices** | For architectural decisions in Node/Vite config |
| **nodejs-backend-patterns** | If we build microservices or APIs (not planned) |

### 🚫 Skills NOT Relevant for this project

| Skill | Reason |
|---|---|
| **wp-block-development** | **Gutenberg blocks are NOT used.** This is a Classic Theme, not a Block Theme. There is no `block.json`, no `register_block_type`. |
| **wp-block-themes** | **theme.json and Full Site Editing are NOT used.** The architecture is classic PHP templates + Vite assets. |

### General Rule
> If the task is visual → **frontend-design** + **core-web-vitals** + **accessibility**.
> If the task is PHP → **wp-performance** + **seo**.
> If the task is build/config → **vite**.
> If the task mentions "blocks", "Gutenberg", "FSE", "block theme", "theme.json" → **DOES NOT APPLY to this project.**

---

## 🌿 Git Workflow — Hermes Agent + Geovanny

### Branch Structure
```
main           ← production (only approved merges)
  ├── develop  ← Geovanny's branch (direct merge to main)
  └── noir-dev ← Hermes Agent's branch (PRs only, never direct merge)
```

---

### ✅ Before starting work — MANDATORY

**Hermes Agent (noir-dev):**
```bash
git checkout noir-dev
git fetch origin
git rebase origin/main
```

**Geovanny (develop):**
```bash
git checkout develop
git fetch origin
git rebase origin/main
```

> ⚠️ Never start working without syncing with `main` first. This prevents merge conflicts.

---

### 📤 When finishing work

**Hermes Agent — open PR:**
1. Run `git rebase origin/main` before pushing
2. Verify that the PR does not have `mergeable_state: dirty` before requesting review
3. If there are conflicts, resolve them in the branch before opening the PR
4. The PR description must include: what changed, affected files, and reason

**Geovanny — direct merge to main:**
```bash
git checkout main
git merge develop --no-ff
git push origin main
git checkout develop
git rebase origin/main   # keep develop updated post-merge
```

---

### 🔀 Geovanny reviewing and merging a PR from Hermes Agent

1. Verify that the PR has no conflicts (`mergeable_state` clean)
2. Review the changed files — if they match recent changes in `develop`, review manually
3. After the merge, update `develop`:
```bash
git checkout develop
git rebase origin/main
```

---

### 🚨 Critical Rules

| # | Rule |
|---|---|
| 1 | Always `rebase origin/main` before starting work |
| 2 | Hermes Agent **never** does direct merge to `main` — only PRs |
| 3 | Geovanny **always** updates `develop` after merging a PR |
| 4 | If a PR has conflicts, Hermes Agent resolves them before requesting review |
| 5 | Do not work on the same files on the same day without prior coordination |

---

**Signed:** Geovanny - Senior Web Dev & SEO Specialist.
