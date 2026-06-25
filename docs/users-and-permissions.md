---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/users-and-permissions
cms_title: Users And Permissions
cms_layout: docs
cms_source_id: webblocks-cms:docs/users-and-permissions.md
---

# Users And Permissions

## Install-Level Users

WebBlocks CMS uses install-level admin accounts.

These users are for managing the CMS itself. They are not public membership accounts, and they are not included in Export / Import site packages.

Because users affect the whole install, the `Users` screen lives under the admin `System` navigation.

Each signed-in CMS admin user manages their own account details from `/webadmin/profile`. The Profile page is separate from Users management and only exposes the current user's name, email, current-password-confirmed password change form, and Save Changes actions.

Password fields on Profile and Users screens use the shipped WebBlocks UI Password Toggle pattern (`data-wb-password-toggle` with `data-wb-target`) from the pinned WebBlocks UI runtime. CMS core does not add custom password-toggle CSS or JavaScript.

## Roles

### `super_admin`

`super_admin` has full install-level access.

This role can access:

- Users
- sites and locales
- settings
- updates
- backups
- export/import
- all site content across the install

Only `super_admin` users can open `/webadmin/users` and manage other CMS accounts.

Users management remains install-level administration. It can manage roles, assigned sites, active or inactive state, delete protection, and admin-set password resets without requiring the target user's current password. Those system-management fields are not available from the Profile page.

### `site_admin`

`site_admin` is site-scoped.

This role can:

- access `/webadmin`
- manage content for assigned sites
- publish pages for assigned sites
- move pages between workflow states for assigned sites

This role cannot access install-level system areas such as Users, settings, updates, backups, or export/import.

### `editor`

`editor` is also site-scoped.

This role can:

- access `/webadmin`
- work with content for assigned sites
- edit pages while they are in `draft`
- submit pages for review
- move an `in_review` page back to `draft`

This role cannot publish or archive pages, and cannot access install-level system areas.

## Assigned-Site Access

- `super_admin` always has access to all sites
- `site_admin` and `editor` must have at least one assigned site
- site-scoped admin areas are filtered and enforced server-side by assigned site access

This site boundary applies across major content areas such as pages, navigation, media, visitor reports, and contact messages.

## Audit References

Pages, page revisions, Shared Slots, and Shared Slot revisions can store nullable references back to CMS users for audit attribution.

- These references are system-managed and are not editable page fields.
- Content records stay intact if a referenced user is deleted.
- Audit foreign keys use null-on-delete behavior, so old admin UI can fall back to `Not recorded` for deleted or unknown actors.

## What Each Role Can And Cannot Do

### `super_admin`

Can:

- manage users and roles
- access all sites
- use install-level system tools
- publish and archive pages

Cannot:

- delete, deactivate, or demote the last active `super_admin`

### `site_admin`

Can:

- manage assigned-site content
- publish pages for assigned sites
- move pages back to draft
- archive pages for assigned sites

Cannot:

- access Users
- access install-level system screens
- manage content outside assigned sites

### `editor`

Can:

- create and edit draft content for assigned sites
- submit pages for review
- move an `in_review` page back to draft

Cannot:

- publish pages
- archive pages
- edit non-draft page content directly
- access install-level system screens

## Why Users Is A System-Level Feature

Users are not page content and they are not site-by-site content records.

They define who can operate the install, which system areas are available, and which sites a person can work on. That makes `Users` part of the install-level administration model, so it belongs under `System` alongside the other system-wide tools.
