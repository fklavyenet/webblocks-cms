---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/appointments-plugin-plan
cms_title: Appointments Plugin Plan
cms_layout: docs
cms_source_id: webblocks-cms:docs/appointments-plugin-plan.md
---

# Appointments Plugin Plan

This document records the agreed design for booking/appointment support in WebBlocks CMS, and what has been built against it. Sections marked as shipped describe real behavior; the rest is still a plan and subject to revision. Where the plan turned out to be wrong, the correction is recorded rather than the original quietly replaced — the reasoning is usually the useful part.

## Decision

Appointments ship as a plugin with the handle `webblocks-appointments`, not as a CMS core feature.

The motivating problem is real: most small businesses running a WebBlocks site have no way to take a booking on their own domain, so they link out to a third-party scheduling service and lose the visitor. Closing that gap is worth doing. But scheduling is a business domain — services, staff, working hours, capacity, cancellation, reminders — and [the plugin system boundary](plugin-system.md) is explicit that domain capabilities do not belong in core. A documentation site or a corporate brochure site must not inherit an appointments menu, six database tables, and a reminder command.

The plugin is developed as its own repository under the workspace's `plugins-catalog/`, alongside the redirect manager, and is installed into a CMS through the normal ZIP upload flow. It is not bundled into CMS core, even as a pilot: `PluginRegistry` only registers ZIP-installed plugins, and bundling would repeat the coupling that `webblocks-ui-manager` was deliberately moved out of core to escape. An independent repository is also what catalog distribution needs, since the catalog serves per-plugin versions and checksums.

## Why Contact Form Is The Template, Not The Precedent

The native `contact_form` block is the closest existing surface and supplies most of the shape: a public block renders a real form, the submission posts to a throttled CSRF-protected route, an accepted record is stored before notification is attempted, and an admin review screen shows status and safe delivery detail. [Contact forms and messages](contact-forms-and-messages.md) describes that model, and the appointments plugin should follow it closely — including the rule that public visitors see generic success or validation feedback and never spam scoring or delivery internals.

Contact Form living in core is not an argument for appointments living in core. A contact form is a single stateless message with no domain behind it. An appointment has availability, conflicts, a lifecycle, and a clock.

## Phase 0 — Core Extension Points

Six gaps were identified as blocking the plugin — five up front, one found while building phase 3. Five turned out to be real core work; 0.5 did not, and the correction is kept below rather than the entry quietly deleted. Every real one is needed by any future plugin that wants a public surface, so this phase is not overhead specific to appointments.

**0.1 Plugin blocks cannot declare the views they render through.** *(Shipped in 1.43.0.)* The original reading of this gap was too strong. Plugin block views did already resolve: installed plugin view paths are appended to the CMS view namespace, and `PluginBlockCatalog` normalizes the handle `appointments::form` to the catalog slug `appointments-form`, so `Block::publicRenderView()` found `webblocks-cms::pages.partials.blocks.appointments-form` by convention. What did not work is that the `admin_view` and `public_view` a plugin declares were parsed and then ignored, leaving the plugin to mirror a core directory layout it does not own and guess the filename. Core now consults the plugin block registry first in both `publicRenderView()` and `adminFormView()`, honors a declared view that resolves, and falls back to the convention otherwise.

**0.2 Plugins cannot declare public routes.** *(Shipped in 1.43.0.)* `PluginDefinition` accepted `adminRoutes()` and `apiRoutes()` only. A booking form needs a visitor-facing slot query and a submit endpoint, so `publicRoutes()` was added, mounted under the reserved `/plugins/{handle}` prefix with `web`, `install.required`, and a mandatory group throttle applied by the registrar rather than left to the plugin. [The plugin system document](plugin-system.md) describes the shipped contract.

