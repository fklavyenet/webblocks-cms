---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/webblocks-commerce-plugin-mvp
cms_title: WebBlocks Commerce Plugin MVP
cms_layout: docs
cms_source_id: webblocks-cms:docs/webblocks-commerce-plugin-mvp.md
---

# WebBlocks Commerce Plugin MVP

This document records the implementation plan and progress log for a simple WebBlocks Commerce plugin. The package foundation, plugin-owned schema/models, setup-required health checks, product admin screens, order admin screens, secret-safe settings diagnostics, public buy and cart bridges, plugin-owned Commerce Buy Button block, no-network fake checkout gateway, pending order creation, signed checkout status pages, PayPal hosted checkout, SumUp Hosted Checkout, provider-specific webhook verification, operator guide, and trusted CMS API automation path are now implemented. Release tags, version bumps, and live gateway sandbox verification are still pending.

## Current Progress

Implemented:

- `plugins/webblocks-commerce` package skeleton, manifest, definition, config stub, and ZIP build script.
- Plugin-owned `webblocks_commerce_*` product, order, order item, payment, and webhook tables.
- Eloquent model foundation and schema readiness health checks.
- Enabled-only product admin routes under `/webadmin/plugins/webblocks-commerce/products`.
- Product list, create, edit, show, and archive screens using WebBlocks UI admin patterns.
- Read-only order list and detail screens under `/webadmin/plugins/webblocks-commerce/orders`.
- Order detail shows line items, payment attempts, and shortened gateway references without editing order state.
- Secret-safe settings diagnostics under `/webadmin/plugins/webblocks-commerce/settings`.
- Public buy URLs at `/commerce/products/{slug}/buy`.
- No-network fake checkout gateway for tests and local foundation work.
- PayPal hosted checkout adapter using OAuth access tokens and PayPal Orders.
- PayPal webhook endpoint at `/commerce/webhooks/paypal` with signature verification and idempotent event storage.
- SumUp Hosted Checkout adapter using server-side API keys, merchant codes, hosted checkout URLs, and API-verified status notifications at `/commerce/webhooks/sumup`.
- Session-backed public cart at `/commerce/cart` with add, update, remove, and hosted checkout actions.
- Plugin-owned `Commerce Buy Button` page-builder block with an active product picker and public renderer that adds the selected product to the cart.
- Checkout start creates a pending order, order item, pending payment attempt, and signed checkout status redirect without marking the order paid.
- PayPal `CHECKOUT.ORDER.APPROVED` webhooks capture the PayPal order and mark matching WebBlocks Commerce orders paid after capture completion.
- Operator guide plus English, German, and Turkish SumUp quick starts with account onboarding,
  protected credential configuration, current sandbox card data, native Commerce block usage,
  sandbox/live verification, and troubleshooting.
- Trusted CMS API endpoints for plugin lifecycle actions, Commerce product/order access, plugin block discovery, and content validate/apply placement of `webblocks-commerce-buy-button`.
- `commerce` is reserved away from public page and redirect-manager catch-alls.
- Setup-required product route guidance before plugin migrations are run.
- Focused tests for plugin lifecycle, API install/enable/setup, route namespace, schema readiness, Commerce Buy Button discovery/rendering/API placement, product CRUD/API creation, order review, settings diagnostics, permissions, public route inertness, public cart checkout, fake checkout start, PayPal checkout, SumUp checkout creation, provider webhook verification, webhook idempotency, and paid order transitions.

Next planned step:

- Package and manually verify the current MVP on an installed CMS through browser, admin, and CMS API flows, including SumUp sandbox checkout and webhook confirmation.

## Purpose

WebBlocks Commerce should let a small creator, artist, or studio sell simple products from a WebBlocks CMS site without turning CMS core into a full ecommerce product.

The first useful scenario is:

1. An artist creates a product for an artwork or small physical item.
2. The public site shows a simple buy link or button.
3. The visitor lands on a checkout start page or hosted gateway checkout.
4. Payment completion records an order in CMS admin.
5. The operator can review products, orders, and payment status.

