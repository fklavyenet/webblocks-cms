---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/feature-inventory
cms_title: Feature Inventory
cms_layout: docs
cms_source_id: webblocks-cms:docs/feature-inventory.md
---

# Feature Inventory

This document is the source inventory for making WebBlocks CMS product features discoverable. It is a product and operational visibility inventory, not a runtime contract. Detailed technical contracts remain in the linked docs.

The inventory helps new users and AI/operator workflows understand which CMS capabilities exist, where they appear in admin/public/API surfaces, and which user-facing documentation pages are still missing. It is also suitable as a Markdown source page for a future source-linked CMS documentation page.

## Inventory Columns

- Feature: the product capability or documented direction.
- Area: the product area that owns the feature.
- Status: implemented, documented direction, planned only, or needs clearer docs.
- Admin path: the browser admin surface when one exists.
- Public behavior: what public visitors or rendered sites experience.
- API support: whether Internal Content API or discovery surfaces expose it.
- Source / implementation notes: short source or implementation context.
- Existing docs: current repo-relative documentation links.
- Documentation gap / next docs page: the next documentation need.

## Core Content Model And Page Builder

| Feature | Area | Status | Admin path | Public behavior | API support | Source / implementation notes | Existing docs | Documentation gap / next docs page |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Sites | Core content | Implemented | `/webadmin/sites` | Site-scoped public routing, branding, locales, variables, and content | Discoverable through Internal Content API | Root content scope for pages, domains, locales, media, and navigation | [docs/multisite.md](multisite.md), [docs/core-concepts.md](core-concepts.md) | covered |
| Locales / Localization | Core content | Implemented | `/webadmin/locales`, site edit locales | Locale-aware pages and public URLs | Discoverable through Internal Content API | Localized page translations and locale routing | [docs/localization.md](localization.md), [docs/multisite.md](multisite.md) | covered |
| Pages | Page Builder | Implemented | `/webadmin/pages` | Published pages render through CMS public routing | Pages read and content plan support | Relational pages, translations, slots, and blocks | [docs/core-concepts.md](core-concepts.md), [docs/internal-content-api.md](internal-content-api.md) | needs user guide |
| Page Layouts | Page Builder | Implemented | `/webadmin/page-layouts`, Edit Page settings | Public shell and slot wrapper behavior | Discoverable for content planning | Managed layouts with layout slots and body classes | [docs/page-layouts.md](page-layouts.md) | covered |
| Slots | Page Builder | Implemented | Edit Page slot editor | Slot regions render page-owned or shared content | Content plans target page-owned slots | Slots separate page regions from block content | [docs/core-concepts.md](core-concepts.md), [docs/page-layouts.md](page-layouts.md) | needs Page Builder overview |
| Shared Slots | Page Builder | Implemented | `/webadmin/shared-slots`, Edit Page slot source | Reusable site-scoped slot block trees render in pages | Shared Slot API foundations and assignment support | Shared content references, not copied templates | [docs/core-concepts.md](core-concepts.md), [docs/internal-content-api.md](internal-content-api.md) | needs user guide |
| Blocks | Page Builder | Implemented | Edit Page slot block editor | Blocks render public page content | Block/content discovery and content plans | Relational block trees with translations and settings | [docs/core-concepts.md](core-concepts.md), [docs/public-block-render-markup.md](public-block-render-markup.md) | needs Page Builder overview |
| Block Type Contracts | Page Builder | Implemented | `/webadmin/block-types` contract modal | Guides safe renderer and editor behavior | Content contract and discovery surfaces expose block contracts | Contract registry defines fields, settings, and capabilities | [docs/block-type-contracts.md](block-type-contracts.md), [docs/api-discovery.md](api-discovery.md) | covered |
| Page Builder | Page Builder | Implemented | Edit Page | Authors public pages from layouts, slots, and blocks | Draft-safe content validate/apply | Core editing model for page-owned content | [docs/core-concepts.md](core-concepts.md), [docs/ai-page-building-guide.md](ai-page-building-guide.md) | needs Page Builder overview |
| Page Assets | Page Builder | Implemented | Edit Page -> Page Management -> Assets | Page-scoped CSS and JS render only on the owning page | Not a primary content plan surface | Relational page assets under site-scoped public paths | [docs/public-assets.md](public-assets.md), [README.md](../README.md) | needs user guide |
| Media Library | Content assets | Implemented | `/webadmin/media` | Public media files can be referenced by blocks and assets | Existing media references are planned for later API use | Site-scoped media management and usage checks | [docs/core-concepts.md](core-concepts.md), [docs/public-assets.md](public-assets.md) | needs media guide |
| Navigation | Public structure | Implemented | `/webadmin/navigation` | Navigation blocks and menus render public navigation | Navigation API foundations | Site-scoped menus and items with groups and icons | [docs/internal-content-api.md](internal-content-api.md), [README.md](../README.md) | needs user guide |
| Public content icons and badges | Page Builder | Implemented | Selected block admin forms; `System -> Icons` catalog | Content blocks can render decorative WebBlocks UI icons and allowlisted badges | Block contracts expose icon and badge fields | Shared catalog icon slugs and badge tones with locale-owned badge labels where supported | [docs/block-type-contracts.md](block-type-contracts.md), [docs/public-block-render-markup.md](public-block-render-markup.md) | covered |
| Navbar / Header composition | Public structure | Implemented | Page Builder block picker | Navbar, brand, navigation, and header actions compose public headers | Creatable when discovered in block contracts | System-owned navbar primitives and composable children | [docs/public-block-render-markup.md](public-block-render-markup.md), [README.md](../README.md) | needs Page Builder overview |
| Page Preview | Editorial | Implemented | Edit Page -> Preview | Authenticated preview for draft, in-review, and published pages | Preview URLs may be reported by operator workflows | Uses public rendering with noindex preview banner | [docs/editorial-workflow.md](editorial-workflow.md), [docs/internal-content-api.md](internal-content-api.md) | covered |
| Page Revisions | Editorial | Implemented | Edit Page revisions | Restores prior page content safely for editors | Not a primary API write surface | Page revision snapshots with actor/source metadata | [docs/revisions.md](revisions.md) | covered |
| Shared Slot Revisions | Editorial | Implemented | Shared Slot revisions | Restores reusable shared content | Not a primary API write surface | Shared Slot revision history and restore | [docs/revisions.md](revisions.md) | covered |
| Page Converter | Page Builder | Implemented | `/webadmin/pages`, Import/Convert flows | Creates draft CMS pages from reviewed static HTML analysis | Uses internal content planning concepts, not a public API | Scoped HTML analysis and signed conversion plans | [docs/page-converter-roadmap.md](page-converter-roadmap.md), [README.md](../README.md) | needs user guide |
| Single Page Import | Page Builder | Implemented | `/webadmin/pages` -> Import Page | Creates one draft page from a documented JSON payload | Separate from Internal Content API | Admin modal workflow for reviewed single-page JSON import | [README.md](../README.md) | needs user guide |

