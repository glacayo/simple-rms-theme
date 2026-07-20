# Verification Report: Wizard AI Multi-Provider Support

Date: 2026-07-20
Change: `wizard-ai-multi-provider-support`
Mode: OpenSpec
Strict TDD: inactive (`strict_tdd: false`; no automated runner configured in `openspec/config.yaml`)
Verdict: **FAIL**

## Completeness

| Area | Status | Evidence |
|------|--------|----------|
| Phase 1 PHP foundation / credential gate | Complete | Tasks 1.1-1.4 checked; source inspection confirms `Step_Controller::configure_ai_provider()` validates new keys with `list_models()` before save and keeps blank-key behavior. |
| Phase 2 provider implementations | Complete | Tasks 2.1-2.5d checked; source inspection confirms OpenAI, Anthropic, Google Gemini, and OpenRouter providers plus registry labels/branches. |
| Phase 3 TypeScript / admin UX | Complete | User manually confirmed UX/admin/TS review for tasks 3.1 and 3.2; source inspection also confirms empty `models[]` manual fallback and `manage_options` gates. `tasks.md` now marks 3.1 and 3.2 complete. |
| Phase 4 static verification | Complete | `php -l` passed for all requested PHP files; `npx tsc --noEmit` passed. `tasks.md` now marks 4.1 and 4.2 complete. |
| Phase 4 provider smoke / negative checks | Partial | REST Test/Load invalid-key smoke passed for OpenAI, Anthropic, Google Gemini, and OpenRouter without persisting fake keys. Real-key model loading/generation and final IA Generation save/failure smoke remain unverified. |

## Command Evidence

| Command | Result | Output summary |
|---------|--------|----------------|
| `php -l inc/wizard/class-step-controller.php` | PASS | No syntax errors detected. |
| `php -l inc/wizard/class-ai-provider-registry.php` | PASS | No syntax errors detected. |
| `php -l inc/wizard/class-openai-provider.php` | PASS | No syntax errors detected. |
| `php -l inc/wizard/class-anthropic-provider.php` | PASS | No syntax errors detected. |
| `php -l inc/wizard/class-google-provider.php` | PASS | No syntax errors detected. |
| `php -l inc/wizard/class-openrouter-provider.php` | PASS | No syntax errors detected. |
| `npx tsc --noEmit` | PASS | Command exited successfully with no output. |
| `git status --short` | PASS before report write | Only pre-existing intended untracked local docs were present: `Wizard ai harness prompt guide.md`, `wizard-prd.html`. |
| REST `/ai/models` invalid-key matrix | PASS (negative) | Fake key blocked for OpenAI (401), Anthropic (401), Google Gemini (400), and OpenRouter (401 via `/api/v1/key`); subsequent empty-key calls returned missing-key errors, confirming fake keys were not persisted. |
| `git status --short` | PASS after report write | Untracked files: `Wizard ai harness prompt guide.md`, `wizard-prd.html`, and this intended verification artifact `openspec/changes/wizard-ai-multi-provider-support/verify-report.md`. |

Coverage: Not available. PHPUnit/unit/integration/e2e runner: Not available per `openspec/config.yaml`.

## Spec Compliance Matrix

| Requirement / Scenario | Status | Evidence |
|------------------------|--------|----------|
| First-Class Provider Availability / Supported providers are available | PASS (source + manual UX evidence) | Registry lists `openai`, `anthropic`, `google`, `openrouter`; `opencode` is absent; UI provider select renders registry entries. |
| First-Class Provider Availability / Existing provider remains usable | PASS (source) | `ollama-cloud` remains first entry in `AI_Provider_Registry::list_providers()` and has a `make_provider()` branch. |
| Provider Configuration Access / Authorized role configures provider | PASS (source + manual UX evidence) | `add_theme_page`, render guard, REST permission, and `Step_Controller::can_access()` use `manage_options`. |
| Provider Configuration Access / Unauthorized role cannot configure provider | STATIC PASS, runtime role test not executed | Source gates all admin page, REST, and step execution paths with `manage_options`; no lower-privileged browser/session runtime test was executed here. |
| Per-Site Credentials / Site stores its own credential | STATIC PASS | `AI_Credential_Store` uses per-site `get_option()` / `update_option()` with provider-specific option names and does not use network options. |
| Per-Site Credentials / Credential status displayed safely | PASS (source) | UI/status uses `AI_Credential_Store::status()` / `mask_status()` and password placeholder masking; raw key is not displayed. |
| Provider Setup Gating / Validation succeeds | UNTESTED | Requires real provider API keys and live successful `list_models()` responses; not provided. |
| Provider Setup Gating / Live model listing validates credential | PARTIAL | OpenAI/Anthropic/Gemini: live model endpoints remain the credential proof (still need real keys). OpenRouter exception: `list_models()` validates via `GET /api/v1/key` first, then lists via `/api/v1/models`; `/models` alone must not validate. |
| Provider Setup Gating / OpenRouter key metadata validates credential | PARTIAL (negative smoke) | Re-smoke after fix: fake key → 401 `rms_wizard_openrouter_key_invalid`; subsequent empty-key → `rms_wizard_missing_openrouter_key` (fake key not persisted). Positive path with a real OpenRouter key still untested. |
| Provider Setup Gating / Validation fails | PARTIAL | REST Test/Load invalid-key smoke confirmed blocked for OpenAI, Anthropic, Google Gemini, and OpenRouter without persisting fake keys. Final IA Generation save invalid-key path remains untested because the wizard is already completed/locked. |
| Provider Setup Gating / Provider lacks live model listing | N/A | All v1 providers implement live model listing. |
| Consistent Single-Provider Generation / Selected provider generates content | UNTESTED | Source uses selected provider/model through `AI_Provider_Registry::make_provider()`, but no live generation smoke was executed. |
| Consistent Single-Provider Generation / Selected provider fails during generation | UNTESTED | Source records failure and uses local fallback content without trying another provider, but no runtime failure smoke was executed. |
| Provider-Neutral Prompt Contract / Prompt behavior stays provider-neutral | STATIC PASS | Generation/review paths pass existing prompts/system prompts into selected provider-specific `generate()` methods without provider routing changes. |

