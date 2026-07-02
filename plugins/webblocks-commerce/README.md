# WebBlocks Commerce

First-party simple commerce plugin for WebBlocks CMS.

Current artifact version: `0.1.0`.

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
