---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/forms-plugin-plan
cms_title: Forms Plugin Plan
cms_layout: docs
cms_source_id: webblocks-cms:docs/forms-plugin-plan.md
---

# Forms Plugin Plan

This document records the design for user-defined forms in WebBlocks CMS, and what has been built against it. The plugin has shipped through `0.3.0`; sections marked as shipped describe real behavior, and the scope section records what each release actually delivered. It follows the format of [the appointments plugin plan](appointments-plugin-plan.md), including its rule that a corrected assumption is recorded rather than quietly replaced — the reasoning is usually the useful part.

## Decision

User-defined forms ship as a plugin with the handle `webblocks-forms`, not as a CMS core feature, and not as an extension of the native `contact_form` block.

## The Gap

The missing capability is not "a form". It is a *form definition*.

[Contact forms and messages](contact-forms-and-messages.md) describes a native `contact_form` block with a working public surface behind it: a CSRF-protected `POST /contact-messages`, a renderer-generated signed check field plus timing and scoring, storage before notification, an admin review screen at `/webadmin/contact-messages`, and a four-step recipient fallback chain. That machinery is sound and this plan does not touch it.

What it cannot do is change its field set. The public contract is fixed at `name`, `email`, `subject`, and optional `message`. A site owner who needs a budget range, a file attachment, a consent checkbox with their own wording, a three-step application, or a CSV of the last quarter's submissions has nowhere to go — and the same document explicitly forbids the escape hatch, instructing operators and AI tools not to build forms with Trusted HTML, raw `<form>` markup, or `mailto:` links. The prohibition is correct. The absence of an alternative behind it is the gap.

## Why A Plugin

The [plugin system boundary](plugin-system.md) settles it: core owns sites, pages, blocks, media, users, rendering, and the extension contracts; product and business-domain capabilities are plugins. A form builder is a domain — field types, validation rules, submission lifecycle, retention policy, export, delivery actions. A documentation site must not inherit a Forms menu, eight tables, a prune command, and a submissions inbox it will never open.

Contact Form living in core is not an argument for a form builder living in core, for the same reason recorded in the appointments plan. A contact form is one fixed stateless message. A form builder is a schema editor with storage, a lifecycle, and a delivery pipeline behind it.

## Prior Art

Independent ecosystems have converged on the same architecture, which is a stronger signal than any one of them individually.

| CMS | Solution | Model |
| --- | --- | --- |
| Drupal | Webform | Form is a config entity, elements declared in YAML, post-submit **handler** chain, wizard steps, conditional states, exporters |
| WordPress | Gravity Forms, WPForms, Fluent Forms | Form is its own content entity, drag-drop builder, entries screen, notifications plus an add-on ecosystem |
| Craft | Freeform, Formie | Form is an element with a field layout, submissions are elements, spam capture |
| TYPO3 | EXT:form | YAML definition plus builder, post-submit **finisher** chain |
| Statamic | Core Forms | Blueprint-driven fields, submission store, honeypot, mail templates |
| Silverstripe | UserForms | Form is a page type, fields are child records |

Four elements are common to all of them:

1. **The form is an entity**, not settings embedded in a placement. It outlives any one page and can appear on several.
2. **Fields are a typed, ordered schema** carrying validation rules.
3. **Submissions have their own store** with filtering and export.
4. **Post-submit behavior is a pluggable chain** — handler, finisher, action, feed. Email is the first entry in that chain, never a privileged special case.

The fourth is the one worth copying deliberately. Products that hard-wired email and added the chain later had to unpick the special case first. The chain is also where a form builder stops being a silo: in the WordPress ecosystem, form plugins became central infrastructure precisely because everything else could hang an action off them.

The relevant counter-example is the headless side — Ghost, Sanity — which declines the problem and delegates to Formspree or Netlify Forms. That option is closed here. The catalog's own product positioning is "on your own domain instead of linking out to a third-party service", which is the sentence the appointments plugin's description already makes.

## Phase 0 — Core Extension Points

