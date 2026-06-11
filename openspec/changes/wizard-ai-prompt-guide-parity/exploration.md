# Exploration: wizard-ai-prompt-guide-parity

## Current State

`inc/wizard/class-ai-content-harness.php` implements a 3-layer prompt architecture (`get_layer1` / `get_layer2` / `get_layer3`) but each layer is a single-sentence placeholder compared to the richness in `Wizard ai harness prompt guide.md`. The ACF JSON (`acf-json/group_rms_page_sections.json`) defines 27 flexible content layouts on the `page_sections` field. Only `class-step-home-page-builder.php` uses the harness — and only for `PAGE_HOME`. All other page type constants (`PAGE_ABOUT`, `PAGE_SERVICE`, etc.) are declared but `get_layer2()` unconditionally falls back to `PAGE_HOME` with a log warning. The Layer 3 template is a flat `"Layout: X\nRequested item count: Y\nAllowed keys: Z\nBlocked keys: W\nClient JSON: ...\nReturn one compact JSON..."` — no layout-specific editorial rules, paragraph counts, word-length constraints, or field-by-field descriptions exist beyond two special cases (`about-us` 3-paragraph rule, `badges` local-directory rule). The builder already has repeater handling patterns for harness output, so repeater prompt parity should be evaluated as part of this change rather than rejected solely as unsupported.

### Harness vs Guide Gap Summary Table

| Layout | Guide Layer 3 detail | PHP parity? | What's missing |
|--------|---------------------|-------------|-----------------|
| `hero` | Full editorial rules, field descriptions, output contract | ❌ None | Field-by-field role descriptions, word counts, "MUST NOT FILL" list per spec |
| `slider` | Full Layer 3: slide fields, different angles rule, count | ❌ None | `FILLABLE_FIELDS` is `[]` — slider is fully blocked. Guide expects AI to fill 4 text fields per slide |
| `about-us` | 3 paragraphs, P1 positioning / P2 differentiation / P3 trust+CTA, min 50w each | ⚠️ Partial | Has 3-paragraph 50–60 word rule and `about_badge_label` remains intentionally fillable, but no paragraph-structure guidance (P1/P2/P3 purpose), no subheadline constraint ("must not restate headline") |
| `area-coverage-v1` | Eyebrow 2-4w, headline 6-12w w/ service area, description 50w+, cta_text 2-5w | ❌ None | All editorial constraints missing; AI gets flat allowed/blocked keys only |
| `badges` | Label only (2-4w), "Trusted & Certified" example, must-not-fill list | ⚠️ Partial | Has custom rule for local-directory framing; missing word count, example, and editorial role |
| `blog-v1` | Headline 6-12w, cta 2-5w, action verb first | ❌ None | No editorial constraints |
| `contact-info` | Headline 6-12w, intro 50w+, tone rules ("warm, brief, not a selling page") | ❌ None | No editorial constraints or tone guidance |
| `cta-v1` | Headline 6-12w, text 1 sentence 20w+, button 2-5w action verb | ❌ None | No editorial constraints |
| `cta-v2` | Same + primary/secondary button differentiation | ❌ None | No editorial constraints; no primary vs secondary guidance |
| `cta-v3` | Headline + button + stat_labels (2-5w each), stat_number blocked | ❌ None | `cta_v3_stats` and `stat_label` are in **BLOCKED_FIELDS** — but guide says AI SHOULD fill `stat_label`. Fillable only has headline + button_text |
| `faq-v1` | Headline 6-12w, subheadline 15-25w, Q&A pairs (Q natural, A 60w+), different aspects | ❌ None | `faq_v1_faqs`, `faq_question`, `faq_answer` all in **BLOCKED_FIELDS**. Only headline/subheadline are fillable. Guide expects AI to write full Q&A pairs |
| `faq-v2` | Same as faq-v1 | ❌ None | Same complete block |
| `gallery-grid` | Guide says "no AI-fillable fields, return {}" | ✅ Match | Both agree: empty fillable, all blocked |
| `portfolio-v1/2/3` | Headline + subheadline only; projects repeater blocked | ✅ Match | Fillable and blocked align with guide |
| `seo-content` | 3 paragraphs: P1 topic/local, P2 process/benefits, P3 trust/soft CTA, 50w+ each | ❌ None | No paragraph-structure rules; no P1/P2/P3 guidance |
| `services-v1/2/3` | Service titles from `service_name`, descriptions 40w+, benefit-focused | ⚠️ Partial | Service repeaters ARE fillable. But no editorial rules (40w min, benefit-focused constraint) are in the prompt. Blocklist incorrectly includes `service_title`/`service_name` |
| `testimonials-v1/2/3` | Headline + subheadline only; all testimonial data blocked | ✅ Match | Aligned |
| `video-v1` | Headline, subheadline, description 50w+, cta_text; video_url/duration/poster blocked | ⚠️ Partial | `video_v1_video_title` remains intentionally fillable for this implementation. Missing editorial constraints on the rest of the fields. |
| `video-v2` | Headline + subheadline only | ✅ Match | Aligned |
| `vision-mission-v1` | Eyebrow, headline, intro 50w+, cards (title 2-5w + text 40w+), cta | ❌ None | `vm_v1_cards`, `card_title`, `card_text`, `card_highlight` all in **BLOCKED_FIELDS**. Only eyebrow/headline/intro/cta are fillable. Guide expects AI to write all card content (title + text) |
| `vision-mission-v2` | Eyebrow, headline, vision_text 50w+, mission_text 50w+, reasons (1 sentence 20w+), cta | ❌ None | `vm_v2_reasons`, `reason_text` all in **BLOCKED_FIELDS**. Only eyebrow/headline/vision_text/mission_text/cta are fillable. Guide expects AI to write reason items |
| `schema` (Theme Options) | `schema_short_description` 2-3 sentences 40w+ | ❌ None | Schema not in harness at all — no layout entry, no fillable/blocked lists |