The MVP proves this flow with a secure hosted-payment surface. It now includes a server-side cart,
rate-driven VAT, inventory reservation, and localized product content. Coupons, shipping-rate
engines, subscriptions, customer accounts, fulfillment, and marketplace behavior remain deferred.

## Current CMS Findings

The current codebase already supports the right plugin boundaries:

- CMS core is a plugin host, not the owner of domain-specific commerce behavior.
- Plugins are manually installed from validated ZIP packages, disabled by default, and enabled explicitly.
- Active plugin admin routes run under `/webadmin/plugins/{plugin-handle}` with CMS auth, admin access, setup guard, and plugin permission middleware.
- Plugin permissions must be handle-prefixed.
- Plugin tables must use a handle-owned snake_case prefix.
- Plugin migrations are explicit setup actions and are not run automatically on upload or enable.
- Missing plugin tables should produce controlled setup-required guidance instead of raw database errors.
- Plugin settings routing exists, but the default settings screen is currently read-only foundation behavior.
- Plugin block declarations exist. Enabled plugin block catalog rows can be surfaced through the block picker, while disabled plugin block rows are filtered out so plugin-owned blocks stay inert when the owner is disabled.

These findings make a first-party installable plugin the correct shape. Commerce should not be added to CMS core.

## Recommended Package

Use the stable handle:

```text
webblocks-commerce
```

Recommended local development path:

```text
plugins/webblocks-commerce
```

Recommended install target after packaging:

```text
storage/app/webblocks/plugins/webblocks-commerce/{version}
```

The plugin should include:

```text
plugins/webblocks-commerce/
  README.md
  build-plugin.php
  webblocks-plugin.json
  config/webblocks-commerce.php
  database/migrations/
  resources/views/plugins/webblocks-commerce/
  routes/webblocks-commerce.php
  routes/public.php
  src/
```

The package must remain manually installed/enabled. It must not be bundled or auto-registered as a default CMS runtime plugin.

## MVP Scope

The MVP started as single-product hosted checkout and now supports a multi-line, single-currency
server-side cart while keeping payment collection on the provider's hosted page.

Included:

- Product admin listing, create, edit, show, archive.
- Order admin listing and show.
- Payment status recorded from gateway webhook events; redirect success pages only show pending/processing status.
- One public buy URL per product.
- One plugin-owned Commerce Buy Button block that lets editors select an active product in the page builder.
- Session-backed cart with quantity update, remove, live VAT totals, and multi-line checkout.
- Atomic tracked-stock reservation and release through the order state machine.
- Localized product titles and descriptions using the CMS Site+Locale system.
- Hosted checkout through PayPal or SumUp adapters.
- Test mode support through environment config.
- Setup-required health check for plugin-owned tables.
- Secret-safe diagnostics that show only configured/not configured states.
- Focused tests for lifecycle, schema readiness, product CRUD, checkout start, webhook idempotency, and order state transitions.

Deferred:

- Product variants.
- Coupons.
- Shipping calculation and labels.
- Refund initiation from CMS.
- Customer accounts.
- Multi-currency catalog pricing.
- Digital downloads.
- Native card collection inside CMS.
- Cart/product listing blocks beyond the single Commerce Buy Button.
- Payment credentials stored in editable CMS settings.

## Non-Goals And Safety

The plugin must not:

- Store card data.
- Ask CMS to handle PCI-sensitive card entry.
- Print, log, or render secret keys or webhook secrets.
- Add routes under `/admin`, `/cms`, or `/webadmin/api` for browser checkout pages.
- Require ordinary operators to SSH after a successful plugin setup.
- Drop plugin-owned tables during normal uninstall.
- Assume host product admin status equals CMS commerce permission.
- Add Tailwind, Vite, React, Vue, Livewire, Inertia, or a Node build chain.

Hosted checkout is the default because the payment form lives at the provider and CMS receives only identifiers, status updates, and non-card order data.

## Plugin Definition

The plugin definition should follow the current plugin registry shape:

