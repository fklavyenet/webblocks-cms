<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * webblocks-ui ships `.wb-table td { white-space: normal; overflow-wrap:
 * anywhere }`. That is a class *and* a type selector, so it outranks a lone
 * class in admin.css: a `.wb-some-id-cell { white-space: nowrap }` rule loads
 * later, looks right in the file, and still loses -- which is how a page id
 * kept breaking across two lines after a release that "fixed" it.
 *
 * Any admin rule that has to beat the base table styling must name the table
 * class too, the way the slot block table rules already do.
 */
class AdminTableCellSpecificityTest extends TestCase
{
  #[Test]
  public function admin_table_cell_overrides_outrank_the_ui_base_rule(): void
  {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/public/cms/css/admin.css');
    $underspecified = [];

    // Selectors for a table cell that fight the base rule: they set white-space
    // or overflow-wrap on something named *-cell.
    preg_match_all('/^([^{}\n][^{}]*-cell[^{}]*)\{([^}]*)\}/m', $css, $matches, PREG_SET_ORDER);

    foreach ($matches as [, $selector, $body]) {
      if (! preg_match('/white-space|overflow-wrap|word-break/', $body)) {
        continue;
      }

      foreach (explode(',', $selector) as $single) {
        if (substr_count($single, '.') < 2) {
          $underspecified[] = trim($single);
        }
      }
    }

    $this->assertSame(
      [],
      $underspecified,
      'These selectors lose to webblocks-ui\'s `.wb-table td`. Scope them with the table class as well.',
    );
  }
}
