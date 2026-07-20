# Verification Report: Wizard AI Multi-Provider Support

Date: 2026-07-20
Change: `wizard-ai-multi-provider-support`
Mode: OpenSpec
Strict TDD: inactive (`strict_tdd: false`; no automated runner configured in `openspec/config.yaml`)
Verdict: **FAIL** (remaining real-key smoke for enabled providers; Anthropic intentionally skipped)

## Enablement status (follow-up)

| Provider | Class / `make_provider()` | Selectable (`list_providers` / `provider_exists`) | Smoke / notes |
|----------|---------------------------|-----------------------------------------------------|---------------|
| Ollama Cloud | Yes | **Enabled** | Existing provider |
| OpenAI | Yes | **Enabled** | Real-key smoke still pending |
| Google Gemini | Yes | **Enabled** | Real-key smoke still pending |
| OpenRouter | Yes | **Enabled** | Real-key smoke still pending; invalid-key `/api/v1/key` path verified |
| Anthropic | Yes (class kept; not deleted) | **Disabled by default** | Implemented but pending real-key smoke. **Skipped** due to external billing/payment issue (minimum deposit required; payment processor failing for maintainer) — **not a code failure**. Re-add label to `list_providers()` when a real key can be tested. |
| OpenCode | No | Deferred | Out of scope |

## Completeness

| Area | Status | Evidence |
|------|--------|----------|
| Phase 1 PHP foundation / credential gate | Complete | Tasks 1.1-1.4 checked; source inspection confirms `Step_Controller::configure_ai_provider()` validates new keys with `list_models()` before save and keeps blank-key behavior. |
| Phase 2 provider implementations | Complete | Tasks 2.1-2.5e checked; OpenAI, Anthropic, Google Gemini, and OpenRouter classes exist. Registry: selectable labels for Ollama Cloud, OpenAI, Google, OpenRouter; Anthropic only in `make_provider()`, omitted from default `list_providers()`. |
| Phase 3 TypeScript / admin UX | Complete | User manually confirmed UX/admin/TS review for tasks 3.1 and 3.2; source inspection also confirms empty `models[]` manual fallback and `manage_options` gates. |
| Phase 4 static verification | Complete | `php -l` passed for provider/registry/step files; `npx tsc --noEmit` passed. Follow-up: `php -l inc/wizard/class-ai-provider-registry.php` after Anthropic disable. |
| Phase 4 provider smoke / negative checks | Partial | REST Test/Load invalid-key smoke passed for OpenAI, Anthropic (historical negative), Google Gemini, and OpenRouter without persisting fake keys. Anthropic selectable UI path is now disabled by design. Real-key model loading/generation for **enabled** providers remains unverified. |

## Command Evidence

| Command | Result | Output summary |
|---------|--------|----------------|
| `php -l inc/wizard/class-step-controller.php` | PASS | No syntax errors detected. |
| `php -l inc/wizard/class-ai-provider-registry.php` | PASS | No syntax errors detected (incl. post Anthropic-disable follow-up). |
| `php -l inc/wizard/class-openai-provider.php` | PASS | No syntax errors detected. |
| `php -l inc/wizard/class-anthropic-provider.php` | PASS | No syntax errors detected. Class retained. |
| `php -l inc/wizard/class-google-provider.php` | PASS | No syntax errors detected. |
| `php -l inc/wizard/class-openrouter-provider.php` | PASS | No syntax errors detected. |
| `npx tsc --noEmit` | PASS | Command exited successfully with no output. |
| Source inspection: `list_providers()` vs `make_provider()` | PASS | `list_providers()` excludes `anthropic`; `make_provider( 'anthropic' )` still returns `Anthropic_Provider`. |
| REST `/ai/models` invalid-key matrix | PASS (negative; historical) | Fake key blocked for OpenAI (401), Anthropic (401 while still testable via factory), Google Gemini (400), and OpenRouter (401 via `/api/v1/key`); subsequent empty-key calls returned missing-key errors, confirming fake keys were not persisted. |

Coverage: Not available. PHPUnit/unit/integration/e2e runner: Not available per `openspec/config.yaml`.

## Spec Compliance Matrix

