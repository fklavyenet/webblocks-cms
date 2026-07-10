<?php

namespace Tests\Unit\System\Updates;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemUpdaterSelfHandledClassPreloadTest extends TestCase
{
  #[Test]
  public function updater_preloads_self_handled_result_and_exception_classes_before_replacing_package_files(): void
  {
    $source = (string) file_get_contents(base_path('packages/webblocks-cms/src/Support/System/Updates/SystemUpdater.php'));

    $preloadPosition = strpos($source, '$this->preloadSelfHandledClasses();');
    $applyPosition = strpos($source, '$this->updateInstaller->applyPackage($packageRoot, $output);');

    $this->assertIsInt($preloadPosition);
    $this->assertIsInt($applyPosition);
    $this->assertLessThan($applyPosition, $preloadPosition);
    $this->assertStringContainsString('class_exists(UpdateException::class);', $source);
    $this->assertStringContainsString('class_exists(UpdateResult::class);', $source);
  }
}
