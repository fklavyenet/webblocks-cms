<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The Edit Site form is the largest Blade in the admin and the one operators
 * hit for every site setting. A directive Blade fails to recognise survives
 * verbatim into the compiled view, renders as text and leaves its variables
 * undefined — which is how a block `@php` once shipped broken.
 */
class SiteFormCompilesTest extends TestCase
{
  /**
   * @return list<string>
   */
  private function adminViews(): array
  {
    return [
      'admin/sites/form.blade.php',
      'admin/sites/partials/theme-tab.blade.php',
      'layouts/admin.blade.php',
    ];
  }

  #[Test]
  public function the_admin_site_views_compile_with_no_directive_left_behind(): void
  {
    $compiler = app('blade.compiler');

    foreach ($this->adminViews() as $view) {
      $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/'.$view);
      $compiled = $compiler->compileString($source);

      foreach ([
        '@php',
        '@endphp',
        '@if',
        '@endif',
        '@foreach',
        '@endforeach',
        '@include',
        '@error',
        '@unless',
      ] as $directive) {
        $this->assertStringNotContainsString(
          $directive,
          $compiled,
          sprintf('%s survived compilation of %s; Blade did not recognise it.', $directive, $view)
        );
      }
    }
  }
}
