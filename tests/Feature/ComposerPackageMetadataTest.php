<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class ComposerPackageMetadataTest extends TestCase
{
  /**
     * @return array<string, string>
     */
  private function packageComposerAutoload(): array
  {
    $composer = json_decode((string) file_get_contents(base_path('packages/webblocks-cms/composer.json')), true, 512, JSON_THROW_ON_ERROR);

    return $composer['autoload']['psr-4'] ?? [];
  }

  /**
     * @return array<string, string>
     */
  private function packageUpdaterSupportClasses(): array
  {
    return [
      'WebBlocks\\Cms\\Support\\System\\Updates\\SystemUpdater' => 'packages/webblocks-cms/src/Support/System/Updates/SystemUpdater.php',
      'WebBlocks\\Cms\\Support\\System\\Updates\\UpdateCheckResult' => 'packages/webblocks-cms/src/Support/System/Updates/UpdateCheckResult.php',
      'WebBlocks\\Cms\\Support\\System\\Updates\\UpdateCommandRunner' => 'packages/webblocks-cms/src/Support/System/Updates/UpdateCommandRunner.php',
      'WebBlocks\\Cms\\Support\\System\\Updates\\UpdateException' => 'packages/webblocks-cms/src/Support/System/Updates/UpdateException.php',
      'WebBlocks\\Cms\\Support\\System\\Updates\\UpdateInstaller' => 'packages/webblocks-cms/src/Support/System/Updates/UpdateInstaller.php',
      'WebBlocks\\Cms\\Support\\System\\Updates\\UpdateMigrationRunner' => 'packages/webblocks-cms/src/Support/System/Updates/UpdateMigrationRunner.php',
      'WebBlocks\\Cms\\Support\\System\\Updates\\UpdatePackageDownloader' => 'packages/webblocks-cms/src/Support/System/Updates/UpdatePackageDownloader.php',
      'WebBlocks\\Cms\\Support\\System\\Updates\\UpdatePackageExtractor' => 'packages/webblocks-cms/src/Support/System/Updates/UpdatePackageExtractor.php',
      'WebBlocks\\Cms\\Support\\System\\Updates\\UpdateResult' => 'packages/webblocks-cms/src/Support/System/Updates/UpdateResult.php',
      'WebBlocks\\Cms\\Support\\System\\Updates\\UpdateServerClient' => 'packages/webblocks-cms/src/Support/System/Updates/UpdateServerClient.php',
      'WebBlocks\\Cms\\Support\\System\\Updates\\UpdateWorkspaceManager' => 'packages/webblocks-cms/src/Support/System/Updates/UpdateWorkspaceManager.php',
    ];
  }

  /**
     * @return array<string, array<int, string>>
     */
  private function packageUpdaterSupportReferences(): array
  {
    return [
      'packages/webblocks-cms/src/Http/Controllers/Admin/SystemUpdateController.php' => [
        'WebBlocks\\Cms\\Support\\System\\Updates\\SystemUpdater',
        'WebBlocks\\Cms\\Support\\System\\Updates\\UpdateException',
      ],
      'packages/webblocks-cms/src/Support/System/SystemUpdateInspector.php' => [
        'WebBlocks\\Cms\\Support\\System\\Updates\\SystemUpdater',
        'WebBlocks\\Cms\\Support\\System\\Updates\\UpdateServerClient',
      ],
    ];
  }

  #[Test]
  public function root_composer_json_exposes_installable_package_metadata(): void
  {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

    $this->assertSame('fklavyenet/webblocks-cms', $composer['name'] ?? null);
    $this->assertSame('library', $composer['type'] ?? null);
    $this->assertSame('app/', $composer['autoload']['psr-4']['App\\'] ?? null);
    $this->assertSame('packages/webblocks-cms/src/', $composer['autoload']['psr-4']['WebBlocks\\Cms\\'] ?? null);
    $this->assertSame('packages/webblocks-cms/database/seeders/', $composer['autoload']['psr-4']['WebBlocks\\Cms\\Database\\Seeders\\'] ?? null);
    $this->assertSame('database/factories/', $composer['autoload']['psr-4']['Database\\Factories\\'] ?? null);
    $this->assertSame('database/seeders/', $composer['autoload']['psr-4']['Database\\Seeders\\'] ?? null);
    $this->assertSame('project/', $composer['autoload']['psr-4']['Project\\'] ?? null);
    $this->assertSame([
      'app/Models/User.php',
      'app/Http/Controllers/Controller.php',
      'app/Providers/AppServiceProvider.php',
      'database/seeders/DatabaseSeeder.php',
      'database/factories/UserFactory.php',
      'project/',
    ], $composer['autoload']['exclude-from-classmap'] ?? []);
    $this->assertContains(WebBlocksCmsServiceProvider::class, $composer['extra']['laravel']['providers'] ?? []);
  }

  #[Test]
  public function maintenance_repository_explicitly_loads_the_package_provider_locally(): void
  {
    $providers = require base_path('bootstrap/providers.php');

    $this->assertContains(WebBlocksCmsServiceProvider::class, $providers);
  }

  #[Test]
  public function package_composer_json_keeps_the_consumer_webblocks_namespace_pointing_at_src(): void
  {
    $composer = json_decode((string) file_get_contents(base_path('packages/webblocks-cms/composer.json')), true, 512, JSON_THROW_ON_ERROR);

    $this->assertSame('fklavyenet/webblocks-cms', $composer['name'] ?? null);
    $this->assertSame('library', $composer['type'] ?? null);
    $this->assertSame('src/', $composer['autoload']['psr-4']['WebBlocks\\Cms\\'] ?? null);
    $this->assertSame('database/seeders/', $composer['autoload']['psr-4']['WebBlocks\\Cms\\Database\\Seeders\\'] ?? null);
  }

  #[Test]
  public function package_composer_json_resolves_installed_update_exception_to_a_package_relative_src_path(): void
  {
    $autoload = $this->packageComposerAutoload();

    $this->assertSame(
      'src/Support/System/Updates/UpdateException.php',
      $this->resolvePsr4ClassPath($autoload, 'WebBlocks\\Cms\\Support\\System\\Updates\\UpdateException')
    );
  }

  #[Test]
  public function package_composer_json_resolves_package_seeders_to_package_relative_database_paths(): void
  {
    $autoload = $this->packageComposerAutoload();

    $this->assertSame('database/seeders/', $autoload['WebBlocks\\Cms\\Database\\Seeders\\'] ?? null);
    $this->assertArrayHasKey('WebBlocks\\Cms\\', $autoload);
    $this->assertArrayHasKey('WebBlocks\\Cms\\Database\\Seeders\\', $autoload);
  }

  #[Test]
  public function package_updater_support_classes_have_matching_files_and_resolve_through_autoload(): void
  {
    foreach ($this->packageUpdaterSupportClasses() as $className => $relativePath) {
      $absolutePath = base_path($relativePath);

      $this->assertFileExists($absolutePath, $relativePath);
      $this->assertTrue(class_exists($className), $className);

      $reflection = new ReflectionClass($className);

      $this->assertSame(realpath($absolutePath), $reflection->getFileName(), $className);
    }
  }

  #[Test]
  public function package_owned_updater_runtime_references_only_existing_package_support_classes(): void
  {
    foreach ($this->packageUpdaterSupportReferences() as $relativePath => $classNames) {
      $contents = (string) file_get_contents(base_path($relativePath));

      foreach ($classNames as $className) {
        $this->assertStringContainsString($className, $contents, $relativePath);
        $this->assertTrue(class_exists($className), $className);
      }
    }
  }

  /**
     * @param  array<string, string>  $autoload
     */
  private function resolvePsr4ClassPath(array $autoload, string $className): ?string
  {
    foreach ($autoload as $prefix => $path) {
      if (! str_starts_with($className, $prefix)) {
        continue;
      }

      $suffix = str_replace('\\', '/', substr($className, strlen($prefix)));

      return rtrim($path, '/').'/'.$suffix.'.php';
    }

    return null;
  }
}
