# ACF JSON Generator Scripts

## `generate-acf-json.php`

### What it does

Reads the ACF flexible content field group definition from
`inc/acf-flexible-content.php` and writes it to
`acf-json/group_rms_page_sections.json` in the format expected by ACF Pro's
JSON sync feature.

The script works standalone — it does **not** require a running WordPress
instance. It mocks the minimal WordPress/ACF functions needed to capture the
field group array, then converts it to JSON and updates the `modified`
timestamp.

### How to run

From the theme root:

```bash
php scripts/generate-acf-json.php
```

### When to run

Run this script **after modifying** `inc/acf-flexible-content.php` (e.g.,
adding a new layout or changing field properties). This keeps the JSON
snapshot in sync with the PHP source so that ACF Pro's JSON sync continues
to work correctly.

### Important notes

- This is a **development tool**, not a production asset.
- The generated JSON is committed to the repo (`acf-json/`) so the team can
  import/sync field groups via ACF Pro.
- The `scripts/` directory itself is **tracked in Git** so the generator is
  available to all team members.
