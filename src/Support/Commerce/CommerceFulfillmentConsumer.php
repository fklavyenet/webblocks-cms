<?php

namespace WebBlocks\Cms\Support\Commerce;

/** Transport authenticates and retrieves scoped canonical context before this port. */
interface CommerceFulfillmentConsumer
{
    /** Accept durably; an accepted receipt does not assert completed fulfillment. */
    public function accept(array $event, array $fulfillment): array;

    /** Return the persisted receipt without repeating the business effect. */
    public function status(string $fulfillmentRef): array;
}
