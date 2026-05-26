# Architecture Decisions

This file records binding architecture decisions for WebBlocks CMS. Longer implementation guidance can live under `docs/`, but these decisions should stay short and product-level.

## CMS Role In Host Applications

- WebBlocks CMS can run as a standalone CMS.
- WebBlocks CMS can also be installed beside another Laravel host product as an optional website and content management layer.
- CMS does not inherit host product domain authority.
- Host products do not inherit CMS authority.
- CMS must stay package-first and avoid route, config, view, and table collisions with the host application.

## CMS Admin Prefix

- The CMS admin prefix must be configurable.
- Coexistence installs should use `/webadmin` as the recommended CMS admin prefix.
- The `/cms` path segment is reserved for CMS static assets.
- CMS admin prefixes must not reuse physical public asset directory segments.
- CMS must not add `/cms` admin aliases or redirects.
- `/admin` may belong to the host application.
- CMS must not assume that `/admin` is always CMS-owned.
- CMS must not restore CMS-owned `/admin` routes.
- Current implementation and target direction must be documented separately until the route prefix is fully configurable.

## Identity And Login

- In a shared Laravel host, the `users` table is the identity and login layer.
- Login and registration are host application responsibilities.
- CMS must not require a separate `/webadmin/login` style login system in host-owned auth applications.
- Guest users who request the CMS admin area should be redirected to the host `/login` page.
- Login must preserve the intended URL so users can return to the originally requested CMS admin page.
- Being authenticated does not imply CMS authorization.

## CMS Authorization

- CMS access is controlled by the product-owned CMS membership and role system.
- CMS super admin status is not the same thing as host product admin status.
- Host product admin status is not the same thing as CMS super admin status.
- CMS super admin status is a CMS membership or role record, not a special `users` table record.
- Installer, register, or invite flows must not create duplicate users for the same email.
- Installer or invite flows should first find an existing host user by email, create one only when needed, then attach the CMS membership or role record.
