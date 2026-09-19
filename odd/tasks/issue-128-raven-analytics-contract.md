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
- [x] **I128-01 — Bootstrap and document the contract.** Added the pre-GTM dataLayer bootstrap, canonical analytics contract documentation, and 30 focused source/order/no-ID assertions; independently verified and approved through native review.
- [x] **I128-02 — Instrument CTA and call surfaces.** Added stable attributes to existing explicit CTA and click-to-call controls, preserved real destinations/E.164-compatible formatting, removed the fake CTA v2 phone fallback, and proved complete coverage without false share/related labels.
- [x] **I128-03 — Emit safe form lifecycle events and deliver.** Added once-only form start plus confirmed CF7 successful-submit events, enforced identity-only payloads, verified real browser behavior, completed native review, merged the bounded chain, closed #128, and synchronized main.

## Planned Chain

| Slice | Responsibility | Budget |
|---|---|---:|
| 1 — Bootstrap/docs | dataLayer bootstrap, docs, focused contract harness | 389 / 400 |
| 2 — CTA/call hooks | Existing CTA/tel templates and coverage harness | 315 / 400 |
| 3 — Forms/delivery | Global analytics module, CF7 lifecycle, behavioral harness, ODD evidence | 400 / 400 |

## Evidence
- Baseline: `main` at `2011ec45ae198ed4174a2d1b20fb1923eeabf238`.
- Tracker: `feat/issue-128-raven-analytics-contract` at `042159c6826e4b074f46104cabd08331acd5f655`.
- Exploration found no share buttons and no related-post/service component; those selectors remain documented but are not falsely attached to social profiles or navigation.
- Slice 1: commit `470931ddc2c6fa7ab2a0b06a1c7c05fd5d13e2e2`, 389/400 lines; bootstrap contract 30/30, PHP lint, header regressions, diff check, production build, clean scope, and independent verification PASS.
- Slice 1 native review: lineage `review-bbe0cb9a304465f0` approved and acknowledged at revision `sha256:fd97ea29df7e7da9633854d6f0fd2eecc2161ebac5f8ee9a4f05923c9fcd331c`.
- Slice 2: commit `cfa0053ddf4674c58a905952dced6d28993d395a`, 315/400 lines; selector contract 247/247 and services-v2 regression 4/4 PASS; relevant header, footer, CTA, slider, blog, area, vision, and internal-page harnesses, PHP lint, diff check, production build, clean scope, and independent verification PASS.
- Slice 2 native review: lineage `review-a2f48fbd017769f3` approved and acknowledged at revision `sha256:5305ab405cbc746d474ba6f64c0d35016a3f647b9e5b39d39d53431849734577`.
- Slice 3 pre-review: form lifecycle harness PASS with 38 executable behavior checks; once-only tracked-form start, CF7 form-target success, no optimistic/raw-form success, exact identity-only payloads, no field reads, TypeScript, diff check, and production build PASS.
- Installed Contact Form 7 6.1.7 evidence: `includes/js/index.js` builds a bubbling `CustomEvent` in the helper `a(e, ...)` and calls `e.dispatchEvent`, and the feedback path dispatches the `mail${r}` event name with the form as `e`, so real `wpcf7mailsent` targets `form.wpcf7-form`; the tracker resolves the tracked form from the event target and requires CF7 markup, so explicit raw Raven and unrelated forms stay silent while wrapper targets still resolve.
- Slice 3: commit `b2488b3c64187b42e1d92a2fba9f29573ee2b413`, exact 400/400 changed lines (`src/ts/form-tracker.ts` 107 additions; behavioral harness 277); independent verification PASS.
- Slice 3 native review: lineage `review-b0e6b627c7a9458a` approved and acknowledged at revision `sha256:c4546eec3dc3f4c2244624f44d5f0cd960dda9ebd2bfa27b275847580f06dea8`.
- Live verification: 14 browser assertions PASS against a real temporary CF7 page; exact payload keys, stable identity, start deduplication, repeated confirmed success, no PII/IDs/external analytics/runtime errors; temporary page deleted and URL returned 404. Rollback/evidence: `/home/glacayom/backups/simple-rms-theme/issue-128-live-20260919T150807Z`.
- Delivery: tracker PR #143 and slice PRs #144–#146 merged; issue #128 closed; `main` synchronized at `4231a114b88da83c2bea837de14d60f94c58a83a`.
- Post-merge verification: bootstrap 30/30, selectors 247/247, forms 68/68 plus 38 executable fixture checks, CF7/render regressions, 23-file PHP lint, TypeScript, 58-module production build, diff/topology/parity/cleanup checks PASS.
