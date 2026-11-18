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

        $path = storage_path(trim((string) config('publisher-client.apply.workspace_root', 'app/publisher-client'), '/').'/production-identity-'.hash('sha256', $product));
        try {
            if (File::isFile($path)) {
                $existing = $this->storedIdentity(File::get($path, true));
                if ($existing !== null) {
                    return $existing;
                }
            }
        } catch (Throwable) {
            // An unreadable identity may already have been reported.
            return null;
        }

        // Preserve recovery for identities already derived by older releases.
        $key = (string) config('app.key', '');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: '';
        }
        $legacyId = strlen($key) >= 32
            ? hash_hmac('sha256', 'webblocks-production-telemetry-v2|'.$product, $key)
            : null;

        try {
            File::ensureDirectoryExists(dirname($path));
            $file = @fopen($path, 'c+b');
        } catch (Throwable) {
            return $legacyId;
        }
        if ($file === false) {
            return $legacyId;
        }

        try {
            if (! flock($file, LOCK_EX)) {
                return null;
            }
            $contents = stream_get_contents($file);
            if ($contents === false) {
                return null;
            }
            // Another request may have persisted an identity while we waited.
            $existing = $this->storedIdentity($contents);
            if ($existing !== null) {
                return $existing;
            }

            $id = $legacyId ?? bin2hex(random_bytes(32));
            if (! rewind($file) || ! ftruncate($file, 0) || fwrite($file, $id) !== strlen($id) || ! fflush($file)) {
                return $legacyId;
            }

            return $id;
        } catch (Throwable) {
            return null;
        } finally {
            fclose($file);
        }
    }

    private function storedIdentity(string $contents): ?string
    {
        $id = trim($contents);

        return preg_match('/\A[a-f0-9]{64}\z/', $id) ? $id : null;
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
