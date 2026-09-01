<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\VersionResolver;
use ZipArchive;

/**
 * Baseline release preparer: zips a source directory (excluding VCS/vendor/build
 * cruft), computes the SHA-256 checksum and writes the publisher payload JSON that
 * `publisher:publish-update` consumes. Product-specific packaging (git archive,
 * manifest injection) can override this per product.
 */
final class PrepareUpdateCommand extends Command
{
    protected $signature = 'publisher:prepare-update
        {--release-version= : Version to package (defaults to the resolved current version)}
        {--source= : Directory to package (defaults to the base path)}
        {--notes= : Release notes}';

    protected $description = 'Build a release ZIP + publisher payload for the current product (owner-side).';

    /**
     * Default packaging excludes. A product overrides these via
     * `publisher-client.publisher.artifact_excludes` when its release artifact
     * must shed more than the baseline (dev-only trees, built assets, product
     * data), e.g. a full-root standalone app shipping `tests/`, `build/` or a
     * SQLite database it must never overwrite on the target.
     */
    private const EXCLUDES = ['.git', '.github', '.env', 'vendor', 'node_modules', 'storage', 'bootstrap/cache', 'public/storage'];

    public function handle(VersionResolver $versions): int
    {
        $product = (string) config('publisher-client.product', '');

        if ($product === '') {
            $this->error('publisher-client.product is not configured.');

            return self::FAILURE;
        }

        $version = (string) ($this->option('release-version') ?: $versions->current());
        $source = rtrim((string) ($this->option('source') ?: base_path()), '/\\');

        if (! File::isDirectory($source)) {
            $this->error('Source directory does not exist: '.$source);

            return self::FAILURE;
        }

        if (! $this->packageIdentityHolds($source)) {
            return self::FAILURE;
        }

        $releaseDir = storage_path(trim((string) config('publisher-client.publisher.release_storage_path', 'app/publisher-client-release'), '/').'/'.$version);
        File::ensureDirectoryExists($releaseDir);

        $artifact = $releaseDir.'/'.$product.'-'.$version.'.zip';
        File::delete($artifact);

        $this->buildZip($source, $artifact, $product, $this->excludes(), $this->allowedRoots());

        $checksum = strtolower((string) hash_file('sha256', $artifact));

        $notes = (string) ($this->option('notes') ?? '');

        if ($notes === '') {
            $notes = (string) $this->notesFromChangelog($source, $version);

            if ($notes !== '') {
                $this->line('Release notes sourced from the changelog for '.$version.'.');
            }
        }

        $payloadPath = $releaseDir.'/'.$product.'-'.$version.'-update-server-payload.json';
        File::put($payloadPath, json_encode(array_filter([
            'product' => $product,
            'channel' => config('publisher-client.channel', 'stable'),
            'version' => $version,
            'artifact_path' => $artifact,
            'artifact_filename' => basename($artifact),
            'checksum_sha256' => $checksum,
            'minimum_client_version' => config('publisher-client.minimum_client_version'),
            'source_reference' => 'v'.$version,
            'release_notes' => $notes,
        ], fn ($v): bool => $v !== null && $v !== ''), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->table(['Field', 'Value'], [
            ['Product', $product],
            ['Version', $version],
            ['Artifact', $artifact],
            ['Checksum', $checksum],
            ['Payload', $payloadPath],
        ]);
        $this->info('Release prepared. Publish it with: php artisan publisher:publish-update');

        return self::SUCCESS;
    }

    /**
     * Extract the release notes for $version from the product's changelog: the
     * body lines under the first `#`-heading that mentions the version, up to the
     * next heading OF THE SAME OR A SHALLOWER LEVEL. Deeper sub-headings inside the
     * section (Keep-a-Changelog's `### Changed` / `### Fixed` under `## [1.2.3]`)
     * are treated as part of the section: their label line is dropped, their items
     * kept. Format-agnostic — matches `### 0.1.56 Release Notes`, `## [1.2.3]`,
     * `## 1.2.3 - 2026-07-20`, etc. The version must not be a prefix of a longer
     * number (0.1.5 never matches inside 0.1.56). Returns null when the changelog
     * is disabled, missing, or has no matching section.
     */
    private function notesFromChangelog(string $source, string $version): ?string
    {
        $relative = config('publisher-client.publisher.changelog_path', 'CHANGELOG.md');

        if (! is_string($relative) || $relative === '') {
            return null;
        }

        $path = $source.'/'.ltrim($relative, '/\\');

        if (! File::isFile($path)) {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) File::get($path)) ?: [];
        $escaped = preg_quote($version, '/');
        $headingStart = null;
        $headingLevel = 0;

        foreach ($lines as $index => $line) {
            if (preg_match('/^(#{1,6})\s/', $line, $m) === 1
                && preg_match('/(?<![\d.])'.$escaped.'(?![\d.])/', $line) === 1) {
                $headingStart = $index;
                $headingLevel = strlen($m[1]);
                break;
            }
        }

        if ($headingStart === null) {
            return null;
        }

        $body = [];

        foreach (array_slice($lines, $headingStart + 1) as $line) {
            if (preg_match('/^(#{1,6})\s/', $line, $m) === 1) {
                // A same-or-shallower heading ends this version's section; a deeper
                // sub-heading (### Changed) is an in-section label — skip it, keep items.
                if (strlen($m[1]) <= $headingLevel) {
                    break;
                }

                continue;
            }

            if (trim($line) !== '') {
                $body[] = trim($line);
            }
        }

        return $body === [] ? null : implode("\n", $body);
    }

    /**
     * The effective packaging excludes: the product's configured list when set,
     * otherwise the baseline {@see self::EXCLUDES}.
     *
     * @return list<string>
     */
    private function excludes(): array
    {
        $configured = config('publisher-client.publisher.artifact_excludes');

        if (is_array($configured) && $configured !== []) {
            return array_values(array_map('strval', $configured));
        }

        return self::EXCLUDES;
    }

    /**
     * Optional allowlist of permitted top-level roots (opt-in, blacklist is the
     * default). When set (non-empty), ONLY files whose first path segment is in
     * the list are packaged — the strong guarantee a strict `laravel_app` product
     * needs (`app`, `config`, `database`, `lang`, `public`, `resources`, `routes`,
     * `artisan`, `composer.json`, `composer.lock`, `bootstrap`). Combine with
     * `artifact_excludes` to shed sub-paths inside an allowed root (e.g.
     * `bootstrap/cache`, `bootstrap/providers.php`). Empty/unset ⇒ blacklist only.
     *
     * @return list<string>
     */
    private function allowedRoots(): array
    {
        $configured = config('publisher-client.publisher.artifact_allowed_roots');

        return is_array($configured) ? array_values(array_map('strval', $configured)) : [];
    }

    /**
     * @param  list<string>  $excludes
     * @param  list<string>  $allowedRoots
     */
    /**
     * A package-first product ships its package directory, not the application
     * that hosts it. Without `--source` this command packages the base path,
     * which produces an artifact the installed site refuses at apply time with
     * "does not match this product" — after a download, a staging copy and a
     * maintenance window. The same identity check belongs here, where the only
     * cost of being wrong is an error message.
     *
     * Prints the reason and returns false when the source is the wrong tree.
     * Full-root products, and package products with no configured identity,
     * pass straight through.
     */
    private function packageIdentityHolds(string $source): bool
    {
        if ((string) config('publisher-client.apply.strategy', '') !== 'package') {
            return true;
        }

        $expected = trim((string) config('publisher-client.package.name', ''));

        if ($expected === '') {
            return true; // Identity not configured: nothing to compare against.
        }

        $actual = $this->composerName($source);

        if ($actual === $expected) {
            return true;
        }

        // One fact per line: the shell wraps a long single line mid-token, and
        // the package names are the part the reader needs intact.
        $this->error('Package identity mismatch — refusing to build this artifact.');
        $this->line('  this product ships: '.$expected);
        $this->line('  source is:          '.($actual ?? 'not a Composer package'));
        $this->line('  source path:        '.$source);
        $this->line('  Pass --source pointing at the package directory. An installed site would reject this artifact.');

        return false;
    }

    private function composerName(string $root): ?string
    {
        $path = rtrim($root, '/\\').DIRECTORY_SEPARATOR.'composer.json';

        if (! File::isFile($path)) {
            return null;
        }

        try {
            $composer = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $name = is_array($composer) ? ($composer['name'] ?? null) : null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    private function buildZip(string $source, string $artifact, string $product, array $excludes, array $allowedRoots): void
    {
        $zip = new ZipArchive;
        $zip->open($artifact, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // By default the payload is wrapped in a single `<product>/` top-level dir;
        // the apply-side extractor unwraps it. A product whose pre-migration
        // bootstrap extractor validates paths at the ROOT (no single-dir unwrap)
        // sets publisher.wrap_in_product_dir=false so the
        // ZIP has files at the root instead — the package extractor handles both.
        $prefix = config('publisher-client.publisher.wrap_in_product_dir', true)
            ? $product.'/'
            : '';

        foreach ($this->candidateRelativePaths($source) as $relative) {
            $fullPath = $source.'/'.$relative;

            if ($relative === ''
                || ! File::isFile($fullPath)
                || $this->hasHiddenSegment($relative)
                || $this->isBackupOrTempFile($relative)
                || ! $this->isAllowedRoot($relative, $allowedRoots)
                || $this->isExcluded($relative, $excludes)) {
                continue;
            }

            $zip->addFile($fullPath, $prefix.$relative);
        }

        $zip->close();
    }

    /**
     * The candidate files to consider for packaging, as root-relative paths.
     *
     * When the source is a git work tree, the candidates are the git-TRACKED
     * files only (`git ls-files`) — a release is a function of the committed
     * source, never of generated/gitignored leftovers a developer's machine
     * happens to have (for example, rebuilt public assets that shipped in early
     * releases for exactly that reason). The allowlist/excludes
     * still shed tracked-but-unwanted files (tests/, docs/, bootstrap/providers.php).
     *
     * Falls back to a filesystem walk when the source is not a git work tree
     * (e.g. an already-exported directory passed via --source).
     *
     * @return list<string>
     */
    private function candidateRelativePaths(string $source): array
    {
        $tracked = $this->gitTrackedRelativePaths($source);

        if ($tracked !== null) {
            return $tracked;
        }

        $paths = [];

        foreach (File::allFiles($source, true) as $file) {
            $paths[] = trim(str_replace('\\', '/', str_replace($source, '', $file->getPathname())), '/');
        }

        return $paths;
    }

    /**
     * Git-tracked files relative to the source, or null when the source is not a
     * git work tree (or git is unavailable).
     *
     * @return list<string>|null
     */
    private function gitTrackedRelativePaths(string $source): ?array
    {
        $isRepo = Process::path($source)->run(['git', 'rev-parse', '--is-inside-work-tree']);

        if (! $isRepo->successful() || trim($isRepo->output()) !== 'true') {
            return null;
        }

        $result = Process::path($source)->run(['git', 'ls-files', '-z']);

        if (! $result->successful()) {
            return null;
        }

        return array_values(array_filter(
            explode("\0", $result->output()),
            static fn (string $path): bool => $path !== '',
        ));
    }

    /**
     * A path with any dot-segment (.git, .env, .DS_Store, public/.htaccess, …) is
     * never packaged. The apply-side extractor rejects hidden segments outright —
     * both this engine's and every adopter's prior bespoke extractor during the
     * bootstrap release — so shipping one aborts the whole update. Dotfiles are
     * VCS/tooling/host cruft anyway; the running install keeps its own (.env,
     * public/.htaccess) since apply never deletes files absent from the package.
     */
    private function hasHiddenSegment(string $relative): bool
    {
        foreach (explode('/', $relative) as $segment) {
            if ($segment !== '' && str_starts_with($segment, '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Backup and temporary files are NEVER packaged, regardless of product config.
     * The apply-side `laravel_app` inspector rejects them outright, and they are
     * runtime cruft anyway. The filesystem walk would otherwise pick up files
     * that tooling leaves on disk but VCS ignores. Matched by suffix, so a dotless backup name
     * (which `hasHiddenSegment` misses) is still dropped.
     */
    private function isBackupOrTempFile(string $relative): bool
    {
        foreach (['.bak', '.tmp', '.old', '~'] as $suffix) {
            if (str_ends_with($relative, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * When an allowlist is configured, a file is packaged only if its first path
     * segment is a permitted root. Empty allowlist ⇒ every root is allowed (the
     * default blacklist-only behavior).
     *
     * @param  list<string>  $allowedRoots
     */
    private function isAllowedRoot(string $relative, array $allowedRoots): bool
    {
        if ($allowedRoots === []) {
            return true;
        }

        return in_array(explode('/', $relative)[0], $allowedRoots, true);
    }

    /**
     * @param  list<string>  $excludes
     */
    private function isExcluded(string $relative, array $excludes): bool
    {
        foreach ($excludes as $exclude) {
            if ($relative === $exclude || str_starts_with($relative, $exclude.'/')) {
                return true;
            }
        }

        return false;
    }
}
