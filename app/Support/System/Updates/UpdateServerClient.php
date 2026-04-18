<?php

namespace App\Support\System\Updates;

use App\Support\System\InstalledVersionStore;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class UpdateServerClient
{
    public function __construct(
        private readonly InstalledVersionStore $installedVersionStore,
    ) {}

    public function check(): UpdateCheckResult
    {
        $serverUrl = rtrim((string) config('webblocks-updates.client.server_url', ''), '/');
        $product = (string) config('webblocks-updates.client.product', 'webblocks-cms');
        $channel = (string) config('webblocks-updates.client.channel', 'stable');
        $installedVersion = $this->installedVersionStore->currentVersion();

        if (! config('webblocks-updates.enabled', true) || ! config('webblocks-updates.client.enabled', true)) {
            return $this->result(
                state: 'client_disabled',
                label: 'Update checks disabled',
                message: 'The CMS update client is disabled in configuration.',
                badgeClass: 'wb-status-danger',
                serverReachable: false,
                apiVersion: null,
                serverUrl: $serverUrl,
                product: $product,
                channel: $channel,
                installedVersion: $installedVersion,
                latestVersion: null,
                updateAvailable: false,
                compatibility: ['status' => 'unknown', 'reasons' => []],
                release: null,
                errorCode: 'client_disabled',
                errorMessage: 'The CMS update client is disabled in configuration.',
            );
        }

        if ($serverUrl === '') {
            return $this->result(
                state: 'invalid_configuration',
                label: 'Update server missing',
                message: 'Configure an update server URL before checking for updates.',
                badgeClass: 'wb-status-danger',
                serverReachable: false,
                apiVersion: null,
                serverUrl: $serverUrl,
                product: $product,
                channel: $channel,
                installedVersion: $installedVersion,
                latestVersion: null,
                updateAvailable: false,
                compatibility: ['status' => 'unknown', 'reasons' => []],
                release: null,
                errorCode: 'invalid_configuration',
                errorMessage: 'Configure an update server URL before checking for updates.',
            );
        }

        $request = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('webblocks-updates.client.timeout_seconds', 5))
            ->connectTimeout((int) config('webblocks-updates.client.connect_timeout_seconds', 3))
            ->withHeaders(array_filter([
                'User-Agent' => 'WebBlocks-CMS/'.$installedVersion,
                'X-WebBlocks-Site-Url' => (string) config('webblocks-updates.client.site_url', config('app.url')),
                'X-WebBlocks-Instance-Id' => config('webblocks-updates.client.instance_id'),
            ], fn ($value): bool => is_string($value) && $value !== ''));

        $retryTimes = (int) config('webblocks-updates.client.retry_times', 0);

        if ($retryTimes > 0) {
            $request = $request->retry($retryTimes, (int) config('webblocks-updates.client.retry_sleep_milliseconds', 150));
        }

        try {
            $response = $request->get($serverUrl.'/api/updates/'.$product.'/latest', [
                'channel' => $channel,
                'installed_version' => $installedVersion,
                'php_version' => PHP_VERSION,
                'laravel_version' => Application::VERSION,
            ]);
        } catch (ConnectionException $exception) {
            return $this->result(
                state: 'server_unreachable',
                label: 'Update server unavailable',
                message: 'The update server could not be reached within the configured timeout.',
                badgeClass: 'wb-status-danger',
                serverReachable: false,
                apiVersion: null,
                serverUrl: $serverUrl,
                product: $product,
                channel: $channel,
                installedVersion: $installedVersion,
                latestVersion: null,
                updateAvailable: false,
                compatibility: ['status' => 'unknown', 'reasons' => []],
                release: null,
                errorCode: 'server_unreachable',
                errorMessage: $exception->getMessage(),
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return $this->result(
                state: 'invalid_response',
                label: 'Invalid update response',
                message: 'The update server returned malformed JSON.',
                badgeClass: 'wb-status-danger',
                serverReachable: $response->successful(),
                apiVersion: null,
                serverUrl: $serverUrl,
                product: $product,
                channel: $channel,
                installedVersion: $installedVersion,
                latestVersion: null,
                updateAvailable: false,
                compatibility: ['status' => 'unknown', 'reasons' => []],
                release: null,
                errorCode: 'invalid_response',
                errorMessage: 'The update server returned malformed JSON.',
            );
        }

        if (! $response->successful()) {
            $errorCode = Arr::get($payload, 'error.code');
            $errorMessage = Arr::get($payload, 'error.message', 'The update server returned an unexpected error.');

            if ($response->status() === 404 && $errorCode === 'release_not_found') {
                return $this->result(
                    state: 'no_releases',
                    label: 'No releases found',
                    message: $errorMessage,
                    badgeClass: 'wb-status-pending',
                    serverReachable: true,
                    apiVersion: Arr::get($payload, 'api_version'),
                    serverUrl: $serverUrl,
                    product: $product,
                    channel: $channel,
                    installedVersion: $installedVersion,
                    latestVersion: null,
                    updateAvailable: false,
                    compatibility: ['status' => 'unknown', 'reasons' => []],
                    release: null,
                    errorCode: $errorCode,
                    errorMessage: $errorMessage,
                );
            }

            return $this->result(
                state: 'server_error',
                label: 'Update server error',
                message: $errorMessage,
                badgeClass: 'wb-status-danger',
                serverReachable: true,
                apiVersion: Arr::get($payload, 'api_version'),
                serverUrl: $serverUrl,
                product: $product,
                channel: $channel,
                installedVersion: $installedVersion,
                latestVersion: null,
                updateAvailable: false,
                compatibility: ['status' => 'unknown', 'reasons' => []],
                release: null,
                errorCode: is_string($errorCode) ? $errorCode : 'server_error',
                errorMessage: is_string($errorMessage) ? $errorMessage : 'The update server returned an unexpected error.',
            );
        }

        return $this->fromSuccessfulPayload($payload, $serverUrl, $product, $channel, $installedVersion);
    }

    private function fromSuccessfulPayload(array $payload, string $serverUrl, string $product, string $channel, string $installedVersion): UpdateCheckResult
    {
        $apiVersion = Arr::get($payload, 'api_version');

        if ((string) $apiVersion !== (string) config('webblocks-updates.api_version', '1')) {
            return $this->result(
                state: 'unsupported_api_version',
                label: 'Unsupported update API',
                message: 'The update server responded with an unsupported API version.',
                badgeClass: 'wb-status-danger',
                serverReachable: true,
                apiVersion: is_string($apiVersion) ? $apiVersion : null,
                serverUrl: $serverUrl,
                product: $product,
                channel: $channel,
                installedVersion: $installedVersion,
                latestVersion: null,
                updateAvailable: false,
                compatibility: ['status' => 'unknown', 'reasons' => []],
                release: null,
                errorCode: 'unsupported_api_version',
                errorMessage: 'The update server responded with an unsupported API version.',
            );
        }

        $data = Arr::get($payload, 'data');

        if (! is_array($data)) {
            return $this->invalidShape($serverUrl, $product, $channel, $installedVersion);
        }

        $latestVersion = Arr::get($data, 'latest_version');
        $updateAvailable = Arr::get($data, 'update_available');
        $compatibilityStatus = Arr::get($data, 'compatibility.status');
        $compatibilityReasons = Arr::get($data, 'compatibility.reasons');
        $release = Arr::get($data, 'release');

        if (! is_string($latestVersion) || ! is_bool($updateAvailable) || ! is_string($compatibilityStatus) || ! is_array($compatibilityReasons) || ! is_array($release)) {
            return $this->invalidShape($serverUrl, $product, $channel, $installedVersion);
        }

        $state = 'up_to_date';
        $label = 'Already up to date';
        $message = 'This install already matches the latest published release for the selected channel.';
        $badgeClass = 'wb-status-active';

        if ($updateAvailable && $compatibilityStatus === 'incompatible') {
            $state = 'incompatible';
            $label = 'Incompatible update available';
            $message = 'A newer release exists, but this install does not meet its compatibility requirements.';
            $badgeClass = 'wb-status-danger';
        } elseif ($updateAvailable) {
            $state = 'update_available';
            $label = 'Update available';
            $message = 'A newer published release is available from the configured update server.';
            $badgeClass = 'wb-status-info';
        }

        return $this->result(
            state: $state,
            label: $label,
            message: $message,
            badgeClass: $badgeClass,
            serverReachable: true,
            apiVersion: (string) $apiVersion,
            serverUrl: $serverUrl,
            product: (string) Arr::get($data, 'product', $product),
            channel: (string) Arr::get($data, 'channel', $channel),
            installedVersion: (string) Arr::get($data, 'installed_version', $installedVersion),
            latestVersion: $latestVersion,
            updateAvailable: $updateAvailable,
            compatibility: [
                'status' => $compatibilityStatus,
                'reasons' => array_values(array_filter($compatibilityReasons, 'is_string')),
            ],
            release: $release,
            errorCode: null,
            errorMessage: null,
        );
    }

    private function invalidShape(string $serverUrl, string $product, string $channel, string $installedVersion): UpdateCheckResult
    {
        return $this->result(
            state: 'invalid_response',
            label: 'Invalid update response',
            message: 'The update server response is missing required keys.',
            badgeClass: 'wb-status-danger',
            serverReachable: true,
            apiVersion: null,
            serverUrl: $serverUrl,
            product: $product,
            channel: $channel,
            installedVersion: $installedVersion,
            latestVersion: null,
            updateAvailable: false,
            compatibility: ['status' => 'unknown', 'reasons' => []],
            release: null,
            errorCode: 'invalid_response',
            errorMessage: 'The update server response is missing required keys.',
        );
    }

    private function result(
        string $state,
        string $label,
        string $message,
        string $badgeClass,
        bool $serverReachable,
        ?string $apiVersion,
        string $serverUrl,
        string $product,
        string $channel,
        string $installedVersion,
        ?string $latestVersion,
        bool $updateAvailable,
        array $compatibility,
        ?array $release,
        ?string $errorCode,
        ?string $errorMessage,
    ): UpdateCheckResult {
        return new UpdateCheckResult(
            state: $state,
            label: $label,
            message: $message,
            badgeClass: $badgeClass,
            serverReachable: $serverReachable,
            apiVersion: $apiVersion,
            serverUrl: $serverUrl,
            product: $product,
            channel: $channel,
            installedVersion: $installedVersion,
            latestVersion: $latestVersion,
            updateAvailable: $updateAvailable,
            compatibility: $compatibility,
            release: $release,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            checkedAt: CarbonImmutable::now(),
        );
    }
}