```php
PluginDefinition::make('webblocks-commerce')
  ->label('WebBlocks Commerce')
  ->version('0.1.1')
  ->provider(WebBlocksCommercePlugin::class)
  ->description('Simple product sales and hosted checkout for WebBlocks CMS sites.')
  ->requiresCms('^1.32')
  ->settingsNamespace('webblocks_commerce')
  ->databasePrefix('webblocks_commerce_')
  ->permissions([
    PluginPermission::make('webblocks-commerce.view'),
    PluginPermission::make('webblocks-commerce.manage'),
    PluginPermission::make('webblocks-commerce.manage-products'),
    PluginPermission::make('webblocks-commerce.manage-orders'),
    PluginPermission::make('webblocks-commerce.manage-settings'),
  ])
  ->menu([
    PluginMenuItem::make('commerce-products')
      ->label('Commerce Products')
      ->route('webblocks.plugins.webblocks_commerce.products.index')
      ->icon('wb-icon-package')
      ->permission('webblocks-commerce.view')
      ->group('Content')
      ->sort(70),
    PluginMenuItem::make('commerce-orders')
      ->label('Commerce Orders')
      ->route('webblocks.plugins.webblocks_commerce.orders.index')
      ->icon('wb-icon-receipt')
      ->permission('webblocks-commerce.manage-orders')
      ->group('Content')
      ->sort(71),
  ])
  ->adminRoutes(dirname(__DIR__).'/routes/webblocks-commerce.php')
  ->migrations([
    'database/migrations',
  ])
  ->settings(
    PluginSettingsDefinition::make()
      ->label('Commerce Settings')
      ->description('Review checkout mode, currency, gateway configuration, and webhook readiness.')
  )
  ->health(WebBlocksCommerceHealth::class);
```

If a WebBlocks UI icon does not exist for a desired commerce symbol, use the closest existing icon instead of adding custom inline SVG.

## Configuration

Add plugin-owned config with environment variables:

```php
return [
  'enabled' => env('WEBBLOCKS_COMMERCE_ENABLED', false),
  'default_currency' => env('WEBBLOCKS_COMMERCE_DEFAULT_CURRENCY', 'USD'),
  'gateway' => env('WEBBLOCKS_COMMERCE_GATEWAY', 'paypal'),
  'paypal' => [
    'mode' => env('WEBBLOCKS_COMMERCE_PAYPAL_MODE', 'sandbox'),
    'client_id' => env('WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_ID'),
    'client_secret' => env('WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_SECRET'),
    'webhook_id' => env('WEBBLOCKS_COMMERCE_PAYPAL_WEBHOOK_ID'),
  ],
];
```

The plugin should display configuration diagnostics as booleans:

- Gateway selected.
- Test/live mode.
- Publishable key configured.
- Secret key configured.
- Webhook secret configured.

Secret values must never be displayed.

## Database Model

Use plugin-owned tables:

```text
webblocks_commerce_products
webblocks_commerce_orders
webblocks_commerce_order_items
webblocks_commerce_payments
webblocks_commerce_webhook_events
```

### Products

Recommended fields:

- `id`
- `site_id` nullable at first if the plugin starts install-wide, required later for multisite storefronts.
- `title`
- `slug`
- `description` nullable text
- `status`: draft, active, archived
- `price_amount`: integer minor units
- `currency`: three-letter uppercase code
- `inventory_quantity` nullable integer
- `sku` nullable
- `image_asset_id` nullable
- `metadata` nullable JSON
- timestamps

Recommended indexes:

- unique `slug`
- `status, created_at`
- `site_id, status` when site-scoped products are enabled

### Orders

Recommended fields:

- `id`
- `order_number` unique
- `site_id` nullable at first, required later for multisite storefronts.
- `customer_email` nullable until gateway/customer data returns
- `status`: pending, paid, failed, cancelled, expired, refunded
- `subtotal_amount`
- `total_amount`
- `currency`
- `gateway`
- `gateway_checkout_id` nullable unique
- `gateway_payment_id` nullable
- `gateway_customer_id` nullable
- `placed_at` nullable
- `paid_at` nullable
- `cancelled_at` nullable
- `metadata` nullable JSON
- timestamps

