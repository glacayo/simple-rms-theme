## Exploration: Wizard AI Multi-Provider Support

### Current State

The wizard AI stack is already abstracted around a thin provider interface, with Ollama Cloud as the only registered implementation. All consumers (Home Page Builder, Generate Pages, AI Content Reviewer) reach the provider through `AI_Provider_Registry::make_provider()`, so the surface for adding new providers is narrow and well-bounded.

**Provider interface (already in place):**

| Member | Signature | Notes |
|--------|-----------|-------|
| `AI_Provider::generate` | `generate(string $model, string $prompt, array $context = [], string $system = ''): array{success,content,error?}` | Optional `$system` param added by the `wizard-ai-content-harness` change. Empty `$system` keeps legacy single-message behavior. |
| `AI_Provider::list_models` | `list_models(): array<int,{id,label}>|WP_Error` | Used as the "Test / Load models" admin action. |
| `AI_Provider::cache_content` | `protected` | Session/section-keyed transient cache (24h). |

**Registry (the single switch point):**

- `AI_Provider_Registry::list_providers()` exposes the `rms_wizard_ai_providers` filter. The default list contains only `[ {slug: 'ollama-cloud', label: 'Ollama Cloud'} ]`.
- `AI_Provider_Registry::default_provider()` returns the first registered slug.
- `AI_Provider_Registry::provider_exists()` and `::get_provider_label()` iterate the filtered list.
- `AI_Provider_Registry::make_provider( $provider, $api_key = '' )` is the only `if/else` over concrete provider classes (line 64-68: `if 'ollama-cloud' === $provider return new Ollama_Provider(...)`; else `return new AI_Provider( $api_key )` as a no-op stub). All five consumers — `Step_Home_Page_Builder`, `Step_Generate_Pages`, `AI_Content_Reviewer`, `Rest_Controller::list_ai_models`, `wizard-init` IA Generation form — go through this single switch.

**Credentials (already provider-agnostic):**

- `AI_Credential_Store` keys encrypted API keys under `rms_wizard_ai_credential_{slug}` (sodium / OpenSSL AES-256-GCM with WP salt fallback). `save()`, `get()`, `has()`, `mask_status()`, `status()` are all provider-keyed. Multiple providers are supported today, just only one is registered.

**Wizard admin UI (already provider-agnostic):**

- IA Generation form (`wizard-init.php` lines 386-428) iterates `AI_Provider_Registry::list_providers()` to render the `<select data-wizard-ai-provider>`, `AI_Credential_Store::mask_status()` for credential display, and the `Manual model name` fallback input (always present, useful as an override or when a live list call is slow).
- TypeScript `wizard.ts` (lines 595-609, 1261-1303, 1313-1390) treats the provider as a free-form string. Model select is populated from the `POST /rms-wizard/v1/ai/models` response; if empty, the manual input is used. Adding a new provider will surface automatically through the existing dropdown.
- `state.ai_config` already carries `{provider, provider_label, model, credential, has_credentials, configured_at}`.

**REST surface (already provider-aware):**

- `POST /rms-wizard/v1/ai/models` body `{provider, api_key}`. The provider is sanitized, validated against `provider_exists()`, and the key is encrypted and saved on success.

**Prompt contract (already supports the four target providers):**

- `AI_Content_Harness` emits `system = L1 + L2(page_type)` and `user = L3(layout, item_count, client_context)` and calls `$provider->generate($model, $user, $context, $system)`. OpenAI, Anthropic, Google Gemini, and OpenRouter (which inherits the OpenAI chat-completions contract) all accept a system instruction paired with a user message, so no prompt surgery is needed.

**Reference docs (preserved untracked):**

- `Wizard ai harness prompt guide.md` (root) — reference only, not committed; do not touch.
- `wizard-prd.html` (root) — product requirements; do not touch.

### Affected Areas

#### Files That Already Need No Change (verifies the abstraction is sound)

