# Public live demo (VPS + Docker + Caddy)

Runs the WebBlocks CMS demo image behind [Caddy](https://caddyserver.com), which
terminates TLS (automatic Let's Encrypt) and reverse-proxies to the CMS. Only
Caddy is exposed to the internet; the CMS container is reachable only on the
internal Docker network.

> This uses `php artisan serve` and an in-container SQLite database — it is a
> **disposable demo**, not a production deployment. State is not persisted, so
> recreating the container resets the demo to a clean seeded site.

## Prerequisites

- A VPS with Docker + Docker Compose.
- Ports **80** and **443** open to the internet.
- A DNS **A record** (and optionally **AAAA**) for your demo domain pointing at
  the VPS IP, e.g. `demo.example.com → 203.0.113.10`. TLS issuance fails until
  DNS resolves to this host.

## Deploy

```bash
git clone https://github.com/fklavyenet/webblocks-cms.git
cd webblocks-cms/deploy
cp .env.example .env
# edit .env: set DEMO_DOMAIN and a DEMO_ADMIN_PASSWORD
docker compose up -d --build
```

Caddy obtains a certificate on first request. Then:

- Public site: `https://<DEMO_DOMAIN>/`
- Admin: `https://<DEMO_DOMAIN>/webadmin` (sign in with the demo admin)

Follow logs with `docker compose logs -f`.

## Reset the demo (recommended, e.g. nightly)

Because there is no persistent volume, recreating the CMS container re-runs
migrations and reseeds a clean showcase site:

```bash
cd webblocks-cms/deploy && docker compose up -d --force-recreate webblocks
```

A cron entry that resets every night at 04:00:

```cron
0 4 * * * cd /path/to/webblocks-cms/deploy && docker compose up -d --force-recreate webblocks >/dev/null 2>&1
```

## Notes

- The demo admin credentials are public by design — anyone visiting can sign in
  and edit content. Keep this instance disposable and reset it regularly.
- `TRUSTED_PROXIES="*"` is set so the CMS detects HTTPS from Caddy. It is safe
  here because the CMS port is never published to the host (only Caddy is).
- To update to a newer CMS release, `git pull` and re-run
  `docker compose up -d --build`.