## Editorial Workflow And Permissions

| Feature | Area | Status | Admin path | Public behavior | API support | Source / implementation notes | Existing docs | Documentation gap / next docs page |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Draft / In Review / Published / Archived workflow | Editorial | Implemented | Edit Page -> Overview | Public routes render published pages only | API remains draft-first for content writes | Page statuses separate authoring, review, publication, and archive | [docs/editorial-workflow.md](editorial-workflow.md) | covered |
| Editor role | Permissions | Implemented | `/webadmin/users`, role management | Editors can manage assigned content within permissions | Authorization affects admin/API access | CMS role separate from host product admin status | [docs/users-and-permissions.md](users-and-permissions.md) | covered |
| Site Admin role | Permissions | Implemented | `/webadmin/users`, role management | Site admins manage assigned site content | Authorization affects admin/API access | Site-scoped administrative role | [docs/users-and-permissions.md](users-and-permissions.md) | covered |
| Super Admin role | Permissions | Implemented | `/webadmin/users`, role management | Install-level CMS administration | Required for token and system operations | CMS super admin is a CMS role, not host admin status | [docs/users-and-permissions.md](users-and-permissions.md), [ARCHITECTURE_DECISIONS.md](../ARCHITECTURE_DECISIONS.md) | covered |
| Assigned-site access | Permissions | Implemented | User management | Restricts admin work to assigned sites | Applies to API authorization | Site memberships constrain CMS access | [docs/users-and-permissions.md](users-and-permissions.md), [docs/multisite.md](multisite.md) | covered |
| Workflow transitions | Editorial | Implemented | Edit Page -> Overview | Controls when pages can become public or leave publication | Publish workflow remains separate from normal content plans | Transition permissions guard editorial state changes | [docs/editorial-workflow.md](editorial-workflow.md) | covered |
| Publish permissions | Permissions | Implemented | Role/user permissions | Determines who can publish public pages | Publish API capability is advanced and separate | Publication is intentionally not default for AI/operator writes | [docs/editorial-workflow.md](editorial-workflow.md), [docs/internal-content-api.md](internal-content-api.md) | needs AI/operator example |
| Archive permissions | Permissions | Implemented | Role/user permissions | Controls removal from public publication | Not a normal content plan behavior | Archive is editorial workflow, not destructive deletion | [docs/editorial-workflow.md](editorial-workflow.md) | covered |
| Revision restore permissions | Permissions | Implemented | Page and Shared Slot revision screens | Restored content affects future public rendering after publication | Not a normal content plan behavior | Restore flows require CMS authorization | [docs/revisions.md](revisions.md) | covered |
| CMS profile page | Admin UX | Implemented | `/webadmin/profile` | No direct public behavior | Not API-oriented | Signed-in admins manage their own profile | [README.md](../README.md) | needs user guide |
| Install-level users | Permissions | Implemented | `/webadmin/users` | No direct public behavior | Authorization affects API tokens and access | CMS user management attaches CMS roles to host users | [docs/users-and-permissions.md](users-and-permissions.md) | covered |