| File | Why it stays put |
|------|------------------|
| `inc/wizard/class-ai-provider.php` | Abstract base with optional `$system` already in the right shape. |
| `inc/wizard/class-ai-credential-store.php` | Provider-agnostic. `OPTION_PREFIX . sanitize_key($slug)` already keys per provider. |
| `inc/wizard/class-ai-content-harness.php` | Emits system/user messages; no provider-specific logic. May gain one helper (see Recommendation). |
| `inc/wizard/class-ai-content-reviewer.php` | Already calls `AI_Provider_Registry::make_provider()->generate()`; no provider coupling. |
| `inc/wizard/class-step-home-page-builder.php` | Provider-agnostic; uses `AI_Provider_Registry::make_provider($provider)->generate(...)`. |
| `inc/wizard/class-step-generate-pages.php` | Same; uses the registry. |
| `inc/wizard/wizard-init.php` | IA Generation form is already generic over `list_providers()`. |
| `inc/wizard/class-rest-controller.php` | `list_ai_models` already accepts provider+key and returns models. |

#### Files That Need Modification

| File | Required change | Why |
|------|----------------|-----|
| `inc/wizard/class-ai-provider-registry.php` | Add `if/else` branches for `openai`, `anthropic`, `google`, and `openrouter` in `make_provider()`. Each branch returns `new <Slug>_Provider( $api_key )`. Keep the unknown fallback (`new AI_Provider`) intact. The `opencode` slug is a separate decision tracked under Open Question 2. | Single switch point — see `class-ai-provider-registry.php:64-68`. |
| `inc/wizard/class-ollama-provider.php` | Optionally rename internal labels from "Ollama Cloud" to clarify it is a hosted endpoint. No behavior change required. | Cosmetic only. |
| `src/ts/admin/wizard.ts` | Confirm the model-picker handles an empty `models[]` response without breaking the flow. The manual-model fallback already exists at `wizard.ts:421-424` (UI) and `wizard.ts:597-599` (collection). | Provider UX parity. |

#### Files That Must Be Created

| File | Purpose |
|------|---------|
| `inc/wizard/class-openai-provider.php` | `extends AI_Provider`. Calls `https://api.openai.com/v1/chat/completions` with `Authorization: Bearer <key>` and `messages: [system?, user]`. List models via `https://api.openai.com/v1/models`. |
| `inc/wizard/class-anthropic-provider.php` | `extends AI_Provider`. Calls `https://api.anthropic.com/v1/messages` with `x-api-key: <key>`, `anthropic-version: 2023-06-01`, body `{model, system, messages: [user], max_tokens}`. `list_models()` calls `GET https://api.anthropic.com/v1/models` (real listing) and doubles as the credential-validation check; curated defaults remain only as a UI fallback/reference. |
| `inc/wizard/class-google-provider.php` | `extends AI_Provider`. Calls `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key=<key>` with `systemInstruction.parts[0].text` and `contents: [{role: user, parts: [{text: $prompt}]}]`. List models via `https://generativelanguage.googleapis.com/v1beta/models?key=<key>`. |
| `inc/wizard/class-openrouter-provider.php` | `extends AI_Provider`. **First-class hosted provider.** OpenAI-compatible at `POST https://openrouter.ai/api/v1/chat/completions` with `Authorization: Bearer <key>`. Optional `HTTP-Referer` and `X-Title` headers for app attribution. List models via `GET https://openrouter.ai/api/v1/models` (Bearer). |
| `inc/wizard/class-opencode-provider.php` (DEFERRED — see Open Question 2) | `extends AI_Provider`. **Not first-class for v1.** Optional local/dev provider only. See Risks for why OpenCode Server is not equivalent to OpenAI/OpenRouter/Anthropic/Gemini. |

### Current Code That Already Supports the Target Providers

| Capability | Today | New provider needs |
|------------|-------|-------------------|
| Provider registry with extension filter | `rms_wizard_ai_providers` | New branch in `make_provider()` |
| Per-provider encrypted credentials | `AI_Credential_Store::save/get/has` | None — already works |
| System + user message split | `generate(..., $system = '')` | None — already works |
| Model list via REST | `POST /ai/models` | None — already works |
| Test connection via model list | "Test / Load models" button | None — already works |
| Manual model fallback | `Manual model name` input | None — already works (override/reference only; all four providers have live listing) |
| Provider label + dropdown | `list_providers()` + filter | New label entries |
| Harness prompt layers L1/L2/L3 | `AI_Content_Harness` | None — provider-agnostic |
| Reviewer dispatches through registry | `AI_Content_Reviewer:209` | None — provider-agnostic |
| State schema `ai_config.{provider,provider_label,model,...}` | `Step_Controller:200-207` | None |

