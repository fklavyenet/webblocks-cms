<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use Illuminate\Http\Client\PendingRequest;
use WebBlocks\Cms\Support\Updates\Client\Contracts\UpdateRequestAuthorizer;

final class NullUpdateRequestAuthorizer implements UpdateRequestAuthorizer
{
    public function authorize(PendingRequest $request, string $url, string $purpose, array $context = []): PendingRequest
    {
        return $request;
    }
}