## Public Site Features

| Feature | Area | Status | Admin path | Public behavior | API support | Source / implementation notes | Existing docs | Documentation gap / next docs page |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Public page rendering | Public site | Implemented | Page Builder and public settings | Renders published CMS pages from layouts, slots, and blocks | Content plans can create draft renderable pages | Public presenter resolves sites, pages, slots, shared slots, and blocks | [docs/core-concepts.md](core-concepts.md), [docs/public-block-render-markup.md](public-block-render-markup.md) | needs Page Builder overview |
| Public search | Public site | Implemented | Maintenance -> Search Rebuild, public search settings | Site/locale-scoped public search results | Not a delivery API | Database-backed search index | [docs/search.md](search.md) | covered |
| Search modal | Public site | Implemented | Header Actions block and public assets | Header-triggered search modal | Not API-oriented | Uses CMS-owned public search modal runtime | [docs/search.md](search.md), [README.md](../README.md) | covered |
| Search Form block | Public blocks | Implemented | Page Builder block picker | Renders public search form | Creatable when discovered in block contracts | Block-based search entry point | [docs/search.md](search.md), [docs/public-block-render-markup.md](public-block-render-markup.md) | covered |
| Visitor Reports | Analytics | Implemented | `/webadmin/visitor-reports` | Privacy-safe public traffic reporting | Not a public delivery API | Visitor event aggregates and reporting | [README.md](../README.md) | needs user guide |
| Site Branding / SEO defaults | Public site | Implemented | Edit Site -> Branding, SEO Defaults | Public metadata fallback and brand assets | Site discovery surfaces expose context | Site-owned display name, tagline, SEO defaults, favicon, social image | [docs/core-concepts.md](core-concepts.md), [README.md](../README.md) | needs user guide |
| Public theme presets and visual tones | Public site / Page Builder | Partially implemented | `Sites -> Edit Site -> Theme`; initial `Icon tone` selects on supported icon-enabled block forms | Public body emits `data-wb-public-theme="{preset}"` with `canvas` fallback; full preset token styling remains planned; initial icon tone classes affect public block icons for supported blocks while blocks choose design roles such as `brand`, `accent`, or `quiet` | Content contract discovery exposes `settings.icon_tone` for supported blocks | Public theme is site-scoped and separate from admin UI preferences; semantic status tones remain separate from design tones | [docs/public-theme-and-tones.md](public-theme-and-tones.md), [docs/public-block-render-markup.md](public-block-render-markup.md) | public theme token styling docs after Phase 2B |
| Page-level SEO overrides | Public site | Implemented | Page translation settings | Locale-aware page metadata overrides | Page read surfaces include page metadata context | SEO values live on page translations | [README.md](../README.md), [docs/localization.md](localization.md) | needs user guide |
| Favicon / social image | Public site | Implemented | Edit Site -> Branding | Public favicon and social preview fallbacks | Not a primary API write surface | Site-level brand media support | [README.md](../README.md) | needs user guide |
| Site variables | Public site | Implemented | Edit Site -> Variables | Replaces `{{ site.variable_key }}` tokens in supported public rendering | Not a primary API write surface | Relational reusable site-scoped text tokens | [README.md](../README.md) | needs user guide |
| Multisite routing | Public site | Implemented | `/webadmin/sites`, domain management | Routes requests to the matching site/domain | Site discovery supports operator context | Primary and alias domains per site | [docs/multisite.md](multisite.md), [docs/coexistence.md](coexistence.md) | covered |
| Localized public URLs | Public site | Implemented | Locales and page translations | Locale-aware page paths and fallbacks | Locale discovery supports operator context | Page translations and locale routing | [docs/localization.md](localization.md) | covered |

