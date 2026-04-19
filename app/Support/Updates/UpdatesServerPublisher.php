<?php

namespace App\Support\Updates;

use App\Models\SystemReleasePublish;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class UpdatesServerPublisher
{
    public function __construct(
        private readonly PublishReleasePayloadBuilder $payloadBuilder,
    ) {}

    public function publish(array $input, bool $dryRun = false): array
    {
        if (! config('webblocks-updates.publish.enabled', true)) {
            throw new UpdatesServerPublishException('Release publishing is disabled in configuration.');
        }

        $serverUrl = rtrim((string) config('webblocks-updates.publish.server_url', ''), '/');
        $token = (string) config('webblocks-updates.publish.token', '');
        $version = $this->payloadBuilder->resolveVersion($input['version'] ?? null);

        if (! is_string($version) || $version === '') {
            throw new UpdatesServerPublishException('Release version is required for publish.');
        }

        if ($serverUrl === '') {
            throw new UpdatesServerPublishException('webblocks-updates.publish.server_url is missing.');
        }

        if ($token === '') {
            throw new UpdatesServerPublishException('webblocks-updates.publish.token is missing.');
        }

        $payload = $this->payloadBuilder->build($input + ['version' => $version]);

        if ($dryRun) {
            return [
                'endpoint' => $serverUrl.'/api/updates/publish',
                'payload' => $payload,
                'dry_run' => true,
            ];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken($token)
                ->timeout((int) config('webblocks-updates.publish.timeout', 30))
                ->post($serverUrl.'/api/updates/publish', $payload);
        } catch (ConnectionException $exception) {
            $this->logFailure($version, $payload['channel'], $payload, $exception->getMessage());

            throw new UpdatesServerPublishException('Publish request failed: '.$exception->getMessage(), previous: $exception);
        }

        $body = $response->json();

        if (! $response->successful()) {
            $message = is_array($body) ? json_encode($body, JSON_UNESCAPED_SLASHES) : $response->body();
            $this->logFailure($version, $payload['channel'], $payload, (string) $message, is_array($body) ? $body : null);

            throw new UpdatesServerPublishException('Publish request failed with status '.$response->status().': '.$message);
        }

        $this->logSuccess($version, $payload['channel'], $payload, is_array($body) ? $body : ['raw' => $response->body()]);

        return [
            'endpoint' => $serverUrl.'/api/updates/publish',
            'payload' => $payload,
            'response' => $body,
            'download_url' => Arr::get($body, 'data.release.download_url'),
        ];
    }

    private function logSuccess(string $version, string $channel, array $payload, array $response): void
    {
        SystemReleasePublish::query()->create([
            'version' => $version,
            'channel' => $channel,
            'status' => 'success',
            'request_payload' => $payload,
            'response_payload' => $response,
            'published_at' => now(),
        ]);
    }

    private function logFailure(string $version, string $channel, array $payload, string $errorMessage, ?array $response = null): void
    {
        SystemReleasePublish::query()->create([
            'version' => $version,
            'channel' => $channel,
            'status' => 'failed',
            'request_payload' => $payload,
            'response_payload' => $response,
            'error_message' => $errorMessage,
        ]);
    }
}
