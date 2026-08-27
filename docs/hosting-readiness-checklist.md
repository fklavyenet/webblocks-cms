---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/hosting-readiness-checklist
cms_title: Hosting Readiness Checklist
cms_layout: docs
cms_source_id: webblocks-cms:docs/hosting-readiness-checklist.md
---

# Hosting Readiness Checklist

Use this checklist before purchasing hosting, before the first production deployment, and after a material server or PHP configuration change. Record the evidence and owner for every item; a verbal “Laravel is supported” is not sufficient.

The normative platform and feature requirements are in [Hosting Requirements](hosting-requirements.md). Resource values must come from recorded [Hosting Capacity Results](hosting-capacity-results.md) produced with [Hosting Capacity Validation](hosting-capacity-validation.md), not an unmeasured estimate. A result marked provisional is comparison evidence only; qualify the actual production profile before treating its values as certified requirements.

## Provider questionnaire

- [ ] PHP 8.4 is available for both web requests and CLI, and the provider states how patch releases are maintained.
- [ ] Composer 2 may run in the application directory without an artificial dependency-install timeout.
- [ ] `mbstring`, `sodium`, `zip`, and `pdo_mysql` are enabled in both web and CLI PHP.
- [ ] MySQL 8.0 with InnoDB is available, including permission to create and alter tables, indexes, and foreign keys.
- [ ] The domain document root can point directly to the Laravel application's `public/` directory.
- [ ] HTTPS certificates and automatic renewal are available.
- [ ] The provider states PHP memory, upload, POST body, request timeout, process, inode, and disk quotas.
- [ ] The provider explains whether `proc_open`, PHP CLI, Composer, and database client binaries are available to the PHP runtime.
- [ ] The provider explains whether symlinks are permitted for `public/storage`.
- [ ] Cron is available if scheduled housekeeping will be enabled.
- [ ] Outbound HTTPS is allowed for Composer, CMS updates, or remote media import as applicable.
- [ ] Backup retention, restore procedure, monitoring, and support escalation are documented.
- [ ] The selected core, media, operations, and traffic profiles are identified as applicable or not applicable.

Any “no” answer must be matched to a deliberately disabled feature or an external deployment/operations procedure before approval.

## Pre-deployment server checks

- [ ] The production release is installed into a Laravel 13 host application, not served directly from the package source repository.
- [ ] Web and CLI report compatible PHP versions and extension sets.
- [ ] `composer check-platform-reqs` passes in the deployed host application.
- [ ] The database connection succeeds and uses the intended production database.
- [ ] `APP_KEY` is set, `APP_DEBUG=false`, and `APP_URL` is the canonical HTTPS URL.
- [ ] The web root is `public/`; `/.env` and `/.git/config` return `404`.
- [ ] Laravel rewrite/front-controller behavior works for a non-file route.
- [ ] `/webadmin` routes through Laravel while `/cms` serves package-owned static assets.
- [ ] `storage/`, `bootstrap/cache`, the backups disk, and required `public/site` paths are writable by the PHP runtime without `777`.
- [ ] `public/storage` works when the standard public disk is used.
- [ ] Database and file backups exist outside the application, and a restore test has an owner and date.
- [ ] The selected capacity profile's memory, timeout, upload, disk, and feature assumptions match this server.

## Feature checks

Mark unused features as not applicable and record why.

- [ ] **Media:** an upload can be stored and served; when image variants are required, GD and the source-format codec are available.
- [ ] **Mail:** password reset and contact notification delivery reach the intended test mailbox through a real transport.
- [ ] **Scheduler:** the host invokes Laravel `schedule:run` when scheduled cleanup is expected.
- [ ] **System Update:** its admin preflight passes database, extension, process, write-access, lock, and free-space checks.
- [ ] **MySQL backup/restore:** dump and client binaries are available when application-level native database backups will be used.
- [ ] **Remote media:** outbound requests work only if remote import is required.

## Post-deployment smoke test

- [ ] The public home page returns the expected secure response.
- [ ] `/webadmin/login` loads over HTTPS and an authorized administrator can sign in.
- [ ] CMS CSS, JavaScript, and brand assets under `/cms` return successful responses.
- [ ] A draft can be created and previewed without becoming publicly visible.
- [ ] A media item can be uploaded, displayed, and deleted according to policy.
- [ ] Logs contain no permission, database, mixed-content, or missing-extension errors from the smoke test.
- [ ] Backups, deployment/update ownership, monitoring, and emergency contacts are recorded in the handoff.

## Evidence to retain

Keep the following with the deployment record without including secrets:

- hosting plan or server profile and region;
- PHP and database versions;
- enabled extension names;
- `composer check-platform-reqs` result;
- configured resource limits and available disk at acceptance time;
- capacity fixture/profile version, raw result location, qualification date, and five-run worst case;
- document-root and filesystem ownership model;
- enabled optional features and their dependencies;
- smoke-test date and operator; and
- backup/restore test date, retention, and responsible party.

Repeat the relevant sections after changing the PHP version, database engine, document root, filesystem ownership, deployment method, or hosting provider.