---

## Affected Areas

| File | Role | Impact |
|------|------|--------|
| `inc/wizard/class-ai-content-harness.php` | Single source of truth for all prompt contracts | **Heavy** — all 3 layers rewritten |
| `inc/wizard/class-step-home-page-builder.php` | Consumer: composes system+user, calls provider, merges results | **Light** — may need decoding logic update for new repeater shapes |
| `acf-json/group_rms_page_sections.json` | ACF layout definitions (read-only source of truth) | **None** — referenced for accuracy, not modified |
| `Wizard ai harness prompt guide.md` | Reference document (untracked) | **None** — never read at runtime or modified |

---

## Approaches

### 1. Full Parity — Rewrite All Three Layers

Rewrite `get_layer1()`, `get_layer2()`, and `get_layer3()` to match every editorial rule, editorial standard, field description, and output constraint from the guide. This means:

- **Layer 1**: Full system prompt with ROLE, EDITORIAL STANDARDS (paragraphs, headlines, subheadlines, eyebrows, CTA text, body copy, FAQ pairs, services copy, output format — all the exact prose from the guide).
- **Layer 2**: Page-specific context prompts. For now only `PAGE_HOME` is wired, but structure all six page types with the exact guide prose so future builders just plug in.
- **Layer 3**: Per-layout user message with field-by-field descriptions, word-count constraints, structural rules, and output-format specs. Enable currently-blocked repeater fields where the guide says AI should fill them (`slider_slides`, `faq_v1_faqs`, `faq_v2_faqs`, `vm_v1_cards`, `vm_v2_reasons`, `cta_v3_stats.stat_label`) while keeping truly factual repeaters blocked (testimonials, portfolio projects, gallery items, area cities, badge items, video galleries).
- **Pros**: Maximum AI output quality. Every layout gets the exact editorial guardrails the guide specifies. Future page builders are ready to use any page type.
- **Cons**: Large PR (probably 300-500 lines of prompt text alone). Risk of prompt bloat reducing provider adherence. Many repeater layouts need corresponding builder-side changes to handle new shapes.
- **Effort**: High

### 2. Pragmatic Mini-Slice — Fix the Most Glaring Gaps

Focus on the highest-impact, lowest-risk changes:

**Layer 1 (Global System Prompt)**: Rewrite with all editorial standards — this is the single biggest quality lever.

