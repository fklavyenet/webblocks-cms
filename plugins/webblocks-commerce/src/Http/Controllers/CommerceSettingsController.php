<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Requests\CommerceSettingsRequest;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\CommerceSettingsStore;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\CommerceGatewayManager;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\PayPalConfig;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\SumUpConfig;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\WebBlocksCommerceSchema;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class CommerceSettingsController extends Controller
{
  public function __construct(
    private readonly SystemSettings $systemSettings,
    private readonly WebBlocksCommerceSchema $schema,
    private readonly CommerceGatewayManager $gateways,
    private readonly PayPalConfig $paypal,
    private readonly SumUpConfig $sumUp,
    private readonly CommerceSettingsStore $settings,
  ) {}

  public function edit(): View
  {
    return view($this->view('edit'), $this->viewData('Commerce Settings', [
      'schemaReady' => $this->schema->isReady(),
      'schemaMessage' => $this->schema->message(),
      'gateway' => $this->gateways->gatewayKey(),
      'gateway_source' => $this->settings->source(CommerceSettingsStore::GATEWAY),
      'checkoutReady' => $this->gateways->supportsCheckout(),
      'checkoutMessage' => $this->gateways->supportsCheckout() ? 'Checkout can be started.' : $this->gateways->unavailableMessage(),
      'paypal' => [
        'mode' => $this->paypal->mode(),
        'mode_source' => $this->settings->source(CommerceSettingsStore::PAYPAL_MODE),
        'client_id' => $this->credentialState(CommerceSettingsStore::PAYPAL_CLIENT_ID),
        'client_secret' => $this->credentialState(CommerceSettingsStore::PAYPAL_CLIENT_SECRET),
        'webhook_id' => $this->credentialState(CommerceSettingsStore::PAYPAL_WEBHOOK_ID),
        'checkout_ready' => $this->paypal->isCheckoutReady(),
        'webhook_ready' => $this->paypal->isWebhookReady(),
        'webhook_url' => url('/commerce/webhooks/paypal'),
      ],
      'sumup' => [
        'mode' => $this->sumUp->mode(),
        'mode_source' => $this->settings->source(CommerceSettingsStore::SUMUP_MODE),
        'api_key' => $this->credentialState(CommerceSettingsStore::SUMUP_API_KEY),
        'merchant_code' => $this->credentialState(CommerceSettingsStore::SUMUP_MERCHANT_CODE),
        'checkout_ready' => $this->sumUp->isCheckoutReady(),
        'webhook_ready' => $this->sumUp->isWebhookReady(),
        'webhook_url' => url('/commerce/webhooks/sumup'),
      ],
      'settingsUpdateUrl' => route('webblocks.plugins.webblocks_commerce.settings.update'),
      'pluginDetailUrl' => route('admin.system.plugins.show', 'webblocks-commerce'),
      'pluginSetupUrl' => route('admin.system.plugins.setup', 'webblocks-commerce'),
    ]));
  }

  public function update(CommerceSettingsRequest $request): RedirectResponse
  {
    $this->settings->save($request->settingsPayload(), $request->clearKeys());

    return redirect()
      ->route('webblocks.plugins.webblocks_commerce.settings.edit')
      ->with('success', $this->text('settings.saved'));
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

  /**
   * @return array{configured: bool, environment_managed: bool, stored: bool, source: string}
   */
  private function credentialState(string $key): array
  {
    return [
      'configured' => $this->settings->isConfigured($key),
      'environment_managed' => $this->settings->isEnvironmentManaged($key),
      'stored' => $this->settings->isStored($key),
      'source' => $this->settings->source($key),
    ];
  }

  private function text(string $key): string
  {
    $locale = app(AdminLocaleResolver::class)->locale();

    return app(CmsTranslator::class)->plugin('webblocks-commerce', 'admin.'.$key, $locale, fallback: 'Commerce settings saved.');
  }
}