**0.3 Timezone is system-wide.** *(Shipped in 1.43.1.)* `SystemSettings::TIMEZONE` held one value for the whole install. In a multisite install each business runs on its own clock, and a booking system that cannot say which clock it means is wrong by construction. Sites now carry a nullable `timezone` column with an Edit Site field; `Site::resolvedTimezone()` returns it or falls back to the system value. The raw attribute stays null when unset, so "follow the install" remains distinguishable from an explicit choice that happens to match.

**0.4 Plugin blocks cannot own translatable fields.** *(Shipped in 1.58.0.)* `BlockTranslationRegistry` was a fixed `match` over core slugs, so a plugin block had no translation family and no translated field map. The MVP works around this: visitor-facing copy comes from the plugin's own translation namespace, which is already supported — `InstalledPluginDefinitionFactory` registers `resources/lang` under the plugin handle — with per-block overrides held in block settings. Real per-block translation for plugin blocks arrived in CMS 1.58.0: a plugin block type declares `translatedFields([...])` and core stores each field per locale in rows. The MVP workaround stays valid — plugin-shipped strings still belong in the plugin's own translation namespace — but an operator can now give one placed block a second language, which the workaround never could.

**0.5 There is no queue or scheduler contract.** *(Resolved without core work.)* Core contains no queued jobs, and reminder delivery needs work that runs on a clock. This was recorded as blocking reminders, which turned out to be too strong: core already registers plugin-declared commands through `PluginCommandRegistrar` and enforces the `{handle}:` prefix, so the plugin ships `webblocks-appointments:dispatch-reminders` and the host's cron runs it. What core genuinely lacks is a *scheduler* — something to run such a command automatically — which is a deployment step an operator supplies, not a code gap. Adopting queues remains a core decision and is still out of scope.

**0.6 Plugin static assets are declarable but not servable.** *(Shipped in 1.57.0.)* Found while building phase 3. `PluginPublicAsset` emitted a `<script>` or `<link>` tag for an enabled plugin, and the manifest documented an `assets` key, but nothing parsed that key and nothing copied a plugin's files into the document root — so the emitted tag pointed at a 404. That is why the booking form is entirely server-rendered. `PluginAssetPublisher` now copies a plugin's `resources/public` into `public/cms/plugins/{handle}` through the same runtime refresh that writes block catalog rows, so the progressive enhancement this phase was waiting for became possible. The plugin took it up in `0.9.0`, and the server-rendered flow stays underneath it as the baseline it always should have been.

Phases 0.1 and 0.2 shipped in `1.43.0`, and 0.3 in `1.43.1`. Phase 0.5 turned out to need no core work. The remaining two were expected to be quality-of-life for future plugins rather than blockers, and to land in later `1.43.x` releases; both took considerably longer and arrived elsewhere — asset publishing in `1.57.0`, plugin block translations in `1.58.0`.

The declared CMS floor has moved with what the plugin actually uses rather than with what was current: `^1.43` at first, then `^1.46.7`, and `^1.57.0` from plugin `0.9.0`, which is the first release to ship a static asset and therefore the first that needs the release which publishes one.

## Plugin Identity And Conventions

The plugin follows the package convention rules in [the plugin system document](plugin-system.md) without exception.

The handle carries the `webblocks-` prefix the other catalog plugins already use, so the earlier bare `appointments` naming in this plan is superseded.

```text
handle              webblocks-appointments
settings namespace  webblocks_appointments
database prefix     webblocks_appointments_
admin routes        /webadmin/plugins/webblocks-appointments
public routes       /plugins/webblocks-appointments
route names         webblocks.plugins.webblocks_appointments.*
permissions         webblocks-appointments.view, .manage, .settings
commands            webblocks-appointments:dispatch-reminders
block handles       webblocks-appointments::form
```

All six phases shipped, as plugin `0.1.0` through `0.6.0`: the skeleton, the domain and slot engine, the public booking block, the operator screens, notifications, and reminders with visitor cancellation. Each phase's section below records what it actually built, including where it departed from this plan.

## Domain Model

Seven tables, all under the `webblocks_appointments_` prefix and all site-scoped. The settings table was not in the original plan; it arrived in phase 4 when per-site booking rules replaced global config.

