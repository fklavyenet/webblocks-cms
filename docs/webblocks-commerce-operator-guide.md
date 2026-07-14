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

This guide explains how to install, configure, and test WebBlocks Commerce. The plugin supports a session-backed public cart, multi-line hosted checkout through PayPal or SumUp, product and read-only order admin, write-only encrypted provider settings, secret-safe diagnostics, public product pages, and a plugin-owned Commerce Buy Button block. Payment-card data stays on the selected provider's hosted payment surface.

Store owners who want to connect SumUp should start with the task-focused
[SumUp Quick Start](webblocks-commerce-sumup-quickstart.md), also available in
[German](webblocks-commerce-sumup-quickstart.de.md) and
[Turkish](webblocks-commerce-sumup-quickstart.tr.md). This operator guide is the technical
reference for architecture, APIs, verification, and advanced troubleshooting.

The plugin is developed under `plugins/webblocks-commerce`. It remains a manually installed plugin package and must not be moved into CMS core.

## Current User Flow

1. A CMS operator installs and enables WebBlocks Commerce.
2. The operator runs plugin migrations from the plugin detail screen.
3. The operator selects PayPal or SumUp and a compatible default currency in `Commerce Settings`, then saves the provider credentials. Hosting-managed environment values may be used as overrides instead.
4. The operator opens `Commerce Settings` to confirm checkout and webhook readiness.
5. The operator creates a commerce product.
6. The product detail screen shows a public buy URL.
7. The operator adds a `Commerce Buy Button` block to a page and selects the product.
8. The block adds the product to `/commerce/cart`; the visitor can update quantities and start checkout.
9. The visitor approves payment on the selected provider's hosted page and returns to the site.
10. The order remains pending until a provider-verified webhook confirms payment.
11. The operator reviews the paid order under `Commerce Orders`.

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

The Commerce Buy Button block is plugin-owned. It is hidden from block discovery while the plugin is disabled, and content validate/apply rejects missing, unknown, or inactive product ids. Its public renderer posts to the plugin-owned cart; no Trusted HTML block is required. The API does not collect card data; visitors complete payment on the configured PayPal or SumUp hosted checkout.

## PayPal Configuration

WebBlocks Commerce uses PayPal REST APIs. PayPal documents that REST APIs use OAuth 2.0 access tokens, and that API calls exchange a client ID and client secret for an access token. Keep the client secret private and never paste it into CMS content, docs pages, screenshots, or support logs.

Official PayPal references:

- [Get Started with PayPal REST APIs](https://developer.paypal.com/api/rest/)
- [PayPal supported currency codes and precision](https://developer.paypal.com/api/rest/reference/currency-codes/)
- [PayPal Webhooks API](https://developer.paypal.com/docs/api/webhooks/v1/)
- [Verify webhook signature](https://developer.paypal.com/docs/api/webhooks/v1/#verify-webhook-signature_post)

Open `Commerce Settings`, select `PayPal`, select `Sandbox`, and enter the client ID, client secret,
and webhook ID. The fields are write-only: saved values are encrypted in the plugin settings table
and are never rendered back into the browser. Leaving a field blank preserves its current value;
use the explicit clear checkbox to remove it.

For hosting-managed configuration, the following environment variables remain supported and take
precedence over encrypted admin settings:

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
3. Copy the sandbox client ID and client secret into the secure Commerce Settings form (or the install environment when using hosting-managed overrides).
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

7. Copy the PayPal webhook ID into the Commerce Settings form (or `WEBBLOCKS_COMMERCE_PAYPAL_WEBHOOK_ID` when using an environment override).
8. Use PayPal sandbox buyer and seller accounts for checkout testing.

For local HTTPS tunnels, use the tunnel HTTPS URL as the webhook URL. For production, use the final public HTTPS site URL.

## SumUp Hosted Checkout Configuration

SumUp Hosted Checkout keeps card entry and supported wallet UI on a SumUp-hosted page. The
integration creates the checkout server-side and never exposes the API key to the browser.

For a screen-by-screen store-owner workflow, use the
[SumUp Quick Start](webblocks-commerce-sumup-quickstart.md). The short setup sequence is:

1. Create and select a sandbox merchant under SumUp Dashboard **Developer Settings → Sandboxes**.
2. Copy the sandbox **Merchant ID** shown in the top-left Dashboard account area.
3. Create a secret test API key under **Settings → For Developers → Toolkit → API Keys**.
4. Enter the gateway, mode, API key, and merchant code in Commerce Settings and confirm readiness.
5. Test with SumUp's documented sandbox card before using live credentials.

Official SumUp references:

- [Hosted Checkout](https://developer.sumup.com/online-payments/checkouts/hosted-checkout)
- [Create and retrieve checkouts](https://developer.sumup.com/api/checkouts/create)
- [Checkout status webhooks](https://developer.sumup.com/online-payments/webhooks)
- [API keys](https://developer.sumup.com/tools/authorization/api-keys)
- [Testing online payments](https://developer.sumup.com/online-payments/testing)

In `Commerce Settings`, select `SumUp`, select `Sandbox`, and enter the API key and merchant code.
Saved credentials are encrypted at rest and remain write-only. Hosting-managed deployments may
instead set these environment variables; they take precedence and make the matching form fields
read-only:

```env
WEBBLOCKS_COMMERCE_GATEWAY=sumup
WEBBLOCKS_COMMERCE_DEFAULT_CURRENCY=EUR
WEBBLOCKS_COMMERCE_SUMUP_MODE=sandbox
WEBBLOCKS_COMMERCE_SUMUP_API_KEY=your-sumup-test-api-key
WEBBLOCKS_COMMERCE_SUMUP_MERCHANT_CODE=your-sandbox-merchant-code
```

Use the secret API key created for the selected sandbox merchant; do not use the SumUp Public Key.
A test secret key normally starts with `sk_test_`. Do not paste it into CMS blocks, site settings,
screenshots, support logs, or normal chat. WebBlocks Commerce sends this callback automatically
when it creates each checkout:

```text
https://your-site.example/commerce/webhooks/sumup
```

No manual webhook registration in the SumUp Dashboard is required for this adapter. The public
HTTPS endpoint must nevertheless be reachable by SumUp and must not be blocked by a firewall,
maintenance page, HTTP password, or proxy rule.

SumUp calls the configured `return_url` with `CHECKOUT_STATUS_CHANGED` and a checkout ID. That
payload is not accepted as proof of payment. WebBlocks Commerce retrieves the checkout from
SumUp, then matches the ID, merchant code, order reference, amount, currency, terminal status,
and successful transaction before marking an order paid. Failed and expired status transitions
release reserved inventory. Unknown event types are safely ignored.

## Readiness Diagnostics

Open:

```text
/webadmin/plugins/webblocks-commerce/settings
```

The settings screen provides write-only credential fields and intentionally shows only safe diagnostics:

- active gateway
- default currency and its configuration source
- PayPal mode
- SumUp mode
- client ID configured or missing
- client secret configured or missing
- webhook ID configured or missing
- checkout readiness
- webhook readiness
- expected webhook URL
- SumUp API key and merchant code configured or missing
- plugin schema readiness

It must not display raw PayPal secrets, SumUp API keys, access tokens, webhook payload signatures, or payment credentials. Blank credential fields preserve existing encrypted values. Explicit clear controls remove stored values, while environment-managed values cannot be edited or cleared from the CMS.

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

## Currency Behavior

The default currency is stored with the other Commerce settings and is used for new products.
`WEBBLOCKS_COMMERCE_DEFAULT_CURRENCY` remains an optional environment override; when present, the
selector is read-only. Product currency is selected from the active gateway's supported list, and
the internal product API enforces the same rule.

Switching gateways is blocked if a non-archived product uses a currency unsupported by the target
gateway. Mixed-currency carts remain rejected. Checkout performs a final gateway compatibility
check before it creates an order or reserves inventory.

Prices are integer minor units, but minor-unit precision is currency-specific rather than always
two digits. Public and admin views use the current CMS locale through PHP `intl`
`NumberFormatter`, so symbols and separators are localized for EUR, USD, GBP, JPY, and every
selectable currency. Gateway requests use the same precision. PayPal-specific zero-decimal
requirements for HUF and TWD are honored.

There is no additional Composer dependency. PHP `ext-intl` is a platform requirement and must be
enabled for both the web server and CLI. The plugin health result warns when it is unavailable.
Supported codes are based on the official
[PayPal currency reference](https://developer.paypal.com/api/rest/reference/currency-codes/) and
[SumUp Checkout API enum](https://developer.sumup.com/api/checkouts/create); merchant-country and
account restrictions can still narrow those provider lists.

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

The block renders a native public form that adds the selected product to:

```text
/commerce/cart
```

The product buy URL remains useful for product-detail links and exposes both add-to-cart and
direct buy-now actions. The cart, product page, and checkout status views all extend the CMS
public layout, preserving the active site's header and footer slots.

Do not paste provider-hosted checkout URLs into CMS content. They are generated per order and should come only from the checkout start flow.

## Checkout Behavior

When a visitor uses a Commerce block or product page:

1. The plugin checks setup, product status, tracked stock, and cart currency.
2. The product is stored in the session-backed server-side cart; no payment data is collected.
3. At checkout, WebBlocks Commerce freezes localized line titles, prices, VAT, and totals onto a pending order and reserves stock atomically.
4. The active provider adapter creates a hosted checkout and the visitor is redirected away.
5. A signed return page can report that processing continues, but it never marks the order paid.
6. PayPal webhooks are signature-verified and approved PayPal Orders are captured.
7. SumUp status notifications trigger a fresh checkout API retrieval and full order/transaction match.
8. Only the verified provider result moves the order and payment attempt to `paid`/`succeeded`.

Webhook events are stored by gateway and event ID so repeated delivery is idempotent.

## Review Orders

Open:

```text
/webadmin/plugins/webblocks-commerce/orders
```

Orders are read-only in the MVP. The order detail screen shows:

- order number
- customer email when supplied or returned by a provider
- order status
- line items
- payment attempts
- gateway checkout and payment references
- timestamps

Manual status editing, refunds, shipping, taxes, and fulfillment workflows are intentionally deferred.

## PayPal Sandbox Verification Checklist

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
- A page with a `Commerce Buy Button` adds the expected product to `/commerce/cart`.
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

## SumUp Sandbox Verification Checklist

- The sandbox merchant is selected in SumUp Dashboard.
- The Merchant ID and `sk_test_` secret key belong to that same sandbox account.
- `Commerce Settings` shows gateway `sumup`, sandbox mode, and checkout ready.
- The test API key and sandbox merchant code are configured, but the key value is not rendered.
- A Commerce block adds the active EUR product to `/commerce/cart`.
- Quantity, VAT, and the final amount are correct before checkout.
- Starting checkout creates one pending order and redirects to `checkout.sumup.com`.
- The SumUp checkout reference matches the CMS order number.
- Completing a sandbox payment produces `CHECKOUT_STATUS_CHANGED` at `/commerce/webhooks/sumup`.
- The handler retrieves the checkout from SumUp and confirms a successful transaction.
- The CMS order becomes `paid` and its payment attempt becomes `succeeded`.
- Re-sending the same paid notification does not create another payment attempt.
- A mismatched merchant code, reference, amount, or currency never marks an order paid.
- Failed or expired SumUp checkouts release reserved inventory.
- The documented successful test card `4200 0000 0000 0091` completes with any future expiry date
  and any three-digit CVV.

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

For SumUp live mode, select the verified real merchant account, create a separate `sk_live_`
secret API key, use that account's live Merchant ID, set `WEBBLOCKS_COMMERCE_SUMUP_MODE=live`,
refresh the application configuration, and run one acceptable low-value payment. Never reuse or
mix a sandbox merchant, test key, live merchant, or live key.

## Troubleshooting

If the buy page says checkout is not ready:

- Open `Commerce Settings`.
- Confirm the selected gateway is `paypal` or `sumup`.
- For PayPal, confirm client ID and client secret are configured.
- For SumUp, confirm API key and merchant code are configured.
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

If a SumUp order stays pending:

- Confirm the public HTTPS URL `/commerce/webhooks/sumup` is reachable.
- Confirm the API key can read the checkout and belongs to the configured merchant code.
- Confirm the checkout reference, amount, and currency still match the CMS order.
- Confirm SumUp reports `PAID` and includes a `SUCCESSFUL` transaction.

If the SumUp hosted page reports an expired or missing checkout, start a new checkout from the
cart. Hosted Checkout sessions expire after about 30 minutes and their URLs must not be bookmarked
or reused.

## Current Limitations

The current plugin does not yet include:

- shipping
- coupons
- subscriptions
- refunds from CMS
- customer accounts
- fulfillment workflows
- provider onboarding or secret editing inside CMS

These remain separate features rather than being hidden inside provider integrations.