## Forms And Messages

Contact Form and Contact Messages are first-class WebBlocks CMS features. The user-facing setup, submission, spam handling, email fallback, and troubleshooting guide now lives in [docs/contact-forms-and-messages.md](contact-forms-and-messages.md).

| Feature | Area | Status | Admin path | Public behavior | API support | Source / implementation notes | Existing docs | Documentation gap / next docs page |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Contact Form block | Forms | Implemented | Page Builder block picker | Renders a public form and stores submissions | Creatable through content plans when `contact_form` is discovered | First-class block with recipient fallback support | [docs/contact-forms-and-messages.md](contact-forms-and-messages.md), [docs/block-type-contracts.md](block-type-contracts.md), [docs/public-block-render-markup.md](public-block-render-markup.md) | covered |
| Contact Messages admin screen | Forms | Implemented | `/webadmin/contact-messages` | No public listing; admins review submissions | Not a public delivery API | Admin table includes delivery and spam status | [docs/contact-forms-and-messages.md](contact-forms-and-messages.md), [README.md](../README.md) | covered |
| Contact form submission storage | Forms | Implemented | Contact Messages | Stores real public submissions for review | Not API-oriented | Submissions are stored before email notification attempts | [docs/contact-forms-and-messages.md](contact-forms-and-messages.md) | covered |
| Anti-spam check discard | Forms | Implemented | Contact Form renderer and submission handling | Filled generated check-field submissions receive normal success redirect and are discarded | Not API-oriented | CMS-owned hidden `.wb-form-check` generated by native renderer | [docs/contact-forms-and-messages.md](contact-forms-and-messages.md) | covered |
| Spam scoring / quarantine | Forms | Implemented | Contact Messages | Suspicious submissions can be quarantined for admin review | Not API-oriented | Conservative spam signals for links, commercial patterns, and repeat IPs | [docs/contact-forms-and-messages.md](contact-forms-and-messages.md) | covered |
| Email notification fallback chain | Forms | Implemented | Contact Form block, Edit Site -> Contact, environment mail | Attempts recipient resolution from block, site, and safe mail fallback | Not API-oriented | Delivery attempts happen after durable storage; setup guidance covers `.env`, site recipients, and cache clearing | [docs/contact-forms-and-messages.md](contact-forms-and-messages.md), [docs/installation.md](installation.md), [docs/getting-started.md](getting-started.md) | covered |
| Contact mail diagnose command | Operations | Implemented | Operator-side diagnostic | No public behavior | Not API-oriented | Secret-safe mail configuration diagnostics and controlled SMTP send checks | [docs/contact-forms-and-messages.md](contact-forms-and-messages.md), [docs/operations.md](operations.md), [README.md](../README.md) | covered |
| Delivery status / failure details | Forms | Implemented | Contact Messages | No public failure disclosure | Not API-oriented | Saved messages include admin-visible notification status | [docs/contact-forms-and-messages.md](contact-forms-and-messages.md) | covered |

