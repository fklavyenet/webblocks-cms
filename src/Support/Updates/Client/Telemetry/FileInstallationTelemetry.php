<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Telemetry;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Throwable;
use WebBlocks\Cms\Support\Updates\Client\Contracts\TelemetryProvider;

/** Product-scoped production pseudonyms; no domains, IPs or personal fields. */
class FileInstallationTelemetry implements TelemetryProvider
{
    public function enabled(): bool
    {
        return filter_var(config('publisher-client.telemetry.enabled', true), FILTER_VALIDATE_BOOL)
            && app()->environment('production') && ! app()->runningUnitTests();
    }

    public function installationId(?string $product = null): ?string
    {
        $product ??= (string) config('publisher-client.product', '');
        if (! $this->enabled() || $product === '') {
            return null;
        }

        // The application secret stays local. A product-specific HMAC provides
        // recovery after storage loss without deriving identity from site/user data.
        $key = (string) config('app.key', '');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: '';
        }
        if (strlen($key) < 32) {
            return null;
        }
        $id = hash_hmac('sha256', 'webblocks-production-telemetry-v2|'.$product, $key);
        $path = storage_path(trim((string) config('publisher-client.apply.workspace_root', 'app/publisher-client'), '/').'/production-identity-'.hash('sha256', $product));
        try {
            if (File::isFile($path)) {
                $existing = trim((string) File::get($path));
                if (preg_match('/\A[a-f0-9]{64}\z/', $existing)) {
                    return $existing;
                }
            }
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $id);
        } catch (Throwable) {
            // The same local application key still gives a stable identity when
            // storage is read-only. Telemetry never blocks update delivery.
        }

        return $id;
    }

    public function updateCheckPayload(string $product, string $installedVersion, string $channel): array
    {
        $id = $this->installationId($product);
        if ($id === null) {
            return [];
        }

        return [
            'product_key' => $product,
            'installed_version' => $installedVersion,
            'channel' => $channel,
            'installation_id' => $id,
            'environment' => 'production',
            'php_version' => PHP_VERSION,
            'laravel_version' => Application::VERSION,
            'telemetry_schema_version' => '2',
        ];
    }
}
