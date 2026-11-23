<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use WebBlocks\Cms\Support\System\Updates\CmsPackageRollback;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherArtifact;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherComposerPlan;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateExtractor;
use WebBlocks\Cms\Support\System\Updates\UpdateCommandRunner;
use WebBlocks\Cms\Support\Updates\Client\Contracts\ApplyStrategy;
use WebBlocks\Cms\Support\Updates\Client\Contracts\PostApplyRunner;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;
use WebBlocks\Cms\Tests\TestCase;
use ZipArchive;

final class CmsPublisherPackageApplyTest extends TestCase
{
  private const PACKAGE = CmsPublisherComposerPlan::PACKAGE;

  private string $temporary;

  private string $host;

  private string $base;

  private string $commit = '1234567890abcdef1234567890abcdef12345678';

  protected function setUp(): void
  {
    parent::setUp();
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
    $this->base = base_path();
    $this->temporary = sys_get_temp_dir().'/cms-publisher-package-'.Str::uuid();
    $this->host = $this->temporary.'/host';
    File::ensureDirectoryExists($this->host);
    $peer = $this->zip('other/peer', '1.0.0', []);
    $previous = $this->zip(self::PACKAGE, '1.0.0', ['other/peer' => '^1']);
    $this->writeJsonFile($this->host.'/composer.json', [
      'name' => 'tests/host',
      'require' => [self::PACKAGE => '1.0.0'],
      'repositories' => [
        ['packagist.org' => false],
        ['type' => 'package', 'package' => $this->package(self::PACKAGE, '1.0.0', ['other/peer' => '^1'], 'file://'.$previous)],
        ['type' => 'package', 'package' => $this->package('other/peer', '1.0.0', [], 'file://'.$peer)],
      ],
    ]);
    $this->composer($this->host, ['update', '--no-audit']);
    $this->app->setBasePath(realpath($this->host));
    $this->app->useStoragePath($this->temporary.'/storage');
    config([
      'publisher-client.apply.target_path' => realpath($this->host).'/vendor/'.self::PACKAGE,
      'publisher-client.channel' => 'test',
      'publisher-client.server_url' => 'https://publisher.example.test',
    ]);
    File::ensureDirectoryExists($this->host.'/bootstrap/cache');
    File::ensureDirectoryExists($this->host.'/public/cms');
    File::put($this->host.'/bootstrap/cache/health.php', 'old-cache');
    File::put($this->host.'/public/cms/asset.css', 'old-asset');
  }

  protected function tearDown(): void
  {
    if (isset($this->temporary)) {
      $this->app->setBasePath($this->base);
      File::deleteDirectory($this->temporary);
    }
    parent::tearDown();
  }

  public function test_verified_local_candidate_keeps_peers_and_reinstalls_at_the_same_commit(): void
  {
    $before = $this->lock()['packages'][1];
    $archive = $this->zip(self::PACKAGE, '1.0.1', ['other/peer' => '^1'], $this->commit);
    $reported = $this->offer($archive, true);
    $this->mock(PostApplyRunner::class)->expects('run')->once();
    $result = app(SystemUpdater::class)->run();
    $this->assertSame('success', $result->status);
    $this->assertSame([true], $reported->health);

    $root = json_decode(File::get($this->host.'/composer.json'), true);
    $this->assertSame('1.0.1', $root['require'][self::PACKAGE]);
    $lock = $this->lock();
    $this->assertSame($before, $lock['packages'][1]);
    $cms = $lock['packages'][0];
    $this->assertSame($this->commit, $cms['source']['reference']);
    $this->assertSame($this->commit, $cms['dist']['reference']);
    $this->assertSame('https://publisher.example.test/test-downloads/123', $cms['dist']['url']);
    $this->assertSame(sha1_file($archive), $cms['dist']['shasum']);
    $this->assertRuntime($this->host, $cms);

    $fresh = $this->temporary.'/fresh';
    File::ensureDirectoryExists($fresh);
    File::copy($this->host.'/composer.json', $fresh.'/composer.json');
    File::copy($this->host.'/composer.lock', $fresh.'/composer.lock');
    // Seed Composer's isolated dist cache with the same authorized download.
    // Keep both root documents byte-for-byte intact for the fresh install.
    $cache = $this->temporary.'/composer-cache/files/'.self::PACKAGE;
    File::ensureDirectoryExists($cache);
    File::copy($archive, $cache.'/'.sha1($cms['dist']['url']).'.zip');
    $this->composer($fresh, ['install']);
    $this->assertRuntime($fresh, $cms);
  }

