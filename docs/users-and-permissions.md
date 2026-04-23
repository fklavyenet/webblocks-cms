# Users And Permissions

## Install-Level Users

WebBlocks CMS uses install-level admin accounts.

These users are for managing the CMS itself. They are not public membership accounts, and they are not included in Export / Import site packages.

Because users affect the whole install, the `Users` screen lives under the admin `System` navigation.

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

Only `super_admin` users can open `/admin/users` and manage other CMS accounts.

### `site_admin`

`site_admin` is site-scoped.

This role can:

- access `/admin`
- manage content for assigned sites
- publish pages for assigned sites
- move pages between workflow states for assigned sites

This role cannot access install-level system areas such as Users, settings, updates, backups, or export/import.

### `editor`

`editor` is also site-scoped.

This role can:

- access `/admin`
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
