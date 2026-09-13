# Canonical Visual Fixtures

WebBlocks CMS publishes its canonical composition fixtures through
`GET /webadmin/api/content-contract` under `design_fixtures`. The source trees
live in `config/design_fixtures.php`; they are product contracts, not starter
content and not install-specific templates.

Each fixture records:

- the content/design problem it solves;
- compatible design-direction characters;
- its rhythm role and complete native block tree;
- expected or forbidden stable public hooks;
- composition-specific anti-patterns;
- deterministic desktop and mobile capture sizes.

The initial catalog contains Editorial Split Hero, Full-bleed Photographic Hero,
Overlapping Editorial Band, Unframed Principles, Alternating Image Story, and
Bounded Entity Cards. Unframed Principles is the
normal repeated-copy reference. Bounded Entity Cards deliberately requires a
card justification and must not be used merely because a source has three
items.

## Capture and approval

The registry status is `render_contract_ready_capture_pending` until the human
operator performs live visual QA. For every fixture, render its tree through the
normal public renderer with the bundled WebBlocks UI and CMS public CSS, then
capture:

| Mode | Desktop | Mobile |
| --- | --- | --- |
| Light | 1440 × 1024 | 390 × 844 |
| Dark | 1440 × 1024 | 390 × 844 |

Store approved images at
`docs/visual-fixtures/{fixture}/{mode}-{viewport}.png`. A capture becomes
canonical only after human review confirms responsive behavior, hierarchy,
contrast, text wrapping, media crop, focus visibility, and absence of overflow.

Live browser QA remains operator-owned. Tests verify that fixture trees only
reference published core block types and that the content contract exposes the
canonical viewports, modes, policies, and composition settings. Screenshot
approval is intentionally not inferred from markup tests.
