# Tasks: Wizard AI Prompt Guide Parity

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 420-560 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 -> PR 2 -> PR 3 |
| Delivery strategy | chained PR slices approved by user |
| Chain strategy | feature-branch-chain |

Decision needed before apply: Resolved for PR 2 by user-approved feature-branch-chain
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Prompt-layer rewrite and non-repeater layout rules | PR 1 | `class-ai-content-harness.php`; php -l included |
| 2 | Repeater contracts plus builder-safe merge/fallbacks | PR 2 | `class-ai-content-harness.php` + `class-step-home-page-builder.php`; depends on PR 1 |
| 3 | Guide sync and runtime verification notes | Deferred docs slice | `Wizard ai harness prompt guide.md`; prepared locally but excluded from this commit per user request |

## Phase 1: Prompt Contracts

- [x] 1.1 Rewrite `get_layer1()` and `get_layer2()` in `inc/wizard/class-ai-content-harness.php` as PHP heredoc/nowdoc prompts reflecting current Home-page rules, lean context, and no-invention constraints.
- [x] 1.2 Update `get_layer3()` and `layout_rules()` in `inc/wizard/class-ai-content-harness.php` to inject resolved `item_count`, keep PHP as runtime source of truth, and add layout rules for fillable non-repeater layouts.
- [x] 1.3 Preserve current product decisions in harness rules: `about_badge_label` fillable, `video_v1_video_title` fillable, `about_text` exactly 3 `<p>` paragraphs at 50-60 words, badges as local directory/profile links.

## Phase 2: Repeater Contracts and Allowlist Safety

- [x] 2.1 Add `TEXT_REPEATER_FIELDS` and `get_text_repeater_fields()` in `inc/wizard/class-ai-content-harness.php` for `slider_slides`, `faq_v1_faqs`, `faq_v2_faqs`, `vm_v1_cards`, `vm_v2_reasons`, and `cta_v3_stats`.
- [x] 2.2 Expand `FILLABLE_FIELDS`/`BLOCKED_FIELDS` in `inc/wizard/class-ai-content-harness.php` so supported text sub-keys are allowed while `slide_bg_image`, `slide_cta_url`, `stat_number`, `card_highlight`, testimonial/project/gallery/area factual data remain blocked.
- [x] 2.3 Extend `validate_fields()` and `sanitize_allowed_value()` in `inc/wizard/class-ai-content-harness.php` to accept only allowed repeater row keys and sanitize unknown/blocked sub-keys per spec scenarios.

## Phase 3: Builder Integration

- [x] 3.1 Update `placeholder_copy()` in `inc/wizard/class-step-home-page-builder.php` to skip string fallbacks for repeater fields and emit `item_count` row-shaped placeholders for supported text repeaters.
- [x] 3.2 Update `section_data()` and related helper flow in `inc/wizard/class-step-home-page-builder.php` to merge newly enabled repeater arrays safely, keep service names sourced from `client_data.company_services`, and strip extra AI keys before save.

## Phase 4: Guide Synchronization

- [ ] 4.1 Revise `Wizard ai harness prompt guide.md` to match shipped Layer 1/2/3 contracts, bump the version, and document lean context plus the approved product deviations. Deferred: file prepared locally but excluded from this commit per user request.
- [ ] 4.2 Update the guide's field classifications for slider, FAQ v1/v2, vision/mission v1/v2, and `cta-v3` repeaters; keep blocked factual/media/project fields and service-name-only sourcing explicit. Deferred: file prepared locally but excluded from this commit per user request.

## Phase 5: Verification

- [x] 5.1 Run `php -l` on changed PHP files and run `npm run build` only if the final diff touches tooling or assets that make the build relevant; record pass or justified skip.
- [ ] 5.2 Perform manual local wizard/runtime checks for `about-us`, `slider`, `faq-v1`, `vision-mission-v1`, `vision-mission-v2`, `cta-v3`, plus AI-failure fallback behavior because no PHPUnit harness exists.
