# Tasks: Wizard AI Multi-Provider Support

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 900-1,150 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 validation gate → PR 2 OpenAI → PR 3 Anthropic → PR 4 Google → PR 5 OpenRouter |
| Delivery strategy | auto-chain (force-chained preflight) |
| Chain strategy | feature-branch-chain |

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Credential validation gate | PR 1 | Base = feature/tracker branch; no provider class dependency |
| 2 | OpenAI provider | PR 2 | Base = PR 1 branch; register only with class present |
| 3 | Anthropic provider | PR 3 | Base = PR 2 branch; `GET /v1/models`, `POST /v1/messages`, `max_tokens` |
| 4 | Google Gemini provider | PR 4 | Base = PR 3 branch; query-param key, no key logging |
| 5 | OpenRouter provider + final smoke | PR 5 | Base = PR 4 branch; no fallback/auto-routing |

## Phase 1: PHP Foundation / Credential Gate

- [x] 1.1 Modify `inc/wizard/class-step-controller.php`: for newly supplied `api_key`, call `AI_Provider_Registry::make_provider( $provider, $api_key )->list_models()` before `AI_Credential_Store::save()`.
- [x] 1.2 In `configure_ai_provider()`, if validation returns `WP_Error`, do not save; mark `ia-generation` failed and return the error/warning.
- [x] 1.3 Preserve blank-key behavior in `configure_ai_provider()`: use existing saved credential/status without revalidating every save.
- [x] 1.4 Confirm `inc/wizard/class-rest-controller.php::list_ai_models()` remains the existing validate-then-save path; do not add a new `validate()` method in v1.

## Phase 2: PHP Provider Implementations

- [x] 2.1 Create `inc/wizard/class-openai-provider.php` with Bearer auth, `POST /v1/chat/completions`, `GET /v1/models`, and OpenAI response parsing.
- [x] 2.2 Create `inc/wizard/class-anthropic-provider.php` with `GET /v1/models` validation/listing and `POST /v1/messages` using `x-api-key`, `anthropic-version`, `system`, and `max_tokens`. (Class kept; selectable availability deferred — see 2.5b.)
- [x] 2.3 Create `inc/wizard/class-google-provider.php` with `generateContent?key=`, `systemInstruction`, `GET /models?key=`, and host-only error logging.
- [x] 2.4 Create `inc/wizard/class-openrouter-provider.php` with OpenAI-shaped generation, `GET /api/v1/models`, and default `HTTP-Referer`/`X-Title` headers.
- [x] 2.5a Update `inc/wizard/class-ai-provider-registry.php` for OpenAI: add the `openai` label and `make_provider()` branch only with `OpenAI_Provider` present.
- [x] 2.5b Update `inc/wizard/class-ai-provider-registry.php` for Anthropic: keep `make_provider( 'anthropic' )` branch with `Anthropic_Provider` present; **do not** add Anthropic to default `list_providers()` until real-key smoke (billing deposit/payment processor blocked). Class file must not be deleted. `provider_exists( 'anthropic' )` remains false by default.
- [x] 2.5c Update `inc/wizard/class-ai-provider-registry.php` for Google Gemini: add the `google` label and `make_provider()` branch only with `Google_Provider` present.
- [x] 2.5d Update `inc/wizard/class-ai-provider-registry.php` for OpenRouter: add the `openrouter` label and `make_provider()` branch only with `OpenRouter_Provider` present; keep OpenCode deferred.
- [x] 2.5e Confirm enabled selectable providers: Ollama Cloud, OpenAI, Google Gemini, OpenRouter. Anthropic = implemented but disabled/pending real-key smoke.

## Phase 3: TypeScript / Admin UX

- [x] 3.1 Inspect/update `src/ts/admin/wizard.ts` so empty `models[]` keeps the manual model fallback, and failed model loading keeps warning + focus behavior.
- [x] 3.2 Verify `inc/wizard/wizard-init.php` keeps admin-only `manage_options` provider configuration; no editor UI expansion in v1.

## Phase 4: Verification

- [x] 4.1 Run `php -l` on every changed PHP file: provider classes, registry, and `class-step-controller.php`.
- [x] 4.2 Run `tsc --noEmit` if `src/ts/admin/wizard.ts` changes.
- [x] 4.3 Manual smoke via WP Admin IA Generation for **enabled v1 new providers** (OpenAI, Google Gemini, OpenRouter): valid key saves and models load through REST/admin evidence; user manually confirmed the end-to-end content generation flow works and the wizard is now locked after personally testing the whole flow. Assistant did not independently reproduce generation after lock, so verification records this as accepted manual evidence. Existing Ollama Cloud remains available/unchanged.
- [ ] 4.3b Anthropic real-key smoke: **skipped / disabled** — external billing issue (minimum deposit + payment processor failure). Not a code failure. Anthropic remains implemented in `class-anthropic-provider.php` and `make_provider( 'anthropic' )`, but is intentionally absent from default `list_providers()` / selectable UI until future real-key smoke passes.
- [x] 4.4 Manual invalid-key check for providers: REST Test/Load invalid-key smoke passed for OpenAI, Anthropic factory path, Google Gemini, and OpenRouter without persisting fake keys; OpenRouter fake-key persistence bug was fixed by validating `/api/v1/key` before listing `/api/v1/models`.
- [x] 4.5 Manual failure check: provider failures are reported through existing wizard/REST behavior with no fallback or auto-routing; Anthropic returned 401 during real-key attempt due external billing/payment issue and is now disabled from selection by design.