**Layer 2 (Page Context)**: Implement full `PAGE_HOME` context from the guide (already the only consumer). Structure other page types but keep them dormant.

**Layer 3 (Section Layouts)**: For layouts already enabled (`hero`, `about-us`, `area-coverage-v1`, `badges`, `blog-v1`, `contact-info`, `cta-v1`, `cta-v2`, `seo-content`, `services-v1/2/3`, `video-v1`, `testimonials-v1/2/3`, `portfolio-v1/2/3`, `video-v2`), add editorial rules from the guide. For layouts currently fully blocked that the guide says AI SHOULD fill (`slider`, `faq-v1`, `faq-v2`, `vision-mission-v1`, `vision-mission-v2`, `cta-v3`), add them **only if** the builder-side decoding already handles them or is trivial to extend.

- **Pros**: Focused, under 400 lines, immediate quality improvement for pages being built today. Low risk of breaking the builder since most changes are prompt-text-only.
- **Cons**: Some layouts remain with partial editorial guidance. Repeater-enabling deferred to a follow-up change for slider/faq/vm/cta-v3.
- **Effort**: Medium

### 3. Prompt Parity with Existing Builder Repeater Support

Adapt the guide's field-level instructions into `class-ai-content-harness.php`, including repeater sections where the builder can already consume structured harness output. Keep factual/media/testimonial/project data blocked, and preserve implementation-specific decisions such as lean context, service-name sourcing, `about_badge_label`, and `video_v1_video_title` remaining fillable.

- **Pros**: Better parity with the guide without starting from stale assumptions. Keeps the scope focused on prompt/contract quality while using existing builder support.
- **Cons**: Still requires careful review of each repeater allowlist/blocklist to avoid accidentally enabling factual data.
- **Effort**: Medium

---

## Recommendation

**Approach 3: Prompt Parity with Existing Builder Repeater Support.** This is the safest path given the review budget of 400 lines and the corrected project fact that the builder already supports repeaters.

**This change — `wizard-ai-prompt-guide-parity`**:
Rewrite `get_layer1()` and `get_layer2()` to closer guide fidelity. Add layout-specific editorial rules to `get_layer3()` / `layout_rules()` for all layouts, including repeater layouts supported by the builder. Keep `video_v1_video_title` and `about_badge_label` fillable per current product decision. Preserve service-name sourcing from `company_services.service_name` and keep factual/media/testimonial/project data blocked.

**Future change**:
Only needed if runtime testing shows a specific repeater layout still needs additional merge behavior beyond the existing builder support.

---

## Analysis Details: Allowlist/Blocklist Mismatches

### Confirmed Product Decisions to Preserve

| Layout | Field | Current status | Guide says | Fix |
|--------|-------|---------------|------------|-----|
| `video-v1` | `video_v1_video_title` | **FILLABLE** | Guide treats it as factual media | Keep FILLABLE per user correction/current implementation |
| `about-us` | `about_badge_label` | **FILLABLE** | Guide lists as "MUST NOT FILL" | Keep FILLABLE per user correction/current implementation |
| `services-v1` | `service_title` | **BLOCKED** | AI fills, but builder sources from client data anyway. Block is redundant but harmless since `service_rows()` overwrites title. | Remove from BLOCKED for clarity; harness already skips it via `sanitize_allowed_value` |
| `services-v2` | `service_title` | **BLOCKED** | Same as above | Same |
| `services-v3` | `service_name` | **BLOCKED** | Same as above | Same |
| `about-us` | `about_badge_years` | **BLOCKED** | Guide says blocked (factual) | ✅ Already correct |
| `area-coverage-v1` | `area_cities`, `city_name` | **BLOCKED** | Guide says blocked (factual) | ✅ Already correct |
| `hero` | `hero_bg_image`, `hero_reviews_label`, `hero_form_shortcode` | **BLOCKED** | Guide says blocked | ✅ Already correct |
| `cta-v3` | `cta_v3_stats`, `stat_number`, `stat_label` | All **BLOCKED** | Guide says AI SHOULD fill `stat_label`. `stat_number` blocked. `cta_v3_stats` is the repeater wrapper. | Re-evaluate during this change because builder repeater support exists |

