<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceOrder;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommercePayment;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceWebhookEvent;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Checkout\CheckoutUnavailableException;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\SumUpApiClient;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Gateways\SumUpConfig;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Orders\OrderStateMachine;

class HandleSumUpWebhook
{
  public function __construct(
    private readonly SumUpApiClient $client,
    private readonly SumUpConfig $config,
    private readonly OrderStateMachine $orders,
  ) {}

  /**
   * @return array{status: string, event_id: string}
   */
  public function handle(Request $request): array
  {
    if (! $this->config->isWebhookReady()) {
      throw new CheckoutUnavailableException('SumUp webhook is not configured yet.');
    }

    $payload = $request->all();
    $eventType = $payload['event_type'] ?? null;
    $checkoutId = $payload['id'] ?? null;

    if (! is_string($eventType) || ! is_string($checkoutId) || trim($checkoutId) === '') {
      throw new WebhookVerificationException('SumUp webhook payload is incomplete.');
    }

    if ($eventType !== 'CHECKOUT_STATUS_CHANGED') {
      return $this->recordIgnored($request, $eventType, $checkoutId, 'SumUp event type is not handled.');
    }

    // SumUp webhooks are notifications rather than signed proof. SumUp's API
    // documentation requires retrieving the checkout before trusting the event.
    $checkout = $this->client->retrieveCheckout($checkoutId);
    $verifiedId = $checkout['id'] ?? null;
    $sumUpStatus = strtoupper(trim((string) ($checkout['status'] ?? 'UNKNOWN')));

    if (! is_string($verifiedId) || ! hash_equals($checkoutId, $verifiedId)) {
      throw new WebhookVerificationException('SumUp checkout identity could not be verified.');
    }

    $eventId = $checkoutId.':'.strtolower($sumUpStatus);
    $existing = $this->existingFinishedEvent($eventId);

    if ($existing !== null) {
      return ['status' => $existing->status, 'event_id' => $existing->event_id];
    }

    $event = CommerceWebhookEvent::query()->firstOrCreate([
      'gateway' => 'sumup',
      'event_id' => $eventId,
    ], [
      'event_type' => $eventType,
      'payload_digest' => hash('sha256', $request->getContent()),
      'status' => CommerceWebhookEvent::STATUS_RECEIVED,
    ]);

    return DB::transaction(function () use ($event, $checkout, $checkoutId, $sumUpStatus): array {
      $order = CommerceOrder::query()
        ->where('gateway', 'sumup')
        ->where('gateway_checkout_id', $checkoutId)
        ->with('payments')
        ->first();

      if ($order === null) {
        return $this->result($event, CommerceWebhookEvent::STATUS_IGNORED, 'No matching WebBlocks Commerce order was found.');
      }

      if (! $this->checkoutMatchesOrder($checkout, $order)) {
        return $this->result($event, CommerceWebhookEvent::STATUS_FAILED, 'SumUp checkout totals or merchant reference did not match the order.');
      }

      return match ($sumUpStatus) {
        'PAID' => $this->handlePaid($event, $order, $checkout),
        'FAILED' => $this->handleFailed($event, $order, $checkout),
        'EXPIRED' => $this->handleExpired($event, $order, $checkout),
        default => $this->result($event, CommerceWebhookEvent::STATUS_IGNORED, 'SumUp checkout is not in a terminal state.'),
      };
    });
  }

  /**
   * @param  array<string, mixed>  $checkout
   * @return array{status: string, event_id: string}
   */
  private function handlePaid(CommerceWebhookEvent $event, CommerceOrder $order, array $checkout): array
  {
    $transaction = collect(is_array($checkout['transactions'] ?? null) ? $checkout['transactions'] : [])
      ->first(fn (mixed $candidate): bool => is_array($candidate) && strtoupper((string) ($candidate['status'] ?? '')) === 'SUCCESSFUL');
    $transactionId = is_array($transaction) ? ($transaction['id'] ?? null) : ($checkout['transaction_id'] ?? null);

    if (! is_string($transactionId) || trim($transactionId) === '') {
      return $this->result($event, CommerceWebhookEvent::STATUS_FAILED, 'Paid SumUp checkout did not include a successful transaction.');
    }

    if (is_array($transaction)
      && ((int) round(((float) ($transaction['amount'] ?? -1)) * 100) !== (int) $order->total_amount
        || strtoupper((string) ($transaction['currency'] ?? '')) !== $order->currency)) {
      return $this->result($event, CommerceWebhookEvent::STATUS_FAILED, 'SumUp transaction totals did not match the order.');
    }

    if ($order->status === CommerceOrder::STATUS_PENDING) {
      $this->orders->markPaid($order, ['gateway_payment_id' => $transactionId]);
    } elseif ($order->status !== CommerceOrder::STATUS_PAID) {
      return $this->result($event, CommerceWebhookEvent::STATUS_IGNORED, 'Order is already in a terminal non-paid state.');
    }

    $this->updatePayment($order, $event, CommercePayment::STATUS_SUCCEEDED, $transactionId, $checkout);

    return $this->result($event, CommerceWebhookEvent::STATUS_PROCESSED, 'SumUp checkout payment was verified.');
  }

