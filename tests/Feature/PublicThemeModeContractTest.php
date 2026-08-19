<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

class PublicThemeModeContractTest extends TestCase
{
  #[Test]
  public function graphite_has_distinct_light_dark_and_auto_palettes(): void
  {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/public/cms/css/public.css');

    $this->assertStringContainsString('body[data-wb-public-theme="graphite"]', $css);
    $this->assertStringContainsString('--wb-public-page-bg: #f4f7fa;', $css);
    $this->assertStringContainsString('html[data-mode="dark"] body[data-wb-public-theme="graphite"]', $css);
    $this->assertStringContainsString('html[data-mode="auto"] body[data-wb-public-theme="graphite"]', $css);
    $this->assertStringContainsString('--wb-public-page-bg: #090d12;', $css);
  }
}
