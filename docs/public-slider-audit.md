# Public Slider Audit

## Scope

This audit reviews the remaining inline public slider JavaScript and determines whether it belongs to WebBlocks CMS or WebBlocks UI.

No runtime code was changed as part of this audit.

## Inline Script Location

The current inline slider JavaScript lives in:

- `resources/views/layouts/public.blade.php`

The script binds to these selectors:

- `[data-wb-slider]`
- `[data-wb-slider-track]`
- `[data-wb-slider-slide]`
- `[data-wb-slider-prev]`
- `[data-wb-slider-next]`
- `[data-wb-slider-dot]`

## Slider Markup Location

Current slider markup is rendered from:

- `resources/views/pages/partials/blocks/fallback.blade.php`

Specifically, it appears in the `@case('slider')` branch and renders:

- `.wb-slider`
- `.wb-slider-track`
- `.wb-slider-slide`
- previous and next buttons
- dot buttons using `data-wb-slider-dot`

## Welcome Page Usage

The current WebBlocks CMS welcome page is rendered from:

- `resources/views/welcome.blade.php`

It extends:

- `resources/views/layouts/public.blade.php`

The welcome page does **not** include any slider markup.

Evidence:

- `resources/views/welcome.blade.php` contains only hero, cards, status pills, and CTA structure
- no `data-wb-slider*` attributes appear in the welcome view

Conclusion:

- the inline slider script still loads on the welcome page because it is in the public layout
- the welcome page itself does not use the slider

## Whether Any CMS Blocks Use It

Yes.

The slider markup is currently tied to CMS-rendered content through the fallback public block renderer:

- `resources/views/pages/partials/blocks/fallback.blade.php`

This means the behavior is not limited to the welcome screen.

It is used when a block resolves to the `slider` fallback case and renders public content with gallery assets.

## WebBlocks UI Dependency Audit

The public layout currently loads this WebBlocks UI asset:

- `https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.js`

### CDN Asset Check

The shipped WebBlocks UI CDN JS and CSS were checked for slider-related support and no slider or carousel behavior was found.

No matches were found for:

- `data-wb-slider`
- `wb-slider`
- `carousel`
- slider control selectors

### Local WebBlocks UI Source Check

A sibling local repo exists at:

- `/Users/osm/Sites/projects/project-web_blocks/project-webblocks-ui`

That source was checked for slider or carousel support.

Relevant findings:

- `webblocks-ui/docs/pattern-gallery.html` explicitly says the gallery pattern is **not** a carousel
- the docs warn not to use the current gallery for sliders or carousels
- no slider implementation files or shipped slider assets were found in the UI repo

Key evidence from the UI docs:

- `wb-gallery` is not a carousel
- slider or carousel capabilities are not present in the shipped source

## Ownership Assessment

The current slider behavior uses `data-wb-*` naming and WebBlocks-style classes, but the available evidence shows that WebBlocks UI does **not** currently ship slider or carousel behavior.

That means the current inline slider JavaScript is not duplicating an existing shipped UI pattern.

It is a CMS-side implementation attached to CMS-rendered slider block markup.

## Classification

Classification:

- **B. Missing WebBlocks UI pattern that should be implemented in the UI project later**

### Why Not A

It is not just welcome-page behavior, because the welcome page does not use the slider and the markup lives in the CMS block fallback renderer.

### Why Not C

It is not an obvious duplicate of shipped WebBlocks UI behavior, because the loaded UI CDN asset and local UI source do not provide a slider pattern.

### Why Not D

It is not dead code, because slider markup is actively present in the public block fallback for the `slider` block case.

## Recommended Next Action

Recommended next action:

- keep it as CMS-owned behavior for now, but move it later to a narrowly named CMS asset such as `public/assets/webblocks-cms/js/public-slider.js`

Reasoning:

- the behavior is currently needed for CMS-rendered slider markup
- it does not belong in the public layout inline script forever
- it does not appear to be a shipped WebBlocks UI pattern today
- the naming should avoid implying that this is already a formal UI primitive

## Follow-Up Task For WebBlocks UI

Recommended follow-up task:

- evaluate whether WebBlocks UI should formally define a slider or carousel pattern and its behavioral contract

Questions for the UI project:

- should slider behavior become a first-class WebBlocks UI primitive?
- what markup, keyboard behavior, accessibility rules, and motion rules should it support?
- should CMS fallback slider markup be aligned to that future UI pattern once it exists?

## Summary

- inline script file: `resources/views/layouts/public.blade.php`
- slider markup file: `resources/views/pages/partials/blocks/fallback.blade.php`
- welcome page uses it: no
- CMS blocks use it: yes, via the `slider` fallback block case
- WebBlocks UI already supports it: no evidence found in the shipped CDN asset or local UI source
- classification: **B. Missing WebBlocks UI pattern that should be implemented in the UI project later**
- recommended next action: move it later to a narrowly named CMS asset and treat UI formalization as a separate future task

## Implemented Refactor

- slider behavior moved to `public/assets/webblocks-cms/js/public-slider.js`
- `resources/views/layouts/public.blade.php` now loads that asset with `defer`
- the welcome page may load the asset, but it safely no-ops because no slider markup exists there
- a formal WebBlocks UI slider or carousel pattern is still a future task outside this CMS refactor
