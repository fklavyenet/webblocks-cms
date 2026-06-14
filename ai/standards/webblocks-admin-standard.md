# WebBlocks CMS Admin Standard

This standard documents the current WebBlocks CMS admin implementation for future CMS and downstream admin work. It is internal AI guidance, not public product documentation.

Implementation note: The WebBlocks Advisor gate was checked for this documentation cleanup on 2026-06-14. This checkout does not expose an Advisor/knowledge Artisan command, so this standard is based on the current package CMS implementation, especially `packages/webblocks-cms/resources/views/layouts/admin.blade.php`, shared admin partials, package admin screens, README standards, and existing feature tests.

## Layout Contract

- Admin screens must extend `webblocks-cms::layouts.admin`.
- The layout root must be `div.wb-dashboard-shell`.
- The sidebar must use `aside.wb-sidebar` with `id="admin-sidebar"`.
- The narrow/mobile sidebar must use the standard WebBlocks UI toggle/backdrop contract: a shell-local `data-wb-sidebar-backdrop` and a toggle button with `data-wb-toggle="sidebar"` and `data-wb-target="#admin-sidebar"`.
- The content area must stay inside `div.wb-dashboard-body`.
- The topbar must be `header.wb-navbar`.
- The main scroll/content region must be `main.wb-dashboard-main`, with page content wrapped in a normal stack such as `div.wb-stack.wb-stack-6`.
- Do not add an additional page shell, floating wrapper, or custom framed surface around every admin page.

## Sidebar Standard

- The sidebar brand links to the admin dashboard.
- The brand mark must use the package inline brand mark component, not image switching, CSS masks, or ad hoc logos.
- Primary navigation uses `a.wb-sidebar-link` with WebBlocks icon classes and `aria-current="page"` on the active item.
- Grouped navigation uses `div.wb-nav-group`, `button.wb-nav-group-toggle`, and `div.wb-nav-group-items`.
- Group items are permission-aware. System and maintenance groups are only shown to users with `access-system`.
- Plugin menu items may join existing groups or create a plugin group, but they must keep the same sidebar class vocabulary and route/permission checks.
- The sidebar footer must show the product name and CMS version as compact muted centered text: `{WebBlocks::name()} v{WebBlocks::VERSION}`.

## Topbar Standard

- The topbar contains the mobile sidebar toggle, the project identity, color mode/theme controls, and the user menu.
- The project identity uses `wb-navbar-identity`, `wb-navbar-brand`, and optional `wb-navbar-context`.
- The fixed CMS product brand remains in the sidebar. Project name/tagline are install context labels in the topbar, not a replacement for the CMS product identity.
- Theme controls must use WebBlocks UI dropdown hooks and icon triggers, not custom menu JavaScript.
- The user menu uses the current authenticated user initials and a WebBlocks dropdown. Logout is a POST form in that dropdown.

## Brand, Logo, And Copy

- Product brand text is `WebBlocks::name()`.
- Product tagline text is `WebBlocks::slogan()`.
- Admin sidebar and auth surfaces use the package-owned inline SVG brand mark component.
- Browser titles are resolved by `SystemSettings::adminBrowserTitle(...)` through the shared layout.
- Admin page titles should be short nouns or noun phrases. Descriptions should state the immediate operational purpose, not marketing copy.

## Admin Assets

- CMS admin has no Vite, Laravel Vite plugin, Tailwind, npm, Node build chain, `public/build`, or hot-file runtime requirement.
- The admin layout loads pinned WebBlocks UI CSS, icons CSS, pinned WebBlocks UI JavaScript, and shared `public/cms/js/admin/core.js`.
- CMS-owned admin CSS and JS are static files under `public/cms` with matching package source copies under `packages/webblocks-cms/public/cms`.
- Feature-specific admin JavaScript must be page-scoped through `admin-scripts` or `scripts` stacks from the view or partial that renders the matching UI.
- Avoid new custom CSS/JS. Add it only after shipped WebBlocks UI composition is insufficient and the scope is documented.

## Overlay And Feedback Contract

- The admin layout owns exactly one shared overlay root: `#wb-overlay-root.wb-overlay-root`.
- Modals, pickers, destructive confirmations, and stacked overlays must render through the `overlays` stack into that root.
- WebBlocks UI owns backdrop visibility, stacking, z-index, pointer lifecycle, Escape/outside-click behavior, and topmost interactivity.
- Do not add duplicate overlay roots inside screens, cards, or partials.
- Use `wb-alert` for validation, blocking, readiness, and user-correctable errors.
- Use `wb-toast` only for transient success or info feedback when the runtime owns the lifecycle.

## Screen Composition

- Start each normal admin screen with `webblocks-cms::admin.partials.page-header`.
- Render `webblocks-cms::admin.partials.flash` directly after the page header.
- Use `wb-card` as the only generic framed admin surface.
- Do not invent generic card synonyms or custom panel wrappers.
- Do not put a broad page section inside a decorative card unless that section is itself a real card surface such as a list, form, detail group, or dashboard widget.
- Use `wb-grid`, `wb-stack`, `wb-cluster`, status pills, buttons, fields, tables, alerts, empty states, and modals from WebBlocks UI vocabulary.
