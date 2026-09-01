<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Contracts;

/**
 * Supplies the extra (anonymous) query parameters an update check sends to the
 * Publisher. Kept behind a contract so telemetry stays opt-in and fully
 * decoupled: the default {@see \WebBlocks\Cms\Support\Updates\Client\Telemetry\NullTelemetryProvider}
 * sends nothing. A real implementation (anonymous install id, php/laravel
 * versions) is added in a later slice and bound per product.
 */
interface TelemetryProvider
{
    /**
     * @return array<string,scalar> Extra query params for the latest-release check.
     */
    public function updateCheckPayload(string $product, string $installedVersion, string $channel): array;
}
