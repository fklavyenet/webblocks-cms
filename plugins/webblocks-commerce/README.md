# WebBlocks Commerce

First-party simple commerce plugin for WebBlocks CMS.

Current artifact version: `0.8.2`.

Version `0.8.2` adds locale-aware currency rendering, a default-currency setting, product currency
selection, gateway compatibility validation, and gateway-correct decimal precision. Euro, dollar,
yen, and every other selectable currency use their locale-appropriate symbol, separators, and
fraction digits. It requires WebBlocks CMS `1.37.3` or newer.

The plugin adds no Composer package. Currency formatting uses PHP's `intl` extension
(`NumberFormatter`), which must be enabled on the web and CLI runtimes. Plugin health reports a
warning when it is missing.

Store-owner setup guides:

- [Connect SumUp to WebBlocks Commerce](../../docs/webblocks-commerce-sumup-quickstart.md)
- [SumUp mit WebBlocks Commerce verbinden](../../docs/webblocks-commerce-sumup-quickstart.de.md)
- [SumUp'ı WebBlocks Commerce'a bağlama](../../docs/webblocks-commerce-sumup-quickstart.tr.md)

The quick start covers sandbox account creation, the exact Dashboard paths for Merchant ID and API
keys, protected server configuration, a current test card, the native Commerce block, the full
cart-to-order test, live-mode transition, and troubleshooting. The
[operator guide](../../docs/webblocks-commerce-operator-guide.md) remains the technical reference. The
build script includes these canonical repository documents in the distributable plugin ZIP.

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

Visitors use the session-backed public cart without an API token:

- `GET /commerce/cart` — review cart lines, VAT, and total
- `POST /commerce/cart/items/{product}` — add a product from a Commerce block or buy page
- `PATCH|DELETE /commerce/cart/items/{product}` — change quantity or remove a line
- `POST /commerce/cart/checkout` — create the order and redirect to the configured hosted gateway

The public cart, buy page, and checkout status pages extend the CMS public layout, so the active
site header and footer slots remain consistent with the rest of the site. The Commerce Buy Button
is a native plugin block and posts to the cart; it does not require a Trusted HTML block.

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

## Currency selection and formatting

`Commerce Settings` contains a default-currency selector. New products inherit that currency,
and the product form lists only currencies supported by the active gateway. The product API
applies the same validation. A gateway change is rejected while any non-archived product uses an
unsupported currency, and checkout rechecks compatibility before reserving inventory.

Amounts remain stored as integer minor units. Rendering is locale-aware: for example `125000 EUR`
is shown as `1.250,00 €` in German and `€1,250.00` in English, while `1250 JPY` has no decimal
fraction. PayPal and SumUp payloads use the same per-currency precision instead of assuming two
decimal places.

Provider support follows the official
[PayPal currency list](https://developer.paypal.com/api/rest/reference/currency-codes/) and
[SumUp Checkout currency enum](https://developer.sumup.com/api/checkouts/create). A provider may
still reject a listed currency when the merchant account or country does not enable it.

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
8. Open `/webadmin/plugins/webblocks-commerce/settings`, select PayPal or SumUp, choose a compatible default currency, enter the provider credentials, and save. Values are encrypted at rest and never rendered back into the page.
9. For PayPal, enter the client ID, client secret, and webhook ID; the webhook endpoint is `/commerce/webhooks/paypal`.
10. For SumUp Hosted Checkout, enter the API key and merchant code; SumUp receives `/commerce/webhooks/sumup` as the checkout `return_url`.
11. Hosting-managed `WEBBLOCKS_COMMERCE_*` environment values remain supported as optional overrides. An overridden field is read-only in the admin form and its raw value is never displayed.
12. Add the `Commerce Buy Button` block to a page and select an active product. The block adds the product to the public cart; the generated public buy URL remains available for product-detail links and direct buy-now checkout.
13. Disable before uninstalling. Uninstall removes the uploaded package and enabled state, but preserves `webblocks_commerce_*` tables.

SumUp uses `https://api.sumup.com/v0.1/checkouts` with `hosted_checkout.enabled=true`. Its webhook is
a notification, not trusted payment proof: the handler retrieves the checkout from SumUp and
matches checkout ID, merchant code, order reference, currency, amount, terminal status, and the
successful transaction before changing order or payment state. Repeated status notifications are
idempotent. Failed or expired checkouts release reserved inventory through the order state machine.

The current implementation provides the plugin package foundation, manifest, settings metadata, schema readiness health checks, database models, product admin screens, read-only order admin screens, write-only encrypted payment settings and secret-safe diagnostics, public product and cart pages, a plugin-owned add-to-cart block, a no-network fake checkout foundation, PayPal hosted checkout and signed webhook capture, plus SumUp Hosted Checkout with API-verified status webhooks.
