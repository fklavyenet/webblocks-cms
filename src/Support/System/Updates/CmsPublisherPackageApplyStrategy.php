<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use WebBlocks\Cms\Support\Updates\Client\Apply\ComposerPackageApplyStrategy;
use WebBlocks\Cms\Support\Updates\Client\Apply\PackageArtifactValidator;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\VersionResolver;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCommandRunner as ClientCommandRunner;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;

final class CmsPublisherPackageApplyStrategy extends ComposerPackageApplyStrategy
{
  private const DIRECTORIES = ['vendor/composer', 'bootstrap/cache', 'public/cms'];

  private const FILES = ['composer.json', 'composer.lock', 'vendor/autoload.php'];

  private ?CmsPublisherComposerPlan $plan = null;

  private ?string $recovery = null;

  public function __construct(
    ClientCommandRunner $legacyCommands,
    private readonly VersionResolver $versions,
    private readonly UpdateCommandRunner $commands,
    private readonly CmsPublisherArtifact $artifact,
    private readonly CmsPackageRollback $packageRollback,
  ) {
    parent::__construct($legacyCommands, $versions);
  }

  public function prepare(string $stagedRoot, array &$output): void
  {
    if ($this->artifact->commit === null && config('publisher-client.channel') === 'stable') {
      parent::prepare($stagedRoot, $output);

      return;
    }
    $this->artifact->transactional = true;
    $root = realpath(base_path());
    if ($root === false || $this->artifact->commit === null || $this->artifact->archive === null || $this->artifact->checksum === null) {
      throw new UpdateException(__('webblocks-cms::admin.update_cli.failed'), 'The verified CMS package is missing its source revision.');
    }
    $target = $root.'/vendor/'.CmsPublisherComposerPlan::PACKAGE;
    if (realpath($this->targetRoot()) !== $target || realpath($target) !== $target
      || (getenv('COMPOSER') ?: 'composer.json') !== 'composer.json'
      || ! in_array(getenv('COMPOSER_VENDOR_DIR') ?: 'vendor', ['vendor', $root.'/vendor'], true)
      || glob($root.'/.publisher-recovery-*') !== []) {
      throw new UpdateException(__('webblocks-cms::admin.update_cli.publisher_composer_incompatible'), 'The active CMS installation requires an operator-managed Composer update.');
    }
    foreach ([...self::FILES, ...self::DIRECTORIES, 'public', 'bootstrap', 'vendor/composer/installed.json', 'vendor/composer/installed.php'] as $path) {
      if (is_link($root.'/'.$path) || (File::exists($root.'/'.$path) && realpath($root.'/'.$path) !== $root.'/'.$path)) {
        throw new UpdateException(__('webblocks-cms::admin.update_cli.publisher_composer_incompatible'), 'The active CMS installation requires an operator-managed Composer update.');
      }
    }

    $this->assertSafeStagedContents($stagedRoot);
    PackageArtifactValidator::fromConfig()->validate($stagedRoot);
    $version = $this->versions->appliedVersion($stagedRoot);
    if ($version === null || preg_match('/\A\d+\.\d+\.\d+\z/', $version) !== 1) {
      throw new UpdateException(__('webblocks-cms::admin.update_cli.failed'), 'The verified CMS artifact does not declare a stable version.');
    }
    $this->artifact->version = $version;
    $plan = new CmsPublisherComposerPlan($root, $stagedRoot, $version, $this->artifact);
    $preflight = dirname($this->artifact->archive).'/composer-preflight';
    File::ensureDirectoryExists($preflight, 0700);
    try {
      File::replace($preflight.'/composer.json', $plan->manifest);
      File::replace($preflight.'/composer.lock', $plan->localLock);
      $this->composer(['validate', '--no-check-publish', '--no-plugins'], $preflight, $output);
      $this->composer($plan->installArguments(dryRun: true), $preflight, $output);
      $this->composer(['check-platform-reqs', '--lock', '--no-interaction', '--no-plugins', ...($plan->dev ? [] : ['--no-dev'])], $preflight, $output);
    } catch (\Throwable $failure) {
      throw $plan->incompatible('The existing lock cannot install the candidate without resolving dependencies.', $failure);
    } finally {
      File::deleteDirectory($preflight);
    }
    $this->plan = $plan;
  }

