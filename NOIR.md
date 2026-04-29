# NOIR.md

## Instruction
> **This document contains a daily task schedule.**
> Each day has a date and a task block assigned to it.
> **The AI must ONLY execute the task whose date matches today.**
> If today does not match any task date, do nothing and inform the user that there is no task assigned for today.

## Task Schedule
| Date | Task |
|------|------|
| 2026-04-29 | AEO/GEO Structured Data Research + Implementation |

> Today's task for **2026-04-29** is described below.

---

## Project Context
- Project: `simple-rms-theme`
- Stack: WordPress classic theme + Vite + SCSS + TypeScript
- Templates live in: `templates/`
- Page templates live in: `pages/`
- **Reference implementation for SVG icons:** `templates/contact-info.php` and `templates/header-three.php` — these files already use correct inline SVGs. Use them as the style and size reference for all other icons.

---

## Task: 2026-04-29 — AEO/GEO Structured Data Research + Implementation

### Goal
Investigate which structured data / schema types are most relevant for a contractor website to be fully optimized for AEO (Answer Engine Optimization) and GEO (Generative Engine Optimization), then apply the appropriate structured data across the website using the MOST scalable and maintainable approach.

### Research Scope
Investigate and document:
1. Which schema types are highest priority for a contractor / roofing / local service business website
2. Which schema types help most with:
   - local SEO
   - AEO / answer engines
   - GEO / AI-generated search and answer surfaces
   - trust / authority / entity clarity
3. Whether the structured data should be:
   - embedded directly in semantic HTML with microdata/RDFa, or
   - implemented as centralized JSON-LD
4. Which approach is more scalable and maintainable for THIS theme architecture

### Expected research topics
- `LocalBusiness`
- `RoofingContractor` or nearest valid subtype if applicable
- `Organization`
- `WebSite`
- `WebPage`
- `Service`
- `FAQPage` (only if the page truly contains FAQs)
- `BreadcrumbList`
- `Review` / `AggregateRating` (only if real data exists)
- `ContactPage`
- `ImageObject`
- `VideoObject` (only if real videos exist)
- `Article` / `BlogPosting` for blog pages

### Implementation Task
After the research, implement ALL structured data that is justified and valid for this website.

### Implementation rules
- Prefer the approach that is most scalable and maintainable for this repo.
- If JSON-LD is the better architecture, centralize and generate it cleanly from PHP.
- Do NOT add fake reviews, fake ratings, fake FAQs, fake videos, or fake business facts.
- Only mark up content that actually exists on the page.
- The implementation must be safe for a classic WordPress theme.
- Keep the output extensible for future templates and pages.

### Deliverables
1. Research summary inside the task notes section in `NOIR.md`
2. Clear recommendation on HTML microdata vs JSON-LD, with reasoning
3. Implementation plan across the theme
4. Apply the structured data to the website code

### Definition of Done
- The best schema strategy for this contractor site is researched and documented
- All relevant structured data for the current site is implemented
- The chosen approach is scalable, maintainable, and aligned with AEO/GEO goals
- No invalid or fake schema is added

### Constraints
- Remove the old `2026-04-28` task entirely; leave only the new `2026-04-29` task in the schedule and body
- Keep the top intro/instruction section intact
- Respect the repo workflow conventions already established in the document
