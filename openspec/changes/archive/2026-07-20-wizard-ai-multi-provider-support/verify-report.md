# Verification Report: Wizard AI Multi-Provider Support

Date: 2026-07-20
Change: `wizard-ai-multi-provider-support`
Mode: OpenSpec
Strict TDD: inactive (`strict_tdd: false`; no automated PHPUnit/unit/e2e runner configured in `openspec/config.yaml`)
Verdict: **PASS WITH WARNINGS**

## Enablement Status

| Provider | Class / `make_provider()` | Selectable (`list_providers` / `provider_exists`) | Runtime / manual evidence |
|----------|---------------------------|-----------------------------------------------------|---------------------------|
| Ollama Cloud | Yes | **Enabled** | Existing provider remains first selectable entry; not changed by this work. |
| OpenAI | Yes | **Enabled** | WP Admin/REST credential/model listing returned 200 OK, 117 models, selected `gpt-5.6-luna` present, credential status `Saved (masked)`. Invalid-key smoke blocked persistence. |
| Google Gemini | Yes | **Enabled** | WP Admin/REST credential/model listing returned 200 OK, 39 models, selected `gemini-3-flash-preview` present, credential status `Saved (masked)`. Invalid-key smoke blocked persistence. |
| OpenRouter | Yes | **Enabled** | WP Admin/REST credential/model listing returned 200 OK, 339 models, credential status `Saved (masked)`, Nemotron free models present including `nvidia/nemotron-3-nano-30b-a3b:free`. Invalid-key smoke blocked persistence after `/api/v1/key` validation fix. |
| Anthropic | Yes (class retained) | **Disabled by default** | Real-key attempt returned 401 due external billing/payment issue. Anthropic remains implemented and available through `make_provider( 'anthropic' )`, but is intentionally absent from default selection until future real-key smoke can pass. |
| OpenCode | No | Deferred | Out of scope. |

## Completeness

| Area | Status | Evidence |
|------|--------|----------|
| Phase 1 PHP foundation / credential gate | Complete | Tasks 1.1-1.4 checked; source inspection confirms `Step_Controller::configure_ai_provider()` validates newly supplied keys with `list_models()` before save and preserves blank-key behavior. |
| Phase 2 provider implementations | Complete | Tasks 2.1-2.5e checked; OpenAI, Anthropic, Google Gemini, and OpenRouter classes exist. Registry selectable labels are Ollama Cloud, OpenAI, Google Gemini, and OpenRouter; Anthropic is factory-only by default. |
| Phase 3 TypeScript / admin UX | Complete | User manually confirmed UX/admin/TS earlier; source inspection confirms empty `models[]` keeps manual fallback, failed model loading warns and focuses manual entry, and provider config remains admin-only. |
| Phase 4 static verification | Complete | `php -l` passed for all requested PHP files; `npx tsc --noEmit` passed. |
| Phase 4 enabled-provider positive smoke | Complete with warning | Assistant verified positive WP Admin/REST credential/model-list evidence for OpenAI, Google Gemini, and OpenRouter. User manually confirmed the real end-to-end content generation flow works and the wizard is locked because they personally tested the whole flow. Generation was not independently reproduced by the assistant after lock. |
| Phase 4 negative/failure smoke | Complete | Invalid-key REST smoke passed for OpenAI, Anthropic factory path, Google Gemini, and OpenRouter without persisting fake keys. Anthropic 401 is external billing/payment state and is disabled from selection. Source confirms no fallback/auto-routing. |
| Anthropic selectable smoke | Pending future enablement | Task 4.3b intentionally remains unchecked because Anthropic is disabled by design until future real-key smoke. This is not a v1 blocker for enabled providers. |

## Command Evidence

| Command | Result | Output summary |
|---------|--------|----------------|
| `php -l inc/wizard/class-step-controller.php` | PASS | No syntax errors detected. |
| `php -l inc/wizard/class-ai-provider-registry.php` | PASS | No syntax errors detected. |
| `php -l inc/wizard/class-openai-provider.php` | PASS | No syntax errors detected. |
| `php -l inc/wizard/class-anthropic-provider.php` | PASS | No syntax errors detected. |
| `php -l inc/wizard/class-google-provider.php` | PASS | No syntax errors detected. |
| `php -l inc/wizard/class-openrouter-provider.php` | PASS | No syntax errors detected. |
| `npx tsc --noEmit` | PASS | Command exited successfully with no TypeScript errors. `npm` printed a minor-version upgrade notice only. |
| `git status --short` | PASS | Only preserved local untracked docs before report/task updates: `Wizard ai harness prompt guide.md`, `wizard-prd.html`. After verification updates, expected modified SDD artifacts are `openspec/changes/wizard-ai-multi-provider-support/tasks.md` and `verify-report.md`. |

Coverage: Not available. PHPUnit/unit/integration/e2e runner: Not available per `openspec/config.yaml`.

## Spec Compliance Matrix