Unlike appointments, this plugin has no blocking core gap. Every contract it needs shipped in `1.43.x` and is exercised by three catalog plugins today: declared block views (`admin_view` / `public_view`), `routes.public` under the reserved `/plugins/{handle}` prefix with `web`, `install.required`, and a mandatory group throttle applied by the registrar, plugin-owned admin routes with `plugin.permission:` middleware, permissions, migrations, settings with a plugin-named route, commands under the `{handle}:` prefix, and `resources/lang` registered under the plugin handle.

Two gaps recorded as open in the appointments plan were still open when this was checked against `1.52.18`, and both shape this design rather than block it.

**0.4 Plugin blocks cannot own translatable fields.** Still open. `BlockTranslationRegistry::familyFor()` is a fixed `match` over core slugs — `contact_form` is in it, a plugin handle cannot be. This plugin is less affected than appointments was, because the workaround is the right design anyway: visitor-facing copy belongs to the *form*, not to the block that places it, so it lives in plugin-owned per-locale rows and never needs a block translation family. Core work here would be a convenience, not a dependency.

**0.6 Plugin static assets are declarable but not servable.** Still open. `PluginPublicAsset` emits the tag, the manifest documents an `assets` key, and `InstalledPluginDefinitionFactory` never parses it — so a plugin cannot ship CSS or JavaScript and the emitted tag would 404.

This is a real constraint on the product, and the honest response is to design around it rather than plan features that need it. Conditional field visibility and multi-step forms are therefore **server-rendered**: a step is a GET/POST round trip and a conditional branch is evaluated on the server between steps, exactly as the appointments booking flow walks its steps. This costs a page load per step and gains something worth having — the form works with JavaScript off, each step is a shareable URL, and there is no client-side validation to drift out of sync with the server's. If 0.6 lands later, a progressive enhancement can collapse the round trip without changing the server contract.

Conditional visibility *within* a single step is the one feature genuinely deferred by this constraint, and it is out of scope for v1.

## Plugin Identity And Conventions

```text
handle              webblocks-forms
settings namespace  webblocks_forms
database prefix     webblocks_forms_
admin routes        /webadmin/plugins/webblocks-forms
public routes       /plugins/webblocks-forms
route names         webblocks.plugins.webblocks_forms.*
permissions         webblocks-forms.view, .manage, .submissions, .export, .settings
commands            webblocks-forms:prune-submissions
block handles       webblocks-forms::form
```