### Order Items

Recommended fields:

- `id`
- `order_id`
- `product_id` nullable to preserve historical orders if a product is archived.
- `title`
- `sku` nullable
- `quantity`
- `unit_amount`
- `total_amount`
- `currency`
- `metadata` nullable JSON
- timestamps

### Payments

Recommended fields:

- `id`
- `order_id`
- `gateway`
- `gateway_payment_id` nullable
- `gateway_checkout_id` nullable
- `status`: pending, succeeded, failed, cancelled, refunded
- `amount`
- `currency`
- `raw_event_id` nullable
- `metadata` nullable JSON
- timestamps

### Webhook Events

Recommended fields:

- `id`
- `gateway`
- `event_id`
- `event_type`
- `processed_at` nullable
- `payload_digest`
- `status`: received, processed, ignored, failed
- `message` nullable text
- timestamps

Use a unique index on `gateway, event_id` to make webhook processing idempotent.

## Domain Services

Keep controllers thin. Suggested services/actions:

- `CommerceConfig`
- `CommerceMoney`
- `OrderNumberGenerator`
- `ProductStatus`
- `OrderStatus`
- `PaymentStatus`
- `CreateCheckoutOrder`
- `StartCheckout`
- `CompleteCheckout`
- `HandleGatewayWebhook`
- `RecordPaymentEvent`
- `GatewayCheckoutSession`
- `PaymentGatewayInterface`
- `PayPalCheckoutGateway`
- `PayPalApiClient`
- `PayPalConfig`
- `CommerceSettingsController`

The gateway interface should hide provider details from controllers:

```php
interface PaymentGatewayInterface
{
  public function createCheckoutSession(CheckoutRequest $request): GatewayCheckoutSession;

  public function parseWebhook(WebhookRequest $request): GatewayWebhookEvent;
}
```

PayPal is the first real adapter, but the plugin should not hard-code PayPal concepts into product and order models beyond `gateway_*` reference columns.

## Admin Routes

All browser admin routes must live under the plugin prefix:

```text
/webadmin/plugins/webblocks-commerce/products
/webadmin/plugins/webblocks-commerce/products/create
/webadmin/plugins/webblocks-commerce/products/{product}
/webadmin/plugins/webblocks-commerce/products/{product}/edit
/webadmin/plugins/webblocks-commerce/orders
/webadmin/plugins/webblocks-commerce/orders/{order}
/webadmin/plugins/webblocks-commerce/settings
```

Suggested route names:

```text
webblocks.plugins.webblocks_commerce.products.index
webblocks.plugins.webblocks_commerce.products.create
webblocks.plugins.webblocks_commerce.products.store
webblocks.plugins.webblocks_commerce.products.show
webblocks.plugins.webblocks_commerce.products.edit
webblocks.plugins.webblocks_commerce.products.update
webblocks.plugins.webblocks_commerce.orders.index
webblocks.plugins.webblocks_commerce.orders.show
webblocks.plugins.webblocks_commerce.settings.edit
```

The settings route should be custom, not the default read-only plugin settings route, because commerce needs gateway diagnostics and setup guidance.

## Public Routes

The current plugin route registrar is focused on admin routes. The MVP has two implementation options:

1. Add a documented plugin public route extension point in CMS core.
2. Keep MVP public checkout routes in a tightly scoped CMS-core bridge until public plugin routes are formally supported.

The preferred path is option 1 if the implementation remains small and generic.

Suggested public plugin routes:

```text
/commerce/products/{product:slug}/buy
/commerce/checkout/{order}/success
/commerce/checkout/{order}/cancel
/commerce/webhooks/{gateway}
```

These routes should:

