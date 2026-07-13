<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\CheckoutUnavailableException;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\WebBlocksCommerceSchema;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Webhooks\HandlePayPalWebhook;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Webhooks\HandleSumUpWebhook;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Webhooks\WebhookVerificationException;

class CommerceWebhookController extends Controller
{
  public function __construct(
    private readonly WebBlocksCommerceSchema $schema,
    private readonly HandlePayPalWebhook $paypalWebhook,
    private readonly HandleSumUpWebhook $sumUpWebhook,
  ) {}

  public function paypal(Request $request): JsonResponse
  {
    abort_unless($this->schema->isReady(), 404);

    try {
      return response()->json($this->paypalWebhook->handle($request));
    } catch (WebhookVerificationException) {
      return response()->json(['status' => 'invalid_signature'], 400);
    } catch (CheckoutUnavailableException $exception) {
      return response()->json(['status' => 'unavailable', 'message' => $exception->getMessage()], 503);
    }
  }

  public function sumup(Request $request): JsonResponse
  {
    abort_unless($this->schema->isReady(), 404);

    try {
      return response()->json($this->sumUpWebhook->handle($request));
    } catch (WebhookVerificationException) {
      return response()->json(['status' => 'invalid_event'], 400);
    } catch (CheckoutUnavailableException $exception) {
      return response()->json(['status' => 'unavailable', 'message' => $exception->getMessage()], 503);
    }
  }
}
