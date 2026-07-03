# WebBlocks CMS Site Migration Playbook

Use this playbook when migrating an existing public site into a newly created WebBlocks CMS site, especially when the target site was created through Herne Panel and still needs wizard-equivalent setup steps completed.

## Principles

- Start with project and site discovery; do not guess install paths, API fields, block handles, or deployment conventions.
- Keep credentials, API tokens, `.env` contents, and machine-specific values out of notes, reports, commits, and skill files.
- Use WebBlocks CMS and WebBlocks UI native capabilities first.
- Treat Trusted HTML, custom public CSS, and site-specific code as last-resort escape hatches. If the migration cannot be completed without them, write a product gap report before changing product code.
- Keep temporary migration artifacts outside the CMS product repo, usually under `project-web_blocks/webblocks-sandbox/{site}-migration/`.

## Workflow

1. Resolve the CMS project from the workspace registry and read its `AGENTS.md`.
2. Create a migration plan document in the sandbox before touching the live target.
3. Inspect the CMS install/deploy docs and existing API content-building skill.
4. Connect to the target host only when the user has explicitly requested live setup.
5. Discover the target site path, runtime, database, document root, HTTPS status, and Herne Panel metadata without printing secrets.
6. Complete skipped panel setup steps using the host's existing conventions.
7. Install or verify WebBlocks CMS through supported CMS package commands.
8. Discover the target CMS API through `/webadmin/api` before creating content.
9. Inventory the source site from public pages only unless the user provides authenticated source access.
10. Map source pages, navigation, media, branding, forms, and metadata to discovered CMS block contracts.
11. Validate content plans before applying them.
12. Build drafts or staged updates first, then publish only when the migration scope requires the live site to go public.
13. Compare the migrated site to the source and record product gaps separately from content-authoring choices.
14. Update this playbook with project-specific lessons that are reusable and not secret.

## Source Inventory Checklist

- Pages and canonical URLs
- Main navigation and footer navigation
- Language/locale behavior
- Page titles and meta descriptions
- Hero sections
- Text sections
- Images, galleries, downloads, and alt text
- Contact forms and required fields
- Repeated/shared blocks such as header, footer, logo, and calls to action
- Colors, typography, spacing, and layout patterns that must be represented through WebBlocks UI/theme/site assets

## Target CMS Checklist

- Site record and primary domain
- Public theme preset and custom properties
- Branding and favicon
- Media Library assets
- Navigation menus
- Shared Slots for header/footer if supported
- Page layouts and slot availability
- Native block handles and field contracts
- Form/contact capabilities
- Publish/promote capabilities
- Cache clear or rebuild steps

## Gap Report Format

For each limitation, record:

- Source requirement
- Attempted WebBlocks CMS/UI mapping
- Blocking missing capability
- Product area: CMS, UI, content API, media, forms, theme, navigation, or deployment
- Suggested product enhancement
- Workaround used, if any

## Notes From Migrations

### farben.fklavye.net / farbe-bewegung-begegnung.de

- 2026-07-03: Migration started. Initial plan created before remote discovery.
- Herne site-app installs should normally be triggered through the panel/web runtime, not directly from SSH as `deploy`, because hosted site roots are writable by `www-data`.
- If Herne logs `Unable to start the one-shot site-apps queue worker automatically`, check for the cron/system worker before assuming the job is stuck.
- Before SSL, verify DNS resolution from public resolvers. If DNS does not resolve, use `curl --resolve` or a `Host` header only for temporary HTTP checks.
- Herne DNS sync can fail independently of local domain metadata when the stored provider token is invalid. Treat that as an operator credential issue, not a CMS issue.
- WebBlocks CMS package fresh-install migrations must use explicit short index names for long prefixed tables. The farben install exposed overlong names for gallery item translations and CMS API token activity logs.
- `webblocks:install --repair-partial` may not recover an incomplete fresh schema if some CMS tables exist but later required tables are missing. For a generated, empty, failed-install database, a targeted cleanup and fresh rerun can be safer than trying to seed into the partial schema.
- Do not log generated admin passwords. If a temporary runner logs a command line containing a password, remove that log immediately through the same runtime owner and revoke any tokens used during migration.
- Source sites may be reachable from the server even when local DNS/network cannot connect. For public-only inventory, run a temporary crawler on the server and copy only non-secret JSON/HTML summaries into the sandbox.
- The Internal Content API does not remote-fetch media. For image-heavy migrations, add an explicit media download/upload mapping step before building gallery blocks.
