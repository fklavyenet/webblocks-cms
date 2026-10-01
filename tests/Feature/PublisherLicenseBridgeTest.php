<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\System\Updates\CmsPackageApplyStrategy;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;
use WebBlocks\Cms\Tests\Fixtures\PublisherV1881\CmsPublisherClientConfigurator as LegacyConfigurator;
use WebBlocks\Cms\Tests\Fixtures\PublisherV1881\PackageApplyStrategy as LegacyStrategy;
use WebBlocks\Cms\Tests\TestCase;
use ZipArchive;

// Frozen v1.88.1 released behavior; namespace/import adaptation only.
require_once __DIR__.'/../fixtures/PublisherV1881/PackageArtifactValidator.php';
require_once __DIR__.'/../fixtures/PublisherV1881/PackageApplyStrategy.php';
require_once __DIR__.'/../fixtures/PublisherV1881/CmsPublisherClientConfigurator.php';

class PublisherLicenseBridgeTest extends TestCase
{
  private string $work;

  protected function setUp(): void
  {
    parent::setUp();
    $this->work = sys_get_temp_dir().'/cms-license-'.uniqid();
    File::ensureDirectoryExists($this->work.'/installed');
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->work);
    parent::tearDown();
  }

  private function target(): void
  {
    config(['publisher-client.apply.target_path' => $this->work.'/installed']);
  }

  private function stage(string $layout): string
  {
    $stage = $this->work.'/stage';
    File::ensureDirectoryExists($stage.'/src');
    File::put($stage.'/src/runtime.php', '<?php');
    File::put($stage.'/composer.json', '{"name":"fklavyenet/webblocks-cms"}');
    if ($layout !== 'missing') {
      File::ensureDirectoryExists(dirname($stage.'/'.$layout));
      File::put($stage.'/'.$layout, 'license');
    }

    return $stage;
  }

  public function test_generated_zip_applies_with_released_updater_then_future_zip_applies(): void
  {
    $repo = dirname(__DIR__, 2);
    // Snapshot tracked working files plus this new adapter without changing the
    // real repository index, HEAD, tags, or branches.
    $snapshot = $this->work.'/source';
    exec('git -C '.escapeshellarg($repo).' ls-files', $paths);
    $paths[] = 'src/Support/System/Updates/CmsPackageApplyStrategy.php';
    foreach ($paths as $path) {
      if (is_file($repo.'/'.$path)) {
        File::ensureDirectoryExists(dirname($snapshot.'/'.$path));
        File::copy($repo.'/'.$path, $snapshot.'/'.$path);
      }
    }
    exec('git -C '.escapeshellarg($snapshot).' init -q');
    exec('git -C '.escapeshellarg($snapshot).' add .');
    exec('git -C '.escapeshellarg($snapshot).' write-tree', $trees, $code);
    $this->assertSame(0, $code);
    exec('bash '.escapeshellarg($snapshot.'/scripts/release/build-package.sh').' '.escapeshellarg($this->work.'/update.zip').' '.escapeshellarg($trees[0]).' 2>&1', $output, $code);
    $this->assertSame(0, $code, implode("\n", $output));
    $zip = new ZipArchive;
    $this->assertTrue($zip->open($this->work.'/update.zip'));
    $this->assertFalse($zip->locateName('LICENSE'));
    $this->assertNotFalse($zip->locateName('docs/LICENSE'));
    $this->assertNotFalse($zip->locateName('src/Support/System/Updates/CmsPackageApplyStrategy.php'));
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $path = $zip->getNameIndex($i);
      if (str_starts_with($path, 'docs/')) {
        $this->assertContains($path, ['docs/', 'docs/LICENSE']);
      }
    }
    $zip->extractTo($this->work.'/transition');
    $zip->close();
    app(LegacyConfigurator::class)->configure();
    $this->target();
    app(LegacyStrategy::class)->apply($this->work.'/transition');
    $this->assertSame(File::get($repo.'/LICENSE'), File::get($this->work.'/installed/LICENSE'));

    app(CmsPublisherClientConfigurator::class)->configure();
    $this->target();
    app(CmsPackageApplyStrategy::class)->apply($this->stage('LICENSE'));
    $this->assertSame('license', File::get($this->work.'/installed/LICENSE'));
    $this->assertDirectoryDoesNotExist($this->work.'/installed/docs');
  }

  public function test_composer_and_git_archives_exclude_documentation(): void
  {
    $repo = dirname(__DIR__, 2);
    foreach (['composer', 'git'] as $kind) {
      $zipPath = $this->work.'/'.$kind.'.zip';
      $command = $kind === 'composer'
        ? 'composer archive --working-dir='.escapeshellarg($repo).' --format=zip --dir='.escapeshellarg($this->work).' --file=composer'
        : 'git -C '.escapeshellarg($repo).' archive --format=zip --output='.escapeshellarg($zipPath).' HEAD';
      exec($command.' 2>&1', $output, $code);
      $this->assertSame(0, $code, implode("\n", $output));
      $zip = new ZipArchive;
      $this->assertTrue($zip->open($zipPath));
      $this->assertNotFalse($zip->locateName('LICENSE'));
      for ($i = 0; $i < $zip->numFiles; $i++) {
        $path = $zip->getNameIndex($i);
        $this->assertDoesNotMatchRegularExpression('~^(docs|tests|scripts)/~', $path);
        $this->assertStringNotContainsString('visual-fixtures/', $path);
      }
      $zip->close();
    }
  }

  public function test_new_updater_accepts_legacy_license(): void
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    $this->target();
    app(CmsPackageApplyStrategy::class)->apply($this->stage('docs/LICENSE'));
    $this->assertSame('license', File::get($this->work.'/installed/LICENSE'));
    $this->assertDirectoryDoesNotExist($this->work.'/installed/docs');
  }

  public function test_root_license_wins_when_both_exist(): void
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    $this->target();
    $stage = $this->stage('docs/LICENSE');
    File::put($stage.'/LICENSE', 'preferred');
    app(CmsPackageApplyStrategy::class)->apply($stage);
    $this->assertSame('preferred', File::get($this->work.'/installed/LICENSE'));
  }

  public function test_released_updater_rejects_root_only_license(): void
  {
    app(LegacyConfigurator::class)->configure();
    $this->target();
    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Package file-promotion source is missing or unsafe: docs/LICENSE.');
    app(LegacyStrategy::class)->apply($this->stage('LICENSE'));
  }

  public function test_released_updater_rejects_duplicate_license_destination(): void
  {
    app(LegacyConfigurator::class)->configure();
    $this->target();
    $stage = $this->stage('docs/LICENSE');
    File::put($stage.'/LICENSE', 'duplicate');
    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Package file-promotion destination already exists: LICENSE.');
    app(LegacyStrategy::class)->apply($stage);
  }

  public function test_new_updater_rejects_docs_content_before_normalizing_license(): void
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    $this->target();
    $stage = $this->stage('docs/LICENSE');
    File::put($stage.'/docs/index.md', 'documentation');
    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Path outside package allowlist');
    app(CmsPackageApplyStrategy::class)->apply($stage);
  }

  public function test_missing_license_fails_before_replacing_installation(): void
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    $this->target();
    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Required package path missing');
    app(CmsPackageApplyStrategy::class)->apply($this->stage('missing'));
  }
}