  /**
   * @param  array<string, mixed>  $checkout
   * @return array{status: string, event_id: string}
   */
  private function handleFailed(CommerceWebhookEvent $event, CommerceOrder $order, array $checkout): array
  {
    if ($order->status !== CommerceOrder::STATUS_PENDING && $order->status !== CommerceOrder::STATUS_FAILED) {
      return $this->result($event, CommerceWebhookEvent::STATUS_IGNORED, 'Order is already in a different terminal state.');
    }

    $this->orders->markFailed($order);

    $this->updatePayment($order, $event, CommercePayment::STATUS_FAILED, null, $checkout);

    return $this->result($event, CommerceWebhookEvent::STATUS_PROCESSED, 'SumUp checkout failure was recorded.');
  }

  /**
   * @param  array<string, mixed>  $checkout
   * @return array{status: string, event_id: string}
   */
  private function handleExpired(CommerceWebhookEvent $event, CommerceOrder $order, array $checkout): array
  {
    if ($order->status !== CommerceOrder::STATUS_PENDING && $order->status !== CommerceOrder::STATUS_EXPIRED) {
      return $this->result($event, CommerceWebhookEvent::STATUS_IGNORED, 'Order is already in a different terminal state.');
    }

    $this->orders->expire($order);

    $this->updatePayment($order, $event, CommercePayment::STATUS_CANCELLED, null, $checkout);

    return $this->result($event, CommerceWebhookEvent::STATUS_PROCESSED, 'SumUp checkout expiration was recorded.');
  }

  /**
   * @param  array<string, mixed>  $checkout
   */
  private function checkoutMatchesOrder(array $checkout, CommerceOrder $order): bool
  {
    $merchantCode = $checkout['merchant_code'] ?? null;
    $reference = $checkout['checkout_reference'] ?? null;
    $currency = $checkout['currency'] ?? null;
    $amount = $checkout['amount'] ?? null;

    return is_string($merchantCode)
      && is_string($reference)
      && is_string($currency)
      && is_numeric($amount)
      && hash_equals((string) $this->config->merchantCode(), $merchantCode)
      && hash_equals($order->order_number, $reference)
      && strtoupper($currency) === $order->currency
      && (int) round(((float) $amount) * 100) === (int) $order->total_amount;
  }

  /**
   * @param  array<string, mixed>  $checkout
   */
  private function updatePayment(
    CommerceOrder $order,
    CommerceWebhookEvent $event,
    string $status,
    ?string $transactionId,
    array $checkout,
  ): void {
    $attributes = [
      'gateway_payment_id' => $transactionId,
      'status' => $status,
      'raw_event_id' => $event->event_id,
      'metadata' => [
        'sumup_status' => $checkout['status'] ?? null,
        'sumup_transaction_code' => $checkout['transaction_code'] ?? data_get($checkout, 'transactions.0.transaction_code'),
      ],
    ];

    $payment = $order->payments()
      ->where('gateway', 'sumup')
      ->where('gateway_checkout_id', $order->gateway_checkout_id)
      ->first();

    if ($payment !== null) {
      $payment->update($attributes);

      return;
    }

    $order->payments()->create($attributes + [
      'gateway' => 'sumup',
      'gateway_checkout_id' => $order->gateway_checkout_id,
      'amount' => $order->total_amount,
      'currency' => $order->currency,
    ]);
  }

  private function existingFinishedEvent(string $eventId): ?CommerceWebhookEvent
  {
    return CommerceWebhookEvent::query()
      ->where('gateway', 'sumup')
      ->where('event_id', $eventId)
      ->whereIn('status', [CommerceWebhookEvent::STATUS_PROCESSED, CommerceWebhookEvent::STATUS_IGNORED])
      ->first();
  }

  /**
   * @return array{status: string, event_id: string}
   */
  private function recordIgnored(Request $request, string $eventType, string $checkoutId, string $message): array
  {
    $event = CommerceWebhookEvent::query()->firstOrCreate([
      'gateway' => 'sumup',
      'event_id' => $checkoutId.':'.hash('sha256', $eventType),
    ], [
      'event_type' => $eventType,
      'payload_digest' => hash('sha256', $request->getContent()),
      'status' => CommerceWebhookEvent::STATUS_RECEIVED,
    ]);

    return $this->result($event, CommerceWebhookEvent::STATUS_IGNORED, $message);
  }

  /**
   * @return array{status: string, event_id: string}
   */
  private function result(CommerceWebhookEvent $event, string $status, string $message): array
  {
    $event->update([
      'status' => $status,
      'processed_at' => now(),
      'message' => $message,
    ]);

    return ['status' => $status, 'event_id' => $event->event_id];
  }
}