### Approaches

1. **Pure-registry extension (recommended)** — Implement `OpenAI_Provider`, `Anthropic_Provider`, `Google_Provider`, and `OpenRouter_Provider` as new `extends AI_Provider` classes. Each implements `generate()` and `list_models()` with the provider's HTTP contract. Register the four new slugs in `AI_Provider_Registry::make_provider()`. `OpenCode_Provider` is a separate decision (see Open Question 2) and is intentionally excluded from the v1 first-class list because OpenCode Server (`opencode serve`) is session/agent-oriented, not a clean text-generation contract. No changes to harness, reviewer, builder, UI, or REST routes.
   - Pros: Minimal blast radius. All existing extension points are reused. Single switch in `make_provider()` grows from one branch to four first-class providers. OpenRouter reuses the OpenAI shape (both speak the OpenAI chat-completions contract), so its parser shares the same extraction path. Each provider is a self-contained ~150-220 line file. Easy to test in isolation.
   - Cons: Some duplication of HTTP boilerplate across providers. The `AI_Adapter` class (custom WP HTTP API with retry/backoff) is not currently used by `Ollama_Provider` — that pattern is worth aligning on (see Recommendation).
   - Effort: Medium

2. **Shared HTTP core with provider-specific shape parsers** — Refactor common HTTP + retry + caching into a `Base_HTTP_Provider` (or extend `AI_Adapter`), and add a thin shape-translation layer per provider (e.g., `parse_chat_completion( $body )` that returns the assistant text). Each provider only owns endpoint URLs, headers, and the shape parser.
   - Pros: DRY. Retry/backoff/cache centralized. New providers become 30-50 lines. The `AI_Adapter` already supports OpenAI/Anthropic/Gemini response shapes via its `extract_content()` paths — that class was previously a stand-in for any provider.
   - Cons: Larger refactor. Touches the shared base class. May regress `Ollama_Provider` if not done carefully. Could exceed the 400-line budget without chained PRs.
   - Effort: Medium-High

3. **Native WP AI bridge** — Use WordPress AI Client (introduced in WP 6.9 alpha, if available) as a meta-provider.
   - Pros: Future-proof if the project moves to WP 6.9+.
   - Cons: Project requires PHP 8+ and currently targets WP 6.x where the AI client is not present. Theme does not depend on WP AI APIs per `wizard-ai-content-reviewer` spec ("Native WordPress AI APIs MUST NOT be used"). Out of scope.
   - Effort: High, not recommended

### Recommendation

**Approach 1 — Pure-registry extension with a small shared helper.**

Each new first-class provider (`OpenAI_Provider`, `Anthropic_Provider`, `Google_Provider`, `OpenRouter_Provider`) is a self-contained `extends AI_Provider` class in `inc/wizard/`. A tiny shared trait/helper (`AI_Provider_HTTP_Helper`) wraps the cross-provider concerns that today live in `Ollama_Provider` (request encoding, response code handling, transient cache, error mapping) so the four new classes are ~120-150 lines each instead of duplicating ~50 lines of HTTP plumbing. `AI_Adapter` (lines 15-141) already implements a generic retry+backoff+cache wrapper that providers can delegate to instead of inlining their own loops; reusing it removes duplication without forcing Ollama to change shape.

**OpenRouter is a real provider, not a flag on OpenAI.** It has its own slug, its own credential option, its own model list (curated defaults from `https://openrouter.ai/api/v1/models`), and optional `HTTP-Referer` / `X-Title` headers for app attribution. It happens to share the OpenAI chat-completions shape, which simplifies `generate()` parsing, but the user wants a single OpenRouter key spanning many upstream models — that is a different product positioning and MUST be a first-class class file, not a thin wrapper.

**OpenCode is intentionally excluded from the v1 first-class list.** Per the research notes, OpenCode Server (`opencode serve`) is session/agent-oriented and is not a drop-in text-generation contract. Whether it lands in v1 at all (as a local/dev-only optional provider) is the single decision still pending — see Open Question 2. The default recommendation is to defer it.

