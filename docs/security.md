---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/security
cms_title: Security
cms_layout: docs
cms_source_id: webblocks-cms:docs/security.md
---

# Security

This page describes the security model of WebBlocks CMS and the steps an operator
or agency should take to run it safely for client sites. For how to report a
vulnerability, see [SECURITY.md](../SECURITY.md) — please do not open public
issues for security reports.

## Deployment hardening

These are the most important controls for a production install:

- **Serve only `public/` as the web root.** The application root contains
  `.env`, `.git`, `.github`, `storage/`, `vendor/`, and source code that must
  never be web-reachable. Point your Nginx/Apache document root at
  `public/` and confirm that `https://your-site/.git/config` and
  `https://your-site/.env` both return **404**. An exposed `.git` directory leaks
  your entire source and any committed secrets.
- **Prefer a release artifact over a raw git clone in production.** Release
  packages exclude `.git`, `.github`, `project/`, and other non-runtime files
  (see `.gitattributes` `export-ignore` rules). If you do deploy a clone, disable
  push on the install (`git remote set-url --push origin DISABLED`).
- **Set `APP_DEBUG=false` and a strong `APP_KEY`** in production. Debug mode
  leaks stack traces, environment values, and internal paths.
- **Use HTTPS** and set `SESSION_SECURE_COOKIE=true` when serving over TLS.
- **File permissions:** the web/PHP user needs read/write on `storage/` and the
  `backups` disk root (default `storage/app/backups`). Do **not** use `777`;
  grant ownership or group access instead.
- **Keep secrets in `.env`.** `.env`, `.env.*` (except `.env.example`), and
  `auth.json` are git-ignored. CMS API tokens and mail passwords are never
  written to the repository.

## Authentication and authorization

- CMS auth is Laravel-native (no Breeze/Jetstream/Fortify requirement). Admins
  sign in at `/webadmin/login`; passwords are bcrypt-hashed.
- Three install roles gate access: `super_admin` (install-wide), `site_admin`
  (assigned sites), and `editor` (draft content within assigned sites). Cross-site
  actions (move/duplicate, Shared Slots) enforce access to both source and target
  sites. See [Users & Permissions](users-and-permissions.md).
- The `/webadmin` admin tree is protected by the CMS web + auth + admin-access
  middleware stack. Public routes never render draft content; draft/preview
  access requires an authenticated admin or a trusted token with `content.read`.
- **Sign-in and password-reset requests are rate-limited.** Failed logins are
  throttled per email+IP (default 5 attempts, then a short lockout that a
  successful sign-in clears; tune with `WEBBLOCKS_CMS_MAX_LOGIN_ATTEMPTS` and
  `WEBBLOCKS_CMS_LOGIN_DECAY_SECONDS`). A per-IP backstop additionally caps the
  login, forgot-password, and reset-password endpoints against floods and
  email-rotation attempts.

## Internal Content API tokens

The Internal Content API (`/webadmin/api`) is for trusted operator/AI tooling.

- Tokens are created by a `super_admin` from `System → API Tokens`, **stored as
  hashes**, and shown in plain text only once.
- Each token carries explicit **capabilities** (read, publish, media, plugin
  lifecycle, commerce, …). Advanced/destructive capabilities are separate opt-ins;
  normal page-building capabilities are the default.
- Tokens can be **revoked** (keeping the audit row) or deleted. A per-token
  activity log records time, method/path, route, capability result, IP, and a
  user-agent summary — but never request bodies, query strings, responses, or
  token values.
- Treat API tokens as secrets. Scope them to the minimum capabilities a tool
  needs, and rotate them if exposed.
- Both the canonical `/webadmin/api` routes and the legacy `/admin-api`
  compatibility routes share the Internal Content API rate limiter.

See [Internal Content API](internal-content-api.md).

## Update security

In-app System Updates replace the CMS package code at
`vendor/fklavyenet/webblocks-cms` on the live site. Because an update executes new
code, the integrity of the downloaded package is a **remote-code-execution
boundary**. WebBlocks CMS mitigates this as follows:

- **Mandatory SHA-256 checksum.** The updater downloads the release ZIP, then
  verifies `hash_file('sha256', …)` against the `checksum_sha256` provided by the
  release metadata using a timing-safe `hash_equals`. If the checksum is missing
  **or** does not match, the update is **refused** — it does not fall back to
  applying an unverified package.
