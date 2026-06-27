# Proposal: Wizard AI Multi-Provider Support

## Intent

The wizard AI stack supports only Ollama Cloud, forcing every site onto a single hosted endpoint. Site owners need to use the credentials and models they already pay for. The provider architecture (`AI_Provider`, `AI_Provider_Registry`, `AI_Credential_Store`) is already abstracted for this — only one provider is registered. This change adds four first-class hosted providers as a pure registry extension.

## Scope

### In Scope
- Four `extends AI_Provider` classes: OpenAI, Anthropic, Google Gemini, OpenRouter.
- Register `openai`, `anthropic`, `google`, `openrouter` slugs in `AI_Provider_Registry::make_provider()` with labels.
- Live model listing per provider as the primary validation path (all four expose a list endpoint, including Anthropic via `GET /v1/models`); curated defaults and manual entry remain UI fallback/reference only. Credentials are persisted only after explicit successful validation.
- Administrator-only access to provider selection/config in the IA Generation form (existing WordPress admin gate; lower-privileged users excluded in v1).
- Block configuration with a warning when credential validation or model listing fails.

### Out of Scope
- OpenCode (deferred — `opencode serve` is session/agent-oriented, not a `chat/completions` contract).
- Provider fallback, auto-routing, multi-key rotation, usage/cost tracking.
- Multisite/network credentials (per-site only), streaming, DRY HTTP refactor (future Approach 2).
- Native WP AI APIs.

## Capabilities

### New Capabilities
- `wizard-ai-providers`: provider integration contract covering registry registration, per-provider HTTP `generate()`/`list_models()` wire shapes, credential gating, and configuration access rules.

### Modified Capabilities
- None. Harness, reviewer, builder, REST surface, and wizard UI are already provider-agnostic; no requirement-level behavior changes.

## Approach

Pure-registry extension (Approach 1). Each provider is a self-contained class using the WordPress HTTP API and `AI_Credential_Store::get($slug)`. The registry switch grows from one branch to four. OpenRouter is a distinct slug (own key, own model URL, optional `HTTP-Referer`/`X-Title`), not a flag on OpenAI. One selected provider used consistently — no fallback.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `inc/wizard/class-ai-provider-registry.php` | Modified | Four new `make_provider()` branches + labels |
| `inc/wizard/class-openai-provider.php` | New | chat/completions wire |
| `inc/wizard/class-anthropic-provider.php` | New | messages API, `GET /v1/models` listing/validation, `max_tokens` |
| `inc/wizard/class-google-provider.php` | New | generateContent, query-param key |
| `inc/wizard/class-openrouter-provider.php` | New | OpenAI-shape + attribution headers |
| `src/ts/admin/wizard.ts` | Modified | Confirm empty-`models[]` handling |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Response-shape divergence per provider | High | Per-class extraction; no shared universal parser |
| Anthropic 400 (missing `max_tokens` on Messages API) | Med | Default `max_tokens` 2048; live `GET /v1/models` validates the key, curated/manual stay as fallback |
| Gemini key leak (query param) | Med | `add_query_arg`; log host only, never full URL |
| 5 chained PRs exceed 400-line budget | Low | One provider per PR + registry wiring PR |

## Rollback Plan

Remove the slug branch(es) from `make_provider()`. Unknown slugs fall back to the `AI_Provider` no-op stub; stored credential options stay inert and harmless. No schema or data migration to revert.

## Dependencies

- Valid API keys per provider (user-supplied at config time).

## Success Criteria

- [ ] Each provider validates its credential, lists/accepts a model, and generates an `about-us` section via the IA Generation form.
- [ ] Selecting any provider routes generation through it with no harness/reviewer changes.
- [ ] Failed credential validation blocks configuration and shows the warning.
- [ ] Every modified/new PHP file is `php -l` clean; `tsc --noEmit` passes.