Why:

- The registry is the single switch point. Adding four first-class providers is four `if/else` lines plus four files. The switch in `make_provider()` grows from one branch to four.
- The harness already produces system/user messages, the reviewer already routes through the registry, the wizard UI already iterates `list_providers()`, and the REST surface already accepts arbitrary providers. **No cross-cutting change is required** — this is the cleanest possible extension.
- The chained-PR strategy (400-line budget, `force-chained`) maps 1:1 to one provider per PR plus a registry wiring PR. Each PR is a reviewable work unit: add provider, register slug, manual verify, ship.
- The `Wizard ai harness prompt guide.md` remains untouched. The new providers receive the same system + user message contract that Ollama already accepts; no provider-specific prompt surgery is required.
- Adding OpenRouter does not bloat the prompt contract or the registry more than adding OpenAI does — it is a first-class peer, not a meta-provider.

The recommendation assumes **Approach 1** is the chosen path. If the user later wants DRY HTTP plumbing, **Approach 2** is the natural follow-up change. If OpenCode is later in-scope (Open Question 2 = Option A), it lands as a separate, non-uniform PR with its own contract notes.

### Per-Provider Wire Details (for the eventual `sdd-design`)

| Provider | Endpoint | Auth | System field | User field | List models |
|----------|----------|------|--------------|------------|-------------|
| OpenAI | `POST https://api.openai.com/v1/chat/completions` | `Authorization: Bearer <key>` | `messages[].role=system` | `messages[].role=user` | `GET https://api.openai.com/v1/models` (Bearer) |
| Anthropic | `POST https://api.anthropic.com/v1/messages` | `x-api-key: <key>`, `anthropic-version: 2023-06-01` | top-level `system` | `messages[].role=user` | `GET https://api.anthropic.com/v1/models` (`x-api-key` + `anthropic-version`); returns `data[].{id,display_name}` |
| Google Gemini | `POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key=<key>` | query `?key=` | `systemInstruction.parts[0].text` | `contents[].parts[0].text` | `GET .../v1beta/models?key=<key>` |
| **OpenRouter** | `POST https://openrouter.ai/api/v1/chat/completions` | `Authorization: Bearer <key>` (+ optional `HTTP-Referer`, `X-Title` for app attribution) | `messages[].role=system` | `messages[].role=user` | `GET https://openrouter.ai/api/v1/models` (Bearer) |
| Opencode (DEFERRED) | Not applicable for v1. `opencode serve` exposes session/message/config APIs, not a public `chat/completions` contract. See Open Question 2. | n/a | n/a | n/a | n/a |

### Risks

