# Wizard AI Providers Specification

## Purpose

Defines the behavior for first-class hosted AI providers available to the wizard IA Generation flow.

## Requirements

### Requirement: First-Class Provider Availability

The system MUST offer OpenAI, Google Gemini, and OpenRouter as selectable first-class hosted AI providers for wizard generation in the current enablement set. Anthropic MUST remain implemented for future enablement (`Anthropic_Provider` class and `make_provider( 'anthropic' )`) but MUST NOT appear as a selectable provider by default until it is explicitly re-enabled after real-key smoke validation.

#### Scenario: Supported providers are available

- GIVEN an administrator opens the IA Generation configuration
- WHEN the provider list is shown
- THEN OpenAI, Google Gemini, and OpenRouter are available as selectable providers
- AND Anthropic is not shown as a selectable provider by default
- AND OpenCode is not shown as a v1 provider

#### Scenario: Anthropic remains implemented but disabled pending real-key smoke

- GIVEN the Anthropic provider class and `make_provider( 'anthropic' )` branch exist
- WHEN `list_providers()` / `provider_exists( 'anthropic' )` are evaluated with default registration
- THEN Anthropic is not listed as selectable
- AND `provider_exists( 'anthropic' )` is false
- AND REST/UI cannot configure Anthropic unless it is explicitly re-added to the selectable provider list (or via the `rms_wizard_ai_providers` filter)

#### Scenario: Existing provider remains usable

- GIVEN an existing site already has an earlier supported provider configured
- WHEN the provider list is shown
- THEN the existing provider remains available unless explicitly removed by a future change

### Requirement: Provider Configuration Access

The system MUST allow administrators to configure wizard AI providers and MUST NOT expose provider configuration to lower-privileged users in v1.

#### Scenario: Authorized role configures provider

- GIVEN a user with administrator access opens the wizard
- WHEN the user configures a provider credential and model
- THEN the configuration controls are available

#### Scenario: Unauthorized role cannot configure provider

- GIVEN a user below administrator access opens the wizard
- WHEN the IA Generation configuration would be rendered
- THEN provider credential and model configuration controls are not available

### Requirement: Per-Site Credentials

The system MUST store provider credentials per individual WordPress site and MUST NOT require network-level or multisite credential sharing in v1.

#### Scenario: Site stores its own credential

- GIVEN a provider credential is saved for one site
- WHEN another site is configured
- THEN the other site does not automatically inherit that credential

#### Scenario: Credential status is displayed safely

- GIVEN a provider credential exists
- WHEN the IA Generation configuration displays credential status
- THEN the system shows only a safe masked or status representation
- AND the raw credential is not displayed

### Requirement: Provider Setup Gating

The system MUST block provider configuration when required credential validation or model loading fails and MUST show an appropriate warning. The system MUST NOT persist a credential until explicit credential validation succeeds; an empty or curated model list alone is not sufficient validation.

#### Scenario: Provider validation succeeds

- GIVEN a supported provider receives a valid credential
- WHEN the system explicitly validates the credential and obtains a usable model choice
- THEN the credential may be persisted
- AND the provider can be saved for wizard generation

#### Scenario: Live model listing validates the credential

- GIVEN a supported provider other than OpenRouter that exposes a live model-listing endpoint whose successful authenticated response proves the credential
- WHEN the system requests the live model list with the supplied credential and the provider returns a successful response
- THEN that successful response counts as explicit credential validation
- AND the credential may be persisted
- AND a failed model-listing response blocks the save and shows a warning

#### Scenario: OpenRouter validates credentials via key metadata, then lists models

- GIVEN OpenRouter is the selected provider and a credential is supplied
- WHEN the system validates the credential
- THEN it MUST call OpenRouter `GET /api/v1/key` with Bearer auth and treat only a successful 2xx response with the expected key-metadata JSON shape (`data` present as an array or object) as explicit credential validation
- AND it MUST NOT treat a successful OpenRouter `GET /api/v1/models` response alone as credential validation, because the model catalog can be public
- AND after key validation succeeds, the system MAY call `GET /api/v1/models` only to obtain the model catalog for listing
- AND a failed key validation or failed model listing blocks the save and shows a warning
- AND the public validation contract remains `list_models()` (no public `validate()` method in v1)

#### Scenario: Provider validation fails

- GIVEN a supported provider receives an invalid credential or unusable model response
- WHEN the user attempts to save or use the provider configuration
- THEN the system blocks the configuration
- AND does not persist the credential
- AND shows a warning describing the failure

#### Scenario: Provider lacks live model listing

- GIVEN a supported provider cannot provide a live model list
- WHEN the provider still has documented usable model options
- THEN the system MAY present curated model options
- AND the provider still requires an explicit credential validation check before the credential is persisted
- AND configuration still requires a valid credential and usable model choice

### Requirement: Consistent Single-Provider Generation

The system MUST use the selected provider consistently for wizard generation and MUST NOT auto-route or fall back to another provider in v1.

#### Scenario: Selected provider generates content

- GIVEN a provider and model are configured
- WHEN the wizard generates content
- THEN generation requests use the selected provider and model

#### Scenario: Selected provider fails during generation

- GIVEN the selected provider returns an error or timeout
- WHEN the wizard attempts generation
- THEN the system reports or records the provider failure according to the existing wizard error behavior
- AND does not automatically retry with another provider

### Requirement: Provider-Neutral Prompt Contract

The system MUST preserve the existing provider-neutral prompt behavior for wizard generation and content review.

#### Scenario: Prompt behavior stays provider-neutral

- GIVEN any v1 hosted provider is selected
- WHEN the wizard sends a generation or review request
- THEN the request preserves the existing system-and-user prompt intent
- AND generated content follows the same wizard content rules regardless of provider
