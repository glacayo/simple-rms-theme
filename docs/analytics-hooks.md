# Analytics Hooks (Raven GTM Contract)

The theme exposes stable, client-agnostic hooks and a safe `dataLayer`
lifecycle. Measurement itself is owned by the onboarding-generated GTM
container, not by the base theme.

## dataLayer bootstrap

`header.php` emits exactly one statement inside `<head>`, before the `wp_head()`
call:

```html
<script>window.dataLayer = window.dataLayer || [];</script>
```

- The bootstrap only initializes an empty queue. It is harmless without GTM and
  pushes no events.
- The base theme ships no container ID, no `gtag()` call, no GTM snippet, no
  `noscript` iframe, and no external analytics URL.
- GTM owns attribution: channels, macros, and conversion mapping belong to the
  container configuration.
- The theme does not compute channels. It only exposes markup, attributes, and
  event names.

## Stable selector attributes

GTM triggers and variables bind to these attributes. They are part of the
public contract and must not be renamed per project.

| Attribute | Surface | Notes |
|-----------|---------|-------|
| `data-raven-cta` | Primary call-to-action controls | Marker only; the surrounding link keeps its real destination. |
| `data-raven-call` | Click-to-call controls | Only on links whose `href` is a real `tel:` URL. |
| `data-raven-share` | Share controls | Only where an actual share UI exists. |
| `data-related` | Related content links | Only where an actual related-content UI exists. |

## Absence policy

Do not invent surfaces to satisfy a selector. When share or related UI does not
exist, do not relabel social profiles or ordinary navigation with
`data-raven-share` or `data-related`. A missing attribute is correct; a
misleading one corrupts attribution.

## Form lifecycle events

- `form_start` — fires once per form per page view, on the first interaction
  with a form. It must not re-fire on later fields or repeated focus.
- `form_success` — fires only on confirmed success, such as a resolved
  successful AJAX submission. Optimistic UI or an unconfirmed click must not
  emit it.

## Payload allowlist

Every event payload is limited to the following keys:

| Key | Meaning |
|-----|---------|
| `event` | The event name (`form_start` or `form_success`). |
| `form_id` | Stable form identifier. |
| `form_name` | Stable form name. |

## Prohibited PII

Payloads must never include personal data. Prohibited values include email,
phone, message contents, field values, and any other user-entered data. Only the
allowlisted identity keys above may travel to the dataLayer.

## Canonical routes

Canonical route slugs are `contact-us`, `services`, `projects`, and
`thank-you`. These slugs are stable for GTM mapping, but WordPress permalink
prefixes may vary by site, so builds must not hardcode a full path segment.

## Real URLs

Preserve real destinations. Click-to-call controls keep their real `tel:` URL,
and download controls keep their real `download` URL. Never substitute a
placeholder, an `#` fragment, or a shortened redirect for a real target.