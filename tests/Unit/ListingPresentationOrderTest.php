<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\ListingViews\ListingViewDefinition;

class ListingPresentationOrderTest extends TestCase
{
  #[Test]
  public function legacy_column_order_keeps_visibility_without_rewriting_the_saved_view(): void
  {
    $definition = new ListingViewDefinition;
    $saved = ['updated', 'view', 'blocks', 'status'];
    $this->assertSame(['status', 'blocks', 'updated'], $definition->presentationColumns('pages', $saved));
    $this->assertSame(['updated', 'view', 'blocks', 'status'], $saved);
    $this->assertSame(['updated'], $definition->presentationColumns('pages', ['updated', 'view']));
    $this->assertSame([], $definition->presentationColumns('pages', ['view']));
    $this->assertSame([], $definition->presentationColumns('media', []));
    $this->assertSame(['preview', 'folder', 'usage', 'updated'], $definition->presentationColumns('media', ['updated', 'usage', 'folder', 'preview']));
  }

  #[Test]
  public function list_descriptions_have_complete_bundled_translations(): void
  {
    $root = dirname(__DIR__, 2).'/resources/lang/';
    $english = require $root.'en/admin_lists.php';
    foreach (['de', 'tr', 'fr', 'es', 'it'] as $locale) {
      $translated = require $root.$locale.'/admin_lists.php';
      $this->assertSame(array_keys($english), array_keys($translated));
      foreach ($translated as $text) {
        $this->assertNotSame('', trim($text));
      }
    }
  }
}
