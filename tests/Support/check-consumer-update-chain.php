<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use WebBlocks\Cms\Actions\System\StartCmsUpdateChain;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\RunCmsUpdateChain;

[$script, $consumer, $fixture, $mode, $sourceOverride] = array_pad($argv, 5, null);
if (! is_string($consumer) || ! is_file($consumer.'/.webblocks-consumer-fixture') || ! is_string($fixture)) {
  throw new RuntimeException('A marked disposable consumer is required.');
}
$script = __FILE__;
require $consumer.'/vendor/autoload.php';

if ($mode === 'apply') {
  try {
  $app = require $consumer.'/bootstrap/app.php';
  $app->make(Kernel::class)->bootstrap();
  app(CmsPublisherClientConfigurator::class)->configure();
  $operation = app(StartCmsUpdateChain::class)->execute(null, false);
  $success = app(RunCmsUpdateChain::class)->run($operation);
  $operation->refresh();
  $fixtureConfig = require $fixture.'/chain.php';
  $expectedFailure = is_file($fixture.'/fail-chain-verification');
  if ($success === $expectedFailure || $operation->status !== ($expectedFailure ? 'restored' : 'success')
    || $operation->recovery_required || app()->isDownForMaintenance()) {
    fwrite(STDERR, 'Unexpected chain outcome: '.$operation->status.PHP_EOL);
    foreach (SystemUpdateRun::latest('id')->limit(2)->get() as $run) {
      fwrite(STDERR, (string) $run->output.PHP_EOL);
    }
    exit(1);
  }
  $steps = UpdateChainStep::where('operation_id', $operation->id)->orderBy('position')->get();
  if ($expectedFailure) {
    if ($steps->count() !== 2 || $steps[0]->status !== 'success' || $steps[1]->status !== 'restored'
      || $operation->last_verified_version !== $fixtureConfig['bridge']) {
      throw new RuntimeException('The failed final step lost its prior verified checkpoint.');
    }
    file_put_contents($fixture.'/first-chain-id', $operation->id);
  } elseif ($steps->count() !== 1 || $steps[0]->version !== $fixtureConfig['target']
    || UpdateChainStep::whereIn('operation_id', [trim(file_get_contents($fixture.'/first-chain-id')), $operation->id])->where('version', $fixtureConfig['bridge'])->count() !== 1) {
    throw new RuntimeException('The retry replayed an already verified bridge.');
  }
  echo 'Chain '.($expectedFailure ? 'checkpoint recovery' : 'completion').' verified.'.PHP_EOL;
  exit(0);
  } catch (Throwable $exception) {
    do {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
    } while ($exception = $exception->getPrevious());
    exit(1);
  }
}

