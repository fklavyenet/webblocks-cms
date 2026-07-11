# WebBlocks CMS Admin UI Skill

Use this skill when changing CMS admin, auth, dashboard, settings, listing, modal, action, or control-panel UI.

## Starting Point

- Start from `AGENTS.md`.
- Read the pinned WebBlocks UI AI contract before UI changes:
  `https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@v2.7.18/packages/webblocks/dist/ai/contract.md`
- The CMS code pins this version in `WebBlocks::UI_VERSION`. If the pinned WebBlocks UI version changes, update the contract URL in `AGENTS.md` and this skill in the same work session.
- Use the contract plus existing CMS admin screens that already follow WebBlocks UI patterns; do not invent new UI vocabulary, custom shells, custom framed surfaces, or custom overlay behavior.

## Pattern-First UI Rules

- Start from shipped WebBlocks UI patterns before creating local wrappers.
- Preserve `wb-dashboard-shell` for admin/dashboard screens.
- Preserve `wb-auth-shell` for auth screens.
- Preserve `wb-settings-shell` for settings screens.
- Use `wb-card` as the generic framed surface.
- Do not invent generic framed wrappers or local UI nouns when `wb-card` or shipped patterns fit.
- Use the shared compact listing filter toolbar when real filters exist.
- Preserve the canonical table action contract: `wb-page-header`, filters before the list card, `section.wb-card`, `.wb-card-body`, `.wb-table-wrap`, explicit `Actions` header, `td.wb-table-actions`, `.wb-action-group`, and pagination in `.wb-card-footer`.
- Use `wb-modal` instead of browser `confirm()`.
- Use the shared `#wb-overlay-root`.
- Use `wb-toast` for transient feedback.
- Use `wb-alert` for validation, blocking, and user-correctable errors.

## CMS Boundaries

- Keep admin routes under `/webadmin`.
- Keep `/webadmin/api` for token-protected JSON APIs, not browser admin pages.
- Keep `/cms` static-assets-only.
- Do not introduce `/admin` CMS routes.
- Use English admin copy.
- Prefer shared admin partials and established listing/modal/card/action patterns.
- Avoid large inline Blade scripts.
- Named CMS admin JavaScript belongs under `public/cms/js/admin/`.
- Do not add Tailwind, Vite, React, Vue, Livewire, Inertia, or a Node build chain.

## Tests And Validation

- Add or update focused tests for meaningful behavior changes.
- Run `composer format:changed`.
- Run focused feature tests for the changed screen.
- Run `composer test:admin-smoke` for layout, sidebar, shared admin UI, or broad shell changes.
- Run `git diff --check`.

## Live Visual Testing Boundary

Use local or test-environment validation for AI-run checks. Do not include live public-site or live admin-panel visual testing in AI commands unless explicitly requested in the same prompt.

By default, Osman/operator performs live browser checks after a release has been applied to the live installation. AI reports should mark live visual checks as not performed and operator-owned.

## Final Report

Include:

- changed screens and files
- WebBlocks UI patterns used
- forbidden patterns avoided
- tests and validation
- screenshots or preview notes when available
- live visual checks: not performed; operator-owned
- warnings or limitations
