<?php

namespace WebBlocks\Cms\Tests\Feature;

use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\Updates\Client\Apply\PackageArtifactValidator;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;
use WebBlocks\Cms\Tests\TestCase;

class DocumentationPackageBoundaryTest extends TestCase
{
  public function test_update_validator_accepts_product_contract_and_root_license(): void
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    $validator = PackageArtifactValidator::fromConfig();
    $validator->assertAllowedPath('resources/contracts/inventory.md');
    $validator->assertAllowedPath('LICENSE');
    $this->addToAssertionCount(2);
  }

  public function test_update_validator_rejects_documentation_sources(): void
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    $this->expectException(UpdateException::class);
    PackageArtifactValidator::fromConfig()->assertAllowedPath('docs/index.md');
  }

  public function test_update_validator_requires_a_root_license(): void
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    $root = sys_get_temp_dir().'/webblocks-package-license-'.uniqid('', true);
    mkdir($root.'/src', 0775, true);
    file_put_contents($root.'/composer.json', '{}');

    try {
      $this->expectException(UpdateException::class);
      $this->expectExceptionMessage('Required package path missing from staged artifact: LICENSE.');
      PackageArtifactValidator::fromConfig()->validate($root);
    } finally {
      app('files')->deleteDirectory($root);
    }
  }
}
