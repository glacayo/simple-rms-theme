# Wizard AI Configuration Specification

## Purpose

A standalone wizard step that collects and validates AI provider credentials (provider, model, API key/endpoint) and stores them encrypted for use by later content-generating steps (Home Page Builder).

---

## Requirements

### Requirement: Provider Selection

The wizard MUST display the list of registered AI providers from `AI_Provider_Registry` as a selectable dropdown. Only providers registered via the `rms_wizard_ai_providers` filter MAY appear. The admin MUST select exactly one provider before the step can complete.

#### Scenario: Registered providers listed

- GIVEN Ollama Cloud and any filter-registered providers are active
- WHEN the admin opens the IA Generation step
- THEN the provider dropdown lists all registered providers

#### Scenario: No provider selected on submit

- GIVEN the provider dropdown has no selection
- WHEN the admin submits the step
- THEN the wizard displays a validation error and blocks progression

---

### Requirement: Model Selection

After a provider is selected the wizard MUST load available models from the provider's `list_models()` endpoint and display them in a model dropdown. If the models endpoint is unreachable the wizard MUST allow the admin to type a model name manually.

#### Scenario: Models loaded dynamically

- GIVEN a provider is selected and its models endpoint is reachable
- WHEN the admin triggers model loading (or the form auto-loads on provider change)
- THEN the model dropdown is populated with the provider's available models via the `POST /ai/models` REST endpoint

#### Scenario: Models endpoint unreachable

- GIVEN the provider's models endpoint returns an error
- WHEN the admin attempts to load models
- THEN a manual text input is shown with a message: "Could not load models. Enter a model name manually."

---

### Requirement: API Key / Endpoint Storage

The wizard MUST accept an API key (or local endpoint URL for Ollama) and persist it encrypted to `wp_options` using the existing sodium/openssl credential store. The key MUST NOT be stored in plaintext. On reload the field MUST render as masked (placeholder only — value not echoed).

#### Scenario: Credentials saved encrypted

- GIVEN the admin enters a valid API key and submits
- WHEN the step runs
- THEN the key is encrypted before storage and `wp_options` contains no plaintext credential

#### Scenario: Masked field on reload

- GIVEN credentials were saved in a previous session
- WHEN the admin reopens the IA Generation step
- THEN the API key field shows a masked placeholder (e.g. `••••••••`) and does not echo the stored value

---

### Requirement: Configuration Test

The wizard SHOULD provide a "Test Connection" action that sends a minimal prompt to the selected provider/model using the stored credentials. The result (success or error message) MUST be shown inline without saving or advancing the step.

#### Scenario: Test succeeds

- GIVEN valid provider, model, and credentials are entered
- WHEN the admin clicks "Test Connection"
- THEN the wizard displays a success indicator and the raw model response excerpt

#### Scenario: Test fails

- GIVEN an invalid API key is entered
- WHEN the admin clicks "Test Connection"
- THEN the wizard displays the provider error message inline and does not advance the step

---

### Requirement: Step State and Downstream Availability

After the step completes, the wizard MUST store `{ provider, model }` (never the raw key) in `state.ai_config`. The Home Page Builder step MUST read `state.ai_config` to determine which provider and model to use for section content generation.

#### Scenario: AI config available to Home Page Builder

- GIVEN the IA Generation step completed with a valid provider and model
- WHEN the Home Page Builder step runs
- THEN it reads `state.ai_config.provider` and `state.ai_config.model` to route AI calls

#### Scenario: Home Page Builder blocked without AI config

- GIVEN the IA Generation step is not complete
- WHEN the admin attempts to submit the Home Page Builder step
- THEN the wizard returns an error: "AI configuration required. Complete the IA Generation step first."
