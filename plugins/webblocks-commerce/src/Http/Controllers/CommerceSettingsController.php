<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\CommerceGatewayManager;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\PayPalConfig;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\SumUpConfig;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\WebBlocksCommerceSchema;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class CommerceSettingsController extends Controller
{
  public function __construct(
    private readonly SystemSettings $systemSettings,
    private readonly WebBlocksCommerceSchema $schema,
    private readonly CommerceGatewayManager $gateways,
    private readonly PayPalConfig $paypal,
    private readonly SumUpConfig $sumUp,
  ) {}

  public function edit(): View
  {
    return view($this->view('edit'), $this->viewData('Commerce Settings', [
      'schemaReady' => $this->schema->isReady(),
      'schemaMessage' => $this->schema->message(),
      'gateway' => $this->gateways->gatewayKey(),
      'checkoutReady' => $this->gateways->supportsCheckout(),
      'checkoutMessage' => $this->gateways->supportsCheckout() ? 'Checkout can be started.' : $this->gateways->unavailableMessage(),
      'paypal' => [
        'mode' => $this->paypal->mode(),
        'client_id_configured' => $this->paypal->clientId() !== null,
        'client_secret_configured' => $this->paypal->clientSecret() !== null,
        'webhook_id_configured' => $this->paypal->webhookId() !== null,
        'checkout_ready' => $this->paypal->isCheckoutReady(),
        'webhook_ready' => $this->paypal->isWebhookReady(),
        'webhook_url' => url('/commerce/webhooks/paypal'),
      ],
      'sumup' => [
        'mode' => $this->sumUp->mode(),
        'api_key_configured' => $this->sumUp->apiKey() !== null,
        'merchant_code_configured' => $this->sumUp->merchantCode() !== null,
        'checkout_ready' => $this->sumUp->isCheckoutReady(),
        'webhook_ready' => $this->sumUp->isWebhookReady(),
        'webhook_url' => url('/commerce/webhooks/sumup'),
      ],
      'pluginDetailUrl' => route('admin.system.plugins.show', 'webblocks-commerce'),
      'pluginSetupUrl' => route('admin.system.plugins.setup', 'webblocks-commerce'),
    ]));
  }

  /**
   * @param  array<string, mixed>  $data
   * @return array<string, mixed>
   */
  private function viewData(string $title, array $data): array
  {
    return array_merge($data, [
      'title' => $title,
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle($title),
    ]);
  }

  private function view(string $name): string
  {
    return WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::plugins.webblocks-commerce.settings.'.$name;
  }
}
