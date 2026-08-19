# Proposal: Wizard AI Prompt Guide Parity

## Intent

The harness prompt layers (`get_layer1/2/3`) are single-sentence placeholders versus the rich editorial rules in `Wizard ai harness prompt guide.md`. AI output lacks per-layout word counts, paragraph structure, and field roles, and several guide-fillable repeaters are blocked even though the builder already consumes repeater shapes. Close the runtime gap first; guide re-alignment can ship as a separate documentation slice when excluded from commit scope.

## Scope

### In Scope
- Rewrite Layer 1 (global editorial standards) and Layer 2 (`PAGE_HOME` context) to guide fidelity.
- Add per-layout editorial rules (word counts, paragraph structure, field roles) to Layer 3 / `layout_rules()`.
- Enable guide-supported text repeaters: slider slides, FAQ items (v1/v2), vision/mission cards (v1) and reasons (v2), cta-v3 `stat_label`.
- Update `Wizard ai harness prompt guide.md` to current product decisions in a deferred documentation slice when included in commit scope.

### Out of Scope
- Re-adding `company_language`, phone, email, address, schedule, payment methods to context.
- Enabling factual/media/testimonial/project repeaters (testimonials, portfolio projects, gallery, area cities, badge items, video galleries, `stat_number`, `card_highlight`).
- Layer 2 wiring for non-Home page types (kept dormant).

## Capabilities

### New Capabilities
- `wizard-ai-prompt-guide`: Reference guide kept in sync with shipped harness contracts so future exploration does not start from stale instructions.

### Modified Capabilities
- `wizard-ai-content-harness`: Add layout-specific editorial rules; widen allowlists to guide-supported text repeaters while keeping factual/media/testimonial/project data blocked; preserve lean context (`company_name`, `company_covered_areas`, `company_services`) and service-name-only sourcing.
- `wizard-home-page-builder`: Decode/merge newly enabled repeater shapes from harness output.

## Approach

Mirror the guide's editorial prose into PHP heredoc strings rather than reading markdown at runtime. Per layout, move guide-fillable text-repeater fields from blocklist to allowlist; keep images, URLs, numbers, and factual records blocked. Preserve fixed rules: `about_text` exactly 3 paragraphs at 50–60 words each; `badges` framed as local directory/profile links (not why-choose-us); `about_badge_label` and `video_v1_video_title` stay fillable. Product criteria win over guide on any conflict.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `inc/wizard/class-ai-content-harness.php` | Modified | Rewrite all 3 layers; adjust allow/blocklists. |
| `inc/wizard/class-step-home-page-builder.php` | Modified | Decode/merge new repeater shapes. |
| `Wizard ai harness prompt guide.md` | Deferred | Sync to current decisions in a separate documentation slice; excluded from this commit scope. |
| `acf-json/group_rms_page_sections.json` | Reference | Source of truth, not modified. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Prompt bloat lowers provider adherence | Med | Use verbatim guide prose; progressive layering if needed. |
| Accidentally enabling factual repeater fields | Med | Per-field allowlist review against ACF JSON. |
| Change exceeds 400 lines | High | Ask before chained PR slicing. |

## Rollback Plan

Revert the harness and builder commits to restore placeholder layers and prior blocklists; revert the guide doc edit. No schema or data migration involved.

## Dependencies

- Existing builder repeater-decoding support.
- Current 27 ACF Flexible Content layouts.

## Success Criteria

- [ ] Each layout's AI output respects guide word counts and paragraph rules.
- [ ] Guide-supported text repeaters fill; factual/media/testimonial/project data stays blocked.
- [ ] `about_badge_label` and `video_v1_video_title` remain fillable; context stays lean.
- [ ] `Wizard ai harness prompt guide.md` matches shipped contracts when the deferred documentation slice is committed.
