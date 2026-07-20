# Design: Wizard AI Multi-Provider Support

## Technical Approach

Pure-registry extension (Approach 1 from exploration). Add four `extends AI_Provider`
classes — `OpenAI_Provider`, `Anthropic_Provider`, `Google_Provider`,
`OpenRouter_Provider` — each owning its HTTP contract via the WordPress HTTP API
(`wp_remote_request`/`wp_remote_get`), mirroring `Ollama_Provider`. Grow
`AI_Provider_Registry::make_provider()` from one branch to five and add four label
entries to `list_providers()`. Credentials reuse `AI_Credential_Store::get($slug)`
(per-site encrypted options). Harness, reviewer, builders, REST `/ai/models`, and
the wizard UI are already provider-agnostic — no behavioral changes there. New
classes autoload by naming convention (`wizard-init.php:18` maps
`Inc\Wizard\OpenAI_Provider` → `class-openai-provider.php`), so no manual `require`.
Satisfies spec `wizard-ai-providers` (availability, per-site credentials, gating,
single-provider generation, neutral prompt).

## Architecture Decisions

| Decision | Choice | Alternative rejected | Rationale |
|----------|--------|----------------------|-----------|
| Provider shape | Per-class `generate()`/`list_models()` extraction | Shared universal parser (`AI_Adapter::extract_content`) | Response shapes diverge (OpenAI `choices[0].message.content`, Anthropic `content[0].text`, Gemini `candidates[0].content.parts[0].text`); a shared parser is best-effort, not contract. |
| OpenRouter | Distinct `openrouter` slug + own credential/model URL + optional `HTTP-Referer`/`X-Title` | Boolean flag on OpenAI | Different key, different model list, different product positioning; shares only the wire shape. |
| OpenRouter credential validation | `list_models()` first calls `GET /api/v1/key` (10s timeout; 2xx + JSON body with `data` array/object), then lists via `GET /api/v1/models` | Treat `/api/v1/models` alone as validation | OpenRouter's model catalog can be public; `/models` alone must not persist a fake key. Public contract stays `list_models()` — no public `validate()` in v1. |
| Anthropic models | `list_models()` calls `GET https://api.anthropic.com/v1/models` (`x-api-key` + `anthropic-version: 2023-06-01`), maps `data[].{id,display_name}`; a successful response also validates the credential | Curated-only / manual-only listing | Anthropic exposes a real list endpoint; the live call is the primary listing+validation path. Curated defaults (e.g. `claude-3-5-sonnet-latest`) and manual entry stay only as UI fallback/reference. |
| Anthropic `max_tokens` | Default `2048` constant on the provider | Omit | Omission causes HTTP 400. |
| Gemini auth | `add_query_arg('key', …)` once; log host only | Header auth / log full URL | Gemini auth is a query param; full-URL logging would leak the key. |
| Failure policy | Single attempt; surface error in wizard log | Auto-fallback / retry next provider | Spec forbids auto-routing/fallback in v1. |
| Role access | Keep existing admin-only `manage_options` gate | Add editor-inclusive capability | v1 is administrator-only; reusing the existing gate keeps this a pure-registry change with no auth blast radius. |

## Data Flow

    [wizard.ts]  --POST /ai/models {provider, api_key}-->  [Rest_Controller]
         |                                                       |
         | populateModelSelect(models[])                         v
         |<----------- {models[], credential} ----- [Registry::make_provider]
                                                                 |
                                                                 v
                                          [<Slug>_Provider::list_models()] --HTTP--> [Vendor API]

Generation reuses the existing path: `AI_Content_Harness` → `make_provider($slug)->generate($model,$user,$ctx,$system)`.

### Sequence: Test/Validate + load models + credential save (PHP↔REST↔JS)

    Admin           wizard.ts            REST /ai/models       Registry/Provider     Credential_Store     Vendor API
      |  click Test     |                       |                      |                    |                 |
      |---------------->| loadAiModels()        |                      |                    |                 |
      |                 |--POST {provider,key}-->|                      |                    |                 |
      |                 |                        | provider_exists()    |                    |                 |
      |                 |                        |--make_provider------>|                    |                 |
      |                 |                        |                      |--validate/list_models()-----HTTP----->|
      |                 |                        |                      |<---ok / models[] | WP_Error-----------|
      |                 |                        |<--models[]-----------|                    |                 |
      |                 |                        | if key AND validation ok: save(slug,key)->| (encrypt)       |
      |                 |<--{models, credential}-|                      |                    |                 |
      |  see options    |  populateModelSelect   |                      |                    |                 |
      |<----------------|  (empty -> manual)     |                      |                    |                 |

