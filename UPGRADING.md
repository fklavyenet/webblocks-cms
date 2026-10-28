# Upgrading and Installation Topologies

## 1.107.0: Site cookie and privacy management

Run the normal package migrations. The update adds relational site consent settings, service overrides, translations and cookie descriptions, plus the External Content block. It requires no new Composer dependency or external consent platform.

Open Cookies and Privacy in the admin sidebar and review each site's settings. Existing sites inherit the former effective Visitor Reports consent-banner setting once; later privacy settings are independent of reports. Published YouTube, Vimeo and Google Maps content uses managed consent placeholders. Disabled consent or disabled providers leave those embeds blocked. Uploaded local video is unchanged. A legacy analytics decision can authorize only CMS measurement, never the new external providers.

Existing API tokens do not automatically receive the new `consent.read`, `consent.write` or system-only `consent.code.write` capabilities. Assign only the required site scope and capabilities. Executable source management requires a Super admin in the panel or a system token with source authority.

Move independently added tracking code from custom head HTML, site JavaScript, tag managers or unregistered plugins into managed integrations when consent control is needed. The CMS does not automatically intercept arbitrary script execution. Overrides of the public layout, cookie-consent partial or Video renderer should be reconciled with the new native consent markup and locally shipped runtime. Use `#cookie-settings` to reopen preferences.

## 1.106.4: Account recovery and session revocation

Run the normal package migrations. The update adds relational per-account recovery state and nullable audit source/reason fields. It does not reset accounts, change role policies or end existing sessions merely because the package was upgraded.

An enrolled Super admin can reset another CMS account from Users after confirming their own password and a fresh email or recovery code. Verify the account owner's identity first and record a reason without secrets. A replacement profile email is optional and must be accessible to the owner; the owner must prove it during new verification setup. Self-reset is unavailable in the panel.

Server operators can recover an account by ID or current profile email with `php artisan webblocks:two-factor:reset USER_ID --reason="Owner identity confirmed" --dry-run`. Remove `--dry-run` for interactive confirmation; use `--force` for deliberate non-interactive execution. Add `--email=recovery@example.test` when the profile mailbox is inaccessible. The same command works for the only Super admin and requires server access rather than a working CMS login. If plugin startup prevents normal console boot, the existing `WEBBLOCKS_PLUGIN_SAFE_MODE=1` environment switch remains available.

A reset invalidates existing email and recovery codes, rotates remembered sign-ins and session authorization, and requires fresh setup regardless of role policy. Existing sessions are rejected on their next web request, including host web routes, across session storage backends. Previously rendered pages are not removed from the browser. The user's password, activity and roles remain unchanged. Bearer-token APIs keep their independent authentication. Panel and console recovery are recorded atomically in Users & access audit history; console records identify server-command authority without attributing a CMS actor. Keep reasons free of passwords, codes and other secrets.

Do not remove recovery state to bypass enrollment. Rollback must preserve revocation and audit evidence; older binaries do not enforce the new session-version boundary, so restore the matching backup and reconcile sessions before downgrading.

## 1.106.3: Email verification and panel account management

CMS sign-in now uses email codes through the existing CMS outgoing-mail settings. No authenticator app, new Composer dependency, queue worker or external QR service is required. Normal account management stays inside the CMS panel; incomplete sign-in, required enrollment and plugin recovery retain the restricted authentication screen.

Before upgrading an installation with enabled TOTP accounts, confirm outgoing-mail delivery and access to those accounts' current profile mailboxes, and retain their saved recovery codes. The normal update migrations bind enabled credentials to each account's current profile email, rotate session proofs, remove TOTP secrets and unfinished setup, and preserve recovery-code hashes and role enforcement. Email codes expire after five minutes and are single use. Delivery failures leave verification incomplete; recovery codes remain available. Log and array mail transports, including fallback destinations, are refused.

After setup, verification stays bound to the confirmed address independently of later profile changes. Changing it requires the current password and an existing email or recovery code, followed by confirmation of the new profile address. Replacement renews recovery codes and requires other sessions to verify again.

The schema no longer supports TOTP-only binaries. A rollback must restore the matching application and database backup. Password resets preserve verification, and no automatic administrator reset is introduced.

## 1.106.1: CMS two-factor authentication

CMS TOTP uses PHP's built-in cryptographic functions. There are no additional 2FA Composer packages or XMLWriter requirement, and no QR-code setup. In your authenticator app, add an account manually, choose time-based (TOTP), and enter the key shown by the CMS. The profile uses six digits, HMAC-SHA-1 and 30-second steps.

The normal package update and CMS migrations are sufficient. A panel update does not need a separate Composer dependency refresh for 2FA. Composer-managed installs can remove the previous optional libraries through their normal dependency update workflow; the CMS does not change the host dependency lock.

The migration adds dedicated CMS credential, pending-enrollment, recovery-code and role-policy tables. It does not modify host passwords or enable enforcement by default. Back up the database and application encryption key together; losing the key makes encrypted authenticator secrets unusable. Restoring older database backups may also restore consumed recovery codes and previous factor settings, so reconcile security state before reopening access.