| Table | Purpose |
| --- | --- |
| `webblocks_appointments_services` | Bookable service: name, slug, duration, buffer before/after, active flag, sort order |
| `webblocks_appointments_resources` | Staff member or room — whatever can only handle one appointment at a time. Required: availability and conflict detection are meaningless without something to be busy, so a site needs at least one |
| `webblocks_appointments_service_resource` | Which resources can deliver which services |
| `webblocks_appointments_availability_rules` | Recurring weekly opening hours per resource |
| `webblocks_appointments_availability_exceptions` | Dated overrides: closures, holidays, one-off hours. A null resource means the whole site |
| `webblocks_appointments_appointments` | The booking: service, resource, `starts_at`/`ends_at` in UTC, status, customer name/email/phone, note, source, cancel token |
| `webblocks_appointments_settings` | Per-site booking rules: slot interval, lead time, horizon, confirmation mode, notification recipient |

Status is `pending`, `confirmed`, `cancelled`, `completed`, or `no_show`. Source is `public` or `admin`, so an operator-entered booking is distinguishable from a visitor one.

Times are stored in UTC and converted to the site timezone for display and for every availability decision. Availability rules and exceptions are stored as local wall-clock times, because "we open at 09:00" survives a daylight-saving transition and a stored UTC offset does not.

## Slot Engine

*(Shipped in plugin `0.2.0`.)*

The slot engine is the core of the product and the part most likely to be quietly wrong. It is a pure service that takes a service, a resource, a date, and a timezone, and returns the bookable slots. It reads weekly rules, applies dated exceptions, subtracts existing appointments plus their buffers, and enforces minimum lead time and maximum booking horizon.

It is deterministic and testable without touching the database: no Eloquent, no container, and `now` is passed in rather than read. Its tests carry the weight of the plugin — daylight-saving transitions in both directions, buffers on both sides of an appointment, exceptions that shorten a day rather than closing it, and horizon boundaries.

Slots are walked in local wall-clock time rather than UTC, so they land on the marks a visitor expects instead of drifting off them for the rest of a transition day. Two consequences follow and both are deliberate: wall-clock times inside a spring-forward gap do not exist and are not offered, and times in a fall-back repeated hour resolve to the earlier instant. PHP's own default for the ambiguous case is the later instant, so the earlier one is an explicit choice rather than inherited behaviour.

Buffers belong to the occupying service, not to the booking being attempted: each existing appointment blocks time according to its own service's buffers, and the candidate according to its own.

## Concurrency

*(Shipped in plugin `0.2.0`.)*

Two visitors submitting the same slot must not both succeed. Three things close the gap between checking and writing, each covering what the one before it cannot: a transaction with a locking overlap read, the unique index, and translation of the resulting integrity violation into an unavailable-slot response rather than a 500.

The locking read is the real guard, and the only one that understands buffers and differing durations — an overlap is not the same thing as an equal start instant. It scans by the longest service on the site plus the widest buffer pair, because the scan is by start time and a long neighbouring booking can begin well before the candidate and still run into it.

The unique index is on `(resource_id, slot_lock)`, not `(resource_id, starts_at)`. `slot_lock` mirrors `starts_at` only while the appointment occupies its slot and is NULL once it does not, which is what lets a cancelled appointment's time be rebooked; a unique index on `starts_at` itself would reject that. Both MySQL and SQLite allow many NULLs in a unique index, so this works on the production and test engines alike.

The concurrency behaviour is covered by integration tests in the plugin repository, which run the real booker against the shipped migrations. The SQLite suite proves the overlap logic, the unique-index backstop, the exception translation, and cancel-then-rebook; the genuinely parallel race runs against MySQL behind an environment variable, because `lockForUpdate` compiles to nothing on SQLite. Two worker processes hold at a shared barrier so their transactions contend, and one round races on overlapping-but-different start instants — the only round that isolates the lock, since the unique index alone already covers an identical instant. Mutation-verified: removing the lock makes that round fail.

