# Delta for wizard-controlled-unlock

> Baseline: NOT published. The only `wizard-controlled-unlock` spec lives in the active change `wizard-landing-page-builder`. Because no `openspec/specs/wizard-controlled-unlock/spec.md` exists yet, this delta uses ADDED only — a MODIFIED block would have no requirement to replace at archive time.
> Ordering: `wizard-landing-page-builder` MUST archive before this change. These requirements are additive to `Controlled Unlock` and `Reversible Unlock with Audit` and contradict neither.

## ADDED Requirements

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
