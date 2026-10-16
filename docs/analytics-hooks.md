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

## Tracking code tab (administrator-managed GTM)

Theme Settings → **Tracking code** stores two optional fragments:

- **GTM head script**: rendered verbatim inside `wp_head()` at priority 1, after
  the `dataLayer` bootstrap above.
- **GTM body noscript**: rendered verbatim on `wp_body_open` (priority 1),
  immediately after `<body>` opens. For logged-in users, core renders the admin
  bar first (priority 0); this does not affect GTM.

Rules:

- Empty fields render nothing. Each fragment renders at most once per request,
  site-wide (Home, standard pages, landing pages).
- Paste Google's snippets unchanged. Do not add your own
  `window.dataLayer = [...]` assignment: it would clobber the queue the theme
  already initialized.
- Only users with `manage_options` (Theme Settings) **and** `unfiltered_html`
  can see or change the fields. Saves from any other user, including direct
  writes through `wp-admin/options.php`, keep the stored value.
  Fragments are not escaped, so the capability is the security boundary.
- Container IDs live only in the database. Never commit them to `acf-json/` or
  theme code.

### Migrating from a hardcoded installation

Loading the same container twice double-fires every event, including the form
lifecycle events below. To switch to the tab:

1. Find the current install: view the page source and search for
   `googletagmanager.com/gtm.js`. Note where it comes from (a child theme, an
   edited `header.php`, or a plugin such as Site Kit or GTM4WP).
2. Remove the hardcoded snippet or disable the plugin's GTM output.
3. Paste the head and body snippets into the Tracking code tab and save.
4. Reload the front end and confirm `gtm.js?id=` and `ns.html?id=` each appear
   exactly once in the source.

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