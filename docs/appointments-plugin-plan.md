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

This document records the agreed design for booking/appointment support in WebBlocks CMS. It is a plan, not a description of shipped runtime behavior: nothing described below exists yet, and every section is subject to revision as the phases land. When a phase ships, its behavior moves into the normal product documentation and this file keeps only the forward-looking remainder.

## Decision

Appointments ship as a plugin with the handle `appointments`, not as a CMS core feature.

The motivating problem is real: most small businesses running a WebBlocks site have no way to take a booking on their own domain, so they link out to a third-party scheduling service and lose the visitor. Closing that gap is worth doing. But scheduling is a business domain — services, staff, working hours, capacity, cancellation, reminders — and [the plugin system boundary](plugin-system.md) is explicit that domain capabilities do not belong in core. A documentation site or a corporate brochure site must not inherit an appointments menu, six database tables, and a reminder command.

The plugin starts as a first-party in-package pilot registered by the CMS package provider, the same path `webblocks-ui-manager` took, and splits into its own Composer package once the contract settles.

## Why Contact Form Is The Template, Not The Precedent

The native `contact_form` block is the closest existing surface and supplies most of the shape: a public block renders a real form, the submission posts to a throttled CSRF-protected route, an accepted record is stored before notification is attempted, and an admin review screen shows status and safe delivery detail. [Contact forms and messages](contact-forms-and-messages.md) describes that model, and the appointments plugin should follow it closely — including the rule that public visitors see generic success or validation feedback and never spam scoring or delivery internals.

Contact Form living in core is not an argument for appointments living in core. A contact form is a single stateless message with no domain behind it. An appointment has availability, conflicts, a lifecycle, and a clock.

## Phase 0 — Core Extension Points

Five gaps in CMS core block the plugin. All five are core work, all five are testable without the plugin existing, and every one of them is needed by any future plugin that wants a public surface — so this phase is not overhead specific to appointments.

**0.1 Plugin blocks cannot declare the views they render through.** *(Shipped in 1.43.0.)* The original reading of this gap was too strong. Plugin block views did already resolve: installed plugin view paths are appended to the CMS view namespace, and `PluginBlockCatalog` normalizes the handle `appointments::form` to the catalog slug `appointments-form`, so `Block::publicRenderView()` found `webblocks-cms::pages.partials.blocks.appointments-form` by convention. What did not work is that the `admin_view` and `public_view` a plugin declares were parsed and then ignored, leaving the plugin to mirror a core directory layout it does not own and guess the filename. Core now consults the plugin block registry first in both `publicRenderView()` and `adminFormView()`, honors a declared view that resolves, and falls back to the convention otherwise.

**0.2 Plugins cannot declare public routes.** *(Shipped in 1.43.0.)* `PluginDefinition` accepted `adminRoutes()` and `apiRoutes()` only. A booking form needs a visitor-facing slot query and a submit endpoint, so `publicRoutes()` was added, mounted under the reserved `/plugins/{handle}` prefix with `web`, `install.required`, and a mandatory group throttle applied by the registrar rather than left to the plugin. [The plugin system document](plugin-system.md) describes the shipped contract.

**0.3 Timezone is system-wide.** *(Shipped in 1.43.1.)* `SystemSettings::TIMEZONE` held one value for the whole install. In a multisite install each business runs on its own clock, and a booking system that cannot say which clock it means is wrong by construction. Sites now carry a nullable `timezone` column with an Edit Site field; `Site::resolvedTimezone()` returns it or falls back to the system value. The raw attribute stays null when unset, so "follow the install" remains distinguishable from an explicit choice that happens to match.

**0.4 Plugin blocks cannot own translatable fields.** `BlockTranslationRegistry` is a fixed `match` over core slugs, so a plugin block has no translation family and no translated field map. The MVP works around this: visitor-facing copy comes from the plugin's own translation namespace, which is already supported — `InstalledPluginDefinitionFactory` registers `resources/lang` under the plugin handle — with per-block overrides held in block settings. Real per-block translation for plugin blocks is a later core change, not an MVP dependency.

**0.5 There is no queue or scheduler contract.** Core contains no queued jobs. Reminder delivery, expiry of unconfirmed holds, and no-show marking all need work that runs on a clock. The plugin ships an Artisan command driven by the host's cron rather than introducing a queue dependency; adopting queues is a core decision and is out of scope here.

Phases 0.1 and 0.2 shipped in `1.43.0`, and 0.3 in `1.43.1`. The remaining two — plugin block translations and the scheduler contract — target later `1.43.x` releases. The plugin declares `requiresCms('^1.43')`.

## Plugin Identity And Conventions

The plugin follows the package convention rules in [the plugin system document](plugin-system.md) without exception.

```text
handle              appointments
settings namespace  appointments
database prefix     appointments_
admin routes        /webadmin/plugins/appointments
route names         webblocks.plugins.appointments.*
permissions         appointments.view, appointments.manage, appointments.settings
commands            appointments:dispatch-reminders, appointments:close-past
block handles       appointments::form
```

