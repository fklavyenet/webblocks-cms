<?php

namespace App\Console\Commands;

use App\Support\Release\ReleaseBuilder;
use App\Support\Release\ReleaseException;
use App\Support\Release\ReleaseNotesResolver;
use App\Support\Release\ReleasePublisher;
use App\Support\Release\ReleaseVersionResolver;
use Illuminate\Console\Command;

class CmsReleaseCommand extends Command
{
    protected $signature = 'cms:release
        {version? : Release version}
        {--release-version= : Release version override}
        {--channel= : Release channel}
        {--notes= : Inline release notes}
        {--notes-file= : Markdown or text file containing release notes}
        {--publish : Publish after building the package}
        {--dry-run : Validate and preview the release without publishing}
        {--minimum-version= : Minimum supported installed version}
        {--output= : Output directory for built artifacts}
        {--force : Overwrite existing artifacts}
        {--description= : Short release description}
        {--name= : Release display name}
        {--published-at= : Published timestamp}
        {--min-php-version= : Minimum PHP version}
        {--min-laravel-version= : Minimum Laravel version}
        {--critical : Mark release as critical}
        {--security : Mark release as security}
        {--local-publish : Persist package and release metadata locally instead of remote publish}';

    protected $description = 'Build a reusable WebBlocks CMS release package and optionally publish it';

    public function handle(
        ReleaseVersionResolver $versionResolver,
        ReleaseNotesResolver $notesResolver,
        ReleaseBuilder $builder,
        ReleasePublisher $publisher,
    ): int {
        try {
            $version = $versionResolver->resolve($this->option('release-version'), $this->argument('version'));
            $notes = $notesResolver->resolve($this->option('notes'), $this->option('notes-file'));
            $channel = (string) ($this->option('channel') ?: config('webblocks-release.default_channel', 'stable'));

            if ($notes === null) {
                $this->warn('Release notes are empty. Continue only if this is intentional.');
            }

            $this->line('Preparing release build...');

            if ($this->option('dry-run')) {
                $this->table(['Field', 'Value'], [
                    ['Version', $version],
                    ['Channel', $channel],
                    ['Output', $this->option('output') ?: storage_path((string) config('webblocks-release.output_directory', 'app/releases/builds'))],
                    ['Publish enabled', $this->option('publish') ? 'yes' : 'no'],
                    ['Excluded patterns', implode(', ', $builder->plannedExclusions())],
                ]);

                return self::SUCCESS;
            }

            $build = $builder->build([
                'product' => (string) config('webblocks-release.product', 'webblocks-cms'),
                'version' => $version,
                'channel' => $channel,
                'notes' => $notes,
                'minimum_supported_version' => $this->option('minimum-version'),
                'output' => $this->option('output'),
                'force' => (bool) $this->option('force'),
            ]);

            $this->table(['Field', 'Value'], [
                ['Version', $build['version']],
                ['Channel', $build['channel']],
                ['Archive', $build['absolute_path']],
                ['Size', (string) $build['size']],
                ['SHA-256', $build['checksum_sha256']],
                ['Metadata', $build['metadata_path']],
            ]);

            if (! $this->option('publish')) {
                $this->info('Release package built successfully.');

                return self::SUCCESS;
            }

            $publishOptions = [
                'release_name' => $this->option('name'),
                'description' => $this->option('description'),
                'published_at' => $this->option('published-at'),
                'minimum_supported_version' => $this->option('minimum-version'),
                'min_php_version' => $this->option('min-php-version'),
                'min_laravel_version' => $this->option('min-laravel-version'),
                'is_critical' => (bool) $this->option('critical'),
                'is_security' => (bool) $this->option('security'),
            ];

            $publishResult = $this->option('local-publish')
                ? $publisher->publishLocally($build, $publishOptions)
                : $publisher->publish($build, $publishOptions);

            $this->table(['Publish field', 'Value'], [
                ['Server', (string) ($publishResult['server_url'] ?? 'unknown')],
                ['Status', (string) ($publishResult['status'] ?? 'unknown')],
                ['Download URL', (string) data_get($publishResult, 'release.download_url', 'n/a')],
            ]);

            $this->info('Release build and publish flow completed successfully.');

            return self::SUCCESS;
        } catch (ReleaseException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