## Implementation / Design Coherence

| Design item | Status | Evidence |
|-------------|--------|----------|
| Four first-class hosted provider classes | PASS | `OpenAI_Provider`, `Anthropic_Provider`, `Google_Provider`, `OpenRouter_Provider` exist and extend `AI_Provider`. |
| Registry-only extension | PASS | `AI_Provider_Registry::list_providers()` and `make_provider()` add the four slugs; no OpenCode branch found. |
| Admin-only access remains `manage_options` | PASS | Confirmed in `Step_Controller::can_access()`, REST permission callback, `add_theme_page`, and admin render guard. |
| Per-site credential store retained | PASS | `AI_Credential_Store` uses ordinary options, not network options. |
| New keys persist only after live `list_models()` validation | PASS (source) | REST `/ai/models` and `Step_Controller::configure_ai_provider()` call `list_models()` before `AI_Credential_Store::save()` for non-empty new keys. |
| Empty/unusable model lists return `WP_Error` | PASS (source) | Each new provider returns `WP_Error` for missing key, non-2xx response, invalid response shape, and empty/unusable model list. |
| Provider HTTP contracts | PASS (source) | OpenAI uses `/v1/models` + `/v1/chat/completions`; Anthropic uses `/v1/models` + `/v1/messages` with `max_tokens`; Gemini uses `/v1beta/models` + `:generateContent?key=`; OpenRouter uses `/api/v1/key` (credential validation, 10s) then `/api/v1/models` (catalog) + `/api/v1/chat/completions`. |
| No auto-fallback/auto-routing | PASS (source) | Generation paths use selected provider; failures return errors or local fallback content only, with no alternate-provider retry. |
| No API key / Authorization / full Gemini URL logging | PASS (source) | No logging calls were found in new provider classes; Authorization headers and Gemini query URLs are only passed to WordPress HTTP API calls. |

## Issues

### CRITICAL

- `UNTESTED`: Required live provider smoke tests were not executed because no real OpenAI, Anthropic, Google Gemini, or OpenRouter API keys were provided. This blocks full spec compliance for successful credential validation, model loading, generation, and selected-provider failure behavior.
- `TASKS_PENDING`: Tasks 4.3, 4.4, and 4.5 remain incomplete. Task 4.4 has partial REST Test/Load evidence, but final IA Generation save blocking was not executed because the wizard is already completed/locked.

### WARNING

- `RUNTIME_ROLE_TEST_SKIPPED`: Admin-only access was source-verified and manually confirmed for UX/admin review, but no lower-privileged WordPress runtime session was executed here.
- `OPENROUTER_PUBLIC_MODELS_CATALOG` (fixed follow-up): Smoke found OpenRouter `GET /api/v1/models` can return models for a fake key, which previously allowed fake-key persistence. Fix: `OpenRouter_Provider::list_models()` validates via `GET /api/v1/key` first (10s timeout; 2xx + JSON `data` array/object), then lists models. Spec/design updated so OpenRouter is the exception to “models endpoint validates.” Fake option `rms_wizard_ai_credential_openrouter` was cleaned from LocalWP DB. Re-smoke: fake key → `rms_wizard_openrouter_key_invalid`; empty key → `rms_wizard_missing_openrouter_key` (not persisted). Positive real-key OpenRouter smoke still pending.

### SUGGESTION

- Run the remaining manual smoke matrix in WP Admin with real provider keys: valid-key model loading/save/generation for all four providers; invalid-key save blocking; provider failure reporting with no alternate-provider routing.

## Final Verdict

**FAIL** — static/source verification and configured quality commands passed, but required provider runtime smoke tests were not executable and therefore key spec scenarios remain unproven.