- ~~**Opencode identity is ambiguous.**~~ **RESOLVED.** `Opencode` = `https://opencode.ai/` (sst/opencode AI coding agent/client). OpenRouter added as a first-class provider per user request. See Open Question 2 for the remaining scope decision.
- **OpenCode Server is not equivalent to OpenAI/OpenRouter.** `opencode serve` exposes `sessions`, `messages`, `config`, `providers` — an agent/session-oriented surface, NOT a `chat/completions` text-generation contract. The docs reviewed do not show a public external inference endpoint suitable for a production cPanel-hosted WordPress theme. Implementing it as a "fourth cloud provider" would be wrong on the merits; even as a local/dev option, it requires a reachable `opencode serve` URL + password and only serves the contractor's workstation.
- **OpenRouter shares the OpenAI wire shape but is a distinct provider.** Treat it as a separate slug (`openrouter`), not a flag on `openai`. Reasons: (1) its `models` list comes from a different URL with different model IDs; (2) it has app-attribution headers (`HTTP-Referer`, `X-Title`) that OpenAI does not use; (3) credentials are independent; (4) the user wants a single OpenRouter key to span many upstream models, which is a different product positioning from going direct to OpenAI.
- **Response shape divergence.** OpenAI returns `choices[0].message.content`; Anthropic returns `content[0].text` (with `stop_reason`); Gemini returns `candidates[0].content.parts[0].text`; **OpenRouter returns the OpenAI shape** (`choices[0].message.content`). Each provider must implement its own extraction; do not rely on the universal paths in `AI_Adapter::extract_content()` for production correctness — they are a best-effort fallback, not a contract. OpenRouter can share an extraction helper with OpenAI but MUST live in its own class.
- **Anthropic requires `max_tokens`.** Must be set explicitly on the Messages API; default to a generous value (e.g. 2048) and document it. Omission causes a 400.
- **Anthropic exposes a real list-models endpoint.** `GET https://api.anthropic.com/v1/models` (headers `x-api-key` + `anthropic-version: 2023-06-01`) returns `data[].{id,display_name,...}`. The provider's `list_models()` SHOULD call it and map `id`/`display_name`; a successful response also validates the credential. Curated defaults (e.g. `claude-3-5-sonnet-latest`) remain only as a UI fallback when the live call cannot run. The manual-input field stays as an override, not the primary path.
- **Gemini auth is a query parameter, not a header.** Easy to leak in server logs if the URL is not sanitized. Mitigation: use `add_query_arg` once, log only the host, never the full URL.
- **OpenRouter attribution headers are optional but recommended.** Per OpenRouter docs, `HTTP-Referer` and `X-Title` identify the app for ranking and are sent as plain headers (not secrets). They can default to the site URL and the plugin/theme name; the user can override them in the admin UI later if needed. Do NOT log them at error level.
- **Cost/quotas are out of scope.** This change wires the providers; it does NOT add usage tracking, budget enforcement, or per-provider rate limiting. The reviewer already short-circuits to a fallback on provider failure (N=2 max passes per section), so the worst case is a failed generation, not runaway spend.
- **No API keys in code, logs, or chat.** The credential store already enforces this. The new providers MUST only read the key from `AI_Credential_Store::get($slug)` and never log it. The dev `Wizard ai harness prompt guide.md` and `wizard-prd.html` stay untracked.
- **Chained PR strategy budget.** Each new provider is ~150-220 lines including the class file, one switch line, one label. Four first-class providers + a registry wiring PR fits 5 chained PRs. If a shared HTTP helper is added (Approach 2), budget may push to Medium. Mitigate by shipping one provider per PR. Adding OpenCode (if v1 scope expands) would be a 6th chained PR with a non-uniform contract — flag for re-budget.
- **Tests:** the project has no automated test runner (`openspec/config.yaml: testing.strict_tdd: false`, `quality_tools.linter: available false`). Each provider should ship with a `php -l`-clean file and a manual smoke test (save a key, list models, generate a `about-us` section). The proposal phase should not promise PHPUnit coverage it cannot deliver.
- **System message format differences.** Anthropic rejects empty `system`; OpenAI tolerates it; Gemini expects a non-empty `systemInstruction.parts[].text` only if used; **OpenRouter inherits OpenAI's tolerance**. The harness always passes a non-empty `$system` (Layer 1 + Layer 2), so this is a non-issue in practice — but each provider should still guard against the empty-system case for callers that bypass the harness.

### Open Questions

1. ✅ **RESOLVED — "Opencode" identity and provider list.** User clarified: `Opencode` = `https://opencode.ai/` (sst/opencode AI coding agent/client). OpenCode Zen/Go are providers *inside* OpenCode. User also explicitly requested **OpenRouter** as a first-class hosted provider (OpenAI-compatible gateway, distinct from OpenCode). First-class v1 provider list is now: **OpenAI, Anthropic, Google Gemini, OpenRouter**.

2. **Should OpenCode be in v1 scope at all, or deferred?** (NEW blocking decision — orchestrator must surface this to the user before launching `sdd-propose`.)
   - **Option A: Local/dev-only OpenCode provider.** Implement `OpenCode_Provider` pointing at a user-configured `opencode serve` base URL + password. Slug: `opencode`. UI: optional "Base URL" + "Password" fields, gated behind a "local dev only" notice. NOT recommended for cPanel/production deployments.
   - **Option B: Defer entirely.** Ship v1 with the four first-class hosted providers and revisit OpenCode only when (and if) a documented public inference endpoint equivalent to `chat/completions` is published. Avoids forcing the wizard's "system + user → assistant text" contract onto a session/agent API that was not designed for it.
   - **Recommended default:** **Option B (defer).** The wizard's prompt contract is text-generation. OpenCode Server's documented surface is session/message-oriented. Forcing a mapping would either be lossy (drop the agent capabilities) or invasive (build a session lifecycle in the wizard), neither of which is justified for v1. If the user later wants it, it lands as a standalone change with its own PR and its own risks.