The CMS floor is `^1.45.6`, derived from the CMS history once the code existed rather than set to whatever was current. The binding contract is the block type catalog row; the reasoning is under [CMS Floor](#cms-floor-1456).

## The Block Is One Setting

`webblocks-forms::form` has exactly one meaningful setting: which form to render.

Everything else — fields, labels, validation copy, success message, redirect, notification routing — lives on the form record. One form can then appear on five pages, a copy change happens in one place, and the block editor stays a single select instead of growing a schema editor inside a page builder panel.

This is the Gravity Forms and Formie model. Contact Form 7's opposite model, where the field markup lives in the placement, is where that product's ceiling comes from.

Plugin block copy posts under `plugin_settings[...]`; `settings[...]` is prohibited for plugin blocks by `BlockRequest` and is written by core from sanitized plugin input.

## Domain Model

Eight tables under the `webblocks_forms_` prefix, all site-scoped. Index and foreign key names must be spelled explicitly and kept short: the reserved prefix consumes most of MySQL's 64-character identifier limit before a table name starts, and SQLite does not enforce the limit — a plugin tested only against SQLite can ship migrations that fail on every real install. This is the schema bug the appointments plugin found the hard way, and it is cheaper to avoid than to rediscover.

| Table | Purpose |
| --- | --- |
| `webblocks_forms_forms` | The form: site, handle, name, active flag, settings (success mode, redirect path, store/notify toggles, per-form throttle, retention days) |
| `webblocks_forms_fields` | Typed field: form, type, key, sort order, required flag, validation rules, options, step number |
| `webblocks_forms_field_translations` | Per-locale label, placeholder, help text, option labels, and custom validation copy |
| `webblocks_forms_form_translations` | Per-locale form title, intro, submit label, success message |
| `webblocks_forms_submissions` | Status, spam score and reason labels, source page, notification status, and the schema snapshot |
| `webblocks_forms_submission_values` | One row per answered field: submission, field key, value |
| `webblocks_forms_files` | Uploaded file: submission, disk path, original name, mime, size |
| `webblocks_forms_actions` | Post-submit action: form, type, sort order, enabled flag, config |
| `webblocks_forms_settings` | Per-site defaults: notification recipient, retention, storage, upload ceiling |

*(The settings table was not in this plan. It arrived while building the skeleton, and the correction is recorded rather than the original quietly replaced.)* The plan assumed per-form settings plus a config file would cover everything, which is wrong the moment an install has two sites: the default notification recipient and how long submissions are kept are decisions a business makes, not properties of an install — and a config file cannot be edited from the admin at all. What stays in `config/webblocks-forms.php` is the shape of the install: which disk uploads land on, the hard ceiling on upload size, the submit rate limit. The appointments plugin reached the same conclusion from the same starting point, which is reason to expect the next plugin with per-site behaviour to need one too.

Fields are rows rather than a single JSON column on the form. JSON needs fewer migrations, but ordering, per-locale translation rows, and "which forms use a file upload" all become awkward, and the admin editor has to hand-roll what a related model gives it. The cost is accepted deliberately.

### The Schema Snapshot Is Not Optional

`webblocks_forms_submissions.schema_snapshot` freezes the form's field definitions as they stood at submit time.

This is the single decision most likely to be dropped as redundant and most expensive to add later. Without it, renaming or deleting a field silently rewrites the meaning of every submission already stored: a column heading changes retroactively, a deleted field's answers become orphan key/value rows with nothing to label them, and an export from last quarter no longer matches what the visitor actually saw. Every mature implementation in the table above has an equivalent, and the ones that lack it accumulate support tickets that cannot be answered.

Values stay in `submission_values` for querying and filtering. The snapshot is what gives them meaning.

## Submit Pipeline

```text
validate -> spam -> store -> run actions -> generic success or redirect
```

Storage happens before any action is attempted and is never retracted by an action failure. This is the rule Contact Form already follows and appointments repeated, and it is the reason an operator can trust the inbox when mail is misconfigured.

Anti-spam reuses the Contact Form model rather than inventing a third one: a renderer-generated signed check field, a timing floor, and conservative scoring. Filled check fields and implausibly fast submissions receive the same generic success response as a real submission without being stored. Scored spam is retained with spam status for review, not auto-deleted. No scoring detail, delivery internal, or validation of the check field itself reaches the visitor.

Two protections are invisible in the markup and easy to lose in a rewrite, both inherited from the appointments public surface. Submitted values are validated against the *stored* field definitions rather than against anything the post claims, so a crafted post cannot introduce a field the form does not have or bypass a rule the renderer applied. And any redirect target is honoured only as a same-site path, so a form cannot be turned into an open redirect.

The registrar's group throttle (60/min per IP and plugin by default) applies to every public route. A per-form throttle is an additional stricter limit, not a replacement.

## Actions

Actions are the extensibility seam and must exist as a chain from v1 even though v1 ships only three types.

| Action | Notes |
| --- | --- |
| `notify` | Admin notification. Recipient resolution follows the Contact Form fallback chain: action config, then the site's Contact recipient, then `CONTACT_RECIPIENT_EMAIL`, then `MAIL_FROM_ADDRESS` |
| `autoresponder` | Reply to the submitter, sent only to a value from a validated email field on the same submission — never to a free-text address in the payload |
| `webhook` | Outbound POST of the submission plus its schema snapshot, with a shared-secret signature header |

Notification status records as sent, failed, skipped, or not configured, with sanitized failure detail — no credentials, tokens, or stack traces. Following the correction recorded in the appointments notifications section, the admin notification and the autoresponder are recorded **separately**: a mistyped business recipient must not suppress the visitor's copy, and a combined status would make the admin screen lie in exactly the case an operator needs it honest.

Later action types are what make the plugin catalog cohere rather than fan out: add a contact to a **WebBlocks Campaigns** list (through that plugin's double opt-in, never bypassing it), open a **WebBlocks Commerce** order, request a **WebBlocks Appointments** booking. Each is a separate release and each depends on the target plugin exposing a supported entry point — none of which has been verified, so none is committed here.

## Plugin-To-Plugin Actions

*(Investigated before building the Campaigns action. One of the three planned integrations is worth doing, two are not, and the reasons are more useful than the conclusion.)*

The plan has always listed actions that hand a submission to another catalog plugin as the thing that stops a form builder being a silo. Before writing one, the three targets were read for what they actually expose.

### There Is No Plugin-To-Plugin Contract

Installed plugins have no PSR-4 autoloader. `InstalledPluginDefinitionFactory::make()` calls `loadPluginSource()`, which `require_once`s every `.php` file under the plugin's `src/` — and it does so only when the plugin is enabled:

```php
if ($enabled) {
    $this->loadPluginSource($path, $provider);
}
```

So one plugin can call another's classes, and that works because they end up in the same process, not because anything says they may. Nothing in [the plugin system document](plugin-system.md) describes plugin-to-plugin calls, core offers no discovery beyond `class_exists()`, and the manifest's `requires` key — present in every catalog manifest — is read by nothing at all, the same shape of gap already recorded here for `config` and `assets`.

One consequence is useful and worth keeping: a disabled plugin's classes are never loaded, so `class_exists()` is an accurate availability check and a disabled plugin cannot be called by accident. Two are not. A call made during boot may run before the other plugin has been loaded, so cross-plugin calls belong at runtime rather than in a service provider. And nothing declares a version, so a changed signature in the providing plugin becomes a fatal error inside a visitor's submission.

### What Should Be Done About Declared Dependencies

Three parts, and the first thing to rule out is the obvious one.

**Not hard dependency resolution.** Refusing to enable a plugin until its dependencies are enabled needs a resolver the CMS does not have, in an install model where plugins arrive as manually uploaded ZIPs. The predictable result is the deadlock: A cannot be enabled because B is disabled, B cannot be disabled because A is enabled. It buys ordering guarantees nothing here needs.

**Core should read the `requires` key it already documents.** It exists in every catalog manifest, it already carries `webblocks-cms` and `php`, and `required_cms_version` proves the pattern works. Checking it at install and enable, and reporting the result on the plugin detail screen and through the health reporter, turns a silent degradation into a sentence an operator can act on. It should **warn, never block**.

**Every cross-plugin dependency stays soft at runtime.** WebBlocks Forms must work completely without WebBlocks Campaigns installed; the action simply reports as not configured. This is what makes `class_exists()` a design decision rather than a workaround, and it is already how the loader behaves.

**And the providing plugin should say what its entry point is.** Today "public method" means "API" by accident. A plugin offering an integration should mark the method as its supported surface and treat it as versioned; otherwise every internal refactor is a breaking change nobody promised not to make.

### Campaigns — Worth Doing

`SubscriptionService::request(siteId, email, consentText, firstName, lastName, listId, requestIp, sourceUrl)` returns a status constant, and its constructor defaults its own collaborators, so it is callable without container wiring.

It fits for reasons beyond convenience. Double opt-in is enforced *inside* the service, so an action calling it cannot skip the confirmation step — the integration cannot weaken the consent model it plugs into. Its docblock states that the return value must never reach the visitor, because a page that distinguishes "already subscribed" from "check your email" turns the form into a way to test who is on a list; that is the same rule this plugin's own generic-success behaviour already follows.

The `consentText` parameter is the part that makes it more than a convenience. This plugin's consent field type already freezes the agreed wording into the submission's schema snapshot, precisely so that what was agreed to stays readable. That is exactly the value Campaigns needs, and it means the action can only run when a consent field exists and was accepted — no consent text, no legal basis, no subscription.

### Appointments — Not Worth Doing

`AppointmentBooker::book()` looks callable and is a trap. [The appointments plan](appointments-plugin-plan.md) records that its own controller re-derives the submitted instant against the slot engine rather than trusting it, because the booker enforces conflicts but not the rules that decide what should have been offered at all — without the recheck, a crafted post books 03:00 on a closed Sunday. An action calling `book()` directly reintroduces exactly that hole.

Doing it properly means depending on the slot engine as well, which is a far wider surface. And the value is low: a form's field list cannot express "choose a service, then a date, then a free time", which is what the appointments block already does well.

### Commerce — The Wrong Shape

`StartCheckout::forProduct()` and `forCart()` return a `GatewayCheckoutSession` — the start of a redirect to a payment provider. The action chain runs after a submission is committed, as a side effect, and the form has already decided its own success or redirect behaviour. A checkout cannot be a side effect of something that has finished responding.

A form that *is* a checkout is a coherent idea and a different design. It is not this seam.

## Admin Surface

Three menu entries under a `Forms` group: the form list, the submissions inbox, and settings.

The form editor holds fields, actions, and per-form settings. The submissions inbox filters by form, status, and date, shows the stored values against the submission's own schema snapshot, and exports CSV.

Screens are site-scoped the way the CMS pages admin is — query parameter, then session, then primary site. Route-level plugin permissions say what an operator may do and nothing about which sites they may do it to, so every site-scoped write calls `AdminAuthorization::abortUnlessSiteAccess` separately.

The settings definition must name its own route. Left to the default, the CMS registers a read-only `/settings` scaffold under the same name and URI and, being registered first, wins the match — a lesson the appointments plugin paid for.

A form that already has submissions is deactivated rather than deleted. Deleting a field does not delete its stored values; they remain readable through the snapshot.

## Localization

No user-facing string is hard-coded. Admin and system strings live under `resources/lang`, registered under the plugin handle, with English as the source of truth and always present — matching the appointments plugin, which ships `en`, `de`, and `tr`.

Editorial copy is different in kind and lives in the database, in `webblocks_forms_form_translations` and `webblocks_forms_field_translations`, because a site owner writes it and a release cannot ship it. This is the design that makes core gap 0.4 irrelevant to this plugin.

Operational settings — recipients, storage, retention — are shared across locales and never translated. Contact Form already draws that line and it holds here.

## Uploaded Files Do Not Go To The Media Library

*(Decided after examining the media library. The obvious reading — an upload is a file, the CMS has a file library, use it — was tried first and rejected on the evidence.)*

Submission attachments go to a **private plugin-owned disk**, not to the CMS media library.

The media library has no plugin-facing contract for this, and two of its properties make it the wrong destination for visitor-submitted files. Neither is a defect: both are correct for what it was built for.

**It stores everything publicly, and cannot be told otherwise.** `MediaUploader::storeFile()` hard-codes `$disk = 'public'` and writes `'visibility' => 'public'`; neither is a parameter. The `visibility` column exists, but no code path ever writes a value other than `public` — it is only read, and only to refuse a transform. Every media file therefore lands under the public disk and is readable by URL. For a logo or a product photo that is exactly right. For the CV attached to a job application, the ID scan attached to an insurance form, or the contract attached to a quote request, it is not: the randomized filename suffix makes the URL unguessable, but anyone who has the URL needs no session to read it.

**Its usage check cannot see a plugin's reference.** `MediaUsageResolver::resolve()` counts five core relations — block `media_id`, `BlockMedia` gallery and attachment roles, site favicon and social image, and page translation OG image. There is no extension point to add a sixth; `src/Support/Plugins/Contracts/` offers `PluginAdminExtension`, `PluginBlockExtension`, and `PluginPublicAssetExtension`, and nothing for media. `MediaDeleter` gates deletion on exactly that count, so a file attached to a submission reports as unused and an editor deletes it from the Media screen without warning. This one is data loss independent of privacy: it would happen just the same if the file were private.

A third, smaller mismatch: media is not site-scoped, so on a multisite install one site's submission attachments would be listed to every other site's editors.

A plugin-owned disk is not a second media library, and the distinction is what makes this the right call rather than a fragmentation of file storage. The media library is a browsable pool of site assets an editor picks from. A submission attachment is not an asset: it belongs to one submission, arrives with it, is read from it, and is deleted with it when retention expires. They are different kinds of thing and separate storage follows from that, not from convenience.

If the media library later gains injectable disk and visibility plus a plugin media-usage extension, revisiting this is cheap — the plugin's own file table already records everything needed to migrate. That is core work with value beyond this plugin, and it is not a dependency of any release below.

## Privacy And Retention

A form builder collects whatever the site owner asks for, which means it collects more sensitive data than any other plugin in the catalog and needs the corresponding care.

- Uploaded files go to a private plugin-owned disk, never the public document root, behind an extension allowlist, a size cap, and signed time-limited admin download URLs.
- Retention is per form: submissions older than N days are removed by `webblocks-forms:prune-submissions`, run from the host's scheduler. Uploaded files are removed with them. Default is unlimited retention, because silently deleting a site's data is worse than keeping it.
- Storing the submitter IP is opt-in per form, off by default, and covered by the same retention policy.
- A consent field type is a first-class type rather than a checkbox convention, so consent text is translatable and the recorded answer is unambiguous.

## Scope

**v0.1** — Form CRUD, submissions inbox with filters, CSV export, the `notify` action, spam handling, per-site scoping, admin localization, and these field types: text, textarea, email, tel, number, select, radio, checkbox group, date, hidden, consent.

*Shipped so far, as plugin `0.1.0`:* the skeleton — manifest, provider, permissions, menu, health reporter, block registration, all nine tables, form CRUD, the submissions inbox and detail screen, CSV export, per-site settings, and `webblocks-forms:prune-submissions`. Reading a submission already goes through its schema snapshot rather than the form's current fields, because that is the part that is expensive to retrofit. The public submit route exists with its throttle and CSRF, and returns 503: the field editor, the public renderer, the submit pipeline and the `notify` action are the remainder of v0.1. A stub that answered "thank you" without storing anything would be the one failure mode a form must never have, so it refuses instead.

**v0.2** — File upload with private storage and signed download, multi-step forms, the `autoresponder` action.

*Shipped as plugin `0.2.0`, in full.*

**v0.3** — The `webhook` action, retention and `webblocks-forms:prune-submissions`, per-form throttle.

*Shipped as plugin `0.3.0`, with one item moved and one deferred.* Retention and the prune command arrived early, in `0.1.0`, because a retention policy nothing runs is a promise rather than a policy; `0.2.0` extended the same command to sweep the uploads an abandoned multi-step flow leaves behind. `0.3.0` delivered the webhook action, and — not in this plan — replaced the per-action delivery status columns with a `webblocks_forms_deliveries` table, because the actions table has always permitted several rows per form and two webhooks writing into one column pair meant the second erased the first.

**The per-form throttle is deferred and not shipped.** What exists is narrower than the plan implies and worth stating precisely: the submit limiter is *keyed* per IP and form, so a visitor rate-limited on one form can still use another on the same page, but the limit *value* is install-wide in `config/webblocks-forms.php`. Making it per-form means a form lookup inside the rate limiter on every submit, which is a cost worth deciding on deliberately rather than adding to close a milestone.

**Later** — A Campaigns action. Within-step conditional visibility, once core gap 0.6 makes a progressive enhancement possible. Commerce and Appointments actions were investigated and dropped; see [Plugin-To-Plugin Actions](#plugin-to-plugin-actions).

## Out Of Scope For v1

Payment fields, e-signatures, save-and-resume, calculated fields, a public submissions API, and a visual drag-drop canvas. The last one is worth naming explicitly: an ordered list with move controls is most of the value of a drag-drop builder at a fraction of the cost, and it works without the client-side assets core cannot yet serve.

Replacing or migrating away from the native `contact_form` block is also out of scope, and this is now a settled decision rather than a recommendation. Core keeps Contact Form and Contact Messages exactly as they are. This plugin ships an importer that reads an existing Contact Form block and creates an equivalent form definition from it, leaving the operator to swap the block on the page when they choose — and leaving the messages already in the core inbox where they are.

The accepted cost is two inboxes on an install that uses both, and it is a real cost paid by the operator, not an oversight. The alternative buys one inbox by having a plugin write into a core schema it does not own, which the plugin boundary forbids for a reason this plugin would immediately demonstrate: `contact_messages` has columns for a fixed four-field message and nowhere to put a schema snapshot, a file, or a per-form action's delivery status.

## Testing

Unit tests cover validation-rule compilation and the spam scorer, which are the parts most likely to be quietly wrong. Feature tests cover the public submit path including spam handling, throttling, rejection of fields not on the stored definition, and same-site redirect enforcement; the admin CRUD screens; CSV export; and permission denial for users without the plugin permission. Lifecycle tests assert that a disabled plugin contributes no routes, no menu entries, and no block types.

Migrations are tested against MySQL, not only SQLite, before any release.

## Resolved Questions

All three questions this plan opened are now closed. Two are recorded in their own sections — two inboxes with core untouched, under out of scope, and a plugin-owned private disk for uploads, under its own heading. The third is below.

### CMS Floor: `^1.45.6`

The placeholder was `^1.52.18`, chosen because it was the current release and not because anything needed it. That is not a harmless default: a floor set to whatever is current refuses installation on an older install the plugin would run on perfectly well, which is why the other catalog plugins sit on narrow floors of `^1.45.5`, `^1.46.0` and `^1.46.7` rather than on the version that happened to be shipping the week they were built.

The real floor was derived from the CMS history once the code existed, by finding the release that introduced each core contract the plugin actually calls. One contract sets it:

**`1.45.6` — "give a plugin's declared block types a catalog row."** That release added `PluginBlockTypeCatalogSyncer` and taught `PluginRuntimeRefresher` to run it. Before it, declaring a block type was not enough to make it placeable: block pickers read `wbcms_block_types`, `PluginBlockCatalog` could only filter that list and never add to it, so the Form block would be absent from every picker with no error to explain why. The same commit added the `category`, `sort_order` and `is_container` block metadata this plugin declares.

Everything else resolves older. `PluginDefinition::publicRoutes()` is `1.43.0`, matching what the appointments plan already recorded. The remaining contracts — `PluginDefinition` and its fluent API, `PluginMenuItem`, `PluginPermission`, `PluginSettingsDefinition` with a named route, `PluginHealthResult`, `PluginBlockTypeDefinition` with declared views, `plugin.permission:` middleware, `AdminAuthorization`, `Site::enabledLocales()`, `Site::contact_recipient_email`, and the `layouts.admin`, `page-header` and `listing-filters` views — all trace back to `cc5e08c0`, a 1740-file repository restructure released as `1.41.1`. That number is an artifact of the move rather than a real introduction date, and it does not matter: it is far below the binding constraint either way.

### A Note Found While Deriving It

`InstalledPluginDefinitionFactory` parses `handle`, `label`, `version`, `description`, `provider`, `required_cms_version`, `permissions`, `commands`, `routes`, `settings`, `menu`, `migrations`, `block_types` and `health`. It does **not** parse `config`, and nothing else does either — the same shape of gap this plan already recorded for `assets` under core gap 0.6: declared by convention, read by no one.

Nothing is broken by it. The plugin's service provider calls `mergeConfigFrom()` itself, which is what actually loads `config/webblocks-forms.php`. The risk is a future reader deleting that call on the reasonable assumption that the manifest key does the work, at which point every config value silently falls back to its inline default. The provider carries a comment saying so.

## Related Docs

- [Plugin System](plugin-system.md)
- [Appointments Plugin Plan](appointments-plugin-plan.md)
- [Contact Forms And Messages](contact-forms-and-messages.md)
- [Block Type Contracts](block-type-contracts.md)
- [Multisite](multisite.md)