- **Canonical update service.** Update metadata and downloads come from
  `publisher.webblocksui.com`. Because the checksum is delivered alongside the
  download by the same service, the checksum protects against corrupted or
  tampered *artifacts*, but not against a fully compromised update service.
- **Installs are consumers, not publishers.** Installed sites fetch updates but
  must not push to the upstream repository. Publishing is done only from the
  maintenance checkout with a `WEBBLOCKS_PUBLISHER_TOKEN`.

### Signature verification (Ed25519)

For defense-in-depth against a compromised update service — where the checksum
travels alongside the artifact — releases can be **cryptographically signed**.
The publisher signs the release checksum with an Ed25519 secret key, and installs
verify the signature against a pinned public key (`sodium_crypto_sign`), so an
install rejects any release not signed by the real key.

To enable it:

1. Generate a key pair once, on the maintenance/publisher machine:
   `php artisan webblocks:updates:keygen`.
2. Keep the printed `WEBBLOCKS_PUBLISHER_SIGNING_KEY` (secret) private — set it
   only where you publish releases. Never commit it or set it on an install.
3. Pin the printed public key so installs verify signed releases: set
   `WEBBLOCKS_UPDATE_PUBLIC_KEY`, or set `ReleaseDefaults::UPDATE_PUBLIC_KEY` so
   the key ships in the CMS code (recommended — a code-pinned key cannot be
   swapped through a compromised `.env`).
4. Publish as usual; the publisher signs each release automatically.

Rollout is safe: while no public key is pinned, signature verification is not
enforced (checksum verification still applies). Once a public key is pinned and
installs receive that code, every future release must carry a valid Ed25519
signature over its checksum, or the update is refused.

## Content and input safety

- **Contact forms** render a real CSRF-protected form with a CMS-owned hidden
  honeypot field; filled honeypots are quietly discarded. Submissions are stored
  first, spam is quarantined for review, and email delivery is resolved
  separately. See [Contact Forms & Messages](contact-forms-and-messages.md).
- **Comments** default to `pending` and quarantine link/contact-pattern spam for
  moderation before public display.
- **Trusted HTML** is limited to wrapper-adjacent layout markup and must not be
  used to inject scripts; prefer the native block contracts, which the Internal
  Content API validates draft-first.
- **Media uploads are restricted to an allowlist of image, video, and document
  types** (content-sniffed, not extension-trusted). **SVG uploads are disabled
  by default**, because an SVG can carry inline script and media is served from
  the same origin as the admin. Enable it only on installs where every account
  that can upload media is trusted, via `WEBBLOCKS_CMS_ALLOW_SVG_UPLOADS=true`.
  The same allowlist governs server-side remote media fetches.
- **Remote media fetching validates every redirect target and pins the HTTP
  connection to the public IP address that passed validation.** This closes the
  DNS lookup/connection race used by DNS-rebinding attacks. Remote fetching
  fails closed when PHP cURL address pinning is unavailable.
- **Managed Embedded Application iframes are opaque-origin sandboxes.** Their
  entry responses apply restrictive CSP and referrer headers, and the iframe
  does not receive same-origin access to CMS cookies, storage, the parent page,
  or authenticated panel requests.

## Telemetry and privacy

- Update checks may send privacy-preserving adoption telemetry to the Publisher:
  only `product_key`, `installed_version`, `channel`, a random locally persisted
  `installation_id`, and `telemetry_schema_version`. No domains, URLs, admin
  emails, paths, database details, user counts, tokens, or arbitrary env/config
  values are sent. Set `WEBBLOCKS_TELEMETRY=false` to opt out; metadata checks
  continue without an installation ID.
- Visitor Reports store normalized referrer host, traffic type, UTM values,
  device category, and a bot flag only — not raw referrer URLs, full query
  strings, full user-agents, or raw IP addresses. See [Operations](operations.md).

## Reporting a vulnerability

Report privately via GitHub Security Advisories or the maintainer contact in
[SECURITY.md](../SECURITY.md). We aim to acknowledge reports within 5 business
days and will coordinate a disclosure timeline with you.
