# Wizard Controlled Unlock Specification

## Purpose

A capability-gated, reversible path to re-open a completed wizard site so owners can add landings without a destructive reset of completion state.

## Requirements

### Requirement: Controlled Unlock

Sites where `rms_wizard_completed = true` MUST be read-only by default. Re-opening MUST occur through a Setup Wizard admin notice plus an explicit "Unlock wizard for editing" action, gated by the `manage_options` capability and a nonce. The unlock MUST NOT destroy `rms_wizard_completed` and MUST NOT perform any destructive reset of wizard state.

#### Scenario: Completed site is read-only by default

- GIVEN a site with `rms_wizard_completed = true` and no active unlock
- WHEN an admin opens the Setup Wizard
- THEN the wizard is read-only and shows the unlock notice

#### Scenario: Unlock re-opens without destroying completion

- GIVEN a completed site
- WHEN an admin triggers "Unlock wizard for editing"
- THEN the wizard becomes editable AND `rms_wizard_completed` is preserved

#### Scenario: Unlock requires capability and nonce

- GIVEN a request to unlock without `manage_options` or with an invalid nonce
- WHEN the action is handled
- THEN the unlock is refused and the wizard stays read-only

### Requirement: Reversible Unlock with Audit

The unlock MUST be reversible via a re-lock action that restores the read-only state without destroying completion. The unlock MUST record `rms_wizard_unlocked_at` and `rms_wizard_unlocked_by`. Re-locking MUST restore read-only state and MUST NOT delete the completion flag.

#### Scenario: Re-lock restores read-only state

- GIVEN the wizard is unlocked
- WHEN an admin triggers the re-lock action
- THEN the wizard returns to read-only AND `rms_wizard_completed` is preserved

#### Scenario: Unlock records who and when

- GIVEN an admin unlocks the wizard
- WHEN the unlock is applied
- THEN `rms_wizard_unlocked_at` and `rms_wizard_unlocked_by` are stored
