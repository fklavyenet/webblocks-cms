# Changelog

This file is a recent rolling changelog for WebBlocks CMS and keeps only the latest release notes. Older release notes are archived under docs/releases/.

## Archived releases

- [1.32.x archive](docs/releases/changelog-1.32.md)
- [1.31 and earlier archive](docs/releases/changelog-1.31-and-earlier.md)

## Unreleased

## 1.32.122

- Handle CMS password reset mail send failures as controlled CMS mail errors instead of raw 500 responses.
- Tighten CMS custom mail diagnostics/readiness and normalize custom SMTP encryption and port values before sending.
- Keep Mail Diagnostics in a compact read-only table while continuing to hide secrets.

## 1.32.121

- Change Mail Diagnostics in `System -> Settings` from a grid panel to a compact read-only table for cleaner scanning.
- Keep mail diagnostic secrets hidden by continuing to show sensitive fields only as configured or not configured.

## 1.32.120

- Refine Mail Diagnostics in `System -> Settings` into a compact read-only key/value grid so mail status is easier to scan.
- Keep mail diagnostic secrets hidden by continuing to show sensitive fields only as configured or not configured.

## 1.32.119

- Refine `System -> Settings` into separate focused cards with section-specific Save Changes actions for General, Project Identity, Mail, and Privacy.
- Keep Runtime Information as a read-only card with no save action.
- Hide CMS custom mail fields while environment mail mode is active, leaving diagnostics visible and secret-safe in both modes.

## 1.32.118

- Add CMS Mail settings to `System -> Settings` so CMS-owned password reset mail can use database-backed custom mail settings without writing to `.env`.
- Reorganize System Settings into General, Project Identity, Mail, Privacy, and Runtime Information sections with secret-safe mail diagnostics.
- Keep CMS custom mail scoped to CMS-owned notifications while host/root app mail continues to use Laravel environment mail configuration.

## 1.32.117

- Separate Auth test coverage so CMS-owned `/webadmin` auth behavior is tested apart from host/root auth compatibility routes.
- Fix package-owned CMS login so inactive users cannot authenticate through `/webadmin/login`.
- Keep broader Auth filtering green by removing stale root password reset, register, verification, and confirmation screen assumptions from CMS package auth tests.

## 1.32.116

- Fix CMS-owned auth screens so password reset links and forms use `/webadmin` auth routes instead of stale root Laravel auth URLs.
- Hide the Register link from the package-owned CMS login screen when no CMS-owned registration route is enabled.

## 1.32.115

- Remove the local root `layouts.admin` compatibility wrapper so plugin and package admin views must use `webblocks-cms::layouts.admin`, matching package-consumer installs.
- Pin CMS WebBlocks UI consumption and the default icon manifest source to the newest published `v2.7.12` release.
- Clean the CMS product brand folder down to the canonical logo, mark, favicon, touch icon, and app icon set, with matching root and package assets.
- Regenerate CMS product PNG brand assets so the actual logo mark is visible instead of flat-color output.

## 1.32.114

- Update CMS WebBlocks UI consumption to `v2.7.11` and adopt the refreshed auth brand helper classes on the package-owned login shell.
- Add CMS product brand variants for normal, dark, on-accent/inverse, and high-contrast favicon usage under `/cms/brand`, keeping product shell branding separate from site-level public favicons.

## 1.32.113

- Simplify System Updates into the two-card `Install Update` and `Update Details` flow, moving release notes, update readiness, and last-run details into accordions.
- Replace the main-screen Update History table and row deletion with automatic retained-run pruning, safe last-run detail modals, CLI run inspection/pruning commands, and a downloadable support report.
