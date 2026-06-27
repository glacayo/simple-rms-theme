# Simple RMS Theme Developer Wiki

This wiki documents how the theme works, what it intentionally does not include yet, and the operational switches a maintainer needs when debugging the setup wizard.

## Quick path

1. Read the root [`README.md`](../README.md) for the theme stack and asset strategy.
2. Read [`wizard-ai-content.md`](wizard-ai-content.md) before changing the setup wizard or AI copy generation.
3. Read [`debug-and-ops.md`](debug-and-ops.md) before testing wizard state, logs, or kill switches.

## Current docs

| Doc | Use it for |
|-----|------------|
| [`wizard-ai-content.md`](wizard-ai-content.md) | Wizard steps, AI provider flow, content harness, review loop, and current limitations. |
| [`debug-and-ops.md`](debug-and-ops.md) | `WP_DEBUG`, `RMS_WIZARD_FORCE`, `WIZARD_REVIEW_ENABLED`, wizard logs, WP-CLI checks, and deployment notes. |

## What this theme is

- A classic WordPress theme for service-business websites.
- A Vite-built frontend with compiled assets deployed to hosting.
- A theme-owned setup wizard for dependencies, ACF import, client data, page creation, menu setup, AI configuration, and Home page section generation.
- A custom AI integration using the theme's provider abstraction and WordPress HTTP API.

## What this theme is not

- Not a block/FSE theme.
- Not a companion plugin architecture.
- Not dependent on native WordPress AI APIs.
- Not designed to run Node.js in production hosting.
- Not currently covered by PHPUnit, Playwright, or E2E automation.

## Maintenance rule

When code changes behavior, update the closest doc in this wiki in the same work unit. Small docs that stay current beat a large wiki that nobody trusts.
