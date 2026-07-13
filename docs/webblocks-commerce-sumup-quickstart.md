---
cms_sync: true
cms_site: docs-site
cms_locale: en
cms_path: /docs/webblocks-commerce-sumup-quickstart
cms_title: Connect SumUp to WebBlocks Commerce
cms_layout: docs
cms_source_id: webblocks-cms:docs/webblocks-commerce-sumup-quickstart.md
---

# Connect SumUp to WebBlocks Commerce

This guide gets a first SumUp sandbox payment working without charging real money. It is written
for store owners and site operators. You do not need to build a payment form: customers enter
their card details on SumUp's hosted checkout page.

Language: **English** · [Deutsch](webblocks-commerce-sumup-quickstart.de.md) ·
[Türkçe](webblocks-commerce-sumup-quickstart.tr.md)

## What You Need

- an installed and enabled WebBlocks Commerce plugin
- access to the SumUp Dashboard
- permission to add secret environment variables to the CMS server, or a hosting administrator
  who can do that for you
- a public HTTPS address for the shop
- about ten minutes for the initial sandbox setup

Sandbox payments are simulations. They do not move real money.

## Before You Start

WebBlocks Commerce currently reads payment credentials from protected server environment
variables. They are not entered into a CMS page, block, product, or public settings field.

If you do not manage the server yourself, securely send your hosting administrator the four
variable names shown in Step 4. Do not send the API key in normal email, chat, a screenshot, or a
support ticket. Use the hosting platform's secret manager or another approved secure channel.

## Step 1 — Create a SumUp Sandbox Merchant

