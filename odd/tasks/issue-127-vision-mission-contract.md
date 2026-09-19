# Issue #127 — Vision/Mission V1 Contract

## Objective

Align `vision-mission-v1` generation, validation, rendering, and canonical reuse around exactly three ordered, grounded cards: **Our Vision**, **Our Mission**, and **Why Choose Us**.

## Scope

- Make every wizard item-count source request three rows.
- Require the exact ordered semantic titles in AI guidance.
- Reject provider drift deterministically without fabricating missing copy.
- Preserve the validated payload through canonical section reuse.
- Resolve an empty CTA URL through the Contact-page helper.
- Remove repeated generic decoration and use one intentional accent treatment.
- Replace unsafe industry-specific fallback claims with neutral copy.
- Add focused regression coverage and retain existing wizard/landing behavior.

## Non-goals

- Changing `vision-mission-v2`.
- Migrating arbitrary existing page content automatically.
- Changing global AI provider behavior or keyword scope.
- Publishing site content unless a temporary verification fixture is required; any fixture must be backed up and removed.

## Tasks

- [ ] **I127-01 — Correct and verify the complete three-card contract.** Add focused RED regression coverage, implement the generation/validation/template/canonical behavior, run focused and regression checks, perform the authorized temporary live verification, obtain independent verification and native review, then deliver the feature through the approved three-PR chain.

## Delivery Strategy

Use a Feature Branch Chain so no partial implementation reaches `main`:

1. Generation and validation contract — 386 changed lines.
2. Wizard default, assembler fallback, and frontend rendering — 387 changed lines.
3. Canonical replacement and landing reuse integration coverage — 398 changed lines.

Every slice remains within the 400-line review budget and targets its immediate parent; the draft tracker remains unmerged until all three child slices are integrated.

## Evidence

- Baseline: clean `main` at `2ab663c12948bf2be3bcff70d3620e0ebaf8e8a9`.
- Issue: <https://github.com/glacayo/simple-rms-theme/issues/127>
- Strict TDD: initial eight-scenario harness failed before production edits and passed after correction; the final split preserves all assertions across three focused harnesses.
- Independent verification: focused generation/render/canonical suites PASS (3/5/3 scenarios), nine changed PHP files lint, diff check, and production build PASS.
- Baseline limitation: three existing landing harness commands fail under default CLI at both `main` and PR3 with `rms_wizard_landing_execution_budget_unavailable`; with `php -d max_execution_time=30`, identity, lifecycle, and aggregate suites PASS at both revisions (8/8, 12/12, and 20/20 scenarios). These are preserved as baseline failures, not reported as default-command passes.
- Live browser: desktop/mobile PASS on a temporary noindex landing; three ordered cards, uniform accent, no card icons, Contact-page CTA, responsive grid, no overflow, and no JavaScript exceptions.
- Cleanup: temporary page ID 36 absent, zero temporary slugs, deleted URL 404, root/login 200, TLS valid, and Tailscale unchanged.
- Evidence directory: `/home/glacayom/backups/simple-rms-theme/issue-127-live-20260918T204747Z`.
- Tracker commit: `f78e6336fd26265994e7e53313e987e061a6e0a1`.
- PR1 generation commit: `bad6799e61a4a2e15aa4ccf25a4c49b7c1ebb6d0`; native review approved and acknowledged.
- PR2 render commit: `a4a3391b3da7090593a7c8a953b45ddc88a94285`; recovered native review approved and acknowledged.
- PR3 canonical reuse commit: `3b01f4c9573e2e2c47f72e4994d5835de038092b`; native lineage `review-a9d858117a506b07` approved and acknowledged at revision `sha256:7aaf606abcad6aecfe0e3d41ffbfa3603c35b9dfcb33e4c9f56768777a0e6ca4`.
