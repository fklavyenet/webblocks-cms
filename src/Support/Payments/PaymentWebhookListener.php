<?php

namespace WebBlocks\Cms\Support\Payments;

/** A notification consumer must authenticate and re-read provider evidence. */
interface PaymentWebhookListener
{
  public function handle(string $provider, array $event, array $headers): bool;
}
