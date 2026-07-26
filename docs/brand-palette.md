# Brand Palette and Typography

## Purpose

A site's visual identity is a handful of decisions — a brand colour, a
supporting accent, a page background, a text colour, and two typefaces. Public
rendering needs far more values than that: hover and active states, soft tints,
borders, muted text, surface layers, and a readable foreground for every filled
surface, in both light and dark mode.

Before this feature the only way to express a brand was to hand-write
`--wb-public-*` custom properties into the site CSS asset. That put brand
identity in a stylesheet instead of the panel, duplicated every value for dark
mode, and left contrast correctness to the author.

Brand Palette moves the small set of decisions into `Sites -> Edit Site ->
Branding` and derives everything else.

## Contract

Six nullable site fields, all operator-editable and API-writable:

| Field | Type | Meaning |
| --- | --- | --- |
| `brand_accent` | hex colour | Primary brand colour: buttons, links, filled bands |
| `brand_accent_secondary` | hex colour | Supporting accent: borders, icon tone, decorative marks |
| `brand_surface` | hex colour | Page background; the surface ramp is derived from it |
| `brand_text` | hex colour | Body text; muted text is derived from it |
| `brand_font_heading` | font stack | Headings, hero/promo titles, content headers |
| `brand_font_body` | font stack | Body copy, navigation, buttons, form controls |

Leaving a field empty keeps the selected public theme preset value for that
role, so a partially configured palette is valid and presets keep working
unchanged.

### Why four colours and two fonts

The field count is a product decision, not a technical limit. Mature design
systems separate *brand hues* (one primary, optionally one secondary) from
*roles* (a dozen or more). Marketing sites converge on two brand hues plus a
neutral ramp; typography converges on one display family and one text family.
Exposing more inputs would push role definition — the system's job — onto the
operator, and every extra typeface is an additional network request and a
weaker visual system. Status colours (success/warning/danger/info) stay
product-owned because they carry meaning that must not vary per site.

## Derivation

`WebBlocks\Cms\Support\Theme\BrandPalette` is a pure, deterministic function of
the four colours. It performs sRGB mixing and WCAG relative-luminance contrast
selection; no colour depends on runtime browser support.

Light mode:

| Token | Derived from |
| --- | --- |
| `--wb-public-page-bg` | `brand_surface` |
| `--wb-public-surface` | `brand_surface` lightened toward white |
| `--wb-public-surface-muted` | `brand_surface` mixed with `brand_text` (3%) |
| `--wb-public-surface-strong` | `brand_surface` mixed with `brand_text` (7%) |
| `--wb-public-border` | `brand_surface` mixed with `brand_text` (14%) |
| `--wb-public-text` | `brand_text` |
| `--wb-public-muted` | `brand_text` mixed with `brand_surface` (42%) |
| `--wb-public-accent` | `brand_accent` |
| `--wb-public-accent-hover` / `-active` | `brand_accent` darkened 12% / 20% |
| `--wb-public-accent-on` | `brand_surface` or `brand_text`, whichever contrasts better against the accent |
| `--wb-public-accent-soft` / `-softer` | `brand_accent` mixed into `brand_surface` (14% / 7%) |
| `--wb-public-accent-border` | `brand_accent_secondary` mixed into `brand_surface` (55%) |
| `--wb-public-accent-text` | `brand_accent`, darkened until it clears 4.5:1 against the page background |
| `--wb-public-tone-brand` | `brand_accent` |
| `--wb-public-tone-accent-value` | `brand_accent_secondary` |
| `--wb-public-inverse-surface` | `brand_text` darkened, warmed by 8% `brand_accent` |
| `--wb-public-inverse-text` | `brand_surface` |

Dark mode reuses the same inputs with swapped roles: the page background
becomes a near-black tinted with the accent, text becomes the surface colour,
and the accent is lightened toward the secondary accent until it clears 4.5:1
against the dark background. The operator does not maintain a second palette.

`--wb-public-inverse-*` exists so a future block-level `tone` setting can fill
a band and flip its foreground without per-site CSS.

## Rendering

The resolved palette is emitted as one `<style id="wb-public-brand">` element in
the public `<head>`, after `cms/css/public.css` and **before** the site CSS
asset. Ordering matters: presets stay the base layer, the palette overrides
them, and hand-written site CSS can still override the palette when a design
genuinely needs it.

Typography is applied by the same block through two tokens:

```css
body.wb-public-body { font-family: var(--wb-public-font-body); }
main :is(h1, h2, h3, h4), .wb-promo-title, .wb-content-title {
  font-family: var(--wb-public-font-heading);
}
```

Font *files* remain a site-asset concern: the palette assigns families, it does
not host webfonts. Operators who need a webfont upload it and declare
`@font-face` in the site CSS asset. When the family is a variable font, declare
one `@font-face` with a weight range (`font-weight: 100 900`) rather than one
declaration per weight — repeating the same file under several fixed weights
downloads it multiple times and pins each rule to a single instance.

## Validation

- Colours must be `#rgb` or `#rrggbb`; anything else is rejected.
- Font stacks accept letters, digits, spaces, hyphens, underscores, dots,
  commas, and quotes only. Braces, semicolons, parentheses, and `url(` are
  rejected, so the stack cannot escape the declaration it is written into.
- Stacks are capped at 180 characters and rendered escaped.
- The admin form reports the contrast ratio of `accent` against the page
  background and warns below 4.5:1 rather than blocking, matching the existing
  site CSS mode-awareness warning model.

## API

`PATCH /webadmin/api/sites/{site}/branding` accepts the six fields alongside the
existing branding fields and requires the same `site-settings.write`
capability. `GET /webadmin/api/sites` returns them, plus a `brand_palette`
object holding the resolved tokens so operator tools can preview the derived
values without reimplementing the maths.

## Boundaries

- The palette never emits selectors other than the two token scopes and the two
  typography rules above; it is not a general CSS injection point.
- Status colours, spacing, radii, and shadows stay product-owned.
- Presets remain the fallback layer and are not removed.
