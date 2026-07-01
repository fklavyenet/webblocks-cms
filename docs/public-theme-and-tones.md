---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/public-theme-and-tones
cms_title: Public Theme And Visual Tones
cms_layout: docs
cms_source_id: webblocks-cms:docs/public-theme-and-tones.md
---

# Public Theme And Visual Tones

This document records the product direction for site-level public theme presets and public block visual tones in WebBlocks CMS. The initial `icon_tone` block setting is implemented for selected public icon-enabled blocks. Phase 2A added site-scoped public theme preset selection and a public body marker. Phase 2B adds CMS-owned public token styling so selected presets visibly affect public pages.

## Purpose

WebBlocks CMS should help authors create consistent public pages without turning every block setting into a free-form design system. The public theme and visual tone model gives authors a controlled vocabulary for design roles while keeping real color decisions at the site level.

The goal is:

- Sites choose a public theme preset.
- Blocks choose visual roles such as `brand`, `accent`, or `quiet`.
- Public rendering maps those roles to theme-owned tokens and classes.
- Authors do not need arbitrary hex color pickers for normal block styling.

## Problem With Arbitrary Colors

Free per-block color pickers make it easy for public pages to drift away from the site identity. They also make accessibility, dark mode, brand consistency, and future theme changes harder to maintain.

The default CMS workflow should avoid arbitrary per-block color values. A page plan should remain portable across public themes: the same block can ask for a `brand` icon tone, while the selected site theme decides what `brand` means visually.

Advanced custom theme building may become useful later, but it requires a guarded design with contrast checks, light and dark pairs, reset/fallback behavior, import/export portability, and accessibility warnings.

## Admin Theme Vs Public Site Theme

The admin panel theme or accent selection is an admin UI preference. It must stay separate from public site theming.

Public themes belong to the rendered website and should not be derived from:

- The currently signed-in admin user's preference.
- The admin navbar accent choice.
- An install-global UI preference.

The admin surface may eventually expose public theme controls, but those controls manage the selected site, not the admin application chrome.

## Site-Level Ownership

Public theme selection should be site-scoped. In multisite installs, each site may need its own identity, audience, public palette, and tone mapping.

Admin ownership lives under:

`Sites -> Edit Site -> Theme`

The `Theme` tab in the existing Edit Site tab architecture stores the selected public preset on the Site record. Public theme selection must not become install-level state in a way that breaks multisite behavior.

## Block Visual Tones

Block visual tones describe design roles, not semantic status. They are intended for public block styling such as icon tone, border tone, or decorative emphasis.

Planned visual tone labels:

| Label | Value | Intent |
| --- | --- | --- |
| Default | `default` | The normal theme-owned treatment. |
| Soft | `soft` | Low-emphasis supporting treatment. |
| Brand | `brand` | Primary brand or identity treatment. |
| Accent | `accent` | Secondary emphasis treatment. |
| Highlight | `highlight` | Attention-drawing but non-status treatment. |
| Bold | `bold` | Strong visual emphasis. |
| Quiet | `quiet` | Muted, background, or reduced-emphasis treatment. |

Example block settings:

```json
{
  "icon_tone": "brand",
  "border_tone": "soft"
}
```

The initial implementation starts narrow with the shared `settings.icon_tone` field for selected blocks where decorative icons already exist:

- `content_header`
- `card_header`
- `column_item`
- `link-list-item`

`icon_tone` is a shared setting, not locale-owned content. Unknown tones are rejected by admin/API validation or ignored by public rendering as a safe fallback. The renderer emits no tone class for `default`, empty, unknown, inactive, or missing icons.

## Semantic Status Tones

Semantic status tones remain separate from public design tones.

Status values such as `info`, `success`, `warning`, and `danger` should keep their existing meaning for alerts, validation, form feedback, admin messages, and other stateful communication.

They should not become the primary vocabulary for public block design controls. For example, documentation or marketing cards should not use `success` only because the icon should be green. They should choose a visual role, and the site theme should map that role to the appropriate token.

## Theme Preset Names And Character

Supported public theme presets:

| Label | Value | Character |
| --- | --- | --- |
| Canvas | `canvas` | Clean, neutral, classic. |
| Atlas | `atlas` | Documentation and information-site oriented. |
| Pulse | `pulse` | Lively SaaS or product feel. |
| Prism | `prism` | Colorful and creative. |
| Graphite | `graphite` | Serious, dark, and technical. |
| Horizon | `horizon` | Open, airy, and modern. |

`canvas` is the default and fallback preset. CMS core maps every supported preset to public page, surface, text, border, link/accent, button, badge, and visual icon tone tokens in `public/cms/css/public.css`.

