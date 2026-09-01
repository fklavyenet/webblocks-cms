<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Telemetry;

use WebBlocks\Cms\Support\Updates\Client\Contracts\TelemetryProvider;

/**
 * Default telemetry provider: sends nothing. Telemetry is opt-in (§ config
 * telemetry.enabled defaults false); a real provider replaces this binding.
 */
final class NullTelemetryProvider implements TelemetryProvider
{
    public function updateCheckPayload(string $product, string $installedVersion, string $channel): array
    {
        return [];
    }
}
