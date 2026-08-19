# Design: Wizard AI Content Harness

## Technical Approach

Introduce a new `AI_Content_Harness` PHP service (Inc\Wizard) that encodes the 3-layer
prompt contract, per-layout field allowlists/blocklists, client-context filtering, and
required-data validation as class constants/methods — no markdown is read at runtime.
`Step_Home_Page_Builder` becomes a thin orchestrator: validate context → compose
system/user prompts → call provider → decode → validate against the layout allowlist →
save. The provider gains an optional `$system` parameter (BC-safe). All invented
fallbacks (badge years, testimonials, ratings, gallery labels, default URLs) are removed.

## Architecture Decisions

| Decision | Choice | Alternatives rejected | Rationale |
|----------|--------|----------------------|-----------|
| Prompt storage | PHP class constants/methods in `AI_Content_Harness` | Read `Wizard ai harness prompt guide.md` at runtime | Versioned, testable, no FS dependency, ships with theme |
| Page-type extensibility | `PAGE_*` constants; only `PAGE_HOME` implemented; unknown → PAGE_HOME + log | Hardcode Home only; throw on unknown | Future page builders reuse the service without structural change |
| Allowlist source of truth | Hand-curated maps keyed by layout, cross-checked vs `group_rms_page_sections.json` | Derive purely from ACF sub_fields | ACF lists image/url/select/shortcode/repeater fields the AI must NOT invent; needs explicit blocklist |
| Provider signature | Add optional 4th param `generate($model,$prompt,$context,$system='')` | New `generate2()` method; required param | Preserves existing single-arg callers; empty `$system` = legacy single-message body |
| Repeater counts | User-set per row via UI; injected as `{{item_count}}` | Hardcoded constants (current) | Spec requirement; removes magic numbers |

## Data Flow

    UI row (layout + item_count)
         │  payload: sections[] = [{layout, item_count}]
         ▼
    Step_Home_Page_Builder.run()
         │  validate_required_context(client_data) ──► missing? ► WP_Error (block, no AI call)
         ▼
    AI_Content_Harness
      get_layer1() + get_layer2(PAGE_HOME) ─► system message
      get_layer3(layout) {{item_count}},{{client_json}} ─► user message
         │            get_harness_context(client_data) (filtered)
         ▼
    Provider.generate(model, user, context, system) ─► JSON
         ▼
    decode_json_content() ─► validate_fields(layout, decoded)
         │  strip keys ∉ get_fillable_fields(); strip get_blocked_fields()
         ▼
    Content_Builder.build_page(section_only)

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `inc/wizard/class-ai-content-harness.php` | Create | New service: 3-layer prompts, `PAGE_*` consts, `get_harness_context`, `validate_required_context`, `get_fillable_fields`, `get_blocked_fields`, `validate_fields`. |
| `inc/wizard/class-step-home-page-builder.php` | Modify | Remove `generate_section_overrides` flat prompt, `section_data` invented switch, `services()` fallback list, `testimonials()`, badge defaults. Orchestrate via harness; per-row `item_count`; continue-on-error with placeholder. |
| `inc/wizard/class-ai-provider.php` | Modify | Add optional `$system = ''` param to `generate()`. |
| `inc/wizard/class-ollama-provider.php` | Modify | When `$system` non-empty, send `[system,user]` messages; else single `user` message (unchanged body). |
| `inc/wizard/class-client-data-fields.php` | Not modified | Approved-context filtering and required-field checks now live in `AI_Content_Harness` (`APPROVED_CONTEXT_FIELDS`, `REQUIRED_CONTEXT_FIELDS`, `validate_required_context`). `class-client-data-fields.php` was intentionally left unchanged; no coupling needed. |
| `inc/wizard/class-flexible-content-layouts.php` | Modify | `build_generic_section()` constrained to `get_fillable_fields()`; drop invented url/duration/radius defaults. |
| `inc/wizard/wizard-init.php` | Modify | Add numeric item-count input (layout-specific default) per section row template; section row carries `item_count`. |
| `src/ts/admin/wizard.ts` | Modify | Collect `{layout,item_count}` per row; render count input for repeater layouts; surface missing-data block warning. |
| `src/scss/admin/wizard.scss` | Modify | Style count control + harness warning banner. |

## Interfaces / Contracts

```php
final class AI_Content_Harness {
    public const PAGE_HOME='PAGE_HOME'; /* PAGE_ABOUT, PAGE_SERVICE, PAGE_LANDING, PAGE_BLOG, PAGE_CONTACT */
    public function get_layer1(): string;                          // global editorial system prompt
    public function get_layer2(string $page_type=self::PAGE_HOME): string;
    public function get_layer3(string $layout, int $item_count, array $client_context): string;
    public function get_harness_context(array $client_data): array; // strips media/color/social/IDs
    public function validate_required_context(array $client_data): array; // missing required keys
    public function get_fillable_fields(string $layout): array;
    public function get_blocked_fields(string $layout): array;
    public function validate_fields(string $layout, array $decoded): array; // allowlist + blocklist strip
}
```

Blocklist rule (derived from ACF types): every `image`, `url`, `select`, `*_form_shortcode`,
and invented `repeater` (testimonials/ratings/gallery/projects/stats/badges/faqs/slides/cards/
videos/cities/filters/reasons) is blocked; plus `about_badge_years`, `*_duration`, `*_radius`,
`hero_reviews_label`. Fillable = text/wysiwyg/textarea copy fields only (headlines,
subheadlines, text, descriptions, eyebrows, labels, cta/button text labels).

## Testing Strategy

| Layer | What | Approach |
|-------|------|----------|
| Unit | `validate_fields` strips unknown + blocked keys (hero, about-us, cta-v1, testimonials-v1) | PHPUnit per spec scenarios |
| Unit | `validate_required_context` returns/empties missing list; `get_harness_context` excludes logo_url/brand_color | PHPUnit |
| Unit | `get_layer2` defaults to PAGE_HOME; unknown type logs + falls back | PHPUnit |
| Unit | Ollama builds 2 messages with system, 1 without (BC) | PHPUnit w/ mocked HTTP |
| Integration | Step blocks on missing data (no provider call); item_count injected; failed section → placeholder, no abort | PHPUnit w/ fake provider |

## Migration / Rollout

No data migration. State key `home_sections` gains `item_count` per row (additive, back-compatible).
Ship behind no flag; revert via commit rollback restores prior prompt/provider behavior.
Delete the untracked guide file after integration (reference-only, never committed).

## Open Questions

- [ ] Are `cta_text`/`button_text` label values considered safe editorial copy, or should they
      use fixed harness defaults (risk: AI inventing "Call 555-...")? Leaning: allowlist as copy.
- [ ] Should `services_*_services` repeaters populate from `company_services` client data
      (not AI) while still respecting fillable sub_fields? Recommended: yes, client-sourced.
- [ ] Confirm exact required-context keys (company_name confirmed; city/state/services?).
