<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommercePayment;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceWebhookEvent;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\CheckoutUnavailableException;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\PayPalApiClient;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\PayPalConfig;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Orders\OrderStateMachine;

class HandlePayPalWebhook
{
  public function __construct(
    private readonly PayPalApiClient $client,
    private readonly PayPalConfig $config,
    private readonly OrderStateMachine $orders,
  ) {}

  /**
   * @return array{status: string, event_id: string}
   */
  public function handle(Request $request): array
  {
    if (! $this->config->isWebhookReady()) {
      throw new CheckoutUnavailableException('PayPal webhook is not configured yet.');
    }

    $payload = $request->all();
    $eventId = $this->eventId($payload, $request);
    $eventType = $this->eventType($payload);

    $this->verify($request, $payload);

    $existing = CommerceWebhookEvent::query()
      ->where('gateway', 'paypal')
      ->where('event_id', $eventId)
      ->first();

    if ($existing !== null && in_array($existing->status, [
      CommerceWebhookEvent::STATUS_PROCESSED,
      CommerceWebhookEvent::STATUS_IGNORED,
    ], true)) {
      return [
        'status' => $existing->status,
        'event_id' => $existing->event_id,
      ];
    }

    $event = $existing ?? CommerceWebhookEvent::query()->create([
      'gateway' => 'paypal',
      'event_id' => $eventId,
      'event_type' => $eventType,
      'payload_digest' => hash('sha256', $request->getContent()),
      'status' => CommerceWebhookEvent::STATUS_RECEIVED,
    ]);

    return DB::transaction(function () use ($event, $payload, $eventType): array {
      $status = match ($eventType) {
        'CHECKOUT.ORDER.APPROVED' => $this->handleApprovedOrder($event, $payload),
        'PAYMENT.CAPTURE.COMPLETED' => $this->handleCompletedCapture($event, $payload),
        default => $this->ignore($event, 'PayPal event type is not handled by the MVP.'),
      };

      return [
        'status' => $status,
        'event_id' => $event->event_id,
      ];
    });
  }

  /**
   * @param  array<string, mixed>  $payload
   */
  private function verify(Request $request, array $payload): void
  {
    $verification = $this->client->verifyWebhook([
      'auth_algo' => (string) $request->header('PAYPAL-AUTH-ALGO', ''),
      'cert_url' => (string) $request->header('PAYPAL-CERT-URL', ''),
      'transmission_id' => (string) $request->header('PAYPAL-TRANSMISSION-ID', ''),
      'transmission_sig' => (string) $request->header('PAYPAL-TRANSMISSION-SIG', ''),
      'transmission_time' => (string) $request->header('PAYPAL-TRANSMISSION-TIME', ''),
      'webhook_id' => (string) $this->config->webhookId(),
      'webhook_event' => $payload,
    ]);

    if (($verification['verification_status'] ?? null) !== 'SUCCESS') {
      throw new WebhookVerificationException('PayPal webhook signature could not be verified.');
    }
  }

  /**
   * @param  array<string, mixed>  $payload
   */
  private function handleApprovedOrder(CommerceWebhookEvent $event, array $payload): string
  {
    $paypalOrderId = data_get($payload, 'resource.id');

    if (! is_string($paypalOrderId) || trim($paypalOrderId) === '') {
      return $this->fail($event, 'PayPal approved-order event did not include an order ID.');
    }

    $order = $this->orderByCheckoutId($paypalOrderId);

    if ($order === null) {
      return $this->ignore($event, 'No matching WebBlocks Commerce order was found.');
    }

    if ($order->status === CommerceOrder::STATUS_PAID) {
      return $this->processed($event, 'Order was already paid.');
    }

    $capture = $this->client->captureOrder($paypalOrderId);

    if (($capture['status'] ?? null) !== 'COMPLETED') {
      return $this->fail($event, 'PayPal capture did not complete.');
    }

    $this->markPaid($order, $event, $capture);

    return $this->processed($event, 'PayPal checkout order was captured.');
  }