## Internal Content API And AI Operations

The Internal Content API is for trusted AI/operator tools. It is not a public delivery API, not an AI vendor integration, and not an import/export replacement. AI/operator tools must use live discovery and must not guess block handles.

Recommended additional docs:

- `docs/ai-page-building-overview.md`
- [docs/contact-forms-and-messages.md](contact-forms-and-messages.md) now exists.
- [docs/markdown-docs-to-cms-sync.md](markdown-docs-to-cms-sync.md) already exists.

| Feature | Area | Status | Admin path | Public behavior | API support | Source / implementation notes | Existing docs | Documentation gap / next docs page |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| API Tokens | AI operations | Implemented | `/webadmin/system/api-tokens` | No public behavior | Bearer token authentication | Tokens are hashed and shown once | [docs/internal-content-api.md](internal-content-api.md), [docs/api-discovery.md](api-discovery.md) | covered |
| Token capabilities | AI operations | Implemented | API token create/edit | No public behavior | Capability-scoped API authorization | Publish/delete capabilities are advanced opt-ins | [docs/internal-content-api.md](internal-content-api.md) | needs AI/operator example |
| API discovery endpoint | AI operations | Implemented | No browser admin path | No public behavior | Discovery root for trusted tools | Starts from API base URL and token | [docs/api-discovery.md](api-discovery.md) | covered |
| OpenAPI endpoint | AI operations | Implemented | No browser admin path | No public behavior | Machine-readable API contract | Linked from discovery | [docs/api-discovery.md](api-discovery.md), [docs/internal-content-api.md](internal-content-api.md) | covered |
| AI guide endpoint | AI operations | Implemented | No browser admin path | No public behavior | Guide link from discovery | Helps operators build safely | [docs/ai-page-building-guide.md](ai-page-building-guide.md) | needs overview |
| Content contract endpoint | AI operations | Implemented | No browser admin path | No public behavior | Block/content contract discovery | Required before content planning | [docs/api-discovery.md](api-discovery.md), [docs/block-type-contracts.md](block-type-contracts.md) | covered |
| Examples endpoint | AI operations | Implemented | No browser admin path | No public behavior | Example content plan discovery | Linked from discovery | [docs/api-discovery.md](api-discovery.md) | needs AI/operator example |
| Pages read API | AI operations | Implemented | No browser admin path | No public behavior | Read-only page inspection | Resource-style endpoint under the internal API | [docs/internal-content-api.md](internal-content-api.md) | covered |
| Block/content discovery | AI operations | Implemented | No browser admin path | No public behavior | Block contract and content inspection | Prevents guessed block handles | [docs/block-type-contracts.md](block-type-contracts.md), [docs/api-discovery.md](api-discovery.md) | covered |
| Content validate | AI operations | Implemented | No browser admin path | No public behavior | Validates content plans before apply | Required safety step for operator workflows | [docs/internal-content-api.md](internal-content-api.md), [docs/ai-page-building-guide.md](ai-page-building-guide.md) | covered |
| Content apply | AI operations | Implemented | No browser admin path | Creates draft CMS content, not public by default | Applies approved content plans | Draft-first and non-destructive by default | [docs/internal-content-api.md](internal-content-api.md), [docs/ai-page-building-guide.md](ai-page-building-guide.md) | covered |
| Draft-safe page creation | AI operations | Implemented | Resulting page appears in Pages | Draft pages can be previewed before publication | Supported through content plans | Creates pages, slots, translations, and block trees | [docs/internal-content-api.md](internal-content-api.md) | needs AI/operator example |
| replace_existing_draft_page / draft-safe replacement model | AI operations | Implemented | Existing draft page in Pages | Replaces draft page-owned managed slots only | Supported validate/apply mode | Uses expected path or updated-at safety guards | [docs/internal-content-api.md](internal-content-api.md), [docs/markdown-docs-to-cms-sync.md](markdown-docs-to-cms-sync.md) | needs AI/operator example |
| Published page staged updates | AI operations | Implemented | Staged draft page preview in Pages | Source published page remains public until explicit promote | Supported through staged validate/apply modes; promote requires `content.publish` | Uses draft page with `settings.staged_update` metadata and page-owned slot promotion | [docs/internal-content-api.md](internal-content-api.md), [docs/markdown-docs-to-cms-sync.md](markdown-docs-to-cms-sync.md), [docs/editorial-workflow.md](editorial-workflow.md) | needs AI/operator example |
| Navigation API foundations | AI operations | Implemented | Navigation resources in admin | Planned content can affect public navigation after approval | Navigation menu/item foundations | Safe foundations without broad destructive behavior | [docs/internal-content-api.md](internal-content-api.md) | needs AI/operator example |
| Shared Slots API foundations | AI operations | Implemented | Shared Slots admin | Shared content can render across pages after approval | Shared Slot and assignment foundations | Compatible same-site assignment support | [docs/internal-content-api.md](internal-content-api.md) | needs AI/operator example |
| JSON-only errors | AI operations | Implemented | No browser admin path | No public behavior | API errors return JSON | Separates operator API from browser admin responses | [docs/internal-content-api.md](internal-content-api.md) | covered |
| CSRF-free Bearer API write behavior | AI operations | Implemented | No browser admin path | No public behavior | Bearer writes do not depend on browser CSRF | Trusted token contract for operator tools | [docs/internal-content-api.md](internal-content-api.md) | covered |
| Preview URL reporting | AI operations | Implemented | Edit Page preview | Operators can review draft previews | Apply responses can report preview context | Preview remains authenticated and draft-safe | [docs/ai-page-building-guide.md](ai-page-building-guide.md), [docs/editorial-workflow.md](editorial-workflow.md) | covered |
| Draft-first AI page building workflow | AI operations | Implemented | Pages and preview screens | AI-created pages are drafts until approved | Discovery, validate, apply workflow | Requires live discovery and exact contracts | [docs/ai-page-building-guide.md](ai-page-building-guide.md) | needs `docs/ai-page-building-overview.md` |
| Markdown docs-to-CMS sync runbook | Documentation | Documented direction | No runtime admin path | Future source-linked docs pages can publish Markdown-derived content | Would use discovery, validate, and approved draft-safe apply workflows | Markdown source remains authoritative and changed `docs/` files drive AI/operator planning | [docs/markdown-docs-to-cms-sync.md](markdown-docs-to-cms-sync.md) | covered |

