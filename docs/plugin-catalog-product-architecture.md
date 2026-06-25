---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/plugin-catalog-product-architecture
cms_title: Plugin Catalog Product Architecture
cms_layout: docs
cms_source_id: webblocks-cms:docs/plugin-catalog-product-architecture.md
---

# Plugin Catalog Product Architecture

This document records product architecture and MVP planning for the proposed `plugins.webblocksui.com` surface before implementation starts. It is documentation-only. It does not add runtime code, routes, migrations, controllers, API clients, database tables, UI screens, deployment scripts, background jobs, release tags, or version bumps.

## Purpose

`plugins.webblocksui.com` is the proposed public Plugin Catalog surface for the WebBlocks ecosystem. It should help users discover plugins, understand plugin metadata, see host-product compatibility, review release history, and follow safe manual installation guidance.

The first goal is discovery, metadata, compatibility visibility, and safe manual ZIP installation guidance. It should not start as a commercial marketplace, and it must not imply automatic remote plugin installation in WebBlocks CMS, QuizTem, Herne Panel, WebBlocks Publisher, or any other host product.

## Product Positioning

The product language should separate three stages:

- Plugin Catalog: browse, search, and discover plugins; read metadata, compatibility, release notes, screenshots, documentation, and download links.
- Plugin Store: later trusted install/update integration from catalog metadata into host products.
- Marketplace: future commercial layer with vendors, accounts, licensing, payments, approvals, ratings, and paid plugin distribution.

The near-term product should be called Plugin Catalog, not Marketplace. Marketplace language should stay reserved for deferred commercial features.

## Candidate Implementation Options

### Dedicated Laravel Application

Pros:

- clean product boundary
- independent roadmap
- easiest long-term API and catalog ownership

Cons:

- more setup and operational work
- separate content management, admin, deployment, and maintenance concerns

### WebBlocks Publisher Capability

Pros:

- reuses update publishing concepts
- can reuse artifact, checksum, release metadata, and manifest patterns

Cons:

- risks making WebBlocks Publisher too broad
- plugin catalog concerns differ from product update publishing
- catalog discovery, compatibility matrices, and public plugin pages may pull Publisher away from its core product role

### WebBlocks CMS-Powered Site Plus Catalog Plugin

Pros:

- demonstrates WebBlocks CMS capabilities
- content pages are easy to manage
- the Plugin Catalog can dogfood the plugin system

Cons:

- requires a catalog plugin
- needs careful separation between public content management and artifact/catalog authority
- must avoid turning CMS content editing into the authority for executable artifact safety

### Hybrid Model

The hybrid model would serve public marketing and content pages from WebBlocks CMS while serving catalog API and artifact metadata from a dedicated catalog service or Publisher-derived backend.

This may be the most practical long-term direction, but it remains a direction, not an implementation commitment.

## Recommended Direction

Document `plugins.webblocksui.com` as an ecosystem product surface with a clean boundary. Start with a catalog-first MVP focused on discovery, metadata, compatibility visibility, release notes, checksums, documentation, and manual download guidance.

Implementation may begin as either a dedicated Laravel application or a WebBlocks CMS-powered catalog plugin, but the data contract should not depend on one UI implementation. WebBlocks Publisher concepts should remain reusable, especially release metadata and artifact verification ideas, without assuming the Plugin Catalog must live inside Publisher.

## Core Domain Model

The following concepts are planning-level only. They do not imply database tables, APIs, models, migrations, or admin screens in the current repository.

### Plugin

- handle
- label
- summary
- description
- vendor/author
- website URL
- documentation URL
- support URL
- license type
- categories
- tags
- status: draft, listed, unlisted, deprecated, suspended
- first published date
- latest release date

### Vendor / Author

- name
- slug
- website
- support contact
- verified status
- public profile page
- future commercial eligibility, deferred

### Host Product

- product key, for example `webblocks-cms`, `quiztem`, `herne-panel`, or `webblocks-publisher`
- product label
- supported version ranges
- catalog visibility rules

### Plugin Release

- plugin handle
- version
- channel: stable, beta, alpha, dev
- release date
- release notes
- compatibility matrix
- required PHP/Laravel version when applicable
- artifact URL
- checksum
- future signature metadata
- migration notes
- breaking change notes
- security notes
- deprecation notes

### Artifact