  /**
   * @param  array<string, mixed>  $payload
   */
  private function handleCompletedCapture(CommerceWebhookEvent $event, array $payload): string
  {
    $resource = data_get($payload, 'resource', []);

    if (! is_array($resource)) {
      return $this->fail($event, 'PayPal capture event did not include a resource.');
    }

    $paypalOrderId = data_get($resource, 'supplementary_data.related_ids.order_id');

    if (! is_string($paypalOrderId) || trim($paypalOrderId) === '') {
      return $this->ignore($event, 'PayPal capture event did not include a related order ID.');
    }

    $order = $this->orderByCheckoutId($paypalOrderId);

    if ($order === null) {
      return $this->ignore($event, 'No matching WebBlocks Commerce order was found.');
    }

    $this->markPaid($order, $event, [
      'status' => 'COMPLETED',
      'payer' => data_get($payload, 'resource.payer', []),
      'purchase_units' => [[
        'payments' => [
          'captures' => [$resource],
        ],
      ]],
    ]);

    return $this->processed($event, 'PayPal capture completed.');
  }

  /**
   * @param  array<string, mixed>  $capture
   */
  private function markPaid(CommerceOrder $order, CommerceWebhookEvent $event, array $capture): void
  {
    $captureId = data_get($capture, 'purchase_units.0.payments.captures.0.id');
    $payerEmail = data_get($capture, 'payer.email_address');
    $payerId = data_get($capture, 'payer.payer_id');

    // Route the status change through the guarded state machine (validates the
    // transition, is idempotent for re-delivered webhooks, keeps stock consumed).
    $this->orders->markPaid($order, [
      'customer_email' => is_string($payerEmail) ? $payerEmail : $order->customer_email,
      'gateway_payment_id' => is_string($captureId) ? $captureId : $order->gateway_payment_id,
      'gateway_customer_id' => is_string($payerId) ? $payerId : $order->gateway_customer_id,
    ]);

    $payment = $order->payments()
      ->where('gateway', 'paypal')
      ->where('gateway_checkout_id', $order->gateway_checkout_id)
      ->first();

    if ($payment === null) {
      $order->payments()->create([
        'gateway' => 'paypal',
        'gateway_payment_id' => is_string($captureId) ? $captureId : null,
        'gateway_checkout_id' => $order->gateway_checkout_id,
        'status' => CommercePayment::STATUS_SUCCEEDED,
        'amount' => $order->total_amount,
        'currency' => $order->currency,
        'raw_event_id' => $event->event_id,
        'metadata' => [
          'paypal_status' => $capture['status'] ?? null,
        ],
      ]);

      return;
    }

    $payment->update([
      'gateway_payment_id' => is_string($captureId) ? $captureId : $payment->gateway_payment_id,
      'status' => CommercePayment::STATUS_SUCCEEDED,
      'raw_event_id' => $event->event_id,
      'metadata' => [
        'paypal_status' => $capture['status'] ?? null,
      ],
    ]);
  }

  private function orderByCheckoutId(string $paypalOrderId): ?CommerceOrder
  {
    return CommerceOrder::query()
      ->where('gateway', 'paypal')
      ->where('gateway_checkout_id', $paypalOrderId)
      ->with('payments')
      ->first();
  }

  private function processed(CommerceWebhookEvent $event, string $message): string
  {
    return $this->finish($event, CommerceWebhookEvent::STATUS_PROCESSED, $message);
  }

  private function ignore(CommerceWebhookEvent $event, string $message): string
  {
    return $this->finish($event, CommerceWebhookEvent::STATUS_IGNORED, $message);
  }

  private function fail(CommerceWebhookEvent $event, string $message): string
  {
    return $this->finish($event, CommerceWebhookEvent::STATUS_FAILED, $message);
  }

  private function finish(CommerceWebhookEvent $event, string $status, string $message): string
  {
    $event->update([
      'status' => $status,
      'processed_at' => now(),
      'message' => $message,
    ]);

    return $status;
  }

  /**
   * @param  array<string, mixed>  $payload
   */
  private function eventId(array $payload, Request $request): string
  {
    $id = $payload['id'] ?? null;

    if (is_string($id) && trim($id) !== '') {
      return $id;
    }

    $transmissionId = $request->header('PAYPAL-TRANSMISSION-ID');

    return is_string($transmissionId) && trim($transmissionId) !== ''
      ? $transmissionId
      : hash('sha256', $request->getContent());
  }

  /**
   * @param  array<string, mixed>  $payload
   */
  private function eventType(array $payload): string
  {
    $type = $payload['event_type'] ?? null;

    return is_string($type) && trim($type) !== '' ? $type : 'unknown';
  }
}