1. Sign in at [SumUp Dashboard](https://me.sumup.com/).
2. Open **Developer Settings**.
3. Open the **Sandboxes** tab.
4. Create a sandbox merchant if none exists.
5. Use the account switcher to select the sandbox merchant.

The selected account should be clearly marked as a sandbox. Never use a live merchant for this
first test.

If your SumUp account has no developer settings or sandbox option, create a developer account from
the link in SumUp's official [online payment testing guide](https://developer.sumup.com/online-payments/testing).

## Step 2 — Copy the Sandbox Merchant Code

With the sandbox merchant selected, look at the top-left area of the SumUp Dashboard. SumUp shows
the account name and **Merchant ID** there. WebBlocks Commerce calls this value the merchant code.
It normally looks similar to `MXXXXXXX`.

Copy that value for Step 4. Do not use the merchant ID of your live account.

## Step 3 — Create a Test API Key

Keep the sandbox merchant selected, then:

1. Expand your profile and open **Settings**.
2. Go to **For Developers → Toolkit**.
3. Open **API Keys**.
4. Select **Create** and give the key a recognizable name, such as
   `WebBlocks Commerce sandbox`.
5. Copy or download the secret key when SumUp shows it.

Do not use the **SumUp Public Key**. WebBlocks Commerce needs the secret server-side API key. A
test secret key normally starts with `sk_test_`. SumUp does not show the complete secret again, so
store it immediately in an approved secret manager.

The current direct integration is for one merchant account controlled by the site owner. OAuth is
not required for this setup.

## Step 4 — Configure the CMS Server

Add these values to the hosting platform's environment-variable or secret settings:

```env
WEBBLOCKS_COMMERCE_GATEWAY=sumup
WEBBLOCKS_COMMERCE_SUMUP_MODE=sandbox
WEBBLOCKS_COMMERCE_SUMUP_API_KEY=replace-with-your-sk_test-key
WEBBLOCKS_COMMERCE_SUMUP_MERCHANT_CODE=replace-with-your-sandbox-merchant-id
```

If the installation uses a Laravel `.env` file, add the values there. Then clear the cached
configuration:

```bash
php artisan config:clear
```

If your deployment normally caches configuration, rebuild that cache using its normal deployment
procedure. Restart long-running PHP workers when your hosting platform requires it.

The `sandbox` mode label helps operators see which environment is intended. SumUp uses one API
hostname, so the API key and merchant code must themselves belong to the sandbox account.

## Step 5 — Confirm Commerce Is Ready

1. Sign in to CMS admin.
2. Open **Commerce → Commerce Settings**, or visit
   `/webadmin/plugins/webblocks-commerce/settings`.
3. Confirm all of the following:
   - active gateway: `sumup`
   - SumUp mode: `sandbox`
   - API key: configured
   - merchant code: configured
   - checkout: ready
   - plugin schema: ready

The settings screen deliberately says only “configured” or “missing”. It must never display the
API key itself.

If the schema is not ready, open **System → Plugins → WebBlocks Commerce** and run plugin setup or
migrations first.

## Step 6 — Create a Test Product

1. Open **Commerce → Products**.
2. Create or edit a product.
3. Set a title, slug, price, currency, and tax class.
4. Use `EUR` for the first SumUp test unless the sandbox merchant uses another supported currency.
5. Set the product status to **Active**.
6. Save the product.

A draft or archived product cannot be checked out. If tracked stock is enabled, make sure at least
one unit is available.

## Step 7 — Add the Native Commerce Block

1. Open the desired CMS page in the page builder.
2. Add a **Commerce Buy Button** block to a normal page slot.
3. Select the active product.
4. Choose the button label, alignment, and price-display options.
5. Preview and publish the page through the normal CMS workflow.

Do not use a Trusted HTML block and do not paste a SumUp checkout URL into content. A checkout URL
is created for each order and expires after about 30 minutes.

## Step 8 — Make a Successful Sandbox Payment

1. Open the public product page or the page containing the Commerce button.
2. Add the product to the cart.
3. Check the quantity, VAT, currency, and final amount.
4. Select **Continue to secure payment**.
5. On the SumUp-hosted page, use this official sandbox test card:

```text
Card number: 4200 0000 0000 0091
Expiry date: any future date, for example 12/30
CVV: any three digits, for example 123
Cardholder: any name
```

6. Complete the payment and use the return action to go back to the shop.
7. In CMS admin, open **Commerce → Orders**.
8. Confirm that the order changes to `paid` and the payment attempt to `succeeded`.

The browser return page is not proof of payment. The order becomes paid only after WebBlocks
Commerce receives the SumUp notification, retrieves the checkout from SumUp, and verifies the
merchant, reference, amount, currency, final status, and successful transaction.

## No Manual SumUp Webhook Setup Is Needed

For this integration, do not create or paste a webhook URL in the SumUp Dashboard. WebBlocks
Commerce automatically sends this callback as the checkout `return_url`:

```text
https://your-shop.example/commerce/webhooks/sumup
```

The public shop must use HTTPS and SumUp must be able to reach this URL. A firewall, maintenance
page, HTTP authentication, or proxy rule must not block SumUp's POST request.

## Optional Failure Test

SumUp's sandbox uses certain totals to simulate a declined payment. To test the failure path,
create a temporary product whose final checkout total is exactly `11.00 EUR`, then use a sandbox
test card. Confirm that the order is not marked paid and that reserved stock is released after the
failure is processed.

Do not change a real product's price for this test.

## Switch to Live Payments

Switch only after the complete sandbox flow succeeds:

1. Select the real merchant account in SumUp Dashboard.
2. Complete any business verification and payout setup required by SumUp.
3. Copy the live Merchant ID.
4. Create a separate live secret API key. A live key normally starts with `sk_live_`.
5. Replace the sandbox values on the CMS server:

```env
WEBBLOCKS_COMMERCE_GATEWAY=sumup
WEBBLOCKS_COMMERCE_SUMUP_MODE=live
WEBBLOCKS_COMMERCE_SUMUP_API_KEY=replace-with-your-sk_live-key
WEBBLOCKS_COMMERCE_SUMUP_MERCHANT_CODE=replace-with-your-live-merchant-id
```

6. Refresh the application configuration using the normal deployment procedure.
7. Recheck **Commerce Settings**.
8. Make one acceptable low-value real purchase and verify the order and payout in both systems.

Never combine a test key with a live merchant ID or reuse the sandbox key in production.

## Troubleshooting

### Commerce Settings says “API key missing”

- Check the environment-variable spelling.
- Confirm the deployment or PHP process was refreshed after the change.
- If configuration is cached, clear and rebuild it using the normal deployment procedure.

### Commerce Settings says ready, but checkout fails

- Confirm the key and merchant ID belong to the same sandbox account.
- Confirm the key is the secret API key, not the public key.
- Confirm the product is active, has a positive price, and has available stock.
- Confirm the product currency matches the merchant's supported currency.

### The customer paid, but the order stays pending

- Confirm `https://your-shop.example/commerce/webhooks/sumup` is publicly reachable by POST over
  HTTPS.
- Check that a firewall, maintenance page, or HTTP password is not blocking the callback.
- Confirm the API key can still retrieve the checkout and belongs to the configured merchant.
- Confirm SumUp reports the checkout as `PAID` with a successful transaction.

### The hosted checkout says expired or not found

Start checkout again from the cart. Hosted checkout sessions expire after about 30 minutes. Do not
bookmark or reuse an old hosted checkout URL.

## Security Rules

- Never put the API key in a CMS block, page, product, browser script, repository, screenshot, or
  support log.
- Never paste a real API key into chat.
- Keep sandbox and live credentials separate.
- Revoke and replace the key immediately if it may have been exposed.
- Treat the CMS order state—not the browser success screen—as the fulfillment signal.

For architecture, webhook verification, order states, and advanced troubleshooting, continue with
the [WebBlocks Commerce Operator Guide](webblocks-commerce-operator-guide.md).

Official SumUp references:

- [Testing online payments](https://developer.sumup.com/online-payments/testing)
- [Create and protect API keys](https://developer.sumup.com/tools/authorization/api-keys)
- [Hosted Checkout](https://developer.sumup.com/online-payments/checkouts/hosted-checkout)
- [Checkout status webhooks](https://developer.sumup.com/online-payments/webhooks)
