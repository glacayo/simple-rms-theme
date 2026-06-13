# Design: Wizard AI Prompt Guide Parity

## Technical Approach

Rewrite the three prompt layers in `AI_Content_Harness` as PHP heredoc/nowdoc strings mirroring the guide's editorial prose, and widen the allowlists to the six guide-supported text repeaters. PHP stays the runtime source of truth; `Wizard ai harness prompt guide.md` remains reference documentation only — never read at runtime — and its sync can ship separately when excluded from the implementation commit. Lean context is untouched (`company_name`, `company_covered_areas`, `company_services`). On any guide/product conflict, product criteria win (e.g., `about_badge_label` and `video_v1_video_title` stay fillable; `about_text` keeps 50–60 words per paragraph).

## Architecture Decisions

| Decision | Choice | Alternatives rejected | Rationale |
|----------|--------|----------------------|-----------|
| Prompt prose storage | Heredoc/nowdoc inside `get_layer1()`, `get_layer2()`, `layout_rules()` | Read markdown guide at runtime; const arrays of sentences | Matches existing harness pattern; testable; no FS dependency |
| Repeater sub-key contract | New `TEXT_REPEATER_FIELDS` const map: layout → `[repeater_field => [allowed sub-keys]]` | Flatten sub-keys into `FILLABLE_FIELDS` | `validate_fields()` needs row-shape awareness to strip blocked sub-keys (`stat_number`, `card_highlight`, `slide_bg_image`, `slide_cta_url`); mirrors existing `SERVICE_DESCRIPTION_FIELDS` pattern |
| `{{item_count}}` inside layout rules | Pass `$item_count` into `layout_rules()` (or run a second `strtr`) | Rely on the template `strtr` | `strtr` is single-pass: tokens inside the substituted `{{layout_rules}}` text are NOT re-replaced. Known gotcha — must be handled explicitly |
| Repeater placeholders on AI failure | `placeholder_copy()` skips repeater field names; builder generates `item_count` row-shaped placeholders via new `get_text_repeater_fields()` | Leave current string placeholder | Current fallback would write a string into an ACF repeater field, corrupting the field structure |
| `service_title`/`service_name` in blocklists | Keep blocked | Remove "for clarity" (exploration suggestion) | Explicit prompt signal that AI must never return service names; harness already strips them — removal adds risk for zero gain |
| Slider blocked image field name | Use ACF's actual `slide_bg_image` | Spec table's `slide_image` | ACF JSON is source of truth; spec table name is a typo — note it, don't propagate it |
| Layer 2 page types | Only PAGE_HOME prose; others keep fallback+warning | Encode all six page contexts now | Out of scope; dormant constants already exist |

## Data Flow

    UI row (layout + item_count)
         │
    Step_Home_Page_Builder.run()
         │  validate_required_context() ── missing? → WP_Error (no AI call)
         ▼
    AI_Content_Harness
      get_layer1() + get_layer2(PAGE_HOME)            → system message
      get_layer3(layout, item_count, context)
        = template + layout_rules(layout, item_count) → user message
         ▼
    Provider.generate(model, user, ctx, system) → JSON
         ▼
    decode_json_content() → validate_fields(layout, decoded)
         │  strip non-fillable keys; for TEXT_REPEATER_FIELDS keep only
         │  allowed sub-keys per row; sanitize each value
         ▼
    section_data() merges rows into ACF structure (existing array path)
    AI failure → row-shaped placeholders for repeaters, strings otherwise

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `inc/wizard/class-ai-content-harness.php` | Modify | Heredoc Layer 1 (editorial standards + no-invention), Layer 2 PAGE_HOME prose, per-layout `layout_rules()` for all layouts with fillable fields; add `TEXT_REPEATER_FIELDS`; add repeater keys to `FILLABLE_FIELDS` (slider, faq-v1/v2, vm-v1/v2, cta-v3); remove enabled wrappers/sub-keys from `BLOCKED_FIELDS` while keeping `slide_bg_image`, `slide_cta_url`, `stat_number`, `card_highlight` blocked; extend `sanitize_allowed_value()` for repeater rows; add `get_text_repeater_fields()` |
| `inc/wizard/class-step-home-page-builder.php` | Modify | Repeater-aware `placeholder_copy()` (skip repeater keys; emit `item_count` placeholder rows); no change to merge path — `section_data()`/`section_value()` already handle arrays |
| `Wizard ai harness prompt guide.md` | Deferred docs slice | Sync field classifications to shipped harness; document product deviations with rationale (lean context, `about_badge_label`, `video_v1_video_title`, 50–60w paragraphs, badges framing, service-name sourcing); bump version. Prepared locally but excluded from this commit per user request. |

## Interfaces / Contracts

```php
private const TEXT_REPEATER_FIELDS = [
    'slider'            => [ 'slider_slides' => [ 'slide_subheadline', 'slide_headline', 'slide_text', 'slide_cta_text' ] ],
    'faq-v1'            => [ 'faq_v1_faqs'   => [ 'faq_question', 'faq_answer' ] ],
    'faq-v2'            => [ 'faq_v2_faqs'   => [ 'faq_question', 'faq_answer' ] ],
    'vision-mission-v1' => [ 'vm_v1_cards'   => [ 'card_title', 'card_text' ] ],
    'vision-mission-v2' => [ 'vm_v2_reasons' => [ 'reason_text' ] ],
    'cta-v3'            => [ 'cta_v3_stats'  => [ 'stat_label' ] ],
];

public function get_text_repeater_fields( string $layout ): array;
private function layout_rules( string $layout, int $item_count ): string;
```

AI output contract per repeater layout (instructed in Layer 3): one JSON object whose repeater key holds an array of `item_count` row objects containing only allowed sub-keys.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | `validate_fields` keeps allowed repeater rows, strips `stat_number`/`card_highlight`/`slide_bg_image` sub-keys and unknown keys | PHPUnit-style per spec scenarios (no test infra exists yet — same as prior change; minimum: WP-CLI `wp eval` assertions) |
| Unit | `get_layer3` for `faq-v1` includes Q&A rules and resolved item count; `gallery-grid` rules instruct `{}` | Assert substrings of composed prompt |
| Integration | Builder merges `faq_v1_faqs` rows into section; AI failure yields row-shaped placeholders | Fake provider; inspect prepared section array |
| Manual | Full wizard run on local site; verify slider/FAQ/VM/cta-v3 render | Local by Flywheel site |

## Migration / Rollout

No migration. Prompt and allowlist changes only; state shape unchanged. Rollback = revert the PHP/TS/SCSS implementation files and OpenSpec artifacts; guide sync is deferred because the guide file is excluded from commit scope. Change likely exceeds the 400-line budget (per proposal risk) — use chained PR slicing where practical.

## Open Questions

- [ ] Chained PRs vs single PR for the >400-line risk — needs user decision before apply.
- [ ] If provider adherence drops with the full Layer 1 prose (small Ollama models), accept progressive layering as follow-up?