## Rendering Model

Public renderers should output classes, attributes, and tokens rather than inline color styles.

Example icon output:

```html
<i class="wb-icon wb-icon-file-text wb-icon-tone-brand" aria-hidden="true"></i>
```

Example public body theme marker:

```html
<body class="wb-public-body" data-wb-public-theme="prism">
```

Example token shape:

```css
body[data-wb-public-theme="prism"] {
  --wb-public-page-bg: ...;
  --wb-public-accent: ...;
  --wb-public-tone-highlight: ...;
}
```

The public layout loads WebBlocks UI CSS first, then CMS public CSS. This lets CMS-owned public theme tokens override WebBlocks UI foundation variables only inside public rendering contexts. Admin chrome, admin sidebar, admin topbar, and admin user appearance preferences do not use `data-wb-public-theme` and are not controlled by these site presets.

The Content API and content-contract discovery should eventually expose tone fields so trusted tools can choose supported values without guessing.

## Accessibility And Dark Mode Rules

Theme presets are token sets, not isolated colors. The current implementation accounts for:

- Text and icon contrast against relevant backgrounds.
- Light and dark context pairs where applicable.
- Token fallback behavior when a theme is missing or incompatible.
- Preview states that show common blocks, icons, borders, links, and surfaces.
- No arbitrary block-level color fields in the default authoring flow.

The site theme preset is the identity layer. The WebBlocks UI color mode toggle remains available through Header Actions where enabled and controls `html[data-mode]`; CMS public theme CSS provides dark-context overrides for non-Graphite presets when `data-mode="dark"` or `data-mode="auto"` resolves dark. `graphite` is intentionally dark by preset character, so its visual identity stays dark in every mode.

## Mode-Aware Site CSS

Canonical `site.css` can refine public pages, but it should cooperate with the selected public theme preset and WebBlocks UI Light/Dark/Auto mode. It should not freeze a page into a separate light or dark design by hard-coding page-wide backgrounds, text colors, white cards, or one-off dark palettes.

Use this order when a page needs visual adjustment:

1. Prefer native block structure and settings, including Media Library background fields and public tone settings.
2. Prefer inherited WebBlocks UI `wb-*` component styling and CMS public theme custom properties.
3. Add semantic site custom properties for site-specific composition.
4. Only use raw colors when existing tokens cannot express the design, and provide light and dark values through active mode selectors or public theme tokens.

The Internal Content API exposes this rule in site asset `guidance`, OpenAPI `x-css-guidance`, discovery workflows, and the content contract so AI/operator tools can read the same constraint before editing `site.css`.

Custom theme builders, if added later, must include guardrails before user-defined colors can affect public pages.

## Implementation Phases

### Phase 1 - Block Visual Tone Foundation

- Done: add `icon_tone` support for the initial target blocks.
- Done: add admin select fields using the visual tone list.
- Done: expose tone field metadata through Internal Content API/content-contract discovery.
- Done: render public tone classes such as `wb-icon-tone-brand`.
- Done: add focused tests, README updates, changelog notes, and relevant docs updates.

### Phase 2A - Site-Level Public Theme Preset Selection

- Done: add site-scoped public theme selection.
- Done: place controls under `Sites -> Edit Site -> Theme`.
- Done: add a compact theme preview/mockup UI.
- Done: output `data-wb-public-theme="{preset}"` on the public body.
- Done: keep `canvas` as the default/fallback for null, unknown, or missing values.
- Done: keep admin theme/accent preferences separate from public site theme selection.

### Phase 2B - Public Theme Tokens

- Done: define CMS-owned public preset tokens for `canvas`, `atlas`, `pulse`, `prism`, `graphite`, and `horizon`.
- Done: map selected presets through public page, surface, text, border, link/accent, button, badge, and visual icon tone hooks.
- Done: keep selected themes scoped to public pages and the admin-safe Theme preview only.
- Done: keep Header Actions preset/accent controls removed while search and safe color mode controls continue to render.

### Phase 3 - Optional Custom Theme Builder

- Add user-defined theme sets only after preset behavior is stable.
- Use guarded color fields with accessibility and contrast warnings.
- Support reset/fallback behavior.
- Support import/export portability.
- Keep arbitrary block-level random colors out of the default workflow.

## Non-Goals

- Custom theme builders are not implemented by this document.
- Do not add Tailwind, Vite, Node build-chain requirements, or package locks.
- Do not expose arbitrary hex color pickers in the first phase.
- Do not couple public themes to admin user theme or accent preferences.
- Do not rename semantic status tones globally.
- Do not add site-specific styling to CMS core.
