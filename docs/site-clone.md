# Site Clone

`site:clone` duplicates site-owned content from one site into another site inside the same WebBlocks CMS install.

## Scope

The clone flow is content-focused.

It clones:

- the target `sites` record when needed
- site locale assignments
- pages
- page translations
- page slots
- blocks
- nested block trees
- block translation rows
- site-scoped navigation items
- asset references used by cloned blocks
- optional duplicated asset records and files when requested

It does not clone install-global or runtime data such as:

- users
- passwords
- sessions
- system settings unrelated to the target site
- backups
- update logs
- contact submissions
- analytics/log data

## Command

```bash
php artisan site:clone {source} {target}
```

Source and target can be resolved by site id, handle, name, or domain.

Common options:

- `--target-name=...`
- `--target-handle=...`
- `--target-domain=...`
- `--with-navigation`
- `--without-navigation`
- `--with-media`
- `--without-media`
- `--copy-media-files`
- `--with-translations`
- `--without-translations`
- `--overwrite-target`
- `--dry-run`

Examples:

```bash
php artisan site:clone webblocksui.com ui-docs \
  --target-name="UI Docs" \
  --target-handle="ui-docs" \
  --target-domain="ui.docs.webblocksui.com.ddev.site"
```

```bash
php artisan site:clone 1 2 --overwrite-target --copy-media-files
```

```bash
php artisan site:clone source-site preview-site --dry-run
```

## Media Behavior

Default behavior keeps shared install-global assets shared.

- block asset references are remapped into the cloned content
- existing asset records are linked, not duplicated
- physical files are not copied by default

When `--copy-media-files` is used:

- asset records are duplicated
- physical files are copied on the same storage disk
- cloned blocks reference the duplicated asset records

## Overwrite Rules

If the target site already contains pages or navigation items, clone refuses by default.

Use `--overwrite-target` only when you intentionally want to replace target site content.

Overwrite clears only target site pages and target site navigation items before the new content is cloned.

## Admin UI

The admin Sites area includes a compact Clone Site screen.

V1 is intentionally thin and uses the same service as the Artisan command.

## Safety Notes

- source content is never modified during clone
- page/block translation rows remain in their relational translation tables
- canonical/shared block fields remain canonical
- dry-run performs validation and reporting only