### Intentional Differences (Keep)

| Difference | Reason |
|------------|--------|
| `APPROVED_CONTEXT_FIELDS` = `[company_name, company_covered_areas, company_services]` only | User decision: lean context. Guide says include city/state/country/phones/schedule/license/estimate. Not included for safety — avoids AI fabricating location-specific claims without verification. The `company_covered_areas` array already conveys location context. |
| `about_text` word count: 50–60 per paragraph (PHP) vs "minimum 50" (guide) | User decision: tighter upper bound prevents AI from writing 150-word paragraphs. Preserve the 50–60 constraint. |
| `badges` rule: "local directories or platforms where the business has a public profile" (PHP) vs just a label (guide) | User decision: contextual framing prevents "why-choose-us" invention. Preserve this rule and merge the guide's word-count + example constraints. |
| Service names sourced from `company_services.service_name` only (PHP) | User decision: AI never invents services. This is already aligned with the guide's Layer 3 instructions. Preserve. |
| No `company_phones`, `company_emails`, `company_schedule` in context | User decision: prevents AI from inventing phone numbers or embedding contact data in copy. Keep excluded. |
| No `company_language`, `company_payment_methods` in context | User decision: keeps context lean. Guide says include these when available. Keep excluded unless user changes mind. |

### Repeater Fields — Re-evaluate During This Change

These are currently blocked in PHP but the guide explicitly says AI should fill them. Because the builder supports repeaters, this change should evaluate whether each can be safely aligned now while keeping factual/media data blocked:

| Layout | Blocked Field | Guide says AI fills | Builder-side work needed |
|--------|--------------|---------------------|--------------------------|
| `slider` | `slider_slides[]` (slide_subheadline, slide_headline, slide_text, slide_cta_text) | Yes, 4 text fields per slide | Safe text-only repeater candidate; keep slide images and URLs blocked |
| `faq-v1` | `faq_v1_faqs[]` (faq_question, faq_answer) | Yes, full Q&A pairs | Safe editorial repeater candidate |
| `faq-v2` | `faq_v2_faqs[]` (faq_question, faq_answer) | Yes, full Q&A pairs | Safe editorial repeater candidate |
| `vision-mission-v1` | `vm_v1_cards[]` (card_title, card_text) | Yes, title+text per card | Safe editorial repeater candidate; keep `card_highlight` blocked |
| `vision-mission-v2` | `vm_v2_reasons[]` (reason_text) | Yes, one sentence each | Safe editorial repeater candidate |
| `cta-v3` | `cta_v3_stats[].stat_label` | Yes, label only (not number) | Safe label-only candidate; keep `stat_number` blocked |

---

## Risks

- **Prompt bloat reduces AI adherence**: The guide's Layer 1 alone is ~40 lines. Sending it as part of every request may cause some providers (especially smaller local models via Ollama) to ignore detailed instructions. Mitigation: keep Layer 1 as the guide specifies — it was designed for this purpose. If adherence drops, consider progressive layering.
- **Line budget overrun**: Even trimming to approach 3, 400 lines is tight once you factor in the full Layer 1 + Layer 2 + per-layout Layer 3 editorial rules. Mitigation: use multiline PHP heredoc/nowdoc strings that mirror the guide verbatim rather than inventing new prose; count carefully.
- **Guide-vs-product differences**: `about_badge_label` and `video_v1_video_title` remain fillable even though the guide is stricter. Mitigation: document these as intentional product decisions in proposal/spec/design so future agents do not "fix" them incorrectly.
- **Future builder consumers will need Layer 2 wiring**: This change only implements `PAGE_HOME` Layer 2. The structure for other page types exists but is dormant. When internal page builders are added, they'll need their own Layer 2 wiring. That's by design — this change is scoped to Home Page only.

---

## Ready for Proposal

**Yes.** The analysis is complete after user correction. The gaps are well-understood. The recommended approach (3: Prompt Parity with Existing Builder Repeater Support) respects the 400-line review budget, preserves all user-requested constraints, keeps `about_badge_label` and `video_v1_video_title` fillable, and evaluates guide-supported repeaters using the builder's existing capabilities. Proceed to `sdd-propose`.