On `WP_Error` the JS catch clears the select, focuses manual input, shows the warning (gating). Credentials are persisted only after explicit successful validation. The public validation contract is the live `list_models()` call — **no public `validate()` method in v1**. For OpenAI (`GET /v1/models`), Gemini (`GET /models`), and Anthropic (`GET /v1/models`), a successful authenticated model-list response is the credential proof. **OpenRouter is the exception:** credential validation is `GET /api/v1/key` (Bearer; expects 2xx JSON with a `data` array/object); only after that succeeds does `list_models()` call `GET /api/v1/models` for the catalog. OpenRouter `/models` alone must not validate credentials because the catalog can be public. A curated/empty fallback list never by itself persists the credential.

**Both save paths obey this rule.** A newly supplied key persists only after `list_models()` succeeds: REST `Rest_Controller::list_ai_models()` already validates then saves; `Step_Controller::configure_ai_provider()` currently saves a non-empty `api_key` directly (the gap) and must route the new key through the same `list_models()` gate before `save()`. v1 uses `list_models()` as the validation contract — **no `validate()` method is added** to the base interface. When no new key is supplied, both paths skip revalidation and rely on existing `AI_Credential_Store::status()`, unchanged.

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `inc/wizard/class-openai-provider.php` | Create | chat/completions; `Authorization: Bearer`; `GET /v1/models`. |
| `inc/wizard/class-anthropic-provider.php` | Create | `/v1/messages`; `x-api-key` + `anthropic-version`; top-level `system`; `max_tokens=2048`; `list_models()` via `GET /v1/models` (validation), curated fallback only. |
| `inc/wizard/class-google-provider.php` | Create | `:generateContent?key=`; `systemInstruction`; `GET /models?key=`; host-only logging. |
| `inc/wizard/class-openrouter-provider.php` | Create | OpenAI shape; optional `HTTP-Referer`/`X-Title`; credential validation via `GET /api/v1/key` then model catalog via `GET /api/v1/models`. |
| `inc/wizard/class-ai-provider-registry.php` | Modify | 4 `make_provider()` branches + 4 `list_providers()` labels. |
| `inc/wizard/class-step-controller.php` | Modify | Close credential-save gap in `configure_ai_provider()`: validate a new `api_key` via `make_provider($provider,$api_key)->list_models()` before `save()`. On `WP_Error`, set `ia-generation` `failed` and return the error (no save). Blank key → existing saved-credential behavior, no revalidation. |
| `src/ts/admin/wizard.ts` | Modify | Confirm empty `models[]` keeps manual fallback (already at `populateModelSelect`); no contract change. |

## Interfaces / Contracts

Each class honors the base contract unchanged:

```php
generate(string $model, string $prompt, array $context = [], string $system = ''): array{success:bool,content:string,error?:string}
list_models(): array<int,array{id:string,label:string}>|\WP_Error
```

Registry `list_providers()` adds `{slug:'openai',label:'OpenAI'}` and peers. Slugs:
`openai`, `anthropic`, `google`, `openrouter`.

## Testing Strategy

No automated runner (`config.yaml: runner.available:false`). Quality gates: `php -l`
per new/modified PHP file; `tsc --noEmit` after the TS confirmation.

| Layer | What | Approach |
|-------|------|----------|
| Unit | n/a | No PHPUnit installed. |
| Integration | n/a | No runner. |
| Manual (E2E) | Per provider: save key → list/accept model → generate `about-us`; invalid key blocks + warns | IA Generation form smoke test. |

## Migration / Rollout

No data/schema migration. Rollback: remove slug branch(es) from `make_provider()`;
unknown slugs fall back to the no-op `AI_Provider`; stored credential options stay
inert. **CSS loading strategy: not applicable** — admin-only wizard form, no new
front-end CSS entry points. **ACF: not applicable** — no ACF fields or `acf-json`
changes; credentials use `AI_Credential_Store` options.

## Resolved Decisions

- [x] Provider configuration access is administrator-only in v1. The existing
  `manage_options` gate in `can_access()`, the `wizard-init.php` gate, and the
  `add_theme_page` capability are kept as-is — no capability change, no auth blast
  radius beyond the pure-registry extension.
- [x] OpenRouter `HTTP-Referer`/`X-Title` defaults are site URL + theme name with no
  admin override in v1.

## Open Questions

- None.
