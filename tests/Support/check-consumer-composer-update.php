<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateProgress;
use WebBlocks\Cms\Support\Updates\Client\Contracts\PostApplyRunner;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\WebBlocks;

[$script, $consumer, $source, $mode] = array_pad($argv, 4, null);
if (! is_string($consumer) || ! is_file($consumer.'/.webblocks-consumer-fixture') || ! is_string($source)) {
  fwrite(STDERR, "This destructive check requires a disposable marked consumer fixture.\n");
  exit(2);
}
require $consumer.'/vendor/autoload.php';

if ($mode === 'released-history') {
  $app = require $consumer.'/bootstrap/app.php';
  $app->make(Kernel::class)->bootstrap();
  // The modern fresh schema is the equivalent of an upgraded 1.112.1 site.
  // Record only migrations shipped in that immutable release; any migration
  // introduced by the working tree must still run during the actual update.
  $batch = (int) DB::table('migrations')->max('batch') + 1;
  foreach (glob($source.'/database/migrations/updates/*.php') ?: [] as $migration) {
    $name = basename($migration, '.php');
    if (! DB::table('migrations')->where('migration', $name)->exists()) {
      DB::table('migrations')->insert(['migration' => $name, 'batch' => $batch]);
    }
  }
  exit(0);
}

if (in_array($mode, ['apply', 'apply-failure', 'apply-bridge', 'apply-bridge-failure'], true)) {
  try {
  $app = require $consumer.'/bootstrap/app.php';
  $app->make(Kernel::class)->bootstrap();
  app(CmsPublisherClientConfigurator::class)->configure();
  $bridging = str_starts_with($mode, 'apply-bridge');
  $bytes = file_get_contents($source.($bridging ? '/bridge.zip' : '/current.zip'));
  $checksum = hash('sha256', $bytes);
  $keys = sodium_crypto_sign_keypair();
  config()->set('publisher-client.signature.public_key', base64_encode(sodium_crypto_sign_publickey($keys)));
  config()->set('publisher-client.telemetry.enabled', false);
  $failing = str_ends_with($mode, '-failure');
  $version = trim(file_get_contents($source.($bridging ? '/bridge-version' : '/version')));
  $expected = $bridging ? '1.112.1' : '1.112.3';
  if (WebBlocks::version() !== $expected) {
    throw new RuntimeException('The fixture must start with the selected released/preparation updater.');
  }
  $operation = AdminUpdateOperation::create([
    'id' => (string) Str::uuid(), 'user_id' => User::query()->firstOrFail()->getKey(),
    'from_version' => WebBlocks::version(), 'target_version' => $version, 'checksum_sha256' => $checksum,
    'status' => 'running', 'started_at' => now(),
  ]);
  app(CmsUpdateProgress::class)->operation($operation->id);
  if ($failing) {
    $delegate = app(PostApplyRunner::class);
    app()->instance(PostApplyRunner::class, new class($delegate) implements PostApplyRunner
    {
      public function __construct(private readonly PostApplyRunner $delegate) {}

      public function run(string $commandDir, array &$output): void
      {
        $this->delegate->run($commandDir, $output);
        throw new RuntimeException('Synthetic failure after the selected update steps.');
      }
    });
  }
  Http::preventStrayRequests();
  Http::fake([
    '*/api/updates/latest*' => Http::response(['data' => [
      'version' => $version,
      'artifact_url' => 'https://fixture.example.test/current.zip',
      'checksum_sha256' => $checksum,
      'signature' => base64_encode(sodium_crypto_sign_detached($checksum, sodium_crypto_sign_secretkey($keys))),
    ]]),
    'https://fixture.example.test/current.zip' => Http::response($bytes),
  ]);
  try {
    $result = app(SystemUpdater::class)->run();
  } catch (Throwable $exception) {
    if (! $failing || SystemUpdateRun::latest('id')->value('status') !== 'restored'
      || ! str_contains($exception->getMessage(), 'Synthetic failure after the selected update steps.')) {
    throw $exception;
    }
    echo "Selected updater restored its backup after its update steps.\n";
    exit(0);
  }
  if ($failing) {
  throw new RuntimeException('The synthetic failure was ignored.');
  }
  if ($result->toVersion !== $version || ! in_array($result->status, ['success', 'success_with_warnings'], true)) {
    throw new RuntimeException('The real consumer update did not finish.');
  }
  echo "Signed Publisher/Composer consumer update verified.\n";
  exit(0);
  } catch (Throwable $exception) {
    fwrite(STDERR, 'Synthetic update failed: '.$exception->getMessage().PHP_EOL);
    try {
      $output = SystemUpdateRun::latest('id')->value('output');
      fwrite(STDERR, (string) $output.PHP_EOL);
    } catch (Throwable) {
      // A fixture boot failure may precede the update journal.
    }
    exit(1);
  }
}