Users enroll through Profile → Manage two-factor authentication. Super admins configure mandatory roles under System → Settings → Security. A matching assignment on any site requires enrollment before further panel access. Existing sessions must verify when 2FA is enabled. Confirm access to an authenticator or safely stored recovery codes before requiring enrollment. Password resets preserve the factor; they do not serve as an MFA bypass. There is no email-based or automatic administrator reset path.

## Admin navigation and ACL screens in 1.105.1

Version 1.105.1 reorganizes existing sidebar destinations and aligns the Roles and permissions listing with shared CMS controls. Dashboard, Sites, Pages, Media and Assets remain direct links. Users, Roles and permissions, and API Tokens are under Users & access. Grouped links show icons and the active destination's group opens automatically. Existing authorization checks, plugin groups, SQL role grants and API capabilities are unchanged; no new database migration is introduced. Installations overriding the admin layout or ACL index should review their view overrides.

## SQL roles and permissions in 1.105.0

Run the normal package update migrations. Existing Site Admin and Editor site assignments are imported into protected SQL compatibility roles, and existing Super Admins receive a separate system membership. Existing rights are preserved, including Editor page deletion and moving published pages back to draft. Re-running the ACL migration does not restore revoked membership or grants.

Active Super Admins can create narrower roles at `/webadmin/access/roles`, assign one role per user/site and inspect effective grants. Custom roles use explicit operation permissions; publishing, unpublishing, archiving and deletion are separate. Personal API tokens remain limited by both token capabilities/sites and current user grants. Enabled plugins must explicitly opt site permissions into custom-role delegation. CMS permissions do not bypass the host application's authorization rules. Publishing the package does not apply it to installed sites.

Back up the application files, database, environment configuration, storage, and uploads before changing CMS versions or installation topology. Validate the result in a non-production environment. Do not delete host-owned files or data as a blanket migration step.

## 1.89.0

- CMS homepage, locale, nested page, and legacy page routes now use real Laravel fallback semantics. Ordinary host routes win; a host controller's 404 is not replaced by CMS content. Audit addresses that previously reached CMS only because of route shadowing.
- Missing/empty `webblocks-cms.public.mount` preserves current integrated URLs and stored translation paths. Existing published config does not need forced replacement. Opt in through `WEBBLOCKS_CMS_PUBLIC_MOUNT=wb` or the config key when an isolated content mount is needed.
- Treat mount activation/change as a site URL migration: rebuild config/route caches, reload long-lived processes as appropriate, run `search:rebuild`, review manual links, and select redirects without claiming host-owned addresses. No URL/data migration runs automatically.
- In mounted mode the host owns root `robots.txt`; include the mounted sitemap there. Admin/API/assets/form endpoints stay in their existing dedicated areas. Legacy content redirects live only inside the active mount.
- Normal host catch-alls still win. Independent host/CMS fallbacks require deliberate integration; they do not chain automatically. A colliding CMS page fallback name receives `webblocks.public.` so the host name and cached route serialization remain valid.

