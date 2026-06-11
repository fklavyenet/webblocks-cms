# Changelog

This file is a recent rolling changelog for WebBlocks CMS and keeps only the latest release notes. Older release notes are archived under docs/releases/.

## Archived releases

- [1.32.x archive](docs/releases/changelog-1.32.md)
- [1.31 and earlier archive](docs/releases/changelog-1.31-and-earlier.md)

## Unreleased

- Add the Page Converter roadmap to the project documentation for future implementation planning.

## 1.32.140

- Align Export / Import row action cells with the standard compact WebBlocks UI admin table action pattern.

## 1.32.139

- Fix MySQL/MariaDB backup dumps so option-file-sensitive database passwords remain intact when the pre-update backup runs `mysqldump`.

## 1.32.138

- Move the Site Transfer import review form above package counts and show package counts in a compact admin table so validated packages can be acted on sooner.

## 1.32.137

- Remove old Publisher/update server, product, and channel environment overrides from CMS update checks and maintainer publishing.
- Make package-owned `ReleaseDefaults` the only source for the release server, product key, channel, and update/publish API paths.
- Keep `WEBBLOCKS_PUBLISHER_TOKEN` as the only normal publish environment secret.

## 1.32.136

- Move CMS update and publisher identity defaults into package product code so installed sites no longer need normal update server, product, or channel environment keys.
- Keep legacy update and publisher identity overrides available for the transition release, while maintainer publishing normally only requires `WEBBLOCKS_PUBLISHER_TOKEN`.

## 1.32.135

- Add a transition verification release after moving CMS update publishing and installed update consumption to `publisher.webblocksui.com`.
- No functional runtime changes beyond release/version metadata.

## 1.32.134

- Move installed CMS update checks to `publisher.webblocksui.com` so publishing and update consumption use the same canonical Publisher service.
- Keep maintainers publishing to `https://publisher.webblocksui.com/api/updates/publish` while installed sites read latest metadata from `https://publisher.webblocksui.com/api/updates/latest`.

## 1.32.133

- Standardize CMS release publishing on `publisher.webblocksui.com` as the canonical Publisher endpoint.
- Keep the canonical Publisher environment key set as the only supported publish configuration during the transition.

## 1.32.132

- Keep the base admin layout JavaScript minimal by loading only pinned WebBlocks UI and shared CMS admin core globally, with picker, builder, rich-text, gallery, media-copy, page-assets, and password-toggle behavior loaded from page-scoped static admin assets.

## 1.32.131

- Document and guard the final CMS brand standard for inline SVG auth/sidebar marks, token-controlled colors, required brand files, and obsolete asset removal.
- Remove obsolete unused CMS brand image files after auth and sidebar moved to the reusable inline SVG product mark.
- Standardize CMS auth and admin sidebar brand marks on a reusable inline SVG component that inherits mode/accent-aware colors, remove obsolete pilot brand image variants, and keep favicons on the accepted non-squircle CMS behavior.

## 1.32.128

- Make CMS auth logos app-mode aware by switching normal and dark brand marks with `html[data-mode]` CSS instead of `picture` media-only markup.
- Render the auth accent-panel mark through a CSS mask so it inherits the active accent contrast color without changing the existing logo assets.

## 1.32.127

- Fix the Profile page cards to use standard WebBlocks UI card header, body, and footer structure.
- Keep Profile password visibility toggles on the existing WebBlocks UI Password Toggle pattern without adding custom CSS or JavaScript.

## 1.32.126

- Add a dedicated CMS Profile page for current-user account details and password changes.
- Keep Users management as install-level user administration for roles, site assignments, active state, and admin password resets.
- Add password visibility toggles to Profile and Users password fields using the existing WebBlocks UI Password Toggle pattern.

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
