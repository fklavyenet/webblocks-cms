<?php

namespace App\Support\System;

use Illuminate\Support\Facades\Log;

class UpdateChecker
{
    public function __construct(
        private readonly InstalledVersionStore $installedVersionStore,
        private readonly LocalConfigUpdateSource $localConfigUpdateSource,
        private readonly RemoteManifestUpdateSource $remoteManifestUpdateSource,
    ) {}

    public function status(bool $refresh = false): array
    {
        $currentVersion = $this->installedVersionStore->currentVersion();
        $resolved = $this->resolveSource($refresh);
        $latestVersion = $resolved['source']->getLatestVersion();

        return [
            'up_to_date' => version_compare($currentVersion, $latestVersion, '>='),
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'release_notes' => $resolved['source']->getReleaseNotes(),
            'channel' => $resolved['source']->getChannel(),
            'published_at' => $resolved['source']->getPublishedAt(),
            'source' => $resolved['source']->getSourceName(),
            'fallback_warning' => $resolved['fallback_warning'],
            'last_checked_at' => $resolved['last_checked_at'],
        ];
    }

    public function refresh(): array
    {
        $this->remoteManifestUpdateSource->forgetCachedManifest();

        return $this->status(true);
    }

    private function resolveSource(bool $refresh = false): array
    {
        $preferredSource = (string) config('cms.update.source', 'remote');
        $desiredChannel = (string) config('cms.update.channel', 'stable');

        if ($preferredSource !== 'remote') {
            return [
                'source' => $this->localConfigUpdateSource,
                'fallback_warning' => null,
                'last_checked_at' => now(),
            ];
        }

        try {
            $manifest = $this->remoteManifestUpdateSource->manifest($refresh);

            if (($manifest['channel'] ?? null) !== $desiredChannel) {
                $message = 'Remote manifest channel '.$manifest['channel'].' does not match configured channel '.$desiredChannel.'. Using local fallback.';
                Log::warning($message);

                return [
                    'source' => $this->localConfigUpdateSource,
                    'fallback_warning' => $message,
                    'last_checked_at' => now(),
                ];
            }

            return [
                'source' => $this->remoteManifestUpdateSource,
                'fallback_warning' => null,
                'last_checked_at' => isset($manifest['checked_at']) ? now()->parse((string) $manifest['checked_at']) : now(),
            ];
        } catch (\Throwable $throwable) {
            $message = 'Could not reach update server. Using local update data.';

            Log::warning($message, ['exception' => $throwable->getMessage()]);

            return [
                'source' => $this->localConfigUpdateSource,
                'fallback_warning' => $message,
                'last_checked_at' => now(),
            ];
        }
    }
}
