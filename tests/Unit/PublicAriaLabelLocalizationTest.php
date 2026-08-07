<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * An aria-label is text a screen reader reads out, so an English one on a
 * French page is as wrong as an English heading would be -- it is just
 * invisible, which is why several sat hard-coded in the public blades through
 * every locale the CMS shipped. Nothing but the eye was checking, so this does.
 */
class PublicAriaLabelLocalizationTest extends TestCase
{
  #[Test]
  public function no_public_blade_hard_codes_an_aria_label(): void
  {
    $offenders = [];

    foreach ($this->publicBlades() as $path) {
      $lines = preg_split('/\r\n|\r|\n/', (string) file_get_contents($path)) ?: [];

      foreach ($lines as $number => $line) {
        // A literal label starts with a letter; a rendered one starts with {{
        // or a PHP concatenation of a translated value.
        if (preg_match('/aria-label="[A-Za-z]/', $line) === 1) {
          $offenders[] = basename($path).':'.($number + 1);
        }
      }
    }

    $this->assertSame(
      [],
      $offenders,
      'These aria-labels are literal English. Read them from blocks.a11y.* through CmsTranslator instead.',
    );
  }

  /**
   * @return list<string>
   */
  private function publicBlades(): array
  {
    $root = dirname(__DIR__, 2).'/resources/views/pages';
    $files = [];

    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

    foreach ($iterator as $file) {
      if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
        $files[] = $file->getPathname();
      }
    }

    sort($files);

    $this->assertNotEmpty($files, 'The public blade directory must be readable for this guard to mean anything.');

    return $files;
  }
}
