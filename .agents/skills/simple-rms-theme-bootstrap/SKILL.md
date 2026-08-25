---
name: simple-rms-theme-bootstrap
description: "Trigger: install Simple RMS Theme, run Setup Wizard, bootstrap WordPress beta. Use an installed browser tool, collect human-confirmed client facts, and verify without hallucinating data."
license: Apache-2.0
metadata:
  author: "glacayo"
  version: "1.0"
---

# Simple RMS Theme Bootstrap

## Activation Contract

Install Simple RMS Theme or run its Setup Wizard. Repo: `https://github.com/glacayo/simple-rms-theme`; releases: `https://github.com/glacayo/simple-rms-theme/releases`. Require an explicit release, WordPress URL, human-assisted login, and selected browser tool. Ask one missing question at a time.

## Hard Rules

- Never invent or scrape client facts. Record provenance per `references/client-intake.md`.
- Never accept or persist secrets in chat, logs, reports, or memory. The human enters and tests AI credentials in WP Admin.
- Never fetch ACF Pro unofficially. The agent may activate the plugin but never its license; pause for human activation and verify status before `acf-import`.
- Prefer a release ZIP with `dist/`; source builds require developer approval and never require Node at production runtime.
- Detect WordPress root; protect existing theme changes. Never guess paths.
- Use only the installed browser tool selected by the developer; never silently install tooling.
- Require screenshot/DOM/URL evidence, fact-ledger approval before generation, and human confirmation for destructive steps.
- End agent assistance after the human configures and validates the AI provider/key/model. Do not run Home Builder, Landing Builder, or Complete Wizard; return a human handoff.

## Decision Gates

| Gate | Stop condition | Resume only after |
|---|---|---|
| G1 Release | ZIP/`dist/` unverified | Valid asset or approved local build. |
| G2 Browser | No selected browser capability | Developer selects one or manual checkpoints. |
| G3 Existing theme | Target has local work | Approved backup/overwrite plan. |
| G4 Facts | Required facts missing/ambiguous | Human confirms facts and final ledger. |
| G5 Destructive | Destructive action pending | Human confirms its checkbox. |
| G6 ACF license | License not verified active | Human activates it; agent verifies without viewing key. |

## Execution Steps

1. Select a browser capability; follow `references/browser-install-runbook.md` or stop at G2.
2. Verify release/`dist/`, WordPress root, and existing files; install and activate theme.
3. Activate dependencies; pause at G6 before `acf-import`.
4. Build and confirm the fact ledger before `client-data`.
5. Confirm pages, roles, menus, and destructive actions; run their steps.
6. Human configures/tests AI credentials and selects a model in WP Admin; agent never observes the secret.
7. Verify only the masked saved status/model, then STOP and return the human continuation from `references/verification-checklist.md`.

## Output Contract

Return provenance, install/plugin status, wizard state through AI configuration, confirmed ledger without secrets, evidence, blockers, and a clear human handoff for Home/Landing builders and completion.

## References

- `references/browser-install-runbook.md` — tool-agnostic checkpoints and evidence.
- `references/client-intake.md` — field map, fact ledger, questions, approvals.
- `references/verification-checklist.md` — end-to-end acceptance checks.