$fixture = sys_get_temp_dir().'/cms-consumer-composer-'.bin2hex(random_bytes(6));
mkdir($fixture, 0700);
$filesystem = new Filesystem;

try {
  $composer = json_decode(file_get_contents($source.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
  $contents = file_get_contents($source.'/src/Support/WebBlocks.php');
  if (preg_match("/const VERSION = '([^']+)'/", $contents, $match) !== 1) {
    throw new RuntimeException('Fixture version is unavailable.');
  }
  $sourceVersion = $match[1];
  $bridge = '1.112.3';
  $current = version_compare($sourceVersion, $bridge, '>') ? $sourceVersion : '1.112.4';
  // Exercise the immutable released updater, not new code with an old VERSION.
  $previous = '1.112.1';
  $released = $fixture.'/released';
  mkdir($released);
  $archivePath = $fixture.'/released.tar';
  (new Process(['git', '-C', dirname(__DIR__, 2), 'archive', '--format=tar', '--output='.$archivePath, 'v'.$previous]))->mustRun();
  (new Process(['tar', '-xf', $archivePath, '-C', $released]))->mustRun();
  file_put_contents($fixture.'/version', $current);
  file_put_contents($fixture.'/bridge-version', $bridge);
  $roots = ['composer.json', 'LICENSE', 'src', 'routes', 'resources', 'database', 'config', 'public', 'stubs'];
  $metadata = [];
  foreach (['previous' => $previous, 'bridge' => $bridge, 'current' => $current] as $label => $version) {
    $tree = $label === 'previous' ? $released : $source;
    $manifest = json_decode(file_get_contents($tree.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $archive = new ZipArchive;
    $archive->open($fixture.'/'.$label.'.zip', ZipArchive::CREATE);
    foreach ($roots as $root) {
      $path = $tree.'/'.$root;
      $files = is_dir($path)
        ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS))
        : [new SplFileInfo($path)];
      foreach ($files as $file) {
        if (! $file->isFile() || $file->isLink()) {
          continue;
        }
        $relative = substr($file->getPathname(), strlen($tree) + 1);
        if (preg_match('~(^|/)\.~', $relative) === 1) {
          continue;
        }
        $bytes = file_get_contents($file->getPathname());
        if ($relative === 'src/Support/WebBlocks.php') {
          $bytes = str_replace("const VERSION = '".$sourceVersion."'", "const VERSION = '".$version."'", $bytes);
        }
        $archive->addFromString('package/'.$relative, $bytes);
      }
    }
    $archive->close();
    if ($label === 'previous') {
      // Reproduce an archive-updated host: old code is the immutable 1.112.1
      // release, but Composer never installed a dependency added since 1.90.2.
      unset($manifest['require']['composer/semver']);
    }
    $metadata[] = array_merge(array_intersect_key($manifest, array_flip(['name', 'type', 'license', 'require', 'autoload', 'extra'])), [
      'version' => $version,
      'dist' => ['type' => 'zip', 'url' => 'file://'.$fixture.'/'.$label.'.zip', 'reference' => 'synthetic-'.$version],
      'source' => ['type' => 'git', 'url' => 'https://fixture.example.test/cms.git', 'reference' => 'synthetic-'.$version],
    ]);
  }

  $run = static function (array $arguments) use ($consumer): void {
    // Laravel intentionally disables real console events in testing mode. Use
    // local so the legacy migration subprocess runs the production event path;
    // telemetry is still suppressed outside production.
    $process = new Process($arguments, $consumer, ['APP_ENV' => 'local']);
    $process->setTimeout(300);
    $process->mustRun();
  };
  $binary = (new ExecutableFinder)->find('composer');
  if (! is_string($binary)) {
    throw new RuntimeException('Composer is required for the real update consumer check.');
  }
  $run([PHP_BINARY, $binary, 'config', '--json', 'repositories.update-fixture', json_encode(['type' => 'package', 'package' => $metadata], JSON_THROW_ON_ERROR)]);
  $common = ['--with-dependencies', '--minimal-changes', '--prefer-dist', '--no-interaction', '--no-progress', '--no-scripts', '--no-plugins'];
  $run([PHP_BINARY, $binary, 'require', 'fklavyenet/webblocks-cms:'.$previous, ...$common]);
  $run([PHP_BINARY, $script, $consumer, $released, 'released-history']);
  $assertMissingSemver = static function () use ($consumer): void {
    $registry = json_decode(file_get_contents($consumer.'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
    if (isset(array_column($registry['packages'], null, 'name')['composer/semver'])
      || is_dir($consumer.'/vendor/composer/semver')) {
      throw new RuntimeException('The historical fixture must have no installed composer/semver.');
    }
  };
  $assertMissingSemver();
  foreach (['apply-bridge-failure', 'apply-failure'] as $failureMode) {
    $originalLock = file_get_contents($consumer.'/composer.lock');
    $originalComposer = file_get_contents($consumer.'/composer.json');
    $originalCode = hash_file('sha256', $consumer.'/vendor/fklavyenet/webblocks-cms/src/Support/WebBlocks.php');
    $originalRegistry = file_get_contents($consumer.'/vendor/composer/installed.json');
    $run([PHP_BINARY, $script, $consumer, $fixture, $failureMode]);
    if ($originalComposer !== file_get_contents($consumer.'/composer.json')
      || $originalCode !== hash_file('sha256', $consumer.'/vendor/fklavyenet/webblocks-cms/src/Support/WebBlocks.php')
      || $originalLock !== file_get_contents($consumer.'/composer.lock')
      || $originalRegistry !== file_get_contents($consumer.'/vendor/composer/installed.json')) {
      throw new RuntimeException('Update recovery did not restore original Composer state.');
    }
    $assertMissingSemver();
    if ($failureMode === 'apply-bridge-failure') {
      $run([PHP_BINARY, $script, $consumer, $fixture, 'apply-bridge']);
      $assertMissingSemver();
      $prepared = json_decode(file_get_contents($consumer.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
      $preparedEntry = array_column($prepared['packages'], null, 'name')['fklavyenet/webblocks-cms'];
      if ($preparedEntry['source']['reference'] !== 'synthetic-'.$previous) {
        throw new RuntimeException('Preparation must not claim that Composer adoption has already completed.');
      }
    }
  }
  $run([PHP_BINARY, $script, $consumer, $fixture, 'apply']);
  $lock = json_decode(file_get_contents($consumer.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
  $entry = array_column($lock['packages'], null, 'name')['fklavyenet/webblocks-cms'];
  if ($entry['version'] !== $current || $entry['source']['reference'] !== 'synthetic-'.$current) {
    throw new RuntimeException('Composer recorded an incorrect version or source reference.');
  }
  $before = hash_file('sha256', $consumer.'/vendor/fklavyenet/webblocks-cms/src/Support/WebBlocks.php');
  $filesystem->deleteDirectory($consumer.'/vendor');
  $run([PHP_BINARY, $binary, 'install', '--prefer-dist', '--no-interaction', '--no-progress', '--no-scripts', '--no-plugins']);
  if ($before !== hash_file('sha256', $consumer.'/vendor/fklavyenet/webblocks-cms/src/Support/WebBlocks.php')) {
    throw new RuntimeException('A clean Composer install produced different CMS code.');
  }
  $run([PHP_BINARY, $consumer.'/artisan', 'webblocks:updates:finalize', '--force']);
  // Exact pinning is intentional in this fixture. Keep schema/lock validation
  // strict while excluding Composer's constraint-style advice.
  $run([PHP_BINARY, $binary, 'validate', '--strict', '--no-check-all']);
  echo "Released 1.112.1 panel preparation, missing-dependency Composer transition, both rollback boundaries, clean reinstall and CLI completion passed.\n";
} finally {
  $filesystem->deleteDirectory($fixture);
}
