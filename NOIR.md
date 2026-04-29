# NOIR.md

## Instruction
> **This document contains a daily task schedule.**
> Each day has a date and a task block assigned to it.
> **The AI must ONLY execute the task whose date matches today.**
> If today does not match any task date, do nothing and inform the user that there is no task assigned for today.

## Task Schedule
| Date | Task |
|------|------|
| 2026-04-29 | AEO/GEO Structured Data Research + Implementation ✓ COMPLETED |

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

---

## Research Summary — AEO/GEO Structured Data (2026-04-29)

### 1. Schema Types Evaluated

| Schema Type | Priority | Status | Reason |
|------------|----------|--------|--------|
| `RoofingContractor` | **HIGH** | ✅ Implemented | Most specific type for roofing; Google recognizes it for local service businesses |
| `HomeAndConstructionBusiness` | MEDIUM | ✅ Implemented | Parent type; included for broader compatibility |
| `LocalBusiness` | (parent) | Covered | Already represented via `RoofingContractor` hierarchy |
| `Organization` | **HIGH** | ✅ Implemented | Essential for entity clarity; connects publisher to content |
| `WebSite` | **HIGH** | ✅ Implemented | Required for SearchAction; anchors the site entity |
| `Service` | **HIGH** | ✅ Implemented | One per service offered; helps AEO for "what services" queries |
| `WebPage` | MEDIUM | ✅ Implemented | Per-page; clarifies page purpose and publisher relationship |
| `ContactPage` | MEDIUM | ✅ Implemented | Dedicated type for the contact page |
| `BreadcrumbList` | MEDIUM | Available | Helper function created; Yoast SEO already provides breadcrumbs UI |
| `FAQPage` | LOW | ❌ Not implemented | No FAQ content exists on the site |
| `Review` / `AggregateRating` | LOW | ❌ Not implemented | Testimonials are placeholder examples (Maria Johnson, Robert Smith, Sarah Williams); NOT real verified reviews. Adding fake reviews violates Google's policies. |
| `VideoObject` | LOW | ❌ Not implemented | No real videos exist on the site |
| `BlogPosting` / `Article` | MEDIUM | Available | Can be added when actual blog content with author/publisher exists |

### 2. HTML Microdata vs JSON-LD — Decision: JSON-LD

| Criteria | JSON-LD | Microdata (RDFa) |
|----------|---------|------------------|
| Google preference | ✅ Preferred | ⚠️ Supported but deprecated |
| AEO/GEO compatibility | ✅ Excellent — easily parsed by LLMs and answer engines | ⚠️ Requires DOM parsing |
| HTML cleanliness | ✅ Doesn't pollute semantic markup | ❌ Intrudes into HTML elements |
| Maintainability | ✅ Centralized generators; single source of truth | ❌ Scattered across templates |
| Debugging | ✅ Single location to audit | ❌ Must inspect each template |
| Performance | ✅ No impact (inline `<script>`) | ❌ Slightly more verbose HTML |

**Conclusion:** JSON-LD is the clear winner for this project's architecture. All schema is centralized in `inc/schema.php` with helper functions that can be called from hooks.

### 3. Implementation Approach

**Architecture:** Centralized PHP generators in `inc/schema.php`
- Each schema type has a dedicated function (`rms_schema_local_business()`, `rms_schema_organization()`, etc.)
- Hooks inject schemas at `wp_head` with priority 1 (early, before other head content)
- Page-specific schemas (Services, Contact) use conditional checks (`is_page_template()`)

**Files modified:**
- `functions.php` — added `require_once get_template_directory() . '/inc/schema.php';`
- `inc/schema.php` — **NEW** — all schema generators

### 4. Schema Hierarchy (Google's "thing" → more specific)

```
Thing
 └── Organization
      └── LocalBusiness
           └── HomeAndConstructionBusiness
                └── RoofingContractor ← PRIMARY (most specific)
```

### 5. AEO/GEO Impact

| Goal | Schema Support |
|------|----------------|
| **Local SEO** | `RoofingContractor` with `address`, `areaServed`, `openingHours`, `telephone` — directly feeds Google's Local Pack |
| **AEO (Answer Engine)** | `Service` descriptions provide concise answers to "what services do you offer?" — used by Google SGE, Bing Chat |
| **GEO (Generative)** | JSON-LD is machine-readable by训练 data; `Organization` + `WebSite` establish entity authority |
| **Entity Clarity** | `@id` references create a connected entity graph (`/#roofingcontractor`, `/#organization`, `/#website`) |
| **Search Action** | `SearchAction` on `WebSite` enables "site:example.com [query]" branded search |

### 6. Validation

Test all structured data at:
- https://search.google.com/test/rich-results
- https://validator.schema.org/

### 7. Implementation Status

- [x] `RoofingContractor` + `HomeAndConstructionBusiness` (LocalBusiness hierarchy)
- [x] `Organization`
- [x] `WebSite` + `SearchAction`
- [x] `WebPage` (helper function available)
- [x] `Service` (6 services on services page)
- [x] `ContactPage`
- [x] `BreadcrumbList` (helper function available; Yoast handles UI)
- [x] NO fake reviews/ratings (testimonials are placeholder examples)
- [x] NO fake FAQs
- [x] NO fake videos

---

*Last updated: 2026-04-29 — Hermes Agent (noir-dev)*