3. **Should each provider ship with a curated default model list, or rely on live listing?** All four providers (OpenAI, Anthropic, Gemini, OpenRouter) expose a live `list_models()` endpoint, which is the primary validation+listing path but can be slow. Recommend: ship curated defaults (e.g. `gpt-4o-mini`, `gpt-4o`, `claude-3-5-sonnet-latest`, `gemini-2.0-flash`, `gemini-1.5-pro`, plus a small OpenRouter default like `openai/gpt-4o-mini`, `anthropic/claude-3.5-sonnet`, `google/gemini-2.0-flash-001`) **only as a UI fallback/reference** so the dropdown is not empty before the user clicks "Test / Load models". Curated defaults never replace the live validation call.

4. **Per-provider max_tokens defaults.** Anthropic requires it. Suggest a single `AI_Provider::default_max_tokens()` constant (e.g. 2048) and let each provider override. Confirm.

5. **Provider fallback policy.** If the configured provider fails (timeout, 5xx, auth), do we (a) bubble the error to the wizard UI only, or (b) try the next registered provider as fallback? The reviewer already silently falls back to the original decoded content; the Home Page Builder already logs a warning and returns `[]`. Recommend: keep the current single-attempt behavior, surface the provider error in the wizard log, do NOT auto-fallback. Multi-key rotation is a future concern.

6. **Does Google Gemini need to support multi-turn (`contents[]` with prior `user`/`model` turns)?** Today the harness sends one user turn. The reviewer also sends one user turn per pass. Recommend: keep single-turn for v1; do not pre-populate `contents[]` with history.

7. **Should the IA Generation form be extended with a separate "Test connection" button?** Not needed: `list_models` is the test, and it now works for all four providers (OpenAI, Ollama, Gemini, OpenRouter, and Anthropic via `GET /v1/models`). Recommend: keep `list_models` as the single validation path. A successful response validates the credential and lets it persist; a failed response blocks the save and shows the warning. Curated defaults may render before the user types a key, but the credential is never persisted from curated/empty lists alone.

8. **Region/data residency.** The user is a contractor; data sensitivity is moderate. OpenAI/Anthropic/Google/OpenRouter all offer EU regions. Out of scope for v1 — recommend documenting it as a future configuration knob (per-provider `api_base` override).

9. **Does the "Opencode" provider need to support streaming?** N/A for v1 — OpenCode is deferred (Open Question 2). For the other four, today Ollama is non-streaming. Keep all new providers non-streaming for v1 to keep the response shape simple and the JSON-decode path stable.

### Ready for Proposal

**Conditionally yes — one decision still needed.** The architecture is already abstracted for this change. **Open Question 1 is resolved** (`Opencode` = `https://opencode.ai/`; OpenRouter added as a first-class provider). The remaining blocker is **Open Question 2** — should OpenCode be in v1 (local/dev-only optional) or deferred entirely. Default recommendation: **defer OpenCode**; ship v1 with the four first-class hosted providers (OpenAI, Anthropic, Google Gemini, OpenRouter).

Once Open Question 2 is answered, the orchestrator can proceed to `sdd-propose` with:

- 5 chained PRs (registry wiring + four first-class providers) sized to stay under the 400-line budget. If OpenCode is in scope, it becomes a 6th chained PR with explicit non-uniform-contract notes.
- Per-provider smoke-test plan using the existing IA Generation form
- A clear rollback path: remove the slug from `AI_Provider_Registry::make_provider()` and the credential option remains inert

If the user wants a single PR instead of chained, this becomes a Medium-High risk for the 400-line budget and the orchestrator should default to chained per the session preflight (`chained_pr_strategy: force-chained`).

### Next Phase

`sdd-propose` — once the OpenCode scope decision (in v1 vs deferred) is answered. The proposal will lock **four first-class provider slugs** (`openai`, `anthropic`, `google`, `openrouter`), the curated default model list (including a small OpenRouter default set), the optional app-attribution headers for OpenRouter, and the chained-PR split. OpenCode remains a separate decision tracked under Open Question 2.
