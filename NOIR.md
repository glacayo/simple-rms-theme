# NOIR.md

## Instruction
> **This document contains a daily task schedule.**
> Each day has a date and a task block assigned to it.
> **The AI must ONLY execute the task whose date matches today.**
> If today does not match any task date, do nothing and inform the user that there is no task assigned for today.

## Task Schedule
| Date | Task |
|------|------|
| 2026-04-28 | SCSS audit + HTML semantics audit + Unlighthouse performance reporting |

> Today's task for **2026-04-28** is described below.

---

## Project Context
- Project: `simple-rms-theme`
- Stack: WordPress classic theme + Vite + SCSS + TypeScript
- Templates live in: `templates/`
- Page templates live in: `pages/`
- **Reference implementation for SVG icons:** `templates/contact-info.php` and `templates/header-three.php` — these files already use correct inline SVGs. Use them as the style and size reference for all other icons.

---

## Task: 2026-04-28 — SCSS Audit + HTML Semantics Audit + Unlighthouse Performance Reporting

### Goal
Three independent audit passes across the entire theme, followed by a performance baseline report.

---

### Pass 1 — SCSS Audit (optimization + redundancy elimination)

**Scope:** Every file in `src/scss/` — templates, layout, components, base.

**Rules:**
- The theme is **modular by design**: each template has its own SCSS file. Do NOT merge or share classes across template files.
- Do NOT remove a rule just because it looks similar to one in another file — modules are intentionally independent.
- Target only redundancy and bloat **within the same file**.

**What to look for and fix:**
1. **Dead selectors** — rules targeting classes that no longer exist in the corresponding template
2. **Duplicated property blocks** — same property+value declared multiple times for the same selector within the file
3. **Oversized files** — if a template's SCSS is disproportionately large relative to what the template renders, identify the excess
4. **Unused variables or overrides** — local variable assignments that shadow globals but are never used
5. **Redundant resets** — `margin: 0`, `padding: 0`, `list-style: none` etc. repeated when already covered by base reset
6. **Specificity bloat** — selectors with unnecessary depth (e.g. `.block .block__element .block__element-child` when `.block__element-child` is sufficient)

**Output:** For each file, list findings as: `[FILE] → [LINE(S)] → [ISSUE] → [FIX APPLIED]`. Apply fixes directly.

---

### Pass 2 — HTML Semantics Audit

**Scope:** Every file in `templates/` and `pages/`.

**What to check and fix:**

1. **Semantic structure**
   - Headings follow logical hierarchy (no skipping H1→H3)
   - Landmark elements used correctly (`<main>`, `<section>`, `<article>`, `<aside>`, `<nav>`, `<header>`, `<footer>`)
   - `<div>` used only when no semantic element fits
   - Lists (`<ul>`, `<ol>`) used for actual lists, not layout

2. **Images**
   - Every `<img>` has a meaningful `alt` attribute (decorative images use `alt=""`)
   - `width` and `height` attributes present to prevent layout shift (CLS)
   - `loading="lazy"` on below-the-fold images; `fetchpriority="high"` only on LCP image
   - `decoding="async"` on non-critical images

3. **Links**
   - No empty `href="#"` on interactive links — use `<button>` instead or add a real destination
   - External links have `rel="noopener noreferrer"`
   - Links that open in new tab (`target="_blank"`) have `aria-label` indicating that behavior

4. **Embeds / iframes**
   - Every `<iframe>` has `title` attribute
   - Lazy loading applied: `loading="lazy"`

5. **Forms**
   - Every `<input>` and `<textarea>` has an associated `<label>` (either wrapping or via `for`/`id`)
   - Submit buttons use `type="submit"` explicitly

6. **ARIA**
   - No redundant ARIA (e.g. `role="button"` on a `<button>`)
   - `aria-hidden="true"` on all decorative SVGs and icons
   - Interactive elements that toggle state have correct `aria-expanded` / `aria-controls`

**Output:** `[FILE] → [LINE(S)] → [ISSUE] → [FIX APPLIED]`. Apply fixes directly.

---

### Pass 3 — Unlighthouse Investigation + Report

**Goal:** Determine whether Unlighthouse can be installed and run against the local WordPress site, generate a full-site performance report, and document the findings.

**Steps:**

1. **Investigate Unlighthouse**
   - Read the docs at `https://unlighthouse.dev`
   - Determine: can it run against a `http://localhost` or Local by Flywheel URL?
   - What is the install command? Does it require a config file?
   - What does the output look like — is there a JSON/HTML report?

2. **Install and configure** (if feasible for local)
   - Install as a dev dependency: `npm install -D @unlighthouse/cli puppeteer`
   - Create `unlighthouse.config.ts` at the project root with:
     - `site`: the local WordPress URL (e.g. `http://simple-rms-theme.local`)
     - `scanner.device`: `desktop` and `mobile` if supported
     - Output directory: `reports/unlighthouse/`
   - Add an npm script to `package.json`: `"perf:report": "unlighthouse --site http://simple-rms-theme.local"`

3. **Run the report**
   - Execute the scan
   - Save the output summary to `reports/unlighthouse/README.md`
   - List the top 5 pages by lowest performance score
   - List the top 3 recurring issues across all pages

4. **If Unlighthouse cannot run locally**, document exactly why in `reports/unlighthouse/README.md` and provide the correct command to run it once deployed to a staging/production URL.

---

### Definition of Done
- Every SCSS file has been reviewed; findings documented and fixes applied
- Every template and page file has been reviewed for semantics; findings documented and fixes applied
- Unlighthouse is installed (or blocked with reason documented) and a report or setup guide exists at `reports/unlighthouse/README.md`
- No regressions: existing visual styles and functionality unchanged after SCSS fixes

### Constraints
- Do NOT share SCSS classes across template files — the theme is intentionally modular
- Do NOT change any visual design — only remove dead/redundant code
- Do NOT add new features or templates during this task
- Respect BEM naming throughout
- Hardcoded content in templates is acceptable — do not convert to dynamic PHP during this task
- **Unlighthouse is completely separate from this repo.** Do NOT install it inside `simple-rms-theme`, do NOT add config files, npm scripts, or report outputs to this repo. All Unlighthouse work (install, config, reports) must live in its own dedicated folder/project outside of `simple-rms-theme`. Keep the theme repo clean.