| Requirement / Scenario | Status | Evidence |
|------------------------|--------|----------|
| First-Class Provider Availability / Supported providers are available | PASS | Source: `list_providers()` exposes `ollama-cloud`, `openai`, `google`, `openrouter`; Anthropic and OpenCode are absent from selectable list. |
| First-Class Provider Availability / Anthropic implemented but disabled | PASS | Source: `class-anthropic-provider.php` exists; `make_provider( 'anthropic' )` returns `Anthropic_Provider`; default `provider_exists( 'anthropic' )` is false because `list_providers()` omits it. |
| First-Class Provider Availability / Existing provider remains usable | PASS | Source: `ollama-cloud` remains first selectable entry with unchanged factory branch. |
| Provider Configuration Access / Authorized role configures provider | PASS | Source: admin page, REST routes, and step execution retain `manage_options`; user manually confirmed admin UX. |
| Provider Configuration Access / Unauthorized role cannot configure provider | PASS (source) | `Rest_Controller::permission_callback()`, `Step_Controller::can_access()`, `add_theme_page`, and render guard all require `manage_options`. No lower-privileged browser session was executed. |
| Per-Site Credentials / Site stores its own credential | PASS (source) | `AI_Credential_Store` uses per-site WordPress options with provider-specific option names; no network credential path introduced. |
| Per-Site Credentials / Credential status displayed safely | PASS | Source and runtime evidence show masked/status-only UI responses (`Saved (masked)`); raw keys are not returned. |
| Provider Setup Gating / Validation succeeds | PASS | Positive WP Admin/REST evidence for OpenAI, Google Gemini, and OpenRouter: 200 OK model-list responses and saved masked credentials. |
| Provider Setup Gating / Live model listing validates credential | PASS | OpenAI and Google Gemini use authenticated live model-list endpoints; OpenRouter validates `/api/v1/key` before listing models. |
| Provider Setup Gating / OpenRouter key metadata validates credential | PASS | Source and invalid-key smoke confirm `/api/v1/key` is required before `/api/v1/models`; fake keys no longer persist from the public model catalog. |
| Provider Setup Gating / Validation fails | PASS | Invalid-key REST smoke passed for OpenAI, Anthropic factory path, Google Gemini, and OpenRouter without persisting fake keys; warning/error behavior surfaced through REST/admin path. |
| Provider Setup Gating / Provider lacks live model listing | NOT APPLICABLE | All enabled v1 new providers use live validation. Curated/manual model entry remains UI fallback only and does not validate credentials. |
| Consistent Single-Provider Generation / Selected provider generates content | PASS WITH WARNING | User manually confirmed real end-to-end content generation flow works and the wizard is locked after personal full-flow testing. Assistant did not independently reproduce generation after lock. Source routes generation through `AI_Provider_Registry::make_provider( $provider )->generate( $model, ... )`. |
| Consistent Single-Provider Generation / Selected provider fails during generation | PASS | Source reports provider failure and returns fallback content for the selected provider only; no fallback/auto-routing branch exists. Anthropic 401 was observed and is now disabled from selection due external billing/payment issue. |
| Provider-Neutral Prompt Contract | PASS | Source: existing `AI_Content_Harness` prompt layers are passed unchanged to selected provider `generate()`; provider classes only adapt wire shape. |

## Implementation / Design Coherence

| Design item | Status | Evidence |
|-------------|--------|----------|
| Pure registry extension | PASS | Provider-specific classes own HTTP contracts; registry grows factory/selectable entries without harness/reviewer redesign. |
| Four hosted provider classes | PASS | OpenAI, Anthropic, Google Gemini, and OpenRouter provider classes exist and lint clean. |
| Registry selectable set vs factory | PASS | Selectable: Ollama Cloud, OpenAI, Google Gemini, OpenRouter. Factory also supports Anthropic. |
| Anthropic disabled pending real-key smoke | PASS | Proposal/design/tasks/source agree: Anthropic class retained, factory branch retained, default selectable label omitted pending future smoke. |
| OpenRouter credential validation | PASS | `OpenRouter_Provider::list_models()` calls private `validate_key()` using `/api/v1/key` before `/api/v1/models`; invalid-key smoke confirmed fake keys do not persist. |
| Admin-only access remains `manage_options` | PASS | Admin page, REST, render guard, and step controller all keep `manage_options`. |
| New keys persist only after live validation | PASS | REST and final IA Generation save path call `list_models()` before `AI_Credential_Store::save()` when a new key is supplied. |
| No auto-fallback/auto-routing | PASS | Source inspection found only selected-provider generation; failures are logged/reported, not routed to another provider. |
| OpenCode deferred | PASS | No OpenCode provider branch/label exists. |

## Issues

### CRITICAL

- None.

### WARNING

- `GENERATION_EVIDENCE_USER_MANUAL`: End-to-end generation is accepted from user manual confirmation; assistant did not independently reproduce generation after the wizard became locked.
- `RUNTIME_ROLE_TEST_SKIPPED`: Lower-privileged WordPress browser session was not executed; source gates were inspected instead.
- `ANTHROPIC_DISABLED_EXTERNAL`: Anthropic selectable availability is intentionally off due external billing/payment issue (minimum deposit + payment processor failure). Code is ready; future real-key smoke is still required before enabling selection.

### SUGGESTION

- When a usable Anthropic key is available, re-add Anthropic to `list_providers()`, run real-key model-list/generation smoke, then mark task 4.3b complete.

## Final Verdict

**PASS WITH WARNINGS** — static gates passed, enabled v1 new providers (OpenAI, Google Gemini, OpenRouter) have positive credential/model-list evidence and invalid-key protection, Anthropic is intentionally disabled/pending future smoke, and generation is accepted as user-confirmed manual runtime evidence rather than assistant-reproduced evidence.
