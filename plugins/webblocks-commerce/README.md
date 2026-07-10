# WebBlocks Commerce

First-party simple commerce plugin for WebBlocks CMS.

Current artifact version: `0.7.2`.

## Order state & inventory

Order status is only ever changed through `Support\Orders\OrderStateMachine`, never a
raw update. It enforces the allowed transition graph (`pending → paid|failed|cancelled|expired`,
`paid → refunded`), is idempotent for re-delivered webhooks, and locks the order row so
racing gateway callbacks cannot double-apply a transition.

Tracked stock (`inventory_quantity` not null) is reserved atomically when checkout starts,
which prevents overselling under concurrent buyers, and released back to the catalog when an
order is cancelled, expires, fails, or is refunded. Products with a null `inventory_quantity`
are untracked (unlimited) and never decremented.

Abandoned `pending` orders hold their reservation until they are expired. Run
`php artisan webblocks-commerce:expire-stale-orders --minutes=30` on a schedule to release the
stock held by checkouts the buyer never completed. Wire it into the host app's console kernel,
for example `$schedule->command('webblocks-commerce:expire-stale-orders')->everyFifteenMinutes();`.

## Cart & AI-first API

Carts are server-side, persistent, and single-currency. A cart stores only product
references + quantities; prices and VAT are resolved live from the current catalog and only
frozen onto the order at checkout (`StartCheckout::forCart`), which builds one multi-line order,
reserves stock atomically for every line, and marks the cart `converted`. Adding the same
product merges quantities; adding a different currency, or more than tracked stock, is rejected.

Everything the cart does is available over the **plugin-owned internal API** — mounted into the
CMS internal API group (`/webadmin/api`, bearer-token auth) via the plugin's `apiRoutes()` hook,
so AI agents get the same capabilities the admin panel gives humans. Endpoints (capability in
parentheses):

- `POST /webadmin/api/commerce/cart` — create a cart (`commerce.cart.write`)
- `GET /webadmin/api/commerce/cart/{token}` — read a cart with live totals (`commerce.cart.read`)
- `POST /webadmin/api/commerce/cart/{token}/items` — add `{product_id, quantity}` (`commerce.cart.write`)
- `PATCH /webadmin/api/commerce/cart/{token}/items/{product}` — set `{quantity}` (0 removes) (`commerce.cart.write`)
- `DELETE /webadmin/api/commerce/cart/{token}/items/{product}` — remove a line (`commerce.cart.write`)
- `DELETE /webadmin/api/commerce/cart/{token}/items` — clear the cart (`commerce.cart.write`)
- `POST /webadmin/api/commerce/cart/{token}/checkout` — start hosted checkout, returns `redirect_url` (`commerce.cart.write`)

Products and orders are exposed the same way (these endpoints are owned by the plugin, not the
CMS core, and are only present when the plugin is enabled):

- `GET|POST /webadmin/api/commerce/products`, `PATCH /webadmin/api/commerce/products/{id}` — catalog incl. `tax_class` (`commerce.read` / `commerce.products.write`)
- `GET /webadmin/api/commerce/orders`, `GET /webadmin/api/commerce/orders/{id}` — read-only, with the full net/tax/gross breakdown (`commerce.orders.read`)

All of these self-advertise: while the plugin is enabled they appear in the CMS API discovery
(`GET /webadmin/api` `_links`, `GET /webadmin/api/openapi.json` paths, and the discovery guidance)
via the plugin's `apiDiscovery()` contribution, and disappear when the plugin is disabled.

There is no separate commerce token: the plugin uses the **shared CMS API token**. Its
`commerce.*` capabilities are contributed to the CMS's grantable set via `apiCapabilities()`
(they appear as a "Commerce" group in the token admin UI while the plugin is enabled), so a
single least-privilege token can be scoped to just the commerce capabilities.

## Multilingual product content (i18n)