Writing those tests found a schema bug that SQLite had hidden. MySQL enforces a 64-character limit on identifier names; the registry-reserved table prefix plus Laravel's generated index names exceeded it, so plugin setup would have failed on every MySQL install. Any plugin using the reserved prefix should name its indexes explicitly and test its migrations against MySQL rather than only SQLite.

## Public Surface

*(Shipped in plugin `0.3.0`.)*

The `webblocks-appointments::form` block renders the booking flow: choose a service, choose a date, choose from the free slots, enter name, email, and phone, submit. Two public routes back it — a throttled JSON slot query and a throttled CSRF-protected submit.

Anti-spam reuses the Contact Form model rather than inventing a second one: the renderer generates the signed check field, filled check fields and implausibly fast submissions receive the same generic success response as a real booking without being stored, and no scoring detail reaches the visitor.

The plan assumed vanilla JS shipped as a plugin-owned public asset. That was not available: `PluginPublicAsset` emitted a script tag and the manifest documented an `assets` key, but nothing parsed that key and nothing published a plugin's static files into the document root, so the tag would have pointed at a 404. This is the same shape of gap as the declared block views that 0.1 fixed — declarable but not wired. The form therefore renders entirely on the server in a stepped GET then POST, which needs no new machinery, makes the chosen day a shareable URL, and degrades perfectly.

*(Resolved in plugin `0.9.0`, once CMS `1.57.0` closed 0.6.)* The enhancement landed exactly as this paragraph predicted it could: the step-one reload is gone and the server contract is untouched. It calls the same JSON slot endpoint phase 3 already shipped, posts nothing, and every booking still goes through the same POST that re-derives the submitted instant against the slot engine.

Three things about it are worth recording, because each is a way the enhancement could have quietly taken something away.

The GET form and its submit button stay on the page, and the script hides that button only *after* its listeners are attached. Hiding first would mean a script that throws in between leaves a visitor unable to see any times at all — the enhancement having removed the feature it exists to improve.

The chosen day stays a shareable, reloadable URL: the script keeps the query string in step rather than dropping a property the stepped design was built to have.

And a day with no free times hands back to a full reload rather than updating in place. The booking form is only rendered when there is something to book, so an empty day changes the page rather than the list; updating in place would leave contact fields sitting under a heading naming the previous day.

One additive server change was needed and is worth naming as an exception to "the contract is untouched": `/slots` now also returns the formatted date label. The label is localised and rendered in the site's timezone, and a browser formatting its own would drift from the page on locale or timezone — a drift visible only to visitors outside the server's locale, which is the hardest place to notice it.

Two protections are not visible in the markup and are easy to lose in a rewrite. Submitted instants are re-derived against the slot engine rather than trusted, because the booker enforces conflicts but not the rules that decide what should have been offered at all — without the recheck a crafted post can book 03:00 on a closed Sunday, since nothing about that collides with an existing appointment. And `source_url` is honoured only as a same-site path, so the form cannot be turned into an open redirect.

## Admin Surface

*(Shipped in plugin `0.4.0`.)*

The plugin contributes four menu entries: a day view of bookings with status transitions and manual entry, plus CRUD for services, for staff and rooms, and for opening hours with their dated exceptions. Settings cover slot interval, automatic versus manual confirmation, minimum lead time, booking horizon, and notification recipient.

Settings are stored per site rather than in config. The plan did not say this and the first implementation read global config, which is wrong the moment an install has two sites: lead time and confirmation mode are decisions a business makes, not an install.

Screens are site-scoped the way the CMS pages admin is — query parameter, then session, then primary site — with one deliberate difference: there is no "all sites" option, because a service belongs to one business with one set of opening hours and a combined list would be one nobody can act on. Route-level plugin permissions say what an operator may do and nothing about which sites they may do it to, so every site-scoped write calls `AdminAuthorization::abortUnlessSiteAccess` separately.

