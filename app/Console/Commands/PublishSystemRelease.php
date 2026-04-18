<?php

namespace App\Console\Commands;

use App\Models\SystemRelease;
use App\Support\System\Updates\VersionComparator;
use Illuminate\Console\Command;

class PublishSystemRelease extends Command
{
    protected $signature = 'system-release:publish
        {version : Semantic version for the release}
        {download_url : Download URL for the release package}
        {--product=webblocks-cms : Product slug}
        {--channel=stable : Release channel}
        {--name= : Release name}
        {--description= : Short description}
        {--changelog= : Changelog text}
        {--checksum= : SHA-256 checksum}
        {--published-at= : Publication timestamp}
        {--critical : Mark release as critical}
        {--security : Mark release as security}
        {--supported-from-version= : Minimum supported installed version}
        {--supported-until-version= : Maximum supported installed version}
        {--min-php-version= : Minimum PHP version}
        {--min-laravel-version= : Minimum Laravel version}';

    protected $description = 'Create or update a published system release entry';

    public function handle(VersionComparator $versionComparator): int
    {
        $version = (string) $this->argument('version');

        try {
            $normalized = $versionComparator->normalize($version);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $release = SystemRelease::query()->updateOrCreate(
            [
                'product' => (string) $this->option('product'),
                'channel' => (string) $this->option('channel'),
                'version' => $version,
            ],
            [
                'version_normalized' => $normalized,
                'release_name' => $this->nullableOption('name') ?? 'WebBlocks CMS '.$version,
                'description' => $this->nullableOption('description'),
                'changelog' => $this->nullableOption('changelog'),
                'download_url' => (string) $this->argument('download_url'),
                'checksum_sha256' => $this->nullableOption('checksum'),
                'is_critical' => (bool) $this->option('critical'),
                'is_security' => (bool) $this->option('security'),
                'published_at' => $this->nullableOption('published-at') ?? now(),
                'supported_from_version' => $this->nullableOption('supported-from-version'),
                'supported_until_version' => $this->nullableOption('supported-until-version'),
                'min_php_version' => $this->nullableOption('min-php-version'),
                'min_laravel_version' => $this->nullableOption('min-laravel-version'),
            ],
        );

        $this->info('Release published: '.$release->product.' '.$release->version.' ['.$release->channel.']');

        return self::SUCCESS;
    }

    private function nullableOption(string $key): ?string
    {
        $value = $this->option($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
