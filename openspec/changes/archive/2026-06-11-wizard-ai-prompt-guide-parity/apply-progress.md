# Apply Progress: Wizard AI Prompt Guide Parity

## Slice

- Work unit: implementation parity plus deferred guide synchronization
- Delivery mode: chained PR slice
- Chain strategy: feature-branch-chain
- Tracker branch: `feature/wizard-setup`
- Review budget: 400 changed lines
- Boundary: PR 3 depends on PR 1 prompt-layer changes and PR 2 repeater/builder changes; it updates only guide/OpenSpec artifacts and stops before final verification/manual runtime checks.

## Completed Tasks

- [x] 1.1 Rewrote `get_layer1()` and `get_layer2()` in `inc/wizard/class-ai-content-harness.php` as PHP-encoded prompts with editorial standards, PAGE_HOME purpose/tone, lean context, and no-invention guardrails.
- [x] 1.2 Updated `get_layer3()` to pass resolved `item_count` into `layout_rules()` and expanded layout-specific rules for currently fillable fields without enabling deferred text repeaters.
- [x] 1.3 Preserved current product decisions: `about_badge_label` remains fillable, `video_v1_video_title` remains fillable, `about_text` requires exactly three `<p>` paragraphs of 50-60 words each, badges are framed as local directory/profile links, and service names remain sourced only from `company_services[].service_name`.
- [x] 2.1 Added `TEXT_REPEATER_FIELDS` and `get_text_repeater_fields()` for `slider_slides`, `faq_v1_faqs`, `faq_v2_faqs`, `vm_v1_cards`, `vm_v2_reasons`, and `cta_v3_stats`.
- [x] 2.2 Expanded allow/block lists so supported text repeater wrappers are fillable while media, URLs, testimonials, project/gallery data, area cities, badge items, `stat_number`, and `card_highlight` remain blocked.
- [x] 2.3 Extended validation/sanitization to keep only allowed repeater row subfields and discard unknown or blocked subkeys before builder merge.
- [x] 3.1 Made builder fallback copy repeater-aware so supported text repeaters receive row-shaped placeholders instead of scalar strings.
- [x] 3.2 Updated builder merge flow so text repeater arrays merge safely, service repeaters remain sourced from `client_data.company_services`, and extra AI keys remain stripped by harness validation before save.
- [ ] 4.1 Revised `Wizard ai harness prompt guide.md` to match shipped Layer 1/2/3 contracts, bumped the version to 1.2, and documented lean context plus approved product deviations. Deferred: file prepared locally but excluded from this commit per user request.
- [ ] 4.2 Updated guide field classifications for slider, FAQ v1/v2, vision/mission v1/v2, and `cta-v3` text repeaters while keeping blocked factual/media/project fields and service-name-only sourcing explicit. Deferred: file prepared locally but excluded from this commit per user request.

## Files Changed

| File | Action | What Was Done |
|------|--------|---------------|
| `inc/wizard/class-ai-content-harness.php` | Modified | Added text repeater contracts, updated allow/block lists, added repeater-aware validation/sanitization, and updated repeater layout rules for PR 2 layouts. |
| `inc/wizard/class-step-home-page-builder.php` | Modified | Added repeater-aware placeholders and kept service repeater rows on the existing client-data-sourced merge path. |
| `Wizard ai harness prompt guide.md` | Local only / not staged | Prepared synchronization locally but intentionally excluded from commit scope per user request. |
| `openspec/changes/wizard-ai-prompt-guide-parity/tasks.md` | Modified | Marks Phase 4 guide synchronization as deferred and records verification status. |
| `openspec/changes/wizard-ai-prompt-guide-parity/apply-progress.md` | Modified | Merged PR 1 and PR 2 progress with deferred guide synchronization status and verification notes. |

## Verification

- Previously passed in PR 2: `php -l inc/wizard/class-ai-content-harness.php`.
- Previously passed in PR 2: `php -l inc/wizard/class-step-home-page-builder.php`.
- Previously skipped in PR 2: `npm run build` because no tooling, scripts, or asset files were changed.
- Skipped for PR 3 guide-sync slice: PHP/build verification because only Markdown/OpenSpec files changed in this slice.
- Not performed in this slice: final verification/manual local wizard/runtime checks; user asked to be notified before verification.

## Deviations from Design

- None for the PR 3 scope. The guide now follows PHP as runtime source of truth and documents approved product deviations instead of reintroducing older guide-only behavior.

## Remaining Tasks

- [ ] Phase 5: Final verification/manual local wizard/runtime checks.
