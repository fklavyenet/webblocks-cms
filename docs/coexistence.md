# Coexistence

## Purpose

This document describes how WebBlocks CMS should coexist with another Laravel host product in the same application. It records architecture direction only; it does not implement route, config, migration, model, controller, installer, register, invite, or authentication changes by itself.

## Standalone CMS Vs Host Product Coexistence

WebBlocks CMS can run as a standalone CMS where the CMS owns the main admin experience, public site rendering, and content operations for the application.

WebBlocks CMS can also be installed beside another Laravel host product. In that model, the host product keeps its own product responsibilities while CMS provides optional website and content management behavior.

## CMS As Optional Website/Content Layer

In coexistence installs, CMS is an optional website and content layer. It should not take over host product domain decisions, host product authorization, or host product admin routes.

CMS should remain package-first and avoid collisions with host application routes, config keys, view namespaces, table ownership, and other application-level boundaries.

## Admin Prefix Direction

The CMS admin prefix should be configurable.

For coexistence installs, the recommended CMS admin prefix is `/webadmin`. The host application may own `/admin`, and CMS must not assume that `/admin` is always available or CMS-owned. The `/cms` path segment is reserved for CMS static assets such as `/cms/css`, `/cms/js`, and `/cms/brand`.

Standalone installs now use `/webadmin` as the canonical CMS admin prefix. Documentation, design, and future implementation work should keep current behavior and longer-term configurable prefix direction clearly separated.

## Host-Owned Login

Within a shared Laravel host, login and registration are host application responsibilities. The shared `users` table is the identity and login layer.

CMS should not require a separate mandatory CMS login system in co-installed apps. When a guest user requests the CMS admin area in a host-owned auth app, the request should use the host login flow, usually `/login`, while preserving the intended CMS URL for the redirect after login. In package-owned standalone auth, the CMS login route is `/webadmin/login`.

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
- `/cms/...` -> WebBlocks CMS static assets
- public site routes -> CMS public rendering, when CMS owns public content for the request

Standalone CMS installs use `/webadmin` for CMS-owned admin routes, with future configurable prefix settings still a target direction.

## Current Implementation Vs Target Direction

Current implementation uses `/webadmin` as the canonical CMS admin prefix and does not intentionally expose CMS admin behavior through `/admin` or `/cms`.

The target direction remains a configurable CMS admin prefix, with `/webadmin` as the default so a host product can keep `/admin` for its own administration area and CMS assets can remain under `/cms`.

Until implementation catches up, documentation and designs should explicitly state whether they describe current behavior or target architecture.

## Out Of Scope

- This document does not make CMS responsible for host product authorization.
- This document does not require host products to depend on CMS.
- This document does not implement route or migration changes by itself.
