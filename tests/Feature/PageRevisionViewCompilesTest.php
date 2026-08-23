<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

class PageRevisionViewCompilesTest extends TestCase
{
  #[Test]
  public function version_history_views_compile(): void
  {
    foreach (['index', 'show'] as $view) {
      $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/pages/revisions/'.$view.'.blade.php');
      $compiled = app('blade.compiler')->compileString($source);

      $this->assertNotSame('', $compiled);
    }
  }
}
