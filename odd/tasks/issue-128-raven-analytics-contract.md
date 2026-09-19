# Issue #128 — Raven GTM / dataLayer Contract

## Goal
Provide stable, client-agnostic Raven selectors and safe dataLayer lifecycle events for onboarding-generated GTM containers.

## Constraints
- Bootstrap `window.dataLayer` before `wp_head()`; never commit a GTM/GA ID or snippet.
- Add `data-raven-cta` to primary CTAs and `data-raven-call` to valid real `tel:` links.
- Add share/related attributes only where actual share/related UI exists; do not relabel social profiles or ordinary navigation.
- Emit `form_success` only from confirmed AJAX success and `form_start` once per form/page view.
- Payloads contain stable form identity only; never email, phone, message, field values, or other PII.
- Preserve real download URLs and document canonical route prefixes.
- Keep each reviewed slice at or below 400 changed lines.

## Tasks
- [ ] **I128-01 — Bootstrap and document the contract.** Add the pre-GTM dataLayer bootstrap, canonical analytics contract documentation, and focused source/order/no-ID tests.
- [ ] **I128-02 — Instrument CTA and call surfaces.** Add stable attributes to existing primary CTA and click-to-call controls, preserve real URLs/E.164-compatible tel formatting, and prove complete surface coverage without false share/related labels.
- [ ] **I128-03 — Emit safe form lifecycle events and deliver.** Add once-only form start plus CF7 successful-submit events, enforce no-PII payloads, verify build/browser behavior, complete native review, publish/merge the chain, and synchronize main.

## Planned Chain

| Slice | Responsibility | Budget |
|---|---|---:|
| 1 — Bootstrap/docs | dataLayer bootstrap, docs, focused contract harness | 389 / 400 |
| 2 — CTA/call hooks | Existing CTA/tel templates and coverage harness | ≤400 |
| 3 — Forms/delivery | Global analytics module, CF7 lifecycle, behavioral harness, ODD evidence | ≤400 |

## Evidence
- Baseline: `main` at `2011ec45ae198ed4174a2d1b20fb1923eeabf238`.
- Tracker: `feat/issue-128-raven-analytics-contract` at `042159c6826e4b074f46104cabd08331acd5f655`.
- Exploration found no share buttons and no related-post/service component; those selectors remain documented but are not falsely attached to social profiles or navigation.
- Slice 1 pre-review: bootstrap contract 30/30, PHP lint, header regressions, diff check, and production build PASS; no GTM/GA identifier, snippet, event push, or external analytics URL added.