## Operations

| Feature | Area | Status | Admin path | Public behavior | API support | Source / implementation notes | Existing docs | Documentation gap / next docs page |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| System Updates | Operations | Implemented | `/webadmin/system/updates` | Updates installed CMS code and package assets | Not a public delivery API | Package-native update flow | [docs/updates.md](updates.md), [docs/operations.md](operations.md) | needs operator guide |
| Release package updates | Operations | Implemented | System Updates | Public site behavior changes after approved update | Not API-oriented | Consumes package release artifacts | [docs/updates.md](updates.md), [docs/package-architecture.md](package-architecture.md) | needs operator guide |
| Package-native update root | Operations | Implemented | System Updates | No direct public behavior | Not API-oriented | Canonical package root is `vendor/fklavyenet/webblocks-cms` | [docs/package-architecture.md](package-architecture.md), [ARCHITECTURE_DECISIONS.md](../ARCHITECTURE_DECISIONS.md) | covered |
| Update migrations | Operations | Implemented | System Updates | Keeps runtime-required schema aligned | Not API-oriented | Package update migrations support existing installs | [docs/updates.md](updates.md), [ARCHITECTURE_DECISIONS.md](../ARCHITECTURE_DECISIONS.md) | covered |
| Update readiness | Operations | Implemented | System Updates | No public behavior | Not API-oriented | Readiness and release detail checks before update | [docs/updates.md](updates.md) | needs operator guide |
| Last update run | Operations | Implemented | System Updates | No public behavior | Not API-oriented | Last-run reporting for operators | [docs/updates.md](updates.md), [README.md](../README.md) | needs operator guide |
| Support report | Operations | Implemented | System Updates | No public behavior | Not API-oriented | Downloadable support diagnostics for update issues | [docs/updates.md](updates.md) | needs operator guide |
| Backups | Operations | Implemented | `/webadmin/backups` | Protects site content and install state | Not API-oriented | Backup records and storage disk | [docs/operations.md](operations.md) | covered |
| Restore | Operations | Implemented | `/webadmin/backups` restore flow | Restored content can affect public site | Not API-oriented | Controlled backup restore workflow | [docs/operations.md](operations.md) | covered |
| Site Export / Import | Operations | Implemented | `/webadmin/site-transfers/exports`, `/webadmin/site-transfers/imports` | Moves site content between installs | Separate from Internal Content API | Packages content, media, navigation, and related site data | [docs/operations.md](operations.md), [README.md](../README.md) | needs user guide |
| Site Clone / Promotion | Operations | Implemented / documented | Site and promotion workflows | Cloned/promoted site content can become public | Not a public delivery API | Package-based site transfer and promotion concepts | [docs/operations.md](operations.md), [README.md](../README.md) | needs operator guide |
| Search Rebuild | Operations | Implemented | Maintenance -> Search Rebuild | Refreshes public search index | Not API-oriented | Rebuilds database-backed public search | [docs/search.md](search.md), [docs/operations.md](operations.md) | covered |
| Catalog repair commands | Operations | Implemented | Operator-side maintenance | Repairs CMS catalogs used by admin/rendering | Not API-oriented | Maintenance commands for catalog consistency | [docs/operations.md](operations.md), [README.md](../README.md) | needs operator guide |
| Install wizard | Installation | Implemented | First-run install screens | Creates initial CMS install state | Not API-oriented | Browser-guided first-run setup | [docs/installation.md](installation.md) | covered |
| Package consumer install | Installation | Implemented | Package install workflow | Enables CMS public/admin routes in a host | Not API-oriented | Package installer prepares schema, assets, and first user | [docs/installation.md](installation.md), [README.md](../README.md) | covered |
| Native local development | Development | Implemented docs | Local development only | Local `.test` development environment | Not API-oriented | HTTPS-only local development guidance | [docs/native-local-development.md](native-local-development.md) | covered |
| Testing strategy | Development | Implemented docs | Development workflow | No direct public behavior | Not API-oriented | Risk-based test scripts and validation guidance | [docs/testing-strategy.md](testing-strategy.md) | covered |

