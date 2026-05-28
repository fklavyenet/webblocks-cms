# Admin Table Actions Audit

Date: 2026-05-28

## Scope

This audit reviewed the WebBlocks UI source/docs/dist output and WebBlocks CMS admin Blade views/tests for `wb-action-group`, `wb-table-actions`, `wb-text-end`, action columns, icon-only row actions, dropdown row actions, and local nowrap/compact-width workarounds.

Downstream consumers such as QuizTem and Herne should adopt the same table-action standard when they next touch admin list screens, but those repositories are not part of this checkout and were not modified here.

## Findings

- WebBlocks UI already ships a dedicated table action context: `.wb-table-actions` sets compact width, `white-space: nowrap`, and left alignment; `.wb-table-actions .wb-action-group` keeps grouped row actions left aligned and non-wrapping.
- WebBlocks UI keeps `.wb-action-group` generic with `flex-wrap: wrap`. That class is used in card footers, page/header actions, rich-text toolbars, modal/form actions, media picker cards, and builder rows, so making it globally nowrap would risk cramped or overflowing non-table layouts.
- WebBlocks UI docs mostly describe the intended standard, but one admin standards example put `wb-table-actions` on the `Actions` header as well as the row cells. The clearer standard is a normal left-aligned `<th>Actions</th>` plus `td.wb-table-actions`.
- WebBlocks CMS uses `wb-action-group` widely in action cells. Contact Messages, Users, Blocks, Pages, Media, System Backups, Page Layouts, Shared Slots, Domains, Icons, Gallery rows, and several builder/slot tables render icon actions this way.
- `wb-table-actions` is used in the CMS plugin management list, but that screen also carries local `wb-whitespace-nowrap` classes on the cell and action group even though the WebBlocks UI table-action context already owns nowrap behavior.
- Contact Messages uses mixed icon + dropdown row actions in a plain action cell. This is the highest-priority mismatch because the row has view/read icons plus a more-actions dropdown and can visually wrap.
- Users uses icon-only row actions in a plain `wb-whitespace-nowrap` cell with a `wb-action-group wb-whitespace-nowrap` workaround.
- Several action groups are intentionally outside table rows: rich text editor button groups, page and block builder controls, gallery item controls, media picker card controls, page headers, form/card footers, and modal/form actions.
- `wb-text-end` is currently used for numeric Search Index rows, which matches its intended data-alignment purpose. Existing tests already guard page-builder action columns from becoming right-aligned.
- No `wb-table-cations` typo was found.

## Recommended Standard

- Keep `wb-action-group` as a generic horizontal action grouping utility. It may wrap by default so card footers, form footers, toolbars, and modal actions remain responsive.
- Use a normal explicit left-aligned table header: `<th>Actions</th>`.
- Use `td.wb-table-actions` for row action cells, whether the cell contains one action link/button, icon-only actions, or mixed icon + dropdown actions.
- Place grouped row actions inside the cell as `td.wb-table-actions > .wb-action-group`.
- Do not add local `wb-whitespace-nowrap` workarounds to table action groups unless a screen has a documented exception; the WebBlocks UI table-action context owns compact width and nowrap.
- Reserve `wb-text-end` for intentionally right-aligned data such as totals, counts, prices, or metrics, not action columns.
- Prefer left-aligned action cells for CMS admin lists so the `Actions` header and row controls share the same starting edge.

## Implementation Notes

- Contact Messages should be updated first to `td.wb-table-actions > .wb-action-group` so view/read/more actions stay on one line while editorial spam status and SMTP notification failure remain separate columns.
- Users and Plugin Management should drop local nowrap workarounds and rely on the shared `wb-table-actions` pattern.
- Broader CMS list screens can migrate incrementally when touched; this audit does not justify a global CSS change or a redesign of every table.
