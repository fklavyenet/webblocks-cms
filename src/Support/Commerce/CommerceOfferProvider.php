<?php

namespace WebBlocks\Cms\Support\Commerce;

/** Product-neutral port. Arrays follow Commerce Integration Contract v1. */
interface CommerceOfferProvider
{
    /** @return array<string, mixed> Negotiated contract, operations, modes and kinds. */
    public function capabilities(): array;

    /** @return array{offers: list<array<string, mixed>>, next_cursor: ?string} */
    public function offers(?string $cursor = null): array;

    /** Validate fresh commercial terms without charging or creating an entitlement. */
    public function quote(array $selection): array;

    /** Durably bind a quote to an order/line; retry the same request ID on ambiguity. */
    public function prepare(array $command): array;

    /** Resolve an ambiguous preparation result using its stable request ID. */
    public function intent(string $requestId): array;
}
