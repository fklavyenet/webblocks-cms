# Changelog

This file is a recent rolling changelog for WebBlocks CMS and keeps only the latest release notes. Older release notes are archived under docs/releases/.

## Archived releases

- [1.32.x archive](docs/releases/changelog-1.32.md)
- [1.31 and earlier archive](docs/releases/changelog-1.31-and-earlier.md)

## Unreleased

## 1.32.125

- Ensure package installs and System Updates create Laravel's `password_reset_tokens` table without running host application migrations.
- Fix existing installs where CMS test email succeeds but `/webadmin/forgot-password` cannot create a password reset token because the host starter migration stayed pending.

## 1.32.124

- Fix CMS-owned password reset mail so host/root password reset notification callbacks cannot override the `/webadmin/reset-password/{token}` reset link or mail rendering.
- Keep CMS forgot-password responses account-safe for missing or inactive users while avoiding reset tokens and notifications for inactive accounts.
- Add password-reset-specific sanitized logging for reset route, URL host/path, user activity state, notifiable class, mailer context, and exception details without tokens, SMTP secrets, or raw recipient emails.

## 1.32.123

- Add a secret-safe `Send Test Email` action to `System -> Settings -> Mail` diagnostics for testing the active CMS mail configuration.
- Send CMS test emails through the same CMS mail resolver path used by CMS-owned password reset mail, without writing to `.env` or changing host/root auth or contact form mail.
- Keep test-send failures controlled for admins while logging sanitized mail context without SMTP secrets, reset tokens, or raw credentials.

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
