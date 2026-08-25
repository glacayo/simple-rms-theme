# Client Intake

Maps to the REAL Simple RMS Theme Setup Wizard client-data fields. The wizard reads field definitions from `acf-json/group_rms_theme_settings.json` (sections: General, Contact, Social Media, Branding, Layout, Business). Schema fields are excluded from the wizard.

## Non-negotiable rule

NEVER invent, infer, scrape, or hallucinate any value below. For each field, record its source as one of:
- `human-confirmed` — the human answered directly.
- `existing-site-confirmed` — read from the live existing site with the human's confirmation.
- `developer-supplied` — the developer provided it explicitly.

If a value is missing or ambiguous, STOP at that field and ask ONE concise question. Reject ambiguous answers and ask a narrower follow-up.

## Fact ledger schema

Record every field as a row:

| field | value | source | status | notes |
|---|---|---|---|---|

`status` ∈ { `pending`, `confirmed`, `skipped-optional` }.

Before running the `client-data` step, print the full ledger and require the human to confirm explicitly.

## Required client-data fields

These are the real ACF Theme Settings fields. `company_name` is the only formally REQUIRED ACF field, but the wizard cannot produce a coherent site without the business-identity fields; ask for all of them.

### General
| field | type | human question |
|---|---|---|
| `company_name` | text (REQUIRED) | "What is the exact legal/trade business name?" |
| `company_language` | text | "What language should the site content use?" |
| `company_estimate_available` | text | "Do you offer free estimates? What exact wording?" |
| `company_license` | text | "License number / registration to display?" |
| `company_covered_areas` | text | "Which geographic areas/cities do you serve?" |

### Contact
| field | type | human question |
|---|---|---|
| `company_phones` | repeater (`phone_label`, `phone_number`) | "List each phone: a label (Main/Office/etc.) and the number." |
| `company_emails` | repeater (`email_label`, `email_address`) | "List each email: label and address." |
| `company_address_line_1` | text | "Street address line 1?" |
| `company_address_line_2` | text | "Street address line 2 (if any)?" |
| `company_city` | text | "City?" |
| `company_state` | text | "State/Province?" |
| `company_postal_code` | text | "Postal code?" |
| `company_country` | text | "Country?" |
| `company_schedule` | repeater (`schedule_day` select, `schedule_open_time` time_picker, `schedule_close_time` time_picker, `schedule_is_closed` true_false) | "For each day: open time, close time, or closed? (HH:MM)" |
| `company_google_maps_url` | url | "Google Maps URL for the location?" |

### Social Media
| field | type | human question |
|---|---|---|
| `company_social_media` | repeater (`social_platform` select, `social_label` text, `social_url` url, `social_is_active` true_false) | "List each social profile: platform, label, URL, active?" |

### Branding
| field | type | human question |
|---|---|---|
| `company_favicon` | image (attachment ID) | "Upload the favicon in WP Admin; provide the attachment ID or upload now." |
| `company_logo_header` | image | "Header logo (upload in WP Admin)." |
| `company_logo_footer` | image | "Footer logo (upload in WP Admin)." |
| `company_palette_color_1` | color_picker (hex) | "Primary brand color (hex)?" |
| `company_palette_color_2` | color_picker | "Secondary color (hex)?" |
| `company_palette_color_3` | color_picker | "Accent color 1 (hex)?" |
| `company_palette_color_4` | color_picker | "Accent color 2 (hex)?" |

### Layout
| field | type | human question |
|---|---|---|
| `company_header_version` | select | "Which header version? (choices come from ACF)" |
| `company_footer_version` | select | "Which footer version? (choices come from ACF)" |

### Business
| field | type | human question |
|---|---|---|
| `company_payment_methods` | repeater (`payment_method_name`) | "Which payment methods do you accept?" |
| `company_services` | repeater (`service_name`, `service_slug`, `service_short_description` textarea) | "List each service: name, slug, short description. Do NOT invent services." |

## Image uploads

Images (`favicon`, `logo_header`, `logo_footer`) are ACF `image` fields storing attachment IDs. The agent must not fabricate image files. Ask the human to upload via WP Admin Media Library and provide the attachment ID, or use the wizard's media picker in the browser.

## Human continuation: keyword and landing questions

These are NOT client-data fields. The agent's bootstrap assistance ends after AI configuration; provide these questions as a human-only Landing Builder checklist. Do not fill or submit them automatically:

1. "Is this landing SEO (indexable, menu-eligible) or Ads (noindex, orphan, never in menu)?"
2. "What is the primary keyword for this landing?" (required, non-empty)
3. "List up to 10 subkeywords, comma-separated."
4. "Which reusable sections should this landing include?" (Only Hero and SEO Content receive keywords; other layouts stay neutral/canonical.)
5. "Should any reusable layouts be regenerated to overwrite the canonical store?" (confirm the canonical-replace decision)

Never invent keywords, search intent, or landing type. Never auto-assign Ads landings to menus.

## Menu choices (menu-setup step)

Ask the human which generated pages go in the primary menu and which in the mobile menu (mobile may reuse primary). Never guess menu composition.

## Page roles (generate-pages step)

Common page defaults the wizard offers: Home, About, Services, Blog, Contact, Projects, Testimonials. The human may rename, remove, or add pages. Only Home and Blog have special roles; confirm both assignments. Never invent page names/roles beyond what the human approves.

## AI provider / credentials (ia-generation step)

Providers: `ollama-cloud`, `openai`, `google`, `openrouter`. The agent NEVER asks for or accepts the API key in chat. Direct the human to WP Admin → Setup Wizard → IA Generation to:
1. Select the provider.
2. Enter the API key in the password field.
3. Click "Test / Load models" to validate and save the encrypted key.
4. Select a model (or enter a manual model name).

After the human confirms credentials are saved and a model is selected, the agent verifies masked status, returns the handoff, and stops. Home Builder and Landing Builder are human-operated.
