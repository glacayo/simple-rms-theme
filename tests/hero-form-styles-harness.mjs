// Focused contract harness for issue #170 (Hero/CF7 form spacing and contrast).
//
// Compiles the real section stylesheets with the theme's Sass and proves:
//   1. CF7 paragraph wrappers inside form groups carry no margin, so stacked
//      fields and grid rows (where margins do not collapse) share one rhythm.
//   2. Groups inside a row drop their own margin; the row owns a single
//      bottom margin and gap, stacks on mobile and splits into two columns
//      from the small breakpoint up.
//   3. Placeholders reach at least 4.5:1 contrast on white, opacity 1, both in
//      the shared Hero form and in the Contact section's own inputs.
//   4. Contact loads the same Hero form field styles, so a reused Hero-style
//      CF7 form is styled there too.
//   5. Every new rule stays scoped to the hero__form-* / contact-info__*
//      classes; no bare element selectors leak to unrelated forms.
//
// Usage: node --test tests/hero-form-styles-harness.mjs

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const sass = require('sass');

const themeRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const compile = (entry) =>
  sass.compile(resolve(themeRoot, entry), { style: 'expanded', loadPaths: [resolve(themeRoot, 'src/scss')] }).css;

const hero = compile('src/scss/templates/hero.scss');
const contact = compile('src/scss/templates/contact-info.scss');

/** Declarations of every top-level rule whose selector list equals `selector`. */
function rules(css, selector) {
  const out = [];
  const re = /([^{}]+)\{([^{}]*)\}/g;
  let m;
  while ((m = re.exec(css))) {
    const sel = m[1].trim().replace(/\s+/g, ' ');
    if (sel === selector) out.push(m[2]);
  }
  return out.join(';');
}

function luminance(hex) {
  const [r, g, b] = hex.match(/[0-9a-f]{2}/gi).map((h) => parseInt(h, 16) / 255).map((c) =>
    c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4,
  );
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}
const contrastOnWhite = (hex) => (1.05) / (luminance(hex) + 0.05);

function placeholder(css, selector) {
  const decl = rules(css, selector);
  const color = (decl.match(/color:\s*(#[0-9a-f]{6})/i) || [])[1];
  const opacity = (decl.match(/opacity:\s*([\d.]+)/) || [])[1];
  return { color, opacity };
}

for (const [label, css] of [['hero', hero], ['contact', contact]]) {
  test(`${label}: CF7 paragraph wrappers inside form groups have no margin`, () => {
    assert.match(rules(css, '.hero__form-group > p'), /margin:\s*0/);
  });

  test(`${label}: grouped fields keep a single bottom margin`, () => {
    assert.match(rules(css, '.hero__form-group'), /margin-bottom:\s*1rem/);
  });

  test(`${label}: rows own one gap and one bottom margin; their groups drop theirs`, () => {
    const row = rules(css, '.hero__form-row');
    assert.match(row, /display:\s*grid/);
    assert.match(row, /grid-template-columns:\s*1fr;/);
    assert.match(row, /gap:\s*1rem/);
    assert.match(row, /margin-bottom:\s*1rem/);
    assert.match(rules(css, '.hero__form-row > .hero__form-group'), /margin-bottom:\s*0/);
  });

  test(`${label}: rows split into two columns from the small breakpoint`, () => {
    assert.match(css, /@media \(min-width: 640px\) \{\s*\.hero__form-row \{\s*grid-template-columns: 1fr 1fr;/);
  });

  test(`${label}: hero form placeholders reach 4.5:1 on white with opacity 1`, () => {
    const { color, opacity } = placeholder(css, '.hero__form-group input::placeholder, .hero__form-group select::placeholder, .hero__form-group textarea::placeholder');
    assert.ok(color, 'placeholder color must be declared');
    assert.ok(contrastOnWhite(color) >= 4.5, `contrast ${contrastOnWhite(color).toFixed(2)}:1 for ${color}`);
    assert.equal(opacity, '1');
  });

  test(`${label}: new form rules stay scoped to hero__form classes`, () => {
    for (const sel of ['p', 'input', 'label', '.wpcf7 p', '.wpcf7-form p', 'form p']) {
      assert.equal(rules(css, sel), '', `unscoped selector "${sel}" must not be emitted`);
    }
  });
}

test('contact: section inputs placeholders reach 4.5:1 on white with opacity 1', () => {
  const { color, opacity } = placeholder(contact, '.contact-info__input::placeholder, .contact-info__select::placeholder, .contact-info__textarea::placeholder');
  assert.ok(color, 'placeholder color must be declared');
  assert.ok(contrastOnWhite(color) >= 4.5, `contrast ${contrastOnWhite(color).toFixed(2)}:1 for ${color}`);
  assert.equal(opacity, '1');
});

test('hero: managed CF7 overrides stay scoped under .hero__form-card', () => {
  assert.match(rules(hero, '.hero__form-card .wpcf7-not-valid-tip'), /display:\s*block/);
  assert.equal(rules(contact, '.hero__form-card .wpcf7-not-valid-tip'), '', 'hero-card-only CF7 overrides must not move into Contact');
});

test('submit button styles are shared by both hosts', () => {
  for (const css of [hero, contact]) {
    assert.match(rules(css, '.hero__form-submit'), /width:\s*100%/);
  }
});
