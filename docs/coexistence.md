---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/coexistence
cms_title: Coexistence
cms_layout: docs
cms_source_id: webblocks-cms:docs/coexistence.md
---

# Coexistence

## Purpose

This document describes how WebBlocks CMS should coexist with another Laravel host product in the same application. It records architecture direction only; it does not implement route, config, migration, model, controller, installer, register, invite, or authentication changes by itself.

## Standalone CMS Vs Host Product Coexistence

WebBlocks CMS can run as a standalone CMS where the CMS owns the main admin experience, public site rendering, and content operations for the application.

WebBlocks CMS can also be installed beside another Laravel host product. In that model, the host product keeps its own product responsibilities while CMS provides optional website and content management behavior.

## CMS As Optional Website/Content Layer

In coexistence installs, CMS is an optional website and content layer. It should not take over host product domain decisions, host product authorization, or host product admin routes.

CMS should remain package-first and avoid collisions with host application routes, config keys, view namespaces, table ownership, and other application-level boundaries.

Package install must be resumable in shared hosts. Before running fresh CMS migrations, `webblocks:install` detects partial CMS table state and reports existing CMS tables, row counts, matching migration rows, and known foreign key conflicts. Empty partial CMS tables may be renamed only through the explicit `--repair-partial` installer flag. Non-empty tables require manual review and must not be dropped, renamed, or treated as CMS-owned automatically.

## Admin Prefix Direction

The CMS admin prefix should be configurable.

For coexistence installs, the recommended CMS admin prefix is `/webadmin`. The host application may own `/admin`, and CMS must not assume that `/admin` is always available or CMS-owned. The `/cms` path segment is reserved for CMS static assets such as `/cms/css`, `/cms/js`, and `/cms/brand`.

Standalone installs now use `/webadmin` as the canonical CMS admin prefix. Documentation, design, and future implementation work should keep current behavior and longer-term configurable prefix direction clearly separated.

CMS admin prefixes must never reuse a physical public asset directory segment. In v1.32.56 the canonical admin prefix moved to `/webadmin` because Nginx `try_files` can resolve `/cms/` as the physical `public/cms/` directory before Laravel sees the route. `/cms` must remain asset-only: do not add CMS-owned `/cms` admin aliases or redirects, and do not restore CMS-owned `/admin` routes.

The final solution avoids the route/filesystem collision entirely instead of relying on a `public/cms/index.php` handoff or front-controller bridge. `public/cms/index.php` must stay absent from both root public assets and package public assets.

Public pages use Page Translation `path` as the canonical URL and may include slash-bearing paths such as `/docs/internal-content-api`. Public page catchalls must continue to leave `/webadmin`, `/webadmin/api`, `/cms`, `/search`, `/search.json`, `/contact-messages`, `/install`, and host auth routes to their owning route files. `/p/...` is legacy compatibility only and must not be generated as a new canonical URL.

## Admin Resource And Action URLs

CMS browser admin routes use `/webadmin`, while `/webadmin/api` is reserved for token-protected JSON APIs. Resource URLs should be predictable:

- collection: `/webadmin/{resource}`
- create: `/webadmin/{resource}/create`
- edit: `/webadmin/{resource}/{id}/edit`
- member action: `/webadmin/{resource}/{id}/{action}`
- collection action: `/webadmin/{resource}/{action}`

Page preview is a member action: `GET /webadmin/pages/{page}/preview`. Do not add CMS page preview routes under `/admin`, `/cms`, `/webadmin/api`, `/webadmin/pages/preview/{page}`, or `/webadmin/preview/pages/{page}`.

Authenticated admin previews must keep public routing separate. They may render draft or in-review page-owned content for authorized CMS users, but the public page route must continue to expose only published pages and published public content.

## Host-Owned Login

Within a shared Laravel host, login and registration are host application responsibilities. The shared `users` table is the identity and login layer.

CMS should not require duplicate user identity in co-installed apps. When package-owned CMS auth routes are active, CMS admin guest redirects and CMS auth screens must use the CMS-owned `/webadmin/login` route name `webblocks.auth.login` instead of the global `login` route name, because the host product may own `login` for paths such as `/quiztem/login`. Hosts that intentionally replace CMS auth may still own their own login flow, but package CMS views and middleware must stay on package-owned route names.

## CMS-Owned Authorization

Authentication only proves that a user is signed in. It does not grant CMS access.

CMS authorization must be decided by the CMS membership and role system. CMS super admin status does not make the user a host product admin, and host product admin status does not make the user a CMS super admin.

## Users Table And Duplicate Email Behavior

The `users` table is the identity layer in a shared Laravel host. CMS access is represented by CMS-owned membership or role records, not by creating a second user for the same person.

CMS installer, register, and invite designs must not create duplicate `users` rows for the same email address.

## Installer/Invite/Register Behavior

Installer, invite, and register flows that grant CMS access should use this sequence:

1. Look for an existing host user with the same email address.
2. Reuse that user when it exists.
3. Create a new user only when no matching host user exists.
4. Add the CMS membership or role record after the identity record is resolved.

Super admin status is a CMS membership or role assignment, not a special kind of `users` record.

## Route Examples

Common coexistence routing should be designed around clear ownership:

- `/login` -> host identity and login
- `/admin` -> host product admin, when the host product owns one
- `/webadmin` -> WebBlocks CMS admin, recommended for coexistence installs
- `/webadmin/login` -> package-owned CMS login, when package CMS auth routes are active
- `/webadmin/forgot-password` and `/webadmin/reset-password/{token}` -> package-owned CMS password reset screens, when package CMS auth routes are active
- `/cms/...` -> WebBlocks CMS static assets
- public site routes -> CMS public rendering, when CMS owns public content for the request

Standalone CMS installs use `/webadmin` for CMS-owned admin routes, with future configurable prefix settings still a target direction.

Package-owned CMS auth views must use CMS-prefixed auth routes for CMS-owned screens: `webblocks.auth.login`, `webblocks.auth.logout`, and the existing `webblocks.auth.password.*` names. If CMS registration is not provided by the package auth route set, CMS login should omit the Register link instead of pointing to a host-owned root `/register` route.

## Current Implementation Vs Target Direction

Current implementation uses `/webadmin` as the canonical CMS admin prefix and does not intentionally expose CMS admin behavior through `/admin` or `/cms`.

The target direction remains a configurable CMS admin prefix, with `/webadmin` as the default so a host product can keep `/admin` for its own administration area and CMS assets can remain under `/cms`.

Until implementation catches up, documentation and designs should explicitly state whether they describe current behavior or target architecture.

## Out Of Scope

- This document does not make CMS responsible for host product authorization.
- This document does not require host products to depend on CMS.
- This document does not implement route or migration changes by itself.
