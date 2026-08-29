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

---

### Requirement: New Optional Step Does Not Invalidate Completed Sites

Adding the internal page builder step MUST NOT change the completion state of a site that was already complete. `rms_wizard_completed` MUST be preserved, the site MUST remain read-only by default, and no admin notice MUST claim the site is incomplete or requires rework.

#### Scenario: Completed site stays complete

- GIVEN a site with `rms_wizard_completed = true` from before the step existed
- WHEN the step is added to the wizard
- THEN the site is still reported complete and stays read-only

#### Scenario: No retroactive incompleteness prompt

- GIVEN a completed site that never ran the internal page builder
- WHEN an admin opens the WordPress admin
- THEN no notice states the wizard is incomplete or must be re-run

---

### Requirement: Optional Step Is Discoverable and Unlockable

The internal page builder MUST be reachable on a completed site through the existing unlock action, and MUST be presented as an optional enhancement rather than a required remediation. Completing or skipping it MUST leave completion intact, and re-locking MUST restore read-only state.

#### Scenario: Step reachable after unlock

- GIVEN a completed site is unlocked with `manage_options` and a valid nonce
- WHEN the admin opens the wizard
- THEN the internal page builder step is available and editable

#### Scenario: Skipping the step preserves completion

- GIVEN an unlocked completed site
- WHEN the admin skips the internal page builder and re-locks
- THEN `rms_wizard_completed` is preserved and the wizard is read-only again

#### Scenario: Locked site cannot mutate pages

- GIVEN a completed site with no active unlock
- WHEN an internal page build is requested
- THEN the request is refused and no page is mutated
