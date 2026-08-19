# Proposal: Wizard AI Content Harness

## Intent

Make Home Page Builder AI output safe, layout-aware, and business-data grounded. The current flat prompt and permissive fallbacks can invent facts, testimonials, ratings, URLs, gallery items, and badge claims that violate the harness rules.

## Scope

### In Scope
- Add a versioned PHP prompt/harness service for Home Page Builder, prepared for future internal page contexts.
- Support optional provider system prompts while preserving existing single-prompt compatibility.
- Filter Client Data to harness-approved context and block/warn when required data is missing.
- Validate/strip AI JSON to exact layout allowlists for all 27 Home ACF layouts.
- Let users set repeater item counts per selected section.

### Out of Scope
- Runtime-reading or committing `Wizard ai harness prompt guide.md`.
- Changing ACF JSON field groups.
- Harness integration for Generate Pages/internal pages beyond architecture preparation.
- Source-code implementation in this proposal step.

## Capabilities

### New Capabilities
- `wizard-ai-content-harness`: Versioned prompt contracts, client-context filtering, field allowlists/blocklists, and AI response validation for section content.

### Modified Capabilities
- `wizard-home-page-builder`: Require harness-guided generation, missing-data blocking/warnings, user-defined repeater counts, and no-invention fallbacks.
- `wizard-ai-configuration`: Extend provider generation to accept optional system/user message separation.

## Approach

Copy the useful guide content into PHP classes rather than reading markdown at runtime. Compose `system` and `user` prompt layers from the harness service, pass filtered Client Data, include per-section `item_count`, and validate decoded JSON against layout-specific fillable fields before saving.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `inc/wizard/class-step-home-page-builder.php` | Modified | Replace flat prompt/fallback invention with harness composition and validation. |
| `inc/wizard/class-ai-provider.php` | Modified | Add optional system prompt support. |
| `inc/wizard/class-ollama-provider.php` | Modified | Send system/user messages when available. |
| `inc/wizard/class-client-data-fields.php` | Modified | Expose harness-safe context and required-data checks. |
| `inc/wizard/class-flexible-content-layouts.php` | Modified | Use fillable/blocklist metadata for generic layouts. |
| `inc/wizard/wizard-init.php` | Modified | Add repeater-count UI fields. |
| `src/ts/admin/wizard.ts` | Modified | Collect item counts and surface warnings/blocks. |
| `src/scss/admin/wizard.scss` | Modified | Style harness validation/count controls. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Provider signature breakage | Med | Keep system prompt optional and fallback-compatible. |
| Field allowlist mismatch | Med | Cross-check against ACF JSON and strip unknown keys. |
| Change exceeds 400 lines | High | Ask before chained PR slicing, per strategy. |

## Rollback Plan

Revert the harness change commits to restore the previous Home Page Builder prompt/fallback behavior and provider interface compatibility path. Remove any new wizard state for section item counts if created.

## Dependencies

- Existing Ollama provider and AI configuration step.
- Current 27 ACF Flexible Content layouts as reference.
- Client Data collected before Home Page Builder.

## Success Criteria

- [ ] AI output never saves fields outside each layout allowlist.
- [ ] Missing required client data blocks or warns before generation.
- [ ] Repeater counts come from the user's selected section settings.
- [ ] No runtime dependency on `Wizard ai harness prompt guide.md`.