- storage path or URL
- checksum
- size
- MIME/type
- package format
- manifest metadata
- validation status
- scan status
- published state

### Compatibility Matrix

- supported host products
- required host product versions
- incompatible host product versions
- PHP/Laravel constraints where relevant
- required extensions
- conflicting plugin handles
- required plugin dependencies, if future support is approved

### Security Advisory

- plugin handle
- affected versions
- severity
- status
- fixed version
- public summary
- recommended operator action

## Public Website Surface

Future public pages for the proposed `plugins.webblocksui.com` surface may include:

- Homepage
- Plugin listing
- Category pages
- Search results
- Plugin detail page
- Release history page
- Vendor profile page
- Host product compatibility pages
- Documentation / install guide pages
- Security advisories page
- Deprecation / removed plugin guidance
- Future marketplace pages, explicitly deferred

Plugin detail pages should include:

- plugin name
- summary
- screenshots
- supported host products
- latest compatible version
- required host product versions
- release notes
- permissions requested
- migrations declared
- routes, settings, commands, and assets declared
- install method
- download and checksum information
- security/deprecation status
- support and documentation links

## Operator/Admin Surface

Future catalog operator screens may include:

- Plugins
- Vendors
- Releases
- Artifacts
- Compatibility
- Security Advisories
- Categories/Tags
- Review Queue, deferred
- Commercial/Licensing, deferred

These are catalog/admin planning concepts, not CMS admin implementation tasks.

## API Direction

Future read-only API endpoints may include:

- `GET /api/plugins`
- `GET /api/plugins/{handle}`
- `GET /api/plugins/{handle}/releases`
- `GET /api/plugins/{handle}/latest?host_product=webblocks-cms&version=...`
- `GET /api/host-products`
- `GET /api/security-advisories`
- `GET /api/catalog/index`

The V1 API should be read-only for host products. API responses should never cause remote install, plugin enablement, migration execution, update apply, arbitrary Composer install, or executable behavior by themselves.

Future publisher/operator endpoints are separate and deferred:

- publish plugin release
- upload artifact
- approve listing
- suspend listing
- issue advisory

## CMS / Host Product Integration Direction

WebBlocks CMS and other host products may consume the Plugin Catalog to:

- browse the catalog from host admin
- show plugin compatibility before download/install
- link to manual ZIP download
- compare installed plugin versions with catalog releases
- show security/deprecation warnings for installed plugins
- support controlled install/update flows only when host products re-check trusted artifact metadata, verify checksums, validate packages, and require explicit operator actions

Catalog browsing must not require installed plugin management to be online. Installed plugin management must work without catalog availability. Host products should cache catalog metadata defensively, and remote metadata must not be trusted as executable behavior.

## Publishing Flow Direction

Future maintainer/operator flow may include:

- prepare plugin artifact locally
- validate plugin manifest
- validate package shape
- compute checksum
- upload artifact and metadata
- catalog verifies compatibility and artifact safety
- release becomes listed only after explicit operator approval or trusted first-party publish flow

This resembles WebBlocks Publisher/update metadata ideas, but plugin catalog publishing should remain a separate capability unless a later decision merges them.

## MVP Scope

A practical first MVP should include:

- static or catalog-managed plugin entries
- public plugin listing and detail pages
- plugin release metadata
- compatibility matrix
- manual download links
- checksums displayed
- documentation links
- no CMS-side automatic remote install
- no paid marketplace
- no licensing
- no third-party self-service publishing
- no automatic update apply

## Non-Goals

This planning phase does not include:

- implementation in this task
- live production deployment
- automatic remote plugin install
- automatic plugin enablement
- automatic plugin migration execution
- automatic plugin update apply
- arbitrary Composer install
- paid marketplace
- license server
- vendor self-service portal
- ratings/reviews
- production artifact publishing automation
- live site verification

## Open Questions

- Should `plugins.webblocksui.com` start as a dedicated Laravel app or CMS-powered catalog plugin?
- Should WebBlocks Publisher own plugin artifact publishing, or should Plugin Catalog have its own publisher flow?
- Should first-party and third-party plugins have different approval flows?
- How should plugin signatures be handled?
- How should commercial licensing be introduced later without changing the catalog contract?
- How should private/internal plugins be represented?
- How should multi-host plugins declare per-host providers and compatibility?
