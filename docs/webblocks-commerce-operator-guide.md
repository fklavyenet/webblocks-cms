---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/webblocks-commerce-operator-guide
cms_title: WebBlocks Commerce Operator Guide
cms_layout: docs
cms_source_id: webblocks-cms:docs/webblocks-commerce-operator-guide.md
---

# WebBlocks Commerce Operator Guide

This guide explains how to install, configure, and test the first WebBlocks Commerce MVP plugin. The current plugin supports single-product hosted checkout through PayPal. It is intentionally small: product admin, read-only order admin, secret-safe readiness diagnostics, public buy URLs, a plugin-owned Commerce Buy Button block, PayPal checkout redirects, and PayPal webhook capture confirmation.

The plugin is developed under `plugins/webblocks-commerce`. It remains a manually installed plugin package and must not be moved into CMS core.

## Current User Flow

1. A CMS operator installs and enables WebBlocks Commerce.
2. The operator runs plugin migrations from the plugin detail screen.
3. The operator configures PayPal credentials in the install environment.
4. The operator opens `Commerce Settings` to confirm checkout and webhook readiness.
5. The operator creates a commerce product.
6. The product detail screen shows a public buy URL.
7. The operator adds a `Commerce Buy Button` block to a page and selects the product.
8. A visitor starts checkout, approves payment in PayPal, and returns to the site.
9. The order remains pending until the PayPal webhook verifies and captures the payment.
10. The operator reviews the paid order under `Commerce Orders`.

## Install The Plugin

Build the plugin ZIP from the CMS repository:

```bash
php plugins/webblocks-commerce/build-plugin.php
```

Then complete the manual plugin lifecycle:

1. Open `System -> Plugins`.
2. Upload the generated WebBlocks Commerce ZIP.
3. Review the plugin detail screen.
4. Enable the plugin.
5. Run plugin setup/migrations if the plugin reports `Setup required`.
6. Confirm that health changes from setup-required to ready.

The plugin owns `webblocks_commerce_*` tables. Disabling the plugin makes routes, menus, settings, and behavior inert. Uninstalling a disabled manually uploaded plugin removes the uploaded package, but preserves plugin-owned tables.

## API Automation

Trusted operator tools can perform the setup and page-building workflow through `/webadmin/api` when the CMS API token has explicit plugin, commerce, and content capabilities.

Plugin lifecycle:

```text
GET /webadmin/api/plugins
POST /webadmin/api/plugins/install
POST /webadmin/api/plugins/webblocks-commerce/enable
POST /webadmin/api/plugins/webblocks-commerce/setup
POST /webadmin/api/plugins/webblocks-commerce/disable
DELETE /webadmin/api/plugins/webblocks-commerce
```

Commerce resources:

```text
GET /webadmin/api/commerce/products
POST /webadmin/api/commerce/products
PATCH /webadmin/api/commerce/products/{product}
GET /webadmin/api/commerce/orders
GET /webadmin/api/commerce/orders/{order}
```

Required token capabilities are intentionally split:

- plugin lifecycle: `plugins.read`, `plugins.install`, `plugins.manage`, `plugins.setup`, and only when needed `plugins.uninstall`
- product work: `commerce.read` and `commerce.products.write`
- order review: `commerce.orders.read`
- page placement: `content.validate` and `content.apply`

The API flow for adding a buy button is:

1. Install, enable, and setup `webblocks-commerce`.
2. Create an active product with `POST /webadmin/api/commerce/products`.
3. Read `GET /webadmin/api/block-types` or `GET /webadmin/api/content-contract`.
4. Add a `webblocks-commerce-buy-button` block through content validate/apply.
5. Set `settings.commerce_product_id` to the product id returned by the Commerce API.

The Commerce Buy Button block is plugin-owned. It is hidden from block discovery while the plugin is disabled, and content validate/apply rejects missing, unknown, or inactive product ids. The API does not collect card data; visitors still complete checkout through the public Commerce/PayPal flow.

## PayPal Configuration