  public function test_incompatible_locked_dependency_leaves_files_byte_identical(): void
  {
    $before = $this->hostFiles();
    [$staged] = $this->stage(['other/peer' => '^2']);
    $output = [];
    try {
      app(ApplyStrategy::class)->prepare($staged, $output);
      $this->fail('Incompatible locked dependency was accepted.');
    } catch (UpdateException $exception) {
      $this->assertStringContainsString('incompatible', $exception->userMessage());
      $this->assertStringContainsString('other/peer', implode("\n", $output));
    }
    $this->assertSame($before, $this->hostFiles());
  }

  public function test_stable_lock_uses_public_distribution_for_the_immutable_source_commit(): void
  {
    config(['publisher-client.channel' => 'stable']);
    [$staged] = $this->stage(['other/peer' => '^1']);
    $strategy = app(ApplyStrategy::class);
    $output = [];
    $strategy->prepare($staged, $output);
    $strategy->apply($staged);
    $strategy->commit();
    app(CmsPackageRollback::class)->commit();

    $cms = $this->lock()['packages'][0];
    $this->assertSame('https://api.github.com/repos/fklavyenet/webblocks-cms/zipball/'.$this->commit, $cms['dist']['url']);
    $this->assertSame($this->commit, $cms['source']['reference']);
    $this->assertSame($this->commit, $cms['dist']['reference']);
    $this->assertRuntime($this->host, $cms);
  }

  public function test_failed_health_check_restores_the_package_and_host_files(): void
  {
    $before = $this->hostFiles();
    $archive = $this->zip(self::PACKAGE, '1.0.1', ['other/peer' => '^1'], $this->commit);
    $reported = $this->offer($archive, false);
    $this->mock(PostApplyRunner::class)->expects('run')->once()->andReturnUsing(function (): never {
      Schema::create('fixture_candidate_table', fn ($table) => $table->id());
      File::put($this->host.'/bootstrap/cache/health.php', 'new-cache');
      File::put($this->host.'/public/cms/asset.css', 'new-asset');
      File::put($this->host.'/public/cms/candidate.css', 'new-file');
      throw new UpdateException('Fixture failed its final health check.');
    });
    try {
      app(SystemUpdater::class)->run();
      $this->fail('An unhealthy update was reported as successful.');
    } catch (UpdateException $exception) {
      $this->assertSame('Fixture failed its final health check.', $exception->userMessage());
    }

    $this->assertSame($before, $this->hostFiles());
    $this->assertFalse(Schema::hasTable('fixture_candidate_table'));
    $this->assertSame([false], $reported->health);
  }

  private function offer(string $archive, bool $healthy): object
  {
    $keys = sodium_crypto_sign_keypair();
    $digest = hash_file('sha256', $archive);
    $proof = base64_encode(random_bytes(90));
    $reported = (object) ['health' => []];
    config([
      'publisher-client.version.source' => '1.0.0',
      'publisher-client.signature.public_key' => base64_encode(sodium_crypto_sign_publickey($keys)),
      'publisher-client.apply.maintenance_mode' => false,
      'publisher-client.backup.enabled' => true,
      'webblocks-updates.test.console_url' => 'https://console.example.test',
      'webblocks-updates.test.installation_id' => (string) Str::uuid(),
      'webblocks-updates.test.installation_key' => str_repeat('A', 43),
    ]);
    Http::preventStrayRequests();
    Http::fake([
      'https://console.example.test/api/v1/publisher-test/authorize-check' => Http::response(['ok' => true, 'authorization' => 'isolated-check']),
      'https://console.example.test/api/v1/publisher-test/authorize-download' => Http::response(['ok' => true, 'authorization' => 'isolated-download']),
      'https://publisher.example.test/api/updates/latest*' => Http::response(['api_version' => '1', 'data' => [
        'product' => 'webblocks-cms', 'channel' => 'test', 'version' => '1.0.1',
        'artifact_url' => 'https://publisher.example.test/test-downloads/123',
        'checksum_sha256' => $digest,
        'signature' => base64_encode(sodium_crypto_sign_detached($digest, sodium_crypto_sign_secretkey($keys))),
      ]]),
      'https://publisher.example.test/test-downloads/123' => Http::response(File::get($archive), 200, ['X-Publisher-Pilot-Receipt' => $proof]),
      'https://publisher.example.test/api/updates/test/releases/123/receipt' => function ($request) use ($digest, $proof, $healthy, $reported) {
        $this->assertTrue($request->hasHeader('Authorization', 'Bearer isolated-download'));
        $this->assertTrue($request->hasHeader('X-Publisher-Pilot-Receipt', $proof));
        $this->assertSame($digest, $request['checksum_sha256']);
        $this->assertSame('1.0.1', $request['version']);
        $this->assertSame($healthy, $request['healthy']);
        $reported->health[] = $request['healthy'];

        return Http::response(['data' => ['release_id' => 123, 'healthy' => $healthy]], 201);
      },
    ]);

    return $reported;
  }