## Plugin System

| Feature | Area | Status | Admin path | Public behavior | API support | Source / implementation notes | Existing docs | Documentation gap / next docs page |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Plugin host architecture | Plugins | Implemented foundations | `/webadmin/system/plugins` | Plugins can add public behavior when enabled | Not a generic public API | CMS core is a plugin host | [docs/plugin-system.md](plugin-system.md), [ARCHITECTURE_DECISIONS.md](../ARCHITECTURE_DECISIONS.md) | covered |
| Manual ZIP plugin install | Plugins | Implemented | System -> Plugins -> Upload | Installed plugins are disabled by default | Not API-oriented | Storage-owned install path and package validation | [docs/plugin-system.md](plugin-system.md) | needs plugin lifecycle guide |
| Disabled-by-default plugin lifecycle | Plugins | Implemented | System -> Plugins | Disabled plugins are inert | Not API-oriented | Installed packages must be explicitly enabled | [docs/plugin-system.md](plugin-system.md) | covered |
| Enable / disable | Plugins | Implemented | System -> Plugins | Enables or removes active plugin behavior | Not API-oriented | Compatibility and setup checks guard activation | [docs/plugin-system.md](plugin-system.md) | needs plugin lifecycle guide |
| Plugin setup / migrations | Plugins | Implemented foundations | Plugin detail/setup screens | Plugin behavior may require setup before routes work | Not API-oriented | Missing plugin-owned tables show controlled setup guidance | [docs/plugin-system.md](plugin-system.md) | needs plugin lifecycle guide |
| Plugin permissions | Plugins | Implemented | Role/user permission management | Controls access to plugin admin routes | Not API-oriented | Permissions are handle-prefixed | [docs/plugin-system.md](plugin-system.md) | covered |
| Plugin admin routes | Plugins | Implemented | `/webadmin/plugins/{plugin-handle}` | Plugin browser admin behavior when enabled | Not API-oriented | Routes are enabled-only and namespace-guarded | [docs/plugin-system.md](plugin-system.md) | covered |
| Plugin health/status | Plugins | Implemented | System -> Plugins | No direct public behavior | Not API-oriented | Health checks and compatibility status | [docs/plugin-system.md](plugin-system.md) | covered |
| Plugin-owned blocks/block packs | Plugins | Implemented foundations | Block picker when plugin is active | Plugin blocks can render public content | Discoverable when block contracts expose them | Plugin block handles are plugin-owned | [docs/plugin-system.md](plugin-system.md), [docs/block-type-contracts.md](block-type-contracts.md) | needs plugin lifecycle guide |
| Public asset hooks | Plugins | Implemented foundations | Plugin registration | Plugins can contribute safe public assets | Not API-oriented | Safe head and body-end contribution hooks | [docs/plugin-system.md](plugin-system.md) | covered |
| Plugin Catalog browser | Plugins | Implemented | System -> Plugins -> Browse Plugin Catalog | No direct public site behavior | Reads catalog metadata, not a public delivery API | Public catalog metadata browsing | [docs/plugin-system.md](plugin-system.md), [docs/plugin-ecosystem-and-catalog.md](plugin-ecosystem-and-catalog.md) | needs plugin lifecycle guide |
| Catalog install | Plugins | Implemented | Plugin Catalog detail | Installed plugin remains disabled until enabled | Not API-oriented | Checksum-verified catalog artifact install | [docs/plugin-system.md](plugin-system.md) | needs plugin lifecycle guide |
| Catalog-backed plugin updates | Plugins | Implemented | System -> Plugins | Updated plugin behavior after approved update | Not API-oriented | Reuses checksum and package validation path | [docs/plugin-system.md](plugin-system.md) | needs plugin lifecycle guide |
| Manual uninstall | Plugins | Implemented | System -> Plugins | Removes disabled uploaded plugin package | Not API-oriented | Preserves plugin-owned database tables | [docs/plugin-system.md](plugin-system.md) | covered |
| WebBlocks UI Manager as operator plugin, not bundled CMS core feature | Plugins | Implemented architecture | Installed only when manually uploaded/enabled | No ordinary CMS runtime behavior | Not API-oriented | First-party operator plugin remains outside bundled core | [docs/plugin-system.md](plugin-system.md), [ARCHITECTURE_DECISIONS.md](../ARCHITECTURE_DECISIONS.md) | covered |

## Documentation And Product Site Readiness

Current docs are technically rich, but feature discoverability is incomplete. This inventory should help decide which user-facing docs pages are missing and which source pages can later be published as source-linked documentation pages in a target documentation site.

Priority documentation pages:

1. AI Page Building Overview
2. Page Builder Overview
3. Feature Inventory
4. Forms / Messages troubleshooting
5. Plugin lifecycle guide
6. System Updates operator guide

webblocksui.com marketing content should remain separate from technical documentation. Marketing pages can summarize product value, while Markdown documentation should remain the source for technical and operator guidance.
