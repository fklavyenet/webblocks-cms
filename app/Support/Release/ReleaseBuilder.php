<?php

namespace App\Support\Release;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class ReleaseBuilder
{
    public function __construct(
        private readonly ChecksumFactory $checksumFactory,
        private readonly ReleaseMetadataFactory $metadataFactory,
    ) {}

    public function build(array $options): array
    {
        $product = (string) ($options['product'] ?? config('webblocks-release.product', 'webblocks-cms'));
        $version = (string) $options['version'];
        $channel = (string) ($options['channel'] ?? config('webblocks-release.default_channel', 'stable'));
        $notes = $options['notes'] ?? null;
        $minimumSupportedVersion = $options['minimum_supported_version'] ?? null;
        $outputDirectory = $this->resolveOutputDirectory($options['output'] ?? null);
        $packagePrefix = (string) config('webblocks-release.package_prefix', 'webblocks-cms');
        $filename = $packagePrefix.'-'.$version.'.zip';
        $absolutePath = $outputDirectory.DIRECTORY_SEPARATOR.$filename;

        File::ensureDirectoryExists($outputDirectory);

        if (File::exists($absolutePath) && ! ($options['force'] ?? false)) {
            throw new ReleaseException('Release archive already exists. Use --force to overwrite it.');
        }

        if (File::exists($absolutePath)) {
            File::delete($absolutePath);
        }

        $zip = new ZipArchive;
        $result = $zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new ReleaseException('Unable to create the release archive.');
        }

        $includedRootItems = [];

        foreach ($this->filesToInclude() as $file) {
            $relativePath = $this->relativePath($file->getPathname());

            if ($this->shouldExclude($relativePath)) {
                continue;
            }

            $root = explode(DIRECTORY_SEPARATOR, $relativePath)[0] ?? $relativePath;
            $includedRootItems[$root] = true;
            $zip->addFile($file->getPathname(), $relativePath);
        }

        $zip->close();

        $checksum = $this->checksumFactory->sha256($absolutePath);
        $builtAt = CarbonImmutable::now()->toIso8601String();

        $build = [
            'product' => $product,
            'version' => $version,
            'channel' => $channel,
            'notes' => $notes,
            'absolute_path' => $absolutePath,
            'filename' => $filename,
            'size' => File::size($absolutePath),
            'checksum_sha256' => $checksum,
            'built_at' => $builtAt,
            'source_commit' => $this->gitOutput('git rev-parse HEAD'),
            'source_tag' => $this->gitOutput('git describe --tags --exact-match'),
            'minimum_supported_version' => $minimumSupportedVersion,
            'included_root_items' => array_keys($includedRootItems),
        ];

        $metadata = $this->metadataFactory->make($build);
        File::put($outputDirectory.DIRECTORY_SEPARATOR.$packagePrefix.'-'.$version.'.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return $build + ['metadata_path' => $outputDirectory.DIRECTORY_SEPARATOR.$packagePrefix.'-'.$version.'.json'];
    }

    public function plannedExclusions(): array
    {
        $exclude = config('webblocks-release.exclude', []);

        if (! config('webblocks-release.exclude_vendor', true)) {
            $exclude = array_values(array_filter($exclude, fn (string $pattern): bool => $pattern !== 'vendor'));
        }

        if (! config('webblocks-release.exclude_git_metadata', true)) {
            $exclude = array_values(array_filter($exclude, fn (string $pattern): bool => $pattern !== '.git'));
        }

        return $exclude;
    }

    private function filesToInclude(): \Traversable
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path(), RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
    }

    private function shouldExclude(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', $relativePath);

        foreach ($this->plannedExclusions() as $pattern) {
            $normalizedPattern = str_replace('\\', '/', $pattern);

            if (fnmatch($normalizedPattern, $normalized, FNM_PATHNAME)) {
                return true;
            }

            if (str_ends_with($normalizedPattern, '/*')) {
                $prefix = rtrim(substr($normalizedPattern, 0, -2), '/');

                if ($normalized === $prefix || str_starts_with($normalized, $prefix.'/')) {
                    return true;
                }
            }

            if (! str_contains($normalizedPattern, '*') && ($normalized === $normalizedPattern || str_starts_with($normalized, rtrim($normalizedPattern, '/').'/'))) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $absolutePath): string
    {
        return ltrim(str_replace(base_path(), '', $absolutePath), DIRECTORY_SEPARATOR);
    }

    private function resolveOutputDirectory(?string $override): string
    {
        if (is_string($override) && trim($override) !== '') {
            return str_starts_with($override, DIRECTORY_SEPARATOR) ? $override : base_path($override);
        }

        return Storage::disk((string) config('webblocks-release.disk', 'local'))->path((string) config('webblocks-release.output_directory', 'releases/builds'));
    }

    private function gitOutput(string $command): ?string
    {
        $output = [];
        $status = 0;

        exec($command.' 2>/dev/null', $output, $status);

        if ($status !== 0) {
            return null;
        }

        $value = trim(Arr::first($output, default: ''));

        return $value !== '' ? $value : null;
    }
}
