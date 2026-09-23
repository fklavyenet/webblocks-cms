<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

class GroupedImageViewerRuntimeTest extends TestCase
{
  #[Test]
  public function public_runtime_gives_separated_grouped_images_a_neutral_gallery_scope(): void
  {
    $root = dirname(__DIR__, 2);
    $javascript = (string) file_get_contents($root.'/public/cms/js/public/grouped-image-viewer.js');
    $css = (string) file_get_contents($root.'/public/cms/css/public.css');
    $layout = (string) file_get_contents($root.'/resources/views/layouts/public.blade.php');

    $this->assertStringContainsString('.wb-gallery-trigger[data-wb-gallery-group]', $javascript);
    $this->assertStringContainsString("classList.add('wb-gallery', 'wb-gallery-document-scope')", $javascript);
    $this->assertStringContainsString('body.wb-gallery.wb-gallery-document-scope', $css);
    $this->assertStringContainsString("public_path('cms/js/public/grouped-image-viewer.js')", $layout);
    $this->assertStringContainsString("asset('cms/js/public/grouped-image-viewer.js')", $layout);
  }
}