Storefront product content shares the CMS Site+Locale system rather than a parallel one. The
base product row holds the default/fallback `title`/`description`; a per-locale translation row
(`webblocks_commerce_product_translations`, keyed by product + CMS locale) overrides them. This
is the admin-panel *content* language axis — distinct from the admin-panel *UI* language, which
stays in Laravel `resources/lang` files.

`ProductLocalizer` resolves the shown title/description for a locale, falling back to the base.
Carts carry a `locale`, so cart summaries and — critically — the **order line title snapshot at
checkout** use the localized text the buyer actually saw. The public buy page localizes via a
`?locale=<code>` query, falling back to the base.

Edit translations in the admin product form (per enabled non-default locale) or over the API
(capability in parentheses):

- `GET /webadmin/api/commerce/products/{product}/translations` — list base + translations (`commerce.read`)
- `PUT /webadmin/api/commerce/products/{product}/translations/{locale}` — upsert `{title?, description?}` (`commerce.products.write`)
- `DELETE /webadmin/api/commerce/products/{product}/translations/{locale}` — remove a locale (`commerce.products.write`)

## VAT / tax

VAT is country-agnostic and rate-driven. Rates are integer basis points
(`1900` = 19.00%) keyed by ISO country then product tax class, so a new jurisdiction is a
config edit — no code change. Each product carries a `tax_class` (`standard`, `reduced`,
`zero`); the applied rate, tax amount, net subtotal, gross total, and tax country are
**snapshotted onto the order at checkout**, so a later rate change never rewrites past orders.

Configure via env (the config file mirrors these defaults; built-in rates cover DE, AT, FR, NL,
IT, ES out of the box):

- `WEBBLOCKS_COMMERCE_TAX_ENABLED` (default `true`)
- `WEBBLOCKS_COMMERCE_PRICES_INCLUDE_TAX` (default `true`) — when true, catalog prices are gross
  (VAT-inclusive, the EU B2C norm) and tax is broken out of the shown price so the amount charged
  equals the catalog price. When false, prices are net and VAT is added on top at checkout.
- `WEBBLOCKS_COMMERCE_TAX_COUNTRY` (default `DE`) — the jurisdiction whose VAT applies. Selling
  B2C below the EU OSS distance-selling threshold you charge your own country's VAT, so this is
  normally your store's country. Add or override rates in `config/webblocks-commerce.php`.

## Manual Lifecycle

1. Build the ZIP artifact with `php plugins/webblocks-commerce/build-plugin.php`.
2. Upload it through `System -> Plugins`.
3. Review the plugin detail screen.
4. Enable the plugin.
5. If health reports `Setup required` or `Plugin migrations pending`, run `Run Plugin Migrations` from the plugin detail screen.
6. Manage products from `/webadmin/plugins/webblocks-commerce/products`.
7. Review orders from `/webadmin/plugins/webblocks-commerce/orders`.
8. Review checkout readiness from `/webadmin/plugins/webblocks-commerce/settings`.
9. Configure PayPal sandbox or live credentials with `WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_ID`, `WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_SECRET`, and `WEBBLOCKS_COMMERCE_PAYPAL_WEBHOOK_ID`.
10. Add the `Commerce Buy Button` block to a page and select an active product, or review the generated public buy URL on product detail screens for manual links. The no-network `fake` gateway is available for local foundation tests, while the default `paypal` gateway starts hosted PayPal checkout when configured.
11. Configure the PayPal webhook endpoint as `/commerce/webhooks/paypal`.
12. Disable before uninstalling. Uninstall removes the uploaded package and enabled state, but preserves `webblocks_commerce_*` tables.

The current implementation provides the plugin package foundation, manifest, settings metadata, schema readiness health checks, database models, product admin screens, read-only order admin screens, secret-safe settings diagnostics, public buy pages, a plugin-owned Commerce Buy Button block, a no-network fake checkout foundation that creates pending orders, PayPal hosted checkout, and PayPal webhook capture handling for approved orders.
