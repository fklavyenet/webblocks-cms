<?php

namespace App\Support\System;

use App\Support\System\Contracts\UpdateSource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RemoteManifestUpdateSource implements UpdateSource
{
    public const CACHE_KEY = 'cms.update.manifest';

    public function manifest(bool $refresh = false): array
    {
        $url = (string) config('cms.update.manifest_url');

        if ($url === '') {
            throw new RuntimeException('CMS_UPDATE_MANIFEST_URL is not configured.');
        }

        if ($refresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinutes((int) config('cms.update.cache_minutes', 10)), function () use ($url) {
            $response = Http::timeout((int) config('cms.update.timeout_seconds', 5))->get($url);

            if (! $response->successful()) {
                throw new RuntimeException('Update manifest request failed with status '.$response->status().'.');
            }

            $manifest = $response->json();

            if (! is_array($manifest)) {
                throw new RuntimeException('Update manifest did not return a valid JSON object.');
            }

            $version = Arr::get($manifest, 'version');
            $channel = Arr::get($manifest, 'channel');
            $releaseNotes = Arr::get($manifest, 'release_notes');
            $publishedAt = Arr::get($manifest, 'published_at');

            if (! is_string($version) || $version === '') {
                throw new RuntimeException('Update manifest is missing a valid version field.');
            }

            if (! is_string($channel) || $channel === '') {
                throw new RuntimeException('Update manifest is missing a valid channel field.');
            }

            if (! is_array($releaseNotes)) {
                throw new RuntimeException('Update manifest is missing a valid release_notes array.');
            }

            return [
                'version' => $version,
                'channel' => $channel,
                'release_notes' => array_values(array_filter($releaseNotes, 'is_string')),
                'published_at' => is_string($publishedAt) ? $publishedAt : null,
                'checked_at' => now()->toIso8601String(),
            ];
        });
    }

    public function forgetCachedManifest(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function getLatestVersion(): string
    {
        return (string) Arr::get($this->manifest(), 'version', config('app.version', '0.1.0'));
    }

    public function getReleaseNotes(): array
    {
        return Arr::get($this->manifest(), 'release_notes', []);
    }

    public function getChannel(): string
    {
        return (string) Arr::get($this->manifest(), 'channel', config('cms.update.channel', 'stable'));
    }

    public function getPublishedAt(): ?string
    {
        $publishedAt = Arr::get($this->manifest(), 'published_at');

        return is_string($publishedAt) ? $publishedAt : null;
    }

    public function getSourceName(): string
    {
        return 'remote';
    }
}