See [Public Routing](https://cms.webblocksui.com/docs/public-routing) for the complete contract.

## 1.49.0

- **Site domain API endpoints now require capabilities.** The `/admin-api` domain routes previously checked only that a CMS API token was valid, so any token could add or remove a domain. Adding, updating, and promoting a domain now require the new `domains.write` capability, and deleting one requires the new destructive `domains.delete`. Reads require `content.read`, which normal tokens already have. Grant the new capabilities on the System > API Tokens screen to any provisioning tool that manages domains, before upgrading it.
- **Domain endpoints have a canonical home.** The same routes are now registered under `/webadmin/api` alongside the rest of the Internal Content API, and gain `PUT .../domains/{domain}` and `POST .../domains/{domain}/primary`. The `/admin-api` prefix keeps working. Prefer `/webadmin/api` for new integrations: its paths are covered by the CSRF exemption, which the legacy prefix never had.
- **Content plans reject unrecognized fields.** `POST /webadmin/api/content/validate` and `/content/apply` used to ignore any plan key they did not understand, so a plan carrying an unsupported field applied cleanly and wrote none of it. Unknown keys now fail with `422` and the code `unsupported_plan_fields`. Tools that sent extra fields alongside a valid plan must stop sending them.

## 1.41.0

WebBlocks CMS `1.41.0` replaces the two-phase System Update flow with a one-click flow. Upgrading through the in-app updater is supported from any `1.40.x` package-mode install: the release artifact keeps the package shape and file paths the `1.40.x` engine validates, so the old engine can apply `1.41.0` directly.

- **One-click updates with automatic backup and restore.** A single `Update to X` action now downloads, backs up, applies, migrates, and verifies the release in one run. The updater takes an automatic pre-update backup before applying and automatically restores it when the apply fails; a failed-then-restored run is recorded with the new `restored` run status. If the automatic restore also fails, the run is recorded as `failed` with both error trails in the run log.
- **Removed admin endpoints.** The `system/updates/continue`, `system/updates/cancel`, and `system/updates/support-report` admin endpoints are removed along with the two-phase pending state and the super-admin support-report download. Anything that linked to those routes must use the remaining `system/updates` index, check, apply, and indicator routes.
- **Pre-update backup download step removed.** The update screen no longer offers a backup download before applying. Automatic pre-update backups (and all other backups) remain available on the Backups screen for download, restore, and deletion, and via `php artisan system:backup:restore` on the CLI.
- **Server-backup advisory.** The update screen now shows an advisory recommending a fresh backup before a major update, linking to the Backups screen. Take a server-level backup (files plus database) before large upgrades; the automatic pre-update backup covers the database and CMS-managed uploads, not host-owned files outside the CMS.
- **Source-maintained apply mode retired.** `WEBBLOCKS_UPDATES_MIGRATION_STRATEGY` is ignored, and the `installer.migration_strategy` and `installer.pending_cache_ttl_seconds` config keys are removed. In-app updates always target the canonical Composer package root `vendor/fklavyenet/webblocks-cms` and always run package update migrations from `database/migrations/updates` when present. Source-maintained maintenance checkouts update through git/Composer, not the in-app updater.
- **Trimmed update-screen view contract.** Forks that override `admin/system/updates.blade.php` must adapt: the view now receives `report`, `runs`, `preflight`, and `checkedAt` only. The pending-update, blocker-state, and support-report view data is gone.
- **New language keys for translated forks.** The `updates.*` admin language group is rewritten to the v3 vocabulary and adds the `server_backup_advisory` and `server_backup_advisory_link` keys plus the `statuses.restored` label. Forks that maintain their own admin translations should re-sync the `updates.*` group against the shipped `en`/`de`/`tr` files.

## Package-only repository transition

WebBlocks CMS `1.37.0` is the first release tagged directly from the package-only repository root. Installations on the `1.36.1` compatibility release may upgrade through their documented Composer or Publisher/System Updates path. The repository transition changes source distribution topology; it does not replace host-owned application state or introduce a CMS schema or WebBlocks UI version change.

## Composer/package-native installations

Keep the same Composer package identity, `fklavyenet/webblocks-cms`, and use Composer in the host application to resolve an intended released version. Follow release-specific notes and use the CMS System Updates flow only according to the installed package's documented update contract.

## Existing full-repository clones

The historical WebBlocks CMS repository was a complete Laravel application. The package-only repository no longer contains that host application. Do not assume `git pull` across this transition is safe: it can remove or conflict with the host shell.

Plan a staged conversion instead. Inventory and back up the existing `.env`, database, storage/uploads, project content, plugins, public overrides, and installed-version state. Prepare a normal Laravel 13 host separately, require the same Composer package identity, verify configuration and database connectivity, and test package installation/update behavior before redirecting traffic or retiring the old clone. Preserve the old installation until the replacement is verified.

## Source-maintained installations

Some maintenance layouts keep package source at `packages/webblocks-cms`. Compatibility code still recognizes this topology. Do not delete or relocate that source merely because the public repository becomes package-only; the maintenance harness must first adopt an explicit authoritative checkout/synchronization model.

As of `1.41.0`, the in-app updater no longer has a source-maintained apply mode: System Updates always target the canonical Composer package root `vendor/fklavyenet/webblocks-cms`. Source-maintained maintenance checkouts are updated through git/Composer, not through the in-app updater.

## Publisher/System Updates consumers

Publisher artifacts and in-app System Updates are not GitHub source checkouts. Continue using the installed client's supported, checksum/signature-verified update flow. Do not replace it with a Git pull, and do not retire legacy bridge behavior without separately released compatibility evidence.

## New Laravel hosts

For a new Laravel 13 application, follow the Composer-first flow in [README.md](README.md). The package install command may patch the normal User model and remove only Laravel's untouched welcome route; review backups created by the command and keep host customization under host ownership.

## Documentation repository separation

User documentation is maintained in the independent `webblocks-cms-docs`
repository and is excluded from CMS distributions. The runtime inventory endpoint
now reads the packaged `resources/contracts/inventory.md` contract.

Older Publisher packages used `docs/LICENSE`. Version 1.89.1 was the bridge
release: its ZIP retained that legacy layout while installing an updater that
accepts both layouts. Current Publisher, Composer, and Git distributions use
root `LICENSE` and contain no documentation tree. The updater still accepts
legacy `docs/LICENSE` packages and prefers root `LICENSE` when both are present.

Installations using a pre-1.89.1 updater must first install the 1.89.1 bridge
release, or upgrade through Composer, before applying current Publisher packages.

## 1.104.3 — shared update progress

Version 1.104.3 includes released Publisher Client 1.2.0 sources and an additive nullable `state` column on SQL update steps. Run the normal package update migrations; the migration is repeatable and preserves receipts on rollback. Earlier schemas remain readable. CMS keeps its existing approved asynchronous operation admission and SQL backup/recovery tracking. The modal uses the shared external asset and lists reached steps with durations; a matching terminal operation and installed-version verification are still required. Refresh an active update to resume its operation, or recheck status without resubmitting the update. Detailed procedures stay in the Updates handbook. Publishing this release does not update live installations.
