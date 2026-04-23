# Revisions

## Overview

Page revisions are page-scoped editorial safety snapshots.

They are designed for recovering the content and structure of one page without treating that task as a full backup or a site transfer.

## What Gets Captured

Revision snapshots include:

- page core fields
- page translations
- page slots
- page block tree
- block translation rows
- asset ID references

## Automatic Capture Points

Revisions are created automatically when these areas change:

- page fields and default translation
- workflow status
- page translation records
- slot structure
- block creation, updates, deletion, and ordering

## Restore Behavior

Restore works in place on the current page.

When a revision is restored:

1. a fresh pre-restore safety revision is created first
2. the selected revision snapshot is applied to the current page
3. a new post-restore revision entry is recorded and linked to the source revision

This keeps both the previous live state and the restored state in revision history.

## Access Rules

- `super_admin`: can view and restore revisions
- `site_admin`: can view and restore revisions within assigned sites
- `editor`: can view revisions within assigned sites, but cannot restore

## Revisions Vs Other Recovery Tools

### Revisions

- page-scoped editorial recovery
- focused on one page's content and structure

### Backup / Restore

- environment-level recovery
- restores the current database and uploads from a backup archive

### Export / Import

- site portability between installs
- used to move one site's content as a package

These tools are intentionally separate and should not be treated as replacements for one another.