- Avoid `/cms`, which remains static assets only.
- Avoid `/webadmin/api`, which is for trusted internal APIs.
- Avoid `/admin`.
- Use signed or hard-to-guess order references for success/cancel pages.
- Validate webhook signatures before processing.
- Return generic public errors without exposing provider payloads.

If a public plugin route extension point is added, it should follow the same inert disabled/incompatible lifecycle as admin routes.

## Public Buy Button Strategy

MVP should not depend on full plugin block rendering until the block extension path is complete.

Phase 1 buy button options:

- Admin product detail shows a copyable public buy URL.
- Authors paste that URL into the existing Button Link block.
- Product pages can be built manually with existing CMS blocks and media.

Phase 2 buy button options:

- Add `webblocks-commerce::buy-button` as a plugin block type after plugin-owned block renderer/admin form lookup is implemented.
- Add `webblocks-commerce::product-card` and `webblocks-commerce::product-grid` later.

This keeps the first payment implementation focused and avoids widening the CMS block renderer contract before necessary.

## Checkout Flow

Recommended hosted checkout flow:

1. Visitor clicks the product buy URL.
2. `StartCheckoutController` validates product status, price, currency, and inventory availability.
3. `CreateCheckoutOrder` creates a pending order and order item in a database transaction.
4. `PaymentGatewayInterface` creates a hosted checkout session.
5. The order stores the gateway checkout ID.
6. Visitor is redirected to the provider-hosted checkout page.
7. Provider redirects to success or cancel page.
8. Webhook confirms the final payment state.
9. Admin order list shows the updated status.

The redirect success page should not mark the order paid by itself. It can show "payment processing" until webhook confirmation arrives. The webhook is the source of truth.

## Order State Rules

Suggested order transitions:

```text
pending -> paid
pending -> failed
pending -> cancelled
pending -> expired
paid -> refunded
```

Avoid arbitrary backwards transitions in the MVP. Admin status edits should be read-only at first unless a clear manual reconciliation workflow is added later.

## Inventory Rules

MVP can support nullable inventory:

- `null` means inventory is not tracked.
- `0` means unavailable.
- positive integer means limited stock.

For the first version, decrement inventory only after payment succeeds. This can oversell under heavy concurrency, but it is acceptable for simple artist/studio usage if documented. A later version can add inventory reservations on checkout start.

## Admin UI

Use WebBlocks UI patterns already used by CMS admin screens:

- `webblocks-cms::layouts.admin`
- `webblocks-cms::admin.partials.page-header`
- `webblocks-cms::admin.partials.flash`
- `section.wb-card` or `.wb-card`
- `.wb-card-body`
- `.wb-table-wrap`
- `.wb-table`
- explicit `Actions` header
- `td.wb-table-actions`
- `.wb-action-group`
- `wb-modal` for destructive confirmations
- `wb-alert` for setup, validation, and configuration issues
- `wb-toast` only when the existing admin runtime supports it

Avoid large inline Blade scripts. If named admin JavaScript becomes necessary, place it under `public/cms/js/admin/` only if CMS core owns it. Plugin-owned JS should be packaged as a plugin public/admin asset only after the asset path contract is explicit.

## Security And Compliance

Required protections:

- Use hosted checkout; never collect card data in CMS.
- Validate webhook signatures.
- Store webhook event IDs and process idempotently.
- Use transactions around order/payment writes.
- Do not log provider payloads with personal data.
- Do not display secret keys.
- Use CSRF for admin POST routes.
- Exempt only gateway webhook routes from CSRF if necessary, and compensate with signature verification.
- Keep public order success/cancel references non-enumerable.
- Do not expose internal order IDs as the only public lookup secret.
- Treat customer email as personal data and keep list views compact.

## Tests

Focused tests should cover:

- Plugin definition conventions.
- Plugin manifest shape.
- Setup-required health when tables are missing.
- Product listing/create/update/archive authorization.
- Product validation for price, currency, status, slug uniqueness.
- Order listing/show authorization.
- Checkout start creates a pending order and redirects to hosted checkout.
- Checkout start refuses inactive, archived, zero-stock, or invalid products.
- Webhook signature failures are rejected.
- Webhook event IDs are idempotent.
- Paid webhook transitions pending order to paid and records payment.
- Cancel/failed/expired events update only allowed pending orders.
- Public plugin routes are inert when the plugin is disabled or incompatible.

