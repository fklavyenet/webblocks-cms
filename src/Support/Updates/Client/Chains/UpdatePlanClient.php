<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Chains;

use Illuminate\Support\Facades\Http;
use WebBlocks\Cms\Support\Updates\Client\Contracts\UpdateRequestAuthorizer;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;

class UpdatePlanClient
{
    public function fetch(string $installed, ?string $target = null): UpdatePlan
    {
        $product = (string) config('publisher-client.product');
        $channel = (string) config('publisher-client.channel', 'stable');
        $url = rtrim((string) config('publisher-client.server_url'), '/').'/api/updates/plan';
        $request = Http::timeout((int) config('publisher-client.timeout_seconds', 5))
            ->connectTimeout((int) config('publisher-client.connect_timeout_seconds', 3))
            ->withoutRedirecting()->acceptJson();
        $request = app(UpdateRequestAuthorizer::class)->authorize($request, $url, 'check',
            ['product' => $product, 'channel' => $channel, 'installed_version' => $installed]);
        try {
            $response = $request->get($url, array_filter(['product' => $product, 'channel' => $channel,
                'installed_version' => $installed, 'target_version' => $target], static fn ($value) => $value !== null));
            if (! $response->successful()) {
                throw new UpdateException('The approved update plan is unavailable.');
            }
            $data = $response->json('data');
            if (! is_array($data) || ($data['protocol_version'] ?? null) !== UpdatePlan::PROTOCOL
                || ($data['product'] ?? null) !== $product || ($data['channel'] ?? null) !== $channel
                || ($data['installed_version'] ?? null) !== $installed || ! is_string($data['target_version'] ?? null)
                || ($target !== null && $data['target_version'] !== $target) || ! is_array($data['steps'] ?? null)) {
                throw new UpdateException('The update service returned a different update plan.');
            }
            return new UpdatePlan($product, $channel, $installed, $data['target_version'], $data['steps']);
        } catch (UpdateException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new UpdateException('The approved update plan is unavailable.', previous: $exception);
        }
    }

    public function refreshStep(array $approved): array
    {
        $plan = $this->fetch($approved['from_version'], $approved['version']);
        $actual = $plan->steps[0] ?? [];
        foreach (['from_version', 'version', 'checksum_sha256', 'signature', 'product', 'channel'] as $key) {
            if (count($plan->steps) !== 1 || ($approved[$key] ?? null) !== ($actual[$key] ?? null)) {
                throw new UpdateException('The approved update artifact changed or became unavailable.');
            }
        }
        // Delivery URLs may be refreshed; the signed artifact identity cannot change.
        return $actual;
    }
}
