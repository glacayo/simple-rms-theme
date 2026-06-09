# Delta for Wizard AI Configuration

## ADDED Requirements

### Requirement: System/User Provider Message Interface

The AI provider abstract interface SHOULD expose an optional `$system` parameter on the
`generate()` method signature as `generate($model, $prompt, $context = [], $system = '')`. When a non-empty `$system` string is provided, the
provider implementation MUST send it as a distinct system-role message separate from
the user prompt. When `$system` is omitted or empty, the provider MUST fall back to
single-message behavior to preserve backward compatibility with any existing callers
that pass only three arguments (`$model`, `$prompt`, `$context`).

#### Scenario: System message sent as separate role

- GIVEN `generate($model, $prompt, $context, $system)` is called with a non-empty `$system`
- WHEN the Ollama provider builds its request body
- THEN the request includes two messages: `{ "role": "system", "content": $system }` followed by `{ "role": "user", "content": $prompt }`

#### Scenario: Empty system falls back to single message

- GIVEN `generate($model, $prompt, $context, '')` is called with an empty `$system`
- WHEN the Ollama provider builds its request body
- THEN the request sends a single `{ "role": "user", "content": $prompt }` message, identical to the pre-harness behavior

#### Scenario: Existing callers unaffected

- GIVEN an existing caller passes only `($model, $prompt, $context)` without a `$system` argument
- WHEN the provider method executes
- THEN the call succeeds without error using single-message behavior because `$system` defaults to `''`