WebBlocks Commerce uses PayPal REST APIs. PayPal documents that REST APIs use OAuth 2.0 access tokens, and that API calls exchange a client ID and client secret for an access token. Keep the client secret private and never paste it into CMS content, docs pages, screenshots, or support logs.

Official PayPal references:

- [Get Started with PayPal REST APIs](https://developer.paypal.com/api/rest/)
- [PayPal Webhooks API](https://developer.paypal.com/docs/api/webhooks/v1/)
- [Verify webhook signature](https://developer.paypal.com/docs/api/webhooks/v1/#verify-webhook-signature_post)

Set these environment variables in the CMS install:

```env
WEBBLOCKS_COMMERCE_GATEWAY=paypal
WEBBLOCKS_COMMERCE_PAYPAL_MODE=sandbox
WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_ID=your-paypal-client-id
WEBBLOCKS_COMMERCE_PAYPAL_CLIENT_SECRET=your-paypal-client-secret
WEBBLOCKS_COMMERCE_PAYPAL_WEBHOOK_ID=your-paypal-webhook-id
```

Use `WEBBLOCKS_COMMERCE_PAYPAL_MODE=live` only after sandbox checkout and webhook verification have been tested.

## PayPal Sandbox Setup

In PayPal Developer Dashboard:

1. Open `Apps & Credentials`.
2. Use the default REST API app or create a new app.
3. Copy the sandbox client ID and client secret into the install environment.
4. Create or open the app webhook settings.
5. Add this webhook URL:

```text
https://your-site.example/commerce/webhooks/paypal
```

6. Subscribe at minimum to:

```text
CHECKOUT.ORDER.APPROVED
PAYMENT.CAPTURE.COMPLETED
```

7. Copy the PayPal webhook ID into `WEBBLOCKS_COMMERCE_PAYPAL_WEBHOOK_ID`.
8. Use PayPal sandbox buyer and seller accounts for checkout testing.

For local HTTPS tunnels, use the tunnel HTTPS URL as the webhook URL. For production, use the final public HTTPS site URL.

## Readiness Diagnostics

Open:

```text
/webadmin/plugins/webblocks-commerce/settings
```

The settings screen intentionally shows only safe diagnostics:

- active gateway
- PayPal mode
- client ID configured or missing
- client secret configured or missing
- webhook ID configured or missing
- checkout readiness
- webhook readiness
- expected webhook URL
- plugin schema readiness

It must not display raw PayPal client secrets, tokens, webhook payload signatures, or payment credentials.

## Create A Product

Open:

```text
/webadmin/plugins/webblocks-commerce/products
```

Create a product with:

- title
- slug
- description
- status
- price amount
- currency
- optional inventory quantity
- optional SKU
- optional site scope

Set the product status to `Active` when it should be available for checkout. Draft and archived products do not start public checkout.

The product detail screen shows the product's public buy URL:

```text
/commerce/products/{slug}/buy
```

## Add A Buy Button To A Page

After the plugin is enabled and setup-ready, the page-builder block picker shows a plugin-owned `Commerce Buy Button` block.

Recommended workflow:

1. Open the artwork, portfolio, or "Works" page in the page builder.
2. Add `Commerce Buy Button` to the desired slot.
3. Select an active commerce product.
4. Optionally change the button label, alignment, and price display.
5. Publish the page when the surrounding content is ready.

The block renders a public button that links to:

```text
/commerce/products/{slug}/buy
```

The product buy URL remains useful as a fallback for manual navigation items or existing link fields.

Do not paste PayPal hosted checkout URLs into CMS content. PayPal approval URLs are generated per order and should come only from the checkout start flow.

## Checkout Behavior

When a visitor clicks the buy URL:

1. The buy page checks that the plugin is enabled, setup is ready, the product is active, and the gateway is configured.
2. The visitor starts checkout.
3. WebBlocks Commerce creates a pending order, order item, and pending payment attempt.
4. The PayPal adapter creates a PayPal Order.
5. The visitor is redirected to PayPal approval.
6. The visitor returns to a signed success or cancel page.
7. The success page does not mark the order paid.
8. PayPal sends a webhook to `/commerce/webhooks/paypal`.
9. WebBlocks Commerce verifies the webhook signature with PayPal.
10. For `CHECKOUT.ORDER.APPROVED`, WebBlocks Commerce captures the PayPal order.
11. If capture is completed, the order is marked `paid` and the payment attempt is marked `succeeded`.

Webhook events are stored by gateway and event ID so repeated delivery is idempotent.

## Review Orders

Open:

```text
/webadmin/plugins/webblocks-commerce/orders
```

Orders are read-only in the MVP. The order detail screen shows:

- order number
- customer email when PayPal returns one
- order status
- line items
- payment attempts
- gateway checkout and payment references
- timestamps

Manual status editing, refunds, shipping, taxes, and fulfillment workflows are intentionally deferred.

## Sandbox Verification Checklist

Use this checklist before switching to live mode:

- WebBlocks Commerce is installed, enabled, and setup-ready.
- `Commerce Settings` shows schema ready.
- `Commerce Settings` shows gateway `paypal`.
- PayPal client ID is configured.
- PayPal client secret is configured.
- PayPal webhook ID is configured.
- Webhook URL uses HTTPS and points to `/commerce/webhooks/paypal`.
- A product is active and has the expected price/currency.
- The product buy URL opens publicly.
- A page with a `Commerce Buy Button` renders the expected product label and buy link.
- Starting checkout redirects to PayPal.
- A sandbox buyer can approve the payment.
- The visitor returns to the signed success page.
- The order stays pending before webhook confirmation.
- PayPal delivers `CHECKOUT.ORDER.APPROVED`.
- The webhook verifies successfully.
- The PayPal order capture completes.
- The CMS order becomes `paid`.
- The payment attempt becomes `succeeded`.
- Re-sending the same webhook does not duplicate payment attempts.
- Invalid webhook signatures are rejected and do not mark orders paid.
- No PayPal secret appears in admin screens, public pages, logs, screenshots, or docs.

## Live Mode Checklist

Before switching to `WEBBLOCKS_COMMERCE_PAYPAL_MODE=live`:

- Confirm the operator owns a PayPal Business account where required by PayPal.
- Create or select the live REST app in PayPal Developer Dashboard.
- Replace sandbox client ID, client secret, and webhook ID with live values.
- Configure the live webhook URL with the production HTTPS domain.
- Confirm the production site can receive public PayPal webhook requests.
- Run one low-value live checkout if acceptable for the operator.
- Review the order in CMS admin.

Keep sandbox and live credentials separate. Do not reuse sandbox webhook IDs in live mode.

## Troubleshooting

If the buy page says checkout is not ready:

- Open `Commerce Settings`.
- Confirm gateway is `paypal`.
- Confirm client ID and client secret are configured.
- Confirm the product is active and has a valid price.
- Confirm plugin migrations have run.

If checkout redirects to PayPal but the order stays pending:

- Confirm the PayPal webhook URL is correct.
- Confirm `WEBBLOCKS_COMMERCE_PAYPAL_WEBHOOK_ID` matches the webhook configured in PayPal.
- Confirm PayPal sends `CHECKOUT.ORDER.APPROVED`.
- Confirm the site is reachable from PayPal over HTTPS.
- Confirm webhook signature verification is not failing.

If a webhook is rejected:

- Check that the webhook event came from the matching PayPal mode.
- Check that sandbox credentials are not mixed with live webhook IDs.
- Check that the webhook ID belongs to the same PayPal REST app as the client credentials.

## Current Limitations

The MVP does not yet include:

- cart or multi-product checkout
- taxes
- shipping
- coupons
- subscriptions
- refunds from CMS
- customer accounts
- inventory reservation
- fulfillment workflows
- PayPal live onboarding UI inside CMS

These are intentionally deferred so the first plugin slice remains small, secure, and reviewable.
