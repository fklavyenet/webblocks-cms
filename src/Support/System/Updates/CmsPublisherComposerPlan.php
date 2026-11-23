<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;

final class CmsPublisherComposerPlan
{
  public const PACKAGE = 'fklavyenet/webblocks-cms';

  private const REPOSITORY = 'https://github.com/fklavyenet/webblocks-cms.git';

  private const DIST = 'https://api.github.com/repos/fklavyenet/webblocks-cms/zipball/';

  public readonly array $original;

  public readonly string $manifest;

  public readonly string $lock;

  public readonly string $localLock;

  public readonly bool $dev;

  private readonly object $package;

  private readonly array $peers;

  public function __construct(public readonly string $root, string $staged, string $version, CmsPublisherArtifact $artifact)
  {
    $this->original = [
      'composer.json' => File::get($root.'/composer.json'),
      'composer.lock' => File::get($root.'/composer.lock'),
      'vendor/composer/installed.json' => File::get($root.'/vendor/composer/installed.json'),
    ];
    $host = $this->decode($this->original['composer.json']);
    $lock = $this->decode($this->original['composer.lock']);
    $installed = $this->decode($this->original['vendor/composer/installed.json']);
    $candidate = $this->decode(File::get($staged.'/composer.json'));
    $name = self::PACKAGE;
    $current = $host->require->{$name} ?? null;
    if (! is_string($current) || ($host->config->{'vendor-dir'} ?? 'vendor') !== 'vendor'
      || ! is_array($lock->packages ?? null) || ! is_array($installed->packages ?? null)
      || ($candidate->name ?? null) !== $name || ($candidate->type ?? null) !== 'library'
      || ! empty($candidate->bin) || isset($candidate->{'target-dir'})) {
      throw $this->incompatible('A standard host Composer install and a CMS library artifact are required.');
    }
    $this->dev = ($installed->dev ?? true) === true;
    $this->peers = $this->peers($installed->packages);
    $lockedPackages = $this->dev ? [...$lock->packages, ...($lock->{'packages-dev'} ?? [])] : $lock->packages;
    if ($this->peers !== $this->peers($lockedPackages)) {
      throw $this->incompatible('Installed dependencies differ from composer.lock. Reconcile the host with Composer first.');
    }
    $entry = collect($installed->packages)->firstWhere('name', $name);
    $locked = collect($lock->packages)->firstWhere('name', $name);
    if (($entry->{'installation-source'} ?? null) !== 'dist' || ($entry->dist->type ?? null) !== 'zip'
      || ($entry->{'install-path'} ?? null) !== '../'.$name || ($entry->version ?? null) !== ($locked->version ?? null)
      || ($entry->source ?? null) != ($locked->source ?? null) || ($entry->dist ?? null) != ($locked->dist ?? null)) {
      throw $this->incompatible('The active CMS package must be a Composer dist installation matching composer.lock.');
    }

    $this->manifest = preg_match('/\Av?\d+\.\d+\.\d+\z/', $current) === 1
      ? $this->advancePin($host, $version)
      : $this->original['composer.json'];
    $lock->{'content-hash'} = $this->contentHash($this->manifest);
    $package = (object) array_intersect_key((array) $candidate, array_flip([
      'name', 'require', 'require-dev', 'conflict', 'provide', 'replace', 'suggest', 'type', 'extra',
      'autoload', 'autoload-dev', 'include-path', 'license', 'authors', 'description', 'homepage', 'keywords', 'support', 'funding',
    ]));
    $package->version = 'v'.$version;
    $package->source = (object) ['type' => 'git', 'url' => self::REPOSITORY, 'reference' => $artifact->commit];
    $test = config('publisher-client.channel') === 'test';
    $distribution = $package->dist = (object) [
      'type' => 'zip',
      'url' => $test ? CmsPublisherTestAccess::downloadUrl($artifact->url) : self::DIST.$artifact->commit,
      'reference' => $artifact->commit,
      'shasum' => $test ? sha1_file($artifact->archive) : '',
    ];
    foreach ($lock->packages as &$record) {
      if ($record->name === $name) {
        $record = $package;
      }
    }
    unset($record);
    $this->package = $package;
    $this->lock = $this->encode($lock);
    $source = $package->source;
    unset($package->source);
    $package->dist = (object) [
      'type' => 'zip',
      'url' => 'file://'.str_replace('%2F', '/', rawurlencode($artifact->archive)),
      'reference' => $artifact->commit,
      'shasum' => sha1_file($artifact->archive),
    ];
    $this->localLock = $this->encode($lock);
    $package->source = $source;
    $package->dist = $distribution;
  }