Two behaviours are worth recording because they look like omissions. A service or resource that already has appointments is deactivated rather than deleted, since the appointments foreign key restricts deletion precisely so history cannot vanish silently. And manual entry deliberately skips the availability recheck the public form performs: it still goes through the booker, so it cannot double-book, but an operator booking outside opening hours is making a decision rather than evading a rule.

The plugin's settings definition must name its own route. Left to the default, the CMS registers a read-only `/settings` scaffold under the same name and URI, and because it is registered before the plugin's route file it wins the match — any plugin shipping an editable settings screen has to name the route or its screen is unreachable.

## Notifications

*(Shipped in plugin `0.5.0`.)*

Storage and notification stay separate concerns, exactly as Contact Form treats them: the booking is committed before notification is attempted, and a delivery failure never retracts it. Notification status reports as sent, failed, skipped, or not configured, with sanitized failure detail — no credentials, tokens, or stack traces.

Both the visitor and the business receive mail. The visitor's confirmation carries an `.ics` attachment, which lands the appointment in the visitor's own calendar with no external service, no OAuth, and no dependency.

Two details the plan did not anticipate, both worth carrying to any other plugin that sends mail. The two sides are recorded separately rather than under one status: a mistyped business recipient must not suppress the visitor's confirmation, and a combined status would make the admin screen lie in exactly the case an operator needs it honest. And the iCalendar file is written by hand rather than taken from a package — the format is small, and the parts that actually break, escaping and folding at 75 octets without splitting a multi-byte character, are the parts a wrapper would hide.

The send itself is not covered by an automated test in the plugin repository: it needs a mailer, a view factory and a CMS host. What is covered is the part that decides whether to send and what to record.

## Reminders And Visitor-Initiated Changes

`appointments:dispatch-reminders` sends a reminder ahead of the appointment and is driven by the host's scheduler. Cancellation and rescheduling use signed URLs carrying the appointment's cancel token, so a visitor manages their own booking without the CMS growing a public account system.

## Out Of Scope For v1

Payments, two-way Google or Outlook calendar sync, staff logins, SMS, and recurring appointments are all excluded. Each one adds an external dependency and a permanent support burden, and none is needed for the problem this plugin exists to solve. Group capacity — more than one booking per slot — is the first candidate for the next release, and rescheduling stays out because cancel-then-rebook already produces it through correct paths.

## Testing

Unit tests cover the slot engine, and they are the thickest part of the suite. Feature tests cover the public submit path including spam handling, throttling, and concurrent booking of the same slot; the admin CRUD screens; and permission denial for users without the plugin permission. Lifecycle tests assert that a disabled plugin contributes no routes, no menu entries, and no block types.

## Delivery Order

Phase 0.1 and 0.2 landed together in `1.43.0`, because they are the two halves of "a plugin can own a public surface" and neither is independently useful. Phase 0.3 followed in `1.43.1`. Plugin phases 1 through 6 shipped as `0.1.0` through `0.6.0`, completing the planned scope; `0.9.0` then added the progressive enhancement phase 3 had deferred, and moved the CMS floor to `^1.57.0` because it is the first release of this plugin to ship a static asset. Installs below that stay on `0.8.0`, which is complete.

Both remaining core phases have now shipped. 0.6 landed in CMS `1.57.0` and this plugin took it up in `0.9.0`. 0.4 landed in CMS `1.58.0`, so the booking block's copy can be translated per placement rather than only per plugin release — **this plugin has not adopted it yet**, and the workaround recorded under 0.4 above is still what runs: block copy comes from block settings, falling back to the plugin's own translations.

What 0.9.0 demonstrates is worth more than the feature. Nothing in the server-rendered flow had to change to accommodate it, because the flow was designed to work without a script rather than to wait for one. Had phase 3 assumed the script it wanted, closing 0.6 would have been the moment the booking form became usable; instead it was the moment an enhancement could be added to something that already worked everywhere.
