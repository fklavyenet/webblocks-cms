<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Structural guards for the Edit Site form.
 *
 * The tab strip pairs one button with one panel by tab key. A second panel
 * carrying an existing key silently changes what the strip shows, which is how
 * the brand palette card first shipped hidden behind the branding tab.
 */
class SiteFormStructureTest extends TestCase
{
  private function form(): string
  {
    return (string) file_get_contents(
      dirname(__DIR__, 2).'/resources/views/admin/sites/form.blade.php'
    );
  }

  /**
   * @return list<string>
   */
  private function tabKeys(string $form): array
  {
    preg_match('/role="tablist".*?@endforeach/s', $form, $navMatch);
    preg_match_all("/'([a-z-]+)' => \\\$adminText\\(/", $navMatch[0] ?? '', $matches);

    return $matches[1] ?? [];
  }

  /**
   * @return list<string>
   */
  private function panelKeys(string $form): array
  {
    preg_match_all('/wb-tabs-panel \{\{ \$siteTab === \'([a-z-]+)\'/', $form, $matches);

    return $matches[1] ?? [];
  }

  #[Test]
  public function every_tab_has_exactly_one_panel(): void
  {
    $form = $this->form();
    $tabs = $this->tabKeys($form);
    $panels = $this->panelKeys($form);

    $this->assertNotEmpty($tabs);
    $this->assertSame(
      $panels,
      array_values(array_unique($panels)),
      'A tab key owns exactly one panel; a duplicate panel hides its own content.'
    );
    $this->assertSame($tabs, $panels, 'Tab buttons and panels must line up one to one.');
  }

  #[Test]
  public function the_brand_palette_fields_live_in_the_brand_tab(): void
  {
    $form = $this->form();

    $brandStart = strpos($form, "wb-tabs-panel {{ \$siteTab === 'brand'");
    $nextPanel = strpos($form, "wb-tabs-panel {{ \$siteTab === 'seo-defaults'");

    $this->assertNotFalse($brandStart);
    $this->assertNotFalse($nextPanel);
    $this->assertLessThan($nextPanel, $brandStart);

    $brandingPanel = substr($form, $brandStart, $nextPanel - $brandStart);

    foreach ([
      'brand_palette',
      'brand_accent',
      'brand_accent_secondary',
      'brand_surface',
      'brand_text',
      'brand_font_heading',
      'brand_font_body',
    ] as $field) {
      $this->assertStringContainsString($field, $brandingPanel);
    }
  }

  #[Test]
  public function the_brand_palette_strings_exist_in_every_shipped_locale(): void
  {
    $keys = [
      'brand_palette',
      'brand_palette_help',
      'brand_accent',
      'brand_accent_help',
      'brand_accent_secondary',
      'brand_accent_secondary_help',
      'brand_surface',
      'brand_surface_help',
      'brand_text',
      'brand_text_help',
      'brand_contrast_warning',
      'brand_font_heading',
      'brand_font_heading_help',
      'brand_font_body',
      'brand_font_body_help',
    ];

    foreach (['en', 'tr', 'de'] as $locale) {
      $lang = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/admin.php';

      foreach ($keys as $key) {
        $this->assertArrayHasKey(
          $key,
          $lang['site_form'],
          sprintf('Missing %s in the %s admin strings.', $key, $locale)
        );
        $this->assertNotSame('', trim((string) $lang['site_form'][$key]));
      }
    }
  }
}
