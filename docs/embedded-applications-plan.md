# Embedded Applications

Embedded Applications lets a system administrator register a trusted browser application once and lets editors place it with the `application` block. Definitions are CMS data. There is no `application.json` upload, configured application directory, filesystem scan, or site-specific path in CMS core.

## Ownership boundary

- CMS core owns the reusable definition model, validation, management UI, API, block contract, and public renderer.
- The host site owns its HTML, CSS, JavaScript, images, translations, and deployment paths.
- An Application Block stores only a stable `application_handle`, validated instance settings, and CMS presentation options.
- Registering executable assets requires system access. Ordinary editors can select enabled applications but cannot add or change scripts.
- URLs must be same-origin absolute paths beginning with one `/`. Protocol-relative and remote URLs are refused.

This boundary prevents a site convention such as `/play-assets` from entering the distributable package. A game may remain at `/play-assets/games/example/index.html`, but that path exists only in the host installation's database record.

## Database definition

`wbcms_embedded_applications` stores:

- identity: handle, name, description, version;
- render contract: inline or iframe, iframe entry URL, inline mount element/classes;
- ordered CSS and JavaScript URL declarations;
- supported context flags such as locale, theme, and fullscreen;
- the typed Application Block settings schema;
- enabled state and creator/updater audit identifiers.

Disabling preserves existing blocks and makes the definition unavailable for new valid placements. Deletion is refused while a block uses the handle.

## Admin workflow

System → Embedded Applications provides create, list, edit, enable/disable, and guarded delete operations. The form exposes fields and selections for every stored definition property. It never asks for a manifest file or scans public directories.

After saving a definition, **Application files** opens its site-scoped file manager. A system administrator selects the host site and uploads `.css`, `.js`, or the single managed HTML entry named `index.html`. CSS and JavaScript live under `/site/{site_handle}/applications/{application_handle}/{type}`; the HTML entry lives at the application root and is served through `/webblocks-applications/{application_handle}/index.html`, where the request host selects the correct site copy. Uploading `index.html` switches the definition to iframe mode and assigns that stable entry URL. Updates use checksums and replacements/deletions retain revision snapshots; a referenced file cannot be deleted until its URL is removed.

Managed iframe entries run as sandboxed, opaque-origin documents. They may execute
scripts, but they do not receive same-origin access to CMS cookies, storage, the
parent document, or authenticated panel requests. The entry response also applies
a restrictive Content Security Policy: assets and network connections are
same-origin by default, objects and forms are disabled, and referrer data is not
sent. Applications that require cross-origin services must use a separately
reviewed host integration rather than weakening the shared CMS origin.

Application Block settings are managed as a compact table rather than a fixed collection of empty field cards. **Add Setting** opens a modal containing the typed schema fields; saving adds the draft setting to the table, while cancel closes the modal without changing the application. Existing rows use the same modal for editing and expose icon actions for editing and removal. The table is part of the parent application form, so these client-side changes are persisted only when the operator saves the application. The submitted `settings[*]` contract and API representation remain unchanged.

Application files may still be deployed by the host's normal release or asset pipeline. The admin file manager and dedicated API provide a narrowly scoped alternative for registered applications; CSS/JS accept safe basenames, while HTML is deliberately restricted to `index.html`. This is not a general executable-file browser.

### Backup and transfer

System backups include the database-backed application contracts and the complete `public/site` tree, then restore both with rollback protection. Site Export/Import includes definitions referenced by exported Application Blocks plus their site-scoped `applications/` files even when ordinary media inclusion is disabled. Existing installation-wide definitions are reused only when their contracts match; a conflicting handle stops the import instead of silently changing applications used by other sites.

## Application Block

The editor selects an enabled definition by handle. The form derives optional instance controls from `settings_schema`. The block also owns width, loading strategy, aspect ratio, minimum height, loading state, and failure state. Public rendering resolves the current database definition and deduplicates its assets.

Executable CSS and JavaScript can be created through the Application Assets
API. They remain physical, host-owned files under
`public/site/{site_handle}/applications/{application_handle}/`; only their safe
same-origin public paths are stored in the database definition. The API never
scans directories and never treats asset files as application discovery data.

## Internal API

- `GET /webadmin/api/applications` and detail/schema reads require `applications.read`.
- `POST /webadmin/api/applications` and `PATCH /webadmin/api/applications/{handle}` require `applications.write`.
- `DELETE /webadmin/api/applications/{handle}` requires the destructive `applications.delete` capability and is refused while in use.
- Content validate/apply continues to require that an Application Block reference an enabled definition and that instance settings match its stored schema.

Executable source is never returned by these endpoints because the database stores URLs and contracts, not file contents.

## Core-boundary guardrails

The release suite must enforce these rules:

1. Core configuration contains no host-specific application roots or product asset paths.
2. The registry queries `EmbeddedApplication`; it does not walk the filesystem.
3. Application URLs remain host data and are never seeded with fklavye.net-specific values.
4. API mutation has dedicated capabilities; content tokens do not implicitly gain executable-asset management.
5. New application examples in generic documentation use `/applications/example/...`, never a real host path.

## Migration from 1.62.0 manifests

The 1.62.0 filesystem pilot is intentionally replaced rather than retained as a second source of truth. Upgrade an affected host in maintenance mode, run the CMS migration, and create matching database definitions through the management screen or `applications.write` API before reopening public traffic. Keep each existing handle unchanged so Application Blocks continue to resolve. After verification, delete the obsolete `application.json`; CMS no longer reads it.