  public function apply(string $stagedRoot): array
  {
    $plan = $this->plan;
    if ($plan === null) {
      return parent::apply($stagedRoot);
    }
    $output = [];
    foreach ($plan->original as $path => $contents) {
      if (File::get($plan->root.'/'.$path) !== $contents) {
        throw $plan->incompatible('Composer files changed after preliminary validation. Check for updates again.');
      }
    }
    if (hash_file('sha256', $this->artifact->archive) !== $this->artifact->checksum) {
      throw new UpdateException(__('webblocks-cms::admin.update_cli.failed'), 'The verified CMS artifact changed during preflight.');
    }

    app(CmsUpdateProgress::class)->stage('apply');
    $this->snapshot($plan->root);
    $this->packageRollback->capture($this->targetRoot());
    $this->artifact->applicationChanged = true;
    if ($plan->manifest !== $plan->original['composer.json']) {
      File::replace($plan->root.'/composer.json', $plan->manifest);
    }
    File::replace($plan->root.'/composer.lock', $plan->localLock);
    $this->composer($plan->installArguments(), $plan->root, $output);
    foreach (File::allFiles($stagedRoot) as $file) {
      $installed = $this->targetRoot().'/'.$file->getRelativePathname();
      if (! File::isFile($installed) || hash_file('sha256', $installed) !== hash_file('sha256', $file->getPathname())) {
        throw new UpdateException(__('webblocks-cms::admin.update_cli.failed'), 'Installed CMS files do not match the verified Publisher package.');
      }
    }
    $plan->publishInstalledMetadata();

    return ['strategy' => $this->name(), 'target' => $this->targetRoot(), 'output' => $output];
  }

  public function rollback(array &$output): void
  {
    if (! $this->artifact->transactional) {
      parent::rollback($output);

      return;
    }
    if ($this->recovery === null || $this->plan === null) {
      return;
    }
    if ($this->artifact->applicationChanged) {
      $root = $this->plan->root;
      $this->packageRollback->restore();
      foreach (self::DIRECTORIES as $path) {
        $destination = $root.'/'.$path;
        $saved = $this->recovery.'/'.str_replace('/', '-', $path);
        $displaced = $saved.'.failed';
        if ((File::exists($destination) && ! @rename($destination, $displaced))
          || (File::isDirectory($saved) && ! @rename($saved, $destination))) {
          throw new UpdateException(__('webblocks-cms::admin.update_cli.failed'), 'The CMS update recovery copy could not be restored at '.$this->recovery);
        }
      }
      foreach (self::FILES as $path) {
        File::replace($root.'/'.$path, File::get($this->recovery.'/'.str_replace('/', '-', $path)));
      }
      foreach (File::allFiles($root.'/vendor/composer') as $file) {
        if ($file->getExtension() === 'php') {
          touch($file->getPathname());
        }
      }
      $output[] = 'Restored the CMS package, Composer metadata, bootstrap cache and CMS assets.';
    }
    $this->commit();
  }

  public function commit(): void
  {
    if ($this->recovery !== null) {
      File::deleteDirectory($this->recovery);
      $this->recovery = null;
    }
    $this->plan = null;
    parent::commit();
  }

  private function snapshot(string $root): void
  {
    $recovery = $root.'/.publisher-recovery-'.Str::uuid();
    File::ensureDirectoryExists($recovery, 0700);
    try {
      foreach (self::DIRECTORIES as $path) {
        $directory = $root.'/'.$path;
        if (File::isDirectory($directory) && ! File::copyDirectory($directory, $recovery.'/'.str_replace('/', '-', $path))) {
          throw new UpdateException(__('webblocks-cms::admin.update_cli.failed'), 'The CMS update could not preserve a recovery copy of '.$path);
        }
      }
      foreach (self::FILES as $path) {
        if (! File::copy($root.'/'.$path, $recovery.'/'.str_replace('/', '-', $path))) {
          throw new UpdateException(__('webblocks-cms::admin.update_cli.failed'), 'The CMS update could not preserve a recovery copy of '.$path);
        }
      }
    } catch (\Throwable $failure) {
      File::deleteDirectory($recovery);
      throw $failure;
    }
    $this->recovery = $recovery;
  }

  private function composer(array $arguments, string $directory, array &$output): void
  {
    $this->commands->runOffline($this->commands->composerCommand($arguments), $directory, $output);
  }
}