| Requirement / Scenario | Status | Evidence |
|------------------------|--------|----------|
| First-Class Provider Availability / Supported providers are available | PASS (source) | Registry selectable list: `ollama-cloud`, `openai`, `google`, `openrouter`. Anthropic not listed. OpenCode absent. |
| First-Class Provider Availability / Anthropic implemented but disabled | PASS (source) | `class-anthropic-provider.php` exists; `make_provider( 'anthropic' )` branch present; default `list_providers()` omits Anthropic so `provider_exists( 'anthropic' )` is false. |
| First-Class Provider Availability / Existing provider remains usable | PASS (source) | `ollama-cloud` remains first entry in `list_providers()` and has a `make_provider()` branch. |
| Provider Configuration Access / Authorized role configures provider | PASS (source + manual UX evidence) | `manage_options` gates retained. |
| Provider Configuration Access / Unauthorized role cannot configure provider | STATIC PASS, runtime role test not executed | Source gates admin page, REST, and step execution with `manage_options`. |
| Per-Site Credentials / Site stores its own credential | STATIC PASS | `AI_Credential_Store` uses per-site options. |
| Per-Site Credentials / Credential status displayed safely | PASS (source) | Masked status only. |
| Provider Setup Gating / Validation succeeds | UNTESTED (enabled providers) | Requires real API keys for OpenAI / Google / OpenRouter. |
| Provider Setup Gating / Live model listing validates credential | PARTIAL | OpenAI/Gemini: live models endpoints. OpenRouter: `/api/v1/key` then `/api/v1/models`. Anthropic path implemented but not selectable. |
| Provider Setup Gating / OpenRouter key metadata validates credential | PARTIAL (negative smoke) | Fake key blocked; positive real-key still pending. |
| Provider Setup Gating / Validation fails | PARTIAL | REST invalid-key smoke for enabled providers historically confirmed; final IA Generation save path still limited by completed/locked wizard. |
| Consistent Single-Provider Generation / Selected provider generates content | UNTESTED | No live generation smoke for enabled providers. |
| Consistent Single-Provider Generation / Selected provider fails during generation | UNTESTED | Source has no auto-fallback; runtime not executed. |
| Provider-Neutral Prompt Contract | STATIC PASS | Existing prompts passed through selected provider `generate()`. |

## Implementation / Design Coherence

| Design item | Status | Evidence |
|-------------|--------|----------|
| Four first-class hosted provider classes | PASS | All four classes exist; Anthropic not deleted. |
| Registry selectable set vs factory | PASS | Selectable: Ollama Cloud, OpenAI, Google, OpenRouter. Factory still supports Anthropic. |
| Anthropic disabled pending real-key smoke | PASS | Documented in proposal/spec/design/tasks; registry omits label by default. |
| Admin-only access remains `manage_options` | PASS | Unchanged. |
| New keys persist only after live `list_models()` validation | PASS (source) | REST + step controller gate. |
| No auto-fallback/auto-routing | PASS (source) | Unchanged. |
| OpenCode deferred | PASS | No OpenCode branch/label. |

## Issues

### CRITICAL

- `UNTESTED`: Required live smoke for **enabled** providers (OpenAI, Google Gemini, OpenRouter) still needs real keys for successful validation, model loading, and generation.
- `TASKS_PENDING`: Tasks 4.3, 4.4, and 4.5 remain incomplete for enabled providers.

### WARNING

- `RUNTIME_ROLE_TEST_SKIPPED`: No lower-privileged WordPress runtime session was executed.
- `ANTHROPIC_DISABLED_EXTERNAL`: Anthropic selectable availability is intentionally off due to external billing/payment processor failure (minimum deposit). Code is ready; not a defect. Task 4.3b records the skip.

### SUGGESTION

- Run remaining manual smoke for enabled providers with real keys.
- When a usable Anthropic key is available: re-add the Anthropic entry to `list_providers()`, run real-key smoke, then mark 4.3b complete.

## Final Verdict

**FAIL** — static/source verification and Anthropic disable follow-up passed; required real-key runtime smoke for enabled providers remains unproven. Anthropic is **skipped/disabled for external billing reasons**, not because of a code failure.
