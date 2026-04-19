<?php

namespace App\Support\Updates;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;

class PublishReleasePayloadBuilder
{
    public function build(array $input): array
    {
        $version = (string) ($input['version'] ?? '');
        $channel = (string) ($input['channel'] ?? config('webblocks-updates.publish.channel', 'stable'));
        $sourceUrl = $this->stringOrNull($input['source_url'] ?? null);
        $tag = $this->stringOrNull($input['tag'] ?? null);
        $commit = $this->gitOutput('git rev-parse HEAD');

        return [
            'product' => (string) config('webblocks-updates.publish.product', 'webblocks-cms'),
            'version' => $version,
            'channel' => $channel,
            'released_at' => now()->toIso8601String(),
            'notes' => $this->stringOrNull($input['notes'] ?? null),
            'source' => [
                'type' => 'github',
                'url' => $sourceUrl,
                'reference' => $tag ?? $commit,
            ],
            'meta' => [
                'app_name' => (string) config('app.name', 'WebBlocks CMS'),
                'app_version' => $version,
                'commit' => $commit,
                'tag' => $tag,
                'php_version' => PHP_VERSION,
                'laravel_version' => Application::VERSION,
            ],
        ];
    }

    public function resolveVersion(?string $version): ?string
    {
        if (is_string($version) && trim($version) !== '') {
            return trim($version);
        }

        $appVersion = (string) config('app.version', '');

        if ($appVersion !== '') {
            return $appVersion;
        }

        $composer = json_decode((string) File::get(base_path('composer.json')), true);
        $composerVersion = is_array($composer) ? ($composer['version'] ?? null) : null;

        return is_string($composerVersion) && $composerVersion !== '' ? $composerVersion : null;
    }

    private function gitOutput(string $command): ?string
    {
        $output = [];
        $status = 0;
        exec($command.' 2>/dev/null', $output, $status);

        if ($status !== 0) {
            return null;
        }

        $value = trim($output[0] ?? '');

        return $value !== '' ? $value : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
