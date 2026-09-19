# Issue #126 — Contact Map URL Separation

## Objective

Separate the Google Maps iframe embed URL from the Google Business Profile share URL used by the Get Directions control, while keeping the map resilient and the overlay scoped to the Contact Us template.

## Scope

- Keep `company_google_maps_url` exclusively for the iframe source.
- Add `company_gbp_share_url` immediately after the embed field in Theme Settings.
- Resolve and trim the share URL without falling back to the embed URL.
- Always render the map section and its existing placeholder when no embed URL exists.
- Render Get Directions whenever the share URL is non-empty, including placeholder mode.
- Place the control inside the map section as a bottom-center overlay.
- Restore readable line-height within the overlay.
- Keep contact-map CSS limited to the Contact Us page and preserve iframe heights.

## Non-goals

- Changing landing-page map behavior or asset loading.
- Validating one specific Google share-link hostname or format.
- Changing the existing iframe height breakpoints.
- Migrating existing option values automatically.

## Tasks

- [x] **I126-01 — Implement and verify the split URL contract.** Added focused RED coverage, updated the ACF schema/helper/template/SCSS, split the unchanged 51 assertions across review slices, and made focused/regression checks green.
- [ ] **I126-02 — Verify delivery and publish.** Run independent regressions/build, complete native review, verify the Contact page behavior, update evidence, commit, push, open and merge the issue-linked PR, then synchronize `main`.

## Delivery Strategy — Feature Branch Chain

The complete diff exceeded the 400-line single-PR review budget, so the work is
delivered as a Feature Branch Chain. Nothing reaches `main` until the tracker is
complete. The verification set is unchanged by the split: the two slice
harnesses together carry the same 51 assertions as the pre-split harness.

- Tracker: `feat/issue-126-contact-map-urls` → `main`, **draft/no-merge, empty**
  (integration branch only; no work lands directly on it).
- Child 1: `fix/issue-126-contact-map-urls-01-contract` → tracker.
- Child 2: `fix/issue-126-contact-map-urls-02-overlay-assets` → child 1 branch.

```text
main
 └── feat/issue-126-contact-map-urls                 (tracker, draft/no-merge)
      ↑ PR #1 base: tracker
      └── fix/issue-126-contact-map-urls-01-contract (slice 1)
           ↑ PR #2 base: ...-01-contract
           └── fix/issue-126-contact-map-urls-02-overlay-assets (slice 2)
```

| Slice | Work unit | Files | Observed changed lines | Budget |
|-------|-----------|-------|------------------------|--------|
| 1 — Contract | Split URL contract: schema, helper, template, contract harness (37 assertions) | `acf-json/group_rms_theme_settings.json`, `inc/acf-theme-options.php`, `templates/contact-map.php`, `tests/gbp-share-url-contract-harness.php` | 327 | 327 / 400 |
| 2 — Overlay assets | Additive overlay SCSS, preserved heights, header enqueue scoping (14 assertions) | `src/scss/templates/contact-map.scss`, `tests/contact-map-overlay-assets-harness.php`, `odd/tasks/issue-126-contact-map-urls.md` | 226 | 226 / 400 |

Chain context: slice 1 starts at the `main` baseline; slice 2 depends on slice 1;
follow-up is I126-02 delivery. Out of scope for the chain: landing-page map
behavior, share-hostname validation, height breakpoint changes, and automatic
option migration. Each slice is independently verifiable, and its tests ship
with the production unit it covers.

## Evidence

- Baseline: clean `main` at `6bcbc965109228a0ab63080bd4bebf8a5cba93d3`.
- Issue: <https://github.com/glacayo/simple-rms-theme/issues/126>
- Strict TDD: the original focused harness produced 26 RED failures before production edits and 51/51 GREEN assertions afterward; the split preserves the same assertion-name set as 37 + 14.
- Slice 1 harness: `php tests/gbp-share-url-contract-harness.php` — 37/37 assertions green.
- Slice 2 harness: `php tests/contact-map-overlay-assets-harness.php` — 14/14 assertions green.
- Independent verification: 110/110 focused and relevant regression assertions/scenarios, PHP lint, JSON parse, diff check, and production build PASS.
- Tracker commit: `57f08673c60d789aaee84600f43495d2df9b60cd`.
- Slice 1 commit: `b9ae533b9040a8f0809c2b07f5bf9e1b46a18573`; native lineage `review-835409c217685e05` approved and acknowledged at revision `sha256:6038a644fb13dffb27c882ab9488eba48a07352738c1ac69d895dde8f7ef3636`.
- Slice 2 commit: `b68a323a4869cd39660e5c35fe873b55195e499b`; native lineage `review-22f58effa23de039` approved and acknowledged at revision `sha256:f13f3796286d458c7045dc49ee4f7338686ff35cf489e2bfa3593e915496d08c`.
- Live verification: private Contact page returned HTTP 200 with valid TLS; iframe and directions used distinct URLs; overlay markup/CSS, focus treatment, and 400px/550px heights passed; desktop and settled mobile screenshots confirmed a centered bottom overlay.
- Cleanup: temporary template assignment and four ACF option rows were removed; Contact page remained HTTP 200. Evidence: `/home/glacayom/backups/simple-rms-theme/issue-126-live-20260919T041627Z`.
