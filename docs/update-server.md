# Update Client Architecture

## Overview

WebBlocks CMS uses a provider-style update client that consumes the external update service at `https://updates.webblocksui.com`.

This repository is the CMS client only. The update server and publisher responsibilities live in the separate central WebBlocks updates infrastructure.

## Config

Configuration lives in `config/webblocks-updates.php`.

Top-level keys:

- `enabled`
- `api_version`
- `products`
- `channels`

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
- `WEBBLOCKS_UPDATE_CLIENT_ENABLED`
- `WEBBLOCKS_UPDATES_CLIENT_SERVER_URL`
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

### `GET /api/updates/{product}/latest`

Query parameters:

- `channel`
- `installed_version`
- `php_version`
- `laravel_version`

Returns the latest published release for the selected product/channel, plus update availability and compatibility evaluation.

## CMS Client Flow

Client service: `App\Support\System\Updates\UpdateServerClient`

Flow:

1. Read configured update server URL, product, channel, installed version, and environment.
2. Request `GET {server}/api/updates/{product}/latest`.
3. Send `installed_version`, `php_version`, and `laravel_version` as query parameters.
4. Parse and validate the response envelope and required keys.
5. Normalize result into `UpdateCheckResult`.
6. Return an explicit state to the admin system page.

Canonical CMS client update server URL env key:

- `WEBBLOCKS_UPDATES_CLIENT_SERVER_URL`

Example:

```env
WEBBLOCKS_UPDATES_CLIENT_SERVER_URL=https://updates.webblocksui.com
```

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

## Publisher Boundary

WebBlocks CMS is only the consumer/client product.

It can:

- display the installed version
- query the central Updates Server for the latest release
- show update availability and compatibility
- link to a downloadable package URL returned by the server

It does not:

- publish releases
- build release packages
- upload packages
- manage update server release records

Those responsibilities belong to the separate WebBlocks Publisher / `updates.webblocksui.com` stack.

## Tests

Client-side coverage includes:

- update client success and failure handling
- admin system updates page rendering

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
