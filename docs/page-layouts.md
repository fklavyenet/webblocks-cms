# Page Layouts

Page Layouts are install-level definitions that manage the outer public shell choice for pages.

## V1 Scope

- Admin path: `Admin -> System -> Page Layouts`
- Access: `super_admin` only
- V1 supports list, create, edit, activate, deactivate, and ordering
- V1 does not support deleting system layouts
- Pages still store the selected layout handle in `pages.settings.public_shell` for backward compatibility in this release

## Built-in Layouts

- `default` / `Default Layout`: standard public page shell
- `docs` / `Docs Layout`: docs shell with header, sidebar, main, and footer region mapping

These built-in layouts remain backward compatible with older pages, imports, exports, and public rendering.

## How Rendering Works

- A page stores a Page Layout handle in `public_shell`
- Runtime resolves that handle to a managed Page Layout when one exists
- The resolved Page Layout points at a supported `shell_type`
- In V1, supported `shell_type` values are only `default` and `docs`

Custom layouts can safely reuse either the default-shell or docs-shell runtime behavior without changing existing public URLs or slot wrappers.

## Ownership Boundaries

- Page Layout owns the outer public shell choice
- Slot names own region wrapper semantics such as `header`, `main`, `sidebar`, and `footer`
- Blocks own content rendered inside those wrappers
- Sticky navbar behavior remains owned by WebBlocks UI `.wb-navbar`, not by CMS Page Layouts

## Unknown Or Missing Layouts

- Existing pages are not migrated away from `public_shell`
- Unknown layout handles remain stored as-is
- Admin editing stays safe by showing the current legacy handle as a preserved option
- Public rendering falls back safely instead of crashing
- Unknown handles fall back to the default shell type in V1

## Shared Slot Compatibility

Shared Slot compatibility remains conservative in V1.

- Exact `public_shell` handle match is required when a Shared Slot sets `public_shell`
- Empty `public_shell` remains generic
- A custom layout handle that maps to `shell_type = docs` does not automatically match Shared Slots constrained to `docs`

## Portability Boundary

- Site export/import and clone still transfer page-level `public_shell` handles as page configuration
- Install-level Page Layout definitions are not included in site export/import in V1
- If a transferred page uses a custom layout handle, the target install should have a matching Page Layout handle for the intended behavior
- If no matching layout exists on the target install, rendering falls back safely