## Domain Model

Six tables, all under the `appointments_` prefix and all site-scoped.

| Table | Purpose |
| --- | --- |
| `appointments_services` | Bookable service: name, slug, duration, buffer before/after, active flag, sort order |
| `appointments_resources` | Staff member or room. Optional — an install with no resources books against a single implicit resource |
| `appointments_service_resource` | Which resources can deliver which services |
| `appointments_availability_rules` | Recurring weekly opening hours per resource |
| `appointments_availability_exceptions` | Dated overrides: closures, holidays, one-off hours. A null resource means the whole site |
| `appointments_appointments` | The booking: service, resource, `starts_at`/`ends_at` in UTC, status, customer name/email/phone, note, source, cancel token |

Status is `pending`, `confirmed`, `cancelled`, `completed`, or `no_show`. Source is `public` or `admin`, so an operator-entered booking is distinguishable from a visitor one.

Times are stored in UTC and converted to the site timezone for display and for every availability decision. Availability rules and exceptions are stored as local wall-clock times, because "we open at 09:00" survives a daylight-saving transition and a stored UTC offset does not.

## Slot Engine

The slot engine is the core of the product and the part most likely to be quietly wrong. It is a pure service that takes a service, a resource, a date, and a timezone, and returns the bookable slots. It reads weekly rules, applies dated exceptions, subtracts existing appointments plus their buffers, and enforces minimum lead time and maximum booking horizon.

It must be deterministic and testable without touching the database. Its unit tests carry the weight of the plugin: daylight-saving transitions in both directions, buffers on both sides of an appointment, exceptions that shorten a day rather than closing it, lead time crossing midnight, and horizon boundaries.

## Concurrency

Two visitors submitting the same slot must not both succeed.

A unique constraint on `(resource_id, starts_at)` catches exact collisions and is the backstop. It is not sufficient on its own: a booking that overlaps another because of buffers has a different `starts_at` and passes the constraint. The write path therefore runs inside a transaction that takes a locking overlap query on the resource's appointments for the day before inserting, and translates both the lock result and a unique-constraint violation into the same "slot no longer available" response.

The behavior is verified against both MySQL and SQLite, because the test suite runs on SQLite in memory and production runs on MySQL 8.

## Public Surface

The `appointments::form` block renders the booking flow: choose a service, choose a date, choose from the free slots, enter name, email, and phone, submit. Two public routes back it — a throttled JSON slot query and a throttled CSRF-protected submit.

Anti-spam reuses the Contact Form model rather than inventing a second one: the renderer generates the signed check field, filled check fields and implausibly fast submissions receive the same generic success response as a real booking without being stored, and no scoring detail reaches the visitor.

The interface is vanilla JS over WebBlocks UI components, shipped as a plugin-owned public asset. No new frontend dependency and no build step.

## Admin Surface

`System → Plugins → Appointments` opens a day and week view of bookings with status transitions, manual entry, and CRUD for services, resources, and opening hours. Settings cover automatic versus manual confirmation, minimum lead time, booking horizon, and notification recipient. The existing Contact Messages admin screen is the layout and interaction template.

## Notifications

Storage and notification stay separate concerns, exactly as Contact Form treats them: an accepted booking is stored before notification is attempted, and a delivery failure never retracts the booking. Notification status reports as sent, failed, skipped, or not configured, with sanitized failure detail — no credentials, tokens, or stack traces.

Both the visitor and the business receive mail. The visitor's confirmation carries an `.ics` attachment, which lands the appointment in the visitor's own calendar with no external service, no OAuth, and no dependency.

## Reminders And Visitor-Initiated Changes

`appointments:dispatch-reminders` sends a reminder ahead of the appointment and is driven by the host's scheduler. Cancellation and rescheduling use signed URLs carrying the appointment's cancel token, so a visitor manages their own booking without the CMS growing a public account system.

## Out Of Scope For v1

Payments, two-way Google or Outlook calendar sync, staff logins, SMS, and recurring appointments are all excluded. Each one adds an external dependency and a permanent support burden, and none is needed for the problem this plugin exists to solve. Group capacity — more than one booking per slot — is the first candidate for v0.2.

## Testing

Unit tests cover the slot engine, and they are the thickest part of the suite. Feature tests cover the public submit path including spam handling, throttling, and concurrent booking of the same slot; the admin CRUD screens; and permission denial for users without the plugin permission. Lifecycle tests assert that a disabled plugin contributes no routes, no menu entries, and no block types.

## Delivery Order

Phase 0.1 and 0.2 landed together in `1.43.0`, because they are the two halves of "a plugin can own a public surface" and neither is independently useful. Phase 0.3 followed in `1.43.1`. The plugin phases — skeleton, domain and slot engine, public surface, admin surface, notifications, reminders — build on top in that order.
