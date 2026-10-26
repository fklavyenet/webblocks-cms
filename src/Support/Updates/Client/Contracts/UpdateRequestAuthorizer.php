<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Contracts;

use Illuminate\Http\Client\PendingRequest;

/** Product-owned credentials; called only for an explicit check/download. */
interface UpdateRequestAuthorizer
{
    public function authorize(PendingRequest $request, string $url, string $purpose, array $context = []): PendingRequest;
}