$source = is_string($sourceOverride) ? $sourceOverride : $consumer.'/vendor/fklavyenet/webblocks-cms';
$sourceText = file_get_contents($source.'/src/Support/WebBlocks.php');
preg_match("/const VERSION = '([^']+)'/", $sourceText, $matches);
$initial = $matches[1];
$major = ((int) explode('.', $initial)[0]) + 1;
$bridge = $major.'.0.0';
$target = $major.'.1.0';
$versions = [$initial, $bridge, $target];
$manifest = json_decode(file_get_contents($source.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$metadata = is_file($fixture.'/packages.php') ? require $fixture.'/packages.php' : [];
$metadata = array_values(array_filter($metadata, fn ($entry) => ! in_array($entry['version'], $versions, true)));
$pair = sodium_crypto_sign_keypair();
$releases = [];
foreach ($versions as $index => $version) {
  $path = $fixture.'/chain-'.$version.'.zip';
  $archive = new ZipArchive;
  $archive->open($path, ZipArchive::CREATE);
  foreach (['composer.json', 'LICENSE', 'src', 'routes', 'resources', 'database', 'config', 'public', 'stubs'] as $root) {
    $entry = $source.'/'.$root;
    $files = is_dir($entry) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($entry, FilesystemIterator::SKIP_DOTS)) : [new SplFileInfo($entry)];
    foreach ($files as $file) {
      if (! $file->isFile() || $file->isLink()) {
      continue;
      }
      $relative = substr($file->getPathname(), strlen($source) + 1);
      if (preg_match('~(^|/)\.~', $relative)) {
      continue;
      }
      $content = file_get_contents($file->getPathname());
      if ($relative === 'src/Support/WebBlocks.php') {
      $content = str_replace("VERSION = '".$initial."'", "VERSION = '".$version."'", $content);
      }
      $archive->addFromString('package/'.$relative, $content);
    }
  }
  $archive->close();
  $reference = 'synthetic-'.$version.'-'.substr(hash_file('sha256', $path), 0, 12);
  $metadata[] = array_merge(array_intersect_key($manifest, array_flip(['name', 'type', 'license', 'require', 'autoload', 'extra'])), [
    'version' => $version, 'dist' => ['type' => 'zip', 'url' => 'file://'.$path, 'reference' => $reference],
    'source' => ['type' => 'git', 'url' => 'https://fixture.example.test/cms.git', 'reference' => $reference]]);
  $hash = hash_file('sha256', $path);
  if ($index === 0) {
  continue;
  }
  $releases[] = ['product' => 'webblocks-cms', 'channel' => 'stable', 'version' => $version,
    'minimum_client_version' => $index === 1 ? $initial : $bridge, 'checksum_sha256' => $hash,
    'signature' => base64_encode(sodium_crypto_sign_detached($hash, sodium_crypto_sign_secretkey($pair))),
    'artifact_url' => 'https://chain.example.test/'.$version.'.zip'];
}
$fixtureConfig = ['initial' => $initial, 'bridge' => $bridge, 'target' => $target, 'releases' => $releases, 'public_key' => base64_encode(sodium_crypto_sign_publickey($pair))];
file_put_contents($fixture.'/chain.php', '<?php return '.var_export($fixtureConfig, true).';');
$provider = <<<'PHP'
<?php
namespace App\Providers;
class UpdateChainFixtureProvider extends \Illuminate\Support\ServiceProvider {
 public function boot(): void {
  $this->app->booted(function (): void {
   $root = __FIXTURE_ROOT__;
   $fixture = require $root.'/chain.php';
   config(['webblocks-updates.server_url' => 'https://chain.example.test', 'webblocks-updates.chains.enabled' => true, 'cache.default' => 'file',
    'publisher-client.signature.public_key' => $fixture['public_key']]);
   if (is_file($root.'/fail-chain-verification') && ($_SERVER['argv'][1] ?? '') === 'webblocks:updates:chain-verify'
    && ! in_array('--checkpoint', $_SERVER['argv'], true) && \WebBlocks\Cms\Support\WebBlocks::version() === $fixture['target']) {
    throw new \RuntimeException('Synthetic failure in the fresh final verification process.');
   }
   \Illuminate\Support\Facades\Http::preventStrayRequests();
   \Illuminate\Support\Facades\Http::fake(['https://chain.example.test/*' => function ($request) use ($fixture, $root) {
    if (str_contains($request->url(), '/api/updates/plan')) {
     $installed = $request['installed_version']; $target = $request['target_version'] ?? $fixture['target']; $current = $installed; $steps = [];
     while ($current !== $target) {
      $candidates = array_values(array_filter($fixture['releases'], fn ($r) => version_compare($r['version'], $current, '>')
       && version_compare($r['version'], $target, '<=') && version_compare($current, $r['minimum_client_version'], '>=')));
      usort($candidates, fn ($a, $b) => version_compare($b['version'], $a['version']));
      if (! $candidates) { return \Illuminate\Support\Facades\Http::response([], 409); }
      $next = $candidates[0]; $steps[] = ['from_version' => $current] + $next; $current = $next['version'];
     }
     return \Illuminate\Support\Facades\Http::response(['data' => ['protocol_version' => 1, 'product' => 'webblocks-cms',
      'channel' => 'stable', 'installed_version' => $installed, 'target_version' => $target, 'steps' => $steps]]);
    }
    $version = basename(parse_url($request->url(), PHP_URL_PATH), '.zip');
    return \Illuminate\Support\Facades\Http::response(file_get_contents($root.'/chain-'.$version.'.zip'));
   }]);
  });
 }
}
PHP;
file_put_contents($consumer.'/app/Providers/UpdateChainFixtureProvider.php', str_replace('__FIXTURE_ROOT__', var_export($fixture, true), $provider));
$providers = file_get_contents($consumer.'/bootstrap/providers.php');
file_put_contents($consumer.'/bootstrap/providers.php', str_replace('];', '    App\\Providers\\UpdateChainFixtureProvider::class,'.PHP_EOL.'];', $providers));
$run = static function (array $arguments) use ($consumer): void {
  $process = new Process($arguments, $consumer, ['APP_ENV' => 'local']);
  $process->setTimeout(600);
  $process->mustRun();
  $output = $process->getOutput();
  if (end($arguments) === 'apply' && ! str_contains($output, ' verified.')) {
    throw new RuntimeException('The chain child did not emit its success assertion.');
  }
  echo $output;
};
$binary = (new ExecutableFinder)->find('composer');
$run([PHP_BINARY, $binary, 'config', '--json', 'repositories.update-fixture', json_encode(['type' => 'package', 'package' => $metadata], JSON_THROW_ON_ERROR)]);
$run([PHP_BINARY, $binary, 'require', 'fklavyenet/webblocks-cms:'.$initial, '--with-dependencies', '--minimal-changes', '--prefer-dist', '--no-interaction', '--no-progress', '--no-scripts', '--no-plugins']);
file_put_contents($fixture.'/fail-chain-verification', 'synthetic');
$run([PHP_BINARY, $script, $consumer, $fixture, 'apply']);
unlink($fixture.'/fail-chain-verification');
$run([PHP_BINARY, $script, $consumer, $fixture, 'apply']);
// Remove all vendor code and prove the resulting lock reproduces the final package.
(new Filesystem)->deleteDirectory($consumer.'/vendor');
$run([PHP_BINARY, $binary, 'install', '--prefer-dist', '--no-interaction', '--no-progress', '--no-scripts', '--no-plugins']);
$run([PHP_BINARY, $consumer.'/artisan', 'webblocks:updates:finalize', '--force']);
echo 'Fresh-process chain, final-step rollback, checkpoint retry, continuous maintenance and clean reinstall passed.'.PHP_EOL;
