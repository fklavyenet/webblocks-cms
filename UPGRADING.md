# Upgrading and Installation Topologies

Back up the application files, database, environment configuration, storage, and uploads before changing CMS versions or installation topology. Validate the result in a non-production environment. Do not delete host-owned files or data as a blanket migration step.

## Package-only repository transition

WebBlocks CMS `1.37.0` is the first release tagged directly from the package-only repository root. Installations on the `1.36.1` compatibility release may upgrade through their documented Composer or Publisher/System Updates path. The repository transition changes source distribution topology; it does not replace host-owned application state or introduce a CMS schema or WebBlocks UI version change.

## Composer/package-native installations

Keep the same Composer package identity, `fklavyenet/webblocks-cms`, and use Composer in the host application to resolve an intended released version. Follow release-specific notes and use the CMS System Updates flow only according to the installed package's documented update contract.

## Existing full-repository clones

The historical WebBlocks CMS repository was a complete Laravel application. The package-only repository no longer contains that host application. Do not assume `git pull` across this transition is safe: it can remove or conflict with the host shell.

Plan a staged conversion instead. Inventory and back up the existing `.env`, database, storage/uploads, project content, plugins, public overrides, and installed-version state. Prepare a normal Laravel 13 host separately, require the same Composer package identity, verify configuration and database connectivity, and test package installation/update behavior before redirecting traffic or retiring the old clone. Preserve the old installation until the replacement is verified.

## Source-maintained installations

Some maintenance layouts keep package source at `packages/webblocks-cms`. Compatibility code still recognizes this topology. Do not delete or relocate that source merely because the public repository becomes package-only; the maintenance harness must first adopt an explicit authoritative checkout/synchronization model.

## Publisher/System Updates consumers

Publisher artifacts and in-app System Updates are not GitHub source checkouts. Continue using the installed client's supported, checksum/signature-verified update flow. Do not replace it with a Git pull, and do not retire legacy bridge behavior without separately released compatibility evidence.

## New Laravel hosts

For a new Laravel 13 application, follow the Composer-first flow in [README.md](README.md). The package install command may patch the normal User model and remove only Laravel's untouched welcome route; review backups created by the command and keep host customization under host ownership.
