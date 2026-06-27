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

This document records the planned product direction for site-level public theme presets and public block visual tones in WebBlocks CMS. It is documentation-only planning and does not describe runtime behavior that exists today.

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

Long-term admin ownership should live under:

`Sites -> Edit Site -> Theme`

The first implementation can add a new `Theme` tab to the existing Edit Site tab architecture if that is the smallest compatible path. Public theme selection must not become install-level state in a way that breaks multisite behavior.

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

Initial implementation should start narrow. The recommended first block setting is `icon_tone` for selected blocks where decorative icons already exist or are planned:

- `content_header`
- `card_header`
- `column_item`
- `link-list-item`

## Semantic Status Tones

Semantic status tones remain separate from public design tones.

Status values such as `info`, `success`, `warning`, and `danger` should keep their existing meaning for alerts, validation, form feedback, admin messages, and other stateful communication.

They should not become the primary vocabulary for public block design controls. For example, documentation or marketing cards should not use `success` only because the icon should be green. They should choose a visual role, and the site theme should map that role to the appropriate token.

## Theme Preset Names And Character

Planned public theme presets:

| Label | Value | Character |
| --- | --- | --- |
| Canvas | `canvas` | Clean, neutral, classic. |
| Atlas | `atlas` | Documentation and information-site oriented. |
| Pulse | `pulse` | Lively SaaS or product feel. |
| Prism | `prism` | Colorful and creative. |
| Graphite | `graphite` | Serious, dark, and technical. |
| Horizon | `horizon` | Open, airy, and modern. |

The exact token values are future implementation work. Presets should define tone tokens for public rendering and may need light/dark-aware values.

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
[data-wb-public-theme="prism"] {
  --wb-public-tone-brand: ...;
  --wb-public-tone-accent: ...;
  --wb-public-tone-highlight: ...;
}
```

The Content API and content-contract discovery should eventually expose tone fields so trusted tools can choose supported values without guessing.

## Accessibility And Dark Mode Rules

Theme presets should be designed as token sets, not isolated colors. Any future implementation should account for:

- Text and icon contrast against relevant backgrounds.
- Light and dark context pairs where applicable.
- Token fallback behavior when a theme is missing or incompatible.
- Preview states that show common blocks, icons, borders, links, and surfaces.
- No arbitrary block-level color fields in the default authoring flow.

Custom theme builders, if added later, must include guardrails before user-defined colors can affect public pages.

## Implementation Phases

### Phase 1 - Block Visual Tone Foundation

- Add `icon_tone` support for the initial target blocks.
- Add admin select fields using the visual tone list.
- Expose tone field metadata through Internal Content API/content-contract discovery.
- Render public tone classes such as `wb-icon-tone-brand`.
- Add focused tests, README updates, changelog notes, and relevant docs updates.

### Phase 2 - Site-Level Public Theme Presets

- Add site-scoped public theme selection.
- Place controls under `Sites -> Edit Site -> Theme`.
- Add a theme preview or mockup UI.
- Define light/dark-aware preset tokens.
- Output a public body class or `data-wb-public-theme` attribute.
- Ensure selected themes apply to public pages only.

### Phase 3 - Optional Custom Theme Builder

- Add user-defined theme sets only after preset behavior is stable.
- Use guarded color fields with accessibility and contrast warnings.
- Support reset/fallback behavior.
- Support import/export portability.
- Keep arbitrary block-level random colors out of the default workflow.

## Non-Goals

- No runtime code is defined by this document.
- No migration is approved by this document.
- No admin screen is implemented by this document.
- No CSS, JavaScript, Blade, or API endpoint is implemented by this document.
- Do not add Tailwind, Vite, Node build-chain requirements, or package locks.
- Do not expose arbitrary hex color pickers in the first phase.
- Do not couple public themes to admin user theme or accent preferences.
- Do not rename semantic status tones globally.
- Do not add site-specific styling to CMS core.