Use focused `php artisan test --filter=...` commands during implementation, then the risk-based composer script that matches the touched surface.

## Documentation Updates During Implementation

When runtime behavior is added, update:

- `README.md` with user-facing commerce summary and setup entry point.
- `CHANGELOG.md` with short operator-facing notes.
- `docs/plugin-system.md` if a public plugin route extension point is added.
- `docs/feature-inventory.md` after the feature moves from documented direction to implemented.
- `docs/webblocks-commerce-operator-guide.md` for installation, PayPal setup, Commerce Buy Button usage, buy URL fallback, and sandbox/live verification.

This planning document should remain as architecture/MVP intent. The operator guide now describes actual setup and operation; future docs should cover broader product listing, cart, fulfillment, and live gateway onboarding work when those features exist.

## Implementation Milestones

### Milestone 1: Package Skeleton

- Create `plugins/webblocks-commerce`.
- Add plugin manifest and definition.
- Add health reporter and schema readiness class.
- Add build script if it can reuse the WebBlocks UI Manager plugin packaging pattern.
- Add convention tests.

Status: implemented as the first foundation slice, including the plugin skeleton, manifest, config stub, setup-required schema health, commerce tables, model foundation, and focused plugin tests.

### Milestone 2: Schema And Models

- Add plugin migrations.
- Add product, order, order item, payment, and webhook event models.
- Add setup-required tests.
- Add safe status enums or constants.

Status: implemented with plugin-owned tables, Eloquent models, setup-required health, and focused schema/model tests.

### Milestone 3: Admin Products

- Add product routes, request validation, controller, views.
- Add product listing with filters for status.
- Add product create/edit/show/archive.
- Add permission tests.

Status: implemented. Product management is available after enabling the plugin and running plugin migrations at `/webadmin/plugins/webblocks-commerce/products`. Missing tables render controlled setup guidance instead of raw database errors.

### Milestone 4: Admin Orders

- Add read-only order listing and detail views.
- Show payment attempts and gateway references without exposing secrets.
- Add permission tests.

Status: implemented. Order review is available after enabling the plugin and running plugin migrations at `/webadmin/plugins/webblocks-commerce/orders`. The screen is read-only; payment state changes remain webhook-owned.

### Milestone 5: Gateway Foundation

- Add `PaymentGatewayInterface`.
- Add fake gateway for tests.
- Add PayPal hosted checkout adapter.
- Add config diagnostics.
- Avoid network calls in tests.

Status: implemented for the MVP PayPal path. `PaymentGatewayInterface`, fake gateway, no-network checkout session creation, PayPal hosted checkout, no-network PayPal HTTP tests, and secret-safe config diagnostics are in place.

### Milestone 6: Public Checkout

- Add or extend CMS plugin public route support.
- Add product buy route, success route, cancel route, and webhook route.
- Add idempotent webhook handling.
- Add public route inertness tests.

Status: implemented for the MVP PayPal path. Public buy page, POST checkout start, pending order creation, signed success/cancel status pages, PayPal webhook verification, idempotent event storage, and paid order transitions are in place.

### Milestone 7: Docs And Validation

- Update README, CHANGELOG, and relevant docs.
- Run focused tests.
- Run formatting and indentation checks.
- Run a package/plugin risk validation script if the changed surface warrants it.

## Standing Decisions

These choices are the current implementation assumptions unless a later planning step changes them:

- Use `webblocks-commerce` as the stable plugin handle.
- Start with PayPal hosted checkout only.
- Keep payment secrets in `.env`/config for MVP, not editable database settings.
- Use existing Button Link block with product buy URLs in Phase 1.
- Add a generic public plugin route extension point only if needed for checkout routes.
- Treat cart, variants, coupons, shipping, taxes, and refunds as post-MVP.
