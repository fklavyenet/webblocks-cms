# WebBlocks CMS Admin Screen Patterns

This standard documents current CMS admin screen patterns for implementation and review. It is internal AI guidance, not public product documentation.

Implementation note: The WebBlocks Advisor gate was checked for this documentation cleanup on 2026-06-14. This checkout does not expose an Advisor/knowledge Artisan command, so this standard is based on current package admin Blade views, shared partials, README standards, and tests.

## Page Header And Flash

- Use `webblocks-cms::admin.partials.page-header` at the top of normal admin screens.
- Page header may include title, short description, context, count, and page-level actions.
- Keep creation/upload/add actions in the listing card header when that is the established pattern for the screen.
- Render `webblocks-cms::admin.partials.flash` directly after the page header.
- Do not duplicate validation and status summary patterns locally unless a screen has a specific blocking state.

## Dashboard

- The dashboard uses normal `wb-card` widgets inside WebBlocks grids and stacks.
- Shortcut/action cards use a card header plus compact button clusters.
- Overview metrics use status pills and terse muted helper copy.
- Recent lists use `wb-link-list`; empty dashboard widgets use `wb-empty wb-empty-sm`.
- Plugin dashboard widgets may render as cards, but only through documented plugin dashboard slots and without changing the core dashboard shell.

## Index And List Screens

- If the screen has real list-changing filters, use `webblocks-cms::admin.partials.listing-filters`.
- Filters live in a muted `wb-card` above the list card.
- Search is first and grows on wide screens.
- Context selectors such as Site follow search.
- Remaining compact selects/inputs follow context selectors.
- Apply/reset actions remain right-aligned on wide screens.
- The list itself is a separate `section` or `div.wb-card`.
- The list card header owns the list title, filtered count, and list creation/upload/import actions.
- Page-header count is the unfiltered base-scope total when both page and card counts are present.
- Listing-card count is the filtered visible-result count.

## Table And Action Standard

- Admin tables must use `wb-card > wb-card-body > wb-table-wrap > table.wb-table`.
- Use `wb-table-striped` and `wb-table-hover` when the existing screen pattern does.
- Use an explicit normal left-aligned `<th>Actions</th>`.
- Row action cells must be `td.wb-table-actions`.
- Grouped row actions must be `td.wb-table-actions > .wb-action-group`.
- Do not use `wb-text-end` for action columns. Reserve it for right-aligned numeric data.
- Do not add local nowrap classes to action cells or action groups unless a documented exception is required.
- Keep editorial status, delivery failures, readiness badges, and other state in separate columns, not inside the action column.
- Use icon buttons with accessible labels/titles for compact row actions.

## Bulk Actions

- Bulk actions select only records visible on the current page.
- Use a leading checkbox column, a visible `select all visible` control, and the shared compact selected-count action bar.
- Bulk destructive actions post selected IDs to a server-side validated endpoint.
- Every selected record must still pass authorization and domain-specific safety checks server-side.
- Mixed success should return partial-success feedback.
- Do not claim or implement select-all-filtered-across-pagination unless the backend and copy explicitly support it.

## Detail Screens

- Detail/read-only screens use `wb-card` groups with `wb-card-header` and `wb-card-body`.
- Read-only operational facts should use compact meta grids, status pills, muted labels, and code blocks only where paths/tokens/checksums need exact display.
- Detail modals should use `wb-modal`, grouped `wb-card wb-card-muted` sections, clear close controls, and the shared overlay root.
- Avoid drawers for standard CMS details unless the product pattern explicitly changes.

## Create And Edit Forms

- Forms should live in `wb-card`.
- Use `wb-card-header` for the form title, `wb-card-body` for fields, and `wb-card-footer` for actions.
- Use `wb-input`, `wb-select`, `wb-textarea`, `wb-checkbox`/`wb-check`, and WebBlocks field/error vocabulary.
- Use package Form Request validation where practical.
- Use `<x-webblocks-cms::admin.form-actions>` for standard submit/cancel/delete action rows where it fits.
- For delete flows, prefer a dedicated danger-zone card or WebBlocks confirmation modal over inline destructive buttons.

## Danger Zone And Destructive Actions

- Destructive actions must be visually marked with `wb-btn-danger` or `wb-action-btn-delete`.
- Browser `confirm()` is not the standard confirmation UI. Use WebBlocks modal confirmation through `webblocks-cms::admin.partials.destructive-confirmation-modal` or an equivalent modal under `#wb-overlay-root`.
- A danger zone should explain what is deleted, what is preserved, and whether the action can be undone from admin.
- Destructive forms must use POST plus method spoofing and CSRF protection.

## Empty States

- Use `wb-empty` for full empty list states.
- Use `wb-empty wb-empty-sm` inside compact cards, dashboard widgets, and previews.
- Empty states should be actionable when there is a clear next step.
- Empty states should not look like loading placeholders after data has resolved.

## Success, Error, Warning, And Info

- Use `wb-alert-success` for durable success status.
- Use `wb-alert-danger` for validation failures and blocked actions.
- Use `wb-alert-warning` for degraded readiness/server details that need attention.
- Use `wb-alert-info` for neutral operational guidance, backup protection, and setup-required guidance.
- Use `wb-toast` only for transient runtime feedback where WebBlocks UI owns toast lifecycle.

## Pagination

- Use `webblocks-cms::admin.partials.pagination`.
- Dense listings should enable compact mode.
- Compact pagination shows page links and a compact `from-to/total` summary in one row.
- Preserve current query strings through paginator URLs.

## Update Screen

- The System Updates screen uses a status-first operational layout: `Update Status`, compact safety summary, visible `Release` and `Readiness` cards, then read-only `Update History`.
- Use status pills, alerts, meta grids, WebBlocks UI tables, and exact code blocks for package safety details.
- Update actions must remain POST-backed and readiness-aware.
- Release notes, readiness diagnostics, support report download, and retained update-run history must stay separate from the primary install action.

## Admin Copy

- Copy should be direct and operational.
- Prefer concrete nouns: `Pages`, `Media`, `System Updates`, `Update Status`, `Release`, `Readiness`, `Update History`.
- Avoid marketing promises, vague reassurance, and implementation backstory in UI copy.
- Risk copy belongs next to the action it affects, usually in alerts, danger zones, modal descriptions, or callouts.
