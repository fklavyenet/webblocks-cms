# Update Server Architecture

## Overview

WebBlocks CMS now uses a provider-style update system with two layers inside the same Laravel codebase:

- Update Server layer: public read-only JSON endpoints at `/api/updates/...`
- CMS Client layer: admin/system update check flow that consumes a configured external update server

The same repository can be deployed as the public update service at `https://updates.webblocksui.com` or as a CMS instance that checks that service.

## Config

Configuration lives in `config/webblocks-updates.php`.

Top-level keys:

- `enabled`
- `api_version`
- `products`
- `channels`

Server keys:

- `server.enabled`
- `server.service_name`
- `server.base_url`
- `server.default_channel`

Client keys:

- `client.enabled`
- `client.server_url`
- `client.channel`
- `client.product`
- `client.current_version`
- `client.site_url`
- `client.instance_id`
- `client.timeout_seconds`
- `client.connect_timeout_seconds`
- `client.retry_times`
- `client.retry_sleep_milliseconds`

Environment variables:

- `APP_VERSION`
- `WEBBLOCKS_UPDATES_ENABLED`
- `WEBBLOCKS_UPDATE_SERVER_ENABLED`
- `WEBBLOCKS_UPDATE_SERVER_NAME`
- `WEBBLOCKS_UPDATE_SERVER_BASE_URL`
- `WEBBLOCKS_UPDATE_SERVER_DEFAULT_CHANNEL`
- `WEBBLOCKS_UPDATE_CLIENT_ENABLED`
- `WEBBLOCKS_UPDATE_CLIENT_SERVER_URL`
- `WEBBLOCKS_UPDATE_CLIENT_CHANNEL`
- `WEBBLOCKS_UPDATE_CLIENT_PRODUCT`
- `WEBBLOCKS_UPDATE_CLIENT_CURRENT_VERSION`
- `WEBBLOCKS_UPDATE_CLIENT_SITE_URL`
- `WEBBLOCKS_UPDATE_CLIENT_INSTANCE_ID`
- `WEBBLOCKS_UPDATE_CLIENT_TIMEOUT_SECONDS`
- `WEBBLOCKS_UPDATE_CLIENT_CONNECT_TIMEOUT_SECONDS`
- `WEBBLOCKS_UPDATE_CLIENT_RETRY_TIMES`
- `WEBBLOCKS_UPDATE_CLIENT_RETRY_SLEEP_MS`

## Canonical Version Source

- `config('app.version')` remains the canonical product version source.
- CMS runtime installed version is read from `system_settings.key = system.installed_version` when present.
- Client config can fall back to `WEBBLOCKS_UPDATE_CLIENT_CURRENT_VERSION` or `APP_VERSION`.

## Release Model

Database table: `system_releases`

Fields:

- `id`
- `product`
- `channel`
- `version`
- `version_normalized`
- `release_name`
- `description`
- `changelog`
- `download_url`
- `checksum_sha256`
- `is_critical`
- `is_security`
- `published_at`
- `supported_from_version`
- `supported_until_version`
- `min_php_version`
- `min_laravel_version`
- `metadata`
- timestamps

Indexes:

- unique: `(product, channel, version)`
- index: `(product, channel, published_at)`
- index: `(product, channel, version_normalized)`

Published selection rules:

- only rows with `published_at` set
- only rows where `published_at <= now()`
- latest release means highest semantic version in the requested product/channel

## API Endpoints

Base prefix: `/api/updates`

### `GET /api/updates`

Returns service information, supported API versions, product catalog summary, and endpoint templates.

### `GET /api/updates/{product}/latest`

Query parameters:

- `channel`
- `installed_version`
- `php_version`
- `laravel_version`

Returns the latest published release for the selected product/channel, plus update availability and compatibility evaluation.

### `GET /api/updates/{product}/releases/{version}`

Returns a single published release document.

### `GET /api/updates/{product}/releases`

Query parameters:

- `channel`
- `limit`

Returns capped/paginated release history.

## JSON Contract

Success envelope:

```json
{
  "api_version": "1",
  "status": "ok",
  "data": {},
  "meta": {
    "generated_at": "2026-04-19T12:00:00Z"
  }
}
```

Error envelope:

```json
{
  "api_version": "1",
  "status": "error",
  "error": {
    "code": "release_not_found",
    "message": "No published release was found for the requested product and channel."
  },
  "meta": {
    "generated_at": "2026-04-19T12:00:00Z"
  }
}
```

Latest release response includes:

- `data.product`
- `data.channel`
- `data.installed_version`
- `data.latest_version`
- `data.update_available`
- `data.compatibility.status`
- `data.compatibility.reasons`
- `data.release.version`
- `data.release.name`
- `data.release.description`
- `data.release.changelog`
- `data.release.download_url`
- `data.release.checksum_sha256`
- `data.release.published_at`
- `data.release.is_critical`
- `data.release.is_security`
- `data.release.requirements.min_php_version`
- `data.release.requirements.min_laravel_version`
- `data.release.requirements.supported_from_version`
- `data.release.requirements.supported_until_version`

## Validation

Update API requests validate:

- product slug
- semantic version strings
- allowed channels
- release history limit bounds

Validation errors use the same update API error envelope with code `validation_failed`.

## CMS Client Flow

Client service: `App\Support\System\Updates\UpdateServerClient`

Flow:

1. Read configured update server URL, product, channel, installed version, and environment.
2. Request `GET {server}/api/updates/{product}/latest`.
3. Send `installed_version`, `php_version`, and `laravel_version` as query parameters.
4. Parse and validate the response envelope and required keys.
5. Normalize result into `UpdateCheckResult`.
6. Return an explicit state to the admin system page.

Handled states:

- `update_available`
- `up_to_date`
- `incompatible`
- `no_releases`
- `server_unreachable`
- `invalid_response`
- `unsupported_api_version`
- `server_error`
- `invalid_configuration`
- `client_disabled`

## Admin UI

Route: `/admin/system/updates`

The page now shows:

- current update state
- installed/latest version summary
- server and API version info
- connectivity and persistence diagnostics
- compatibility state and reasons
- release metadata and requirements
- manual package download link when present
- client environment summary

V1 copy is explicit: update checking only, no fake automation.

## Release Publishing

Create or update a release:

```bash
php artisan system-release:publish 0.2.0 https://updates.webblocksui.com/downloads/webblocks-cms-0.2.0.zip \
  --product=webblocks-cms \
  --channel=stable \
  --name="WebBlocks CMS 0.2.0" \
  --description="Stability and admin improvements." \
  --changelog="Compact changelog text here." \
  --checksum=abcdef123456 \
  --published-at="2026-04-19T10:00:00Z" \
  --min-php-version=8.3.0 \
  --min-laravel-version=13.0.0 \
  --supported-from-version=0.1.0
```

List releases:

```bash
php artisan system-release:list
```

## Tests

Added coverage for:

- update server API endpoints
- semantic version comparison and compatibility logic
- CMS client success and failure handling
- admin system updates page rendering
- release publish/list artisan commands

## V1 Limitations

- no public auth or licensing
- no signatures or package verification flow beyond stored checksum metadata
- no write API endpoints
- no automatic package download/install
- no migration plan execution support beyond metadata fields

## Future Extensions

The design keeps room for:

- stable/beta/nightly channel policy expansion
- signed packages and checksum enforcement
- license or token auth
- richer maintenance/migration notes
- staged rollout or product catalog growth