  public function installArguments(bool $dryRun = false): array
  {
    return [
      'install', '--prefer-dist', '--no-interaction', '--no-progress', '--no-scripts', '--no-plugins', '--optimize-autoloader',
      ...($this->dev ? [] : ['--no-dev']),
      ...($dryRun ? ['--dry-run'] : []),
    ];
  }

  public function publishInstalledMetadata(): void
  {
    $path = $this->root.'/vendor/composer/installed.json';
    $installed = $this->decode(File::get($path));
    $entry = collect($installed->packages)->firstWhere('name', self::PACKAGE);
    $registryPath = $this->root.'/vendor/composer/installed.php';
    if (function_exists('opcache_invalidate')) {
      opcache_invalidate($registryPath, true);
    }
    $runtime = require $registryPath;
    $runtime = $runtime['versions'][self::PACKAGE] ?? [];
    if ($this->peers !== $this->peers($installed->packages)
      || ($entry->version ?? null) !== $this->package->version
      || ($entry->dist->reference ?? null) !== $this->package->source->reference
      || ($runtime['pretty_version'] ?? null) !== $this->package->version
      || ($runtime['reference'] ?? null) !== $this->package->source->reference
      || realpath($runtime['install_path'] ?? '') !== $this->root.'/vendor/'.self::PACKAGE) {
      throw $this->incompatible('Composer did not install the expected CMS source revision.');
    }
    $entry->source = $this->package->source;
    $entry->dist = $this->package->dist;
    File::replace($path, $this->encode($installed));
    File::replace($this->root.'/composer.lock', $this->lock);
  }

  public function incompatible(string $detail, ?\Throwable $previous = null): UpdateException
  {
    return new UpdateException(__('webblocks-cms::admin.update_cli.publisher_composer_incompatible'), $detail, previous: $previous);
  }

  private function peers(array $packages): array
  {
    $peers = [];
    foreach ($packages as $package) {
      if ($package->name !== self::PACKAGE) {
        $peers[$package->name] = json_encode([$package->version, $package->source ?? null, $package->dist ?? null], JSON_THROW_ON_ERROR);
      }
    }
    ksort($peers);

    return $peers;
  }

  private function advancePin(object $manifest, string $version): string
  {
    $manifest->require->{self::PACKAGE} = $version;

    return $this->encode($manifest);
  }

  private function contentHash(string $manifest): string
  {
    // Composer's Locker::getContentHash hashes only resolution-related fields.
    // The Composer preflight independently checks the resulting lock.
    $config = json_decode($manifest, true, flags: JSON_THROW_ON_ERROR);
    $relevant = array_intersect_key($config, array_flip([
      'name', 'version', 'require', 'require-dev', 'conflict', 'replace', 'provide',
      'minimum-stability', 'prefer-stable', 'repositories', 'extra',
    ]));
    if (isset($config['config']['platform'])) {
      $relevant['config']['platform'] = $config['config']['platform'];
    }
    ksort($relevant);

    return md5(json_encode($relevant, JSON_THROW_ON_ERROR));
  }

  private function encode(object $document): string
  {
    return json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
  }

  private function decode(string $document): object
  {
    return json_decode($document, flags: JSON_THROW_ON_ERROR);
  }
}
