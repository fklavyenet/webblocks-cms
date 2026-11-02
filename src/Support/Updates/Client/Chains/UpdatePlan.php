<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Chains;

use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateSignatureVerifier;

/** A bounded, strictly increasing path; all artifact identities are pinned. */
final class UpdatePlan
{
    public const PROTOCOL = 1;
    public const LIMIT = 20;

    public function __construct(public readonly string $product, public readonly string $channel,
        public readonly string $installedVersion, public readonly string $targetVersion, public readonly array $steps)
    {
        $version = '/^v?\d+\.\d+\.\d+(?:-[A-Za-z0-9.-]+)?(?:\+[A-Za-z0-9.-]+)?$/D';
        if ($product === '' || $channel === '' || ! preg_match($version, $installedVersion)
            || ! preg_match($version, $targetVersion) || ! array_is_list($steps) || count($steps) > self::LIMIT) {
            throw new UpdateException('The update plan is invalid.');
        }
        $current = $installedVersion;
        foreach ($steps as $step) {
            if (! is_array($step)) { throw new UpdateException('The update plan is invalid.'); }
            $url = parse_url((string) ($step['artifact_url'] ?? ''));
            if (($step['from_version'] ?? null) !== $current || ($step['product'] ?? null) !== $product
                || ($step['channel'] ?? null) !== $channel || ! preg_match($version, (string) ($step['version'] ?? ''))
                || version_compare(ltrim($step['version'], 'v'), ltrim($current, 'v'), '<=')
                || version_compare(ltrim($step['version'], 'v'), ltrim($targetVersion, 'v'), '>')
                || ! preg_match('/\A[a-f0-9]{64}\z/', (string) ($step['checksum_sha256'] ?? ''))
                || ! is_array($url) || ! in_array($url['scheme'] ?? '', ['https', 'http'], true)
                || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['fragment'])
                || (! empty($step['minimum_client_version']) && (! preg_match($version, $step['minimum_client_version'])
                    || version_compare(ltrim($current, 'v'), ltrim($step['minimum_client_version'], 'v'), '<')))) {
                throw new UpdateException('The update plan is invalid or incompatible.');
            }
            $output = [];
            // Chain execution never permits the optional unsigned legacy mode.
            if (strlen((string) base64_decode((string) ($step['signature'] ?? ''), true)) !== SODIUM_CRYPTO_SIGN_BYTES
                || trim((string) config('publisher-client.signature.public_key')) === '') {
                throw new UpdateException('Every update step requires a release signature.');
            }
            app(UpdateSignatureVerifier::class)->verify($step['checksum_sha256'], $step, $output);
            $current = $step['version'];
        }
        if ($current !== $targetVersion) {
            throw new UpdateException('The update plan does not reach the approved target.');
        }
    }
}