  private function stage(array $requirements): array
  {
    $archive = $this->zip(self::PACKAGE, '1.0.1', $requirements, $this->commit);
    app(CmsPublisherArtifact::class)->start('https://publisher.example.test/test-downloads/123');
    $staged = app(CmsUpdateExtractor::class)->extract($archive, $this->temporary.'/extracted');

    return [$staged, $archive];
  }

  private function zip(string $name, string $version, array $requirements, ?string $commit = null): string
  {
    $path = $this->temporary.'/'.str_replace('/', '-', $name).'-'.$version.'.zip';
    $archive = new ZipArchive;
    $this->assertTrue($archive->open($path, ZipArchive::CREATE));
    $archive->addFromString('composer.json', json_encode($this->package($name, $version, $requirements), JSON_THROW_ON_ERROR));
    $archive->addFromString('LICENSE', 'MIT');
    $archive->addFromString('src/Support/WebBlocks.php', "<?php namespace WebBlocks\\Cms\\Support; class WebBlocks { public const VERSION = '$version'; }");
    if ($commit !== null) {
      $archive->setArchiveComment(json_encode(['product' => 'webblocks-cms', 'source_commit' => $commit], JSON_THROW_ON_ERROR));
    }
    $this->assertTrue($archive->close());

    return $path;
  }

  private function package(string $name, string $version, array $requirements, ?string $url = null): array
  {
    $data = ['name' => $name, 'version' => $version, 'type' => 'library', 'require' => (object) $requirements];
    if ($name === self::PACKAGE) {
      $data['autoload'] = ['psr-4' => ['WebBlocks\\Cms\\' => 'src/']];
    }
    if ($url !== null) {
      $data['dist'] = ['type' => 'zip', 'url' => $url, 'reference' => 'previous'];
    }

    return $data;
  }

  private function writeJsonFile(string $path, array $document): void
  {
    File::put($path, json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
  }

  private function lock(): array
  {
    return json_decode(File::get($this->host.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
  }

  private function composer(string $root, array $arguments): void
  {
    $runner = app(UpdateCommandRunner::class);
    $command = $runner->composerCommand([...$arguments, '--no-interaction', '--no-plugins', '--no-scripts']);
    $process = new Process($command, $root, [
      'COMPOSER_DISABLE_NETWORK' => '1',
      'COMPOSER_CACHE_DIR' => $this->temporary.'/composer-cache',
    ]);
    $process->mustRun();
  }

  private function assertRuntime(string $root, array $package): void
  {
    $installed = json_decode(File::get($root.'/vendor/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
    $entry = collect($installed['packages'])->firstWhere('name', self::PACKAGE);
    $this->assertSame($package['version'], $entry['version']);
    $this->assertSame($package['source'], $entry['source']);
    $this->assertSame($package['dist'], $entry['dist']);
    $php = '$loader = require "vendor/autoload.php"; echo $loader->findFile("WebBlocks\\\\Cms\\\\Support\\\\WebBlocks")."\\n".Composer\\InstalledVersions::getReference("'.self::PACKAGE.'");';
    $process = new Process([PHP_BINARY, '-r', $php], $root);
    $process->mustRun();
    $this->assertStringContainsString('/fklavyenet/webblocks-cms/src/Support/WebBlocks.php', $process->getOutput());
    $this->assertStringEndsWith($this->commit, $process->getOutput());
  }

  private function hostFiles(): array
  {
    $files = [];
    foreach (File::allFiles($this->host, true) as $file) {
      $files[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
    }
    ksort($files);

    return $files;
  }
}
