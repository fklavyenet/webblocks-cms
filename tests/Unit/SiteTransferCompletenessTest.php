<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Sites\ExportImport\ImportDataMapper;

/**
 * Guards for what a site transfer actually carries.
 *
 * Every failure this file pins was found the same way: the package looked
 * complete, the import reported success, and the site came out wrong. The
 * export used to ship the site row's name and handle and none of its
 * configuration, so an imported site silently lost its brand palette and fell
 * back to the product theme. It shipped site.css and site.js and no other file
 * under the site directory, so a stylesheet arrived without the fonts it
 * declared. And the two-filename allowlist behind that was written down in
 * three separate places, which is why generalising two of them was not enough.
 */
class SiteTransferCompletenessTest extends TestCase
{
  private function source(string $relative): string
  {
    return (string) file_get_contents(dirname(__DIR__, 2).'/src/Support/Sites/ExportImport/'.$relative);
  }

  #[Test]
  public function the_export_carries_every_site_setting_the_admin_form_owns(): void
  {
    $builder = $this->source('SiteExportDataBuilder.php');
    $siteBlock = substr($builder, (int) strpos($builder, "'site' => ["), 2000);

    // Anything editable on Edit Site is part of the site's identity and has to
    // survive a transfer. The brand fields are the ones that bit: a site
    // imported without them renders in the product default theme.
    foreach ([
      'display_name',
      'tagline',
      'favicon_media_id',
      'social_image_media_id',
      'seo_title',
      'seo_description',
      'seo_keywords',
      'contact_recipient_email',
      'timezone',
      'public_theme_preset',
      'custom_head_html',
      'brand_accent',
      'brand_accent_secondary',
      'brand_surface',
      'brand_text',
      'brand_font_heading',
      'brand_font_body',
    ] as $field) {
      $this->assertStringContainsString("'".$field."'", $siteBlock, $field.' is not exported.');
    }
  }

  #[Test]
  public function the_import_applies_those_settings_to_the_new_site(): void
  {
    $mapper = $this->source('ImportDataMapper.php');
    $createSite = substr($mapper, (int) strpos($mapper, 'private function createSite('), 2600);

    foreach (['brand_accent', 'brand_font_heading', 'public_theme_preset', 'custom_head_html', 'seo_description'] as $field) {
      $this->assertStringContainsString("'".$field."'", $createSite, $field.' is exported but never applied.');
    }

    // Favicon and social image are ids in the source install and the site row
    // is written before the media exists, so they are rebound in their own
    // phase rather than at creation.
    $this->assertStringNotContainsString("'favicon_media_id'", $createSite);
    $this->assertStringContainsString('private function importSiteBranding(', $mapper);
    $this->assertStringContainsString("'site_branding'", $mapper);
  }

  #[Test]
  public function no_copy_of_the_two_filename_allowlist_survives(): void
  {
    // This list lived in the export builder, the archive builder and the
    // importer. Two were generalised first and the third rejected the fonts
    // outright, so the guard covers all three files at once.
    foreach (['SiteExportDataBuilder.php', 'ExportArchiveBuilder.php', 'ImportDataMapper.php'] as $file) {
      $this->assertStringNotContainsString(
        'css/site\.css|js/site\.js',
        $this->source($file),
        $file.' still restricts site assets to two filenames.'
      );
    }
  }

  #[Test]
  public function a_site_asset_write_that_fails_stops_the_import(): void
  {
    $mapper = $this->source('ImportDataMapper.php');

    // Both writes used to discard their result, so a site could import with
    // none of its assets on disk and nothing anywhere saying so.
    $this->assertMatchesRegularExpression('/!\s*mkdir\(\$targetDirectory/', $mapper);
    $this->assertStringContainsString('$written = file_put_contents($targetPath, $contents);', $mapper);
    $this->assertStringContainsString('if ($written === false || $written !== strlen((string) $contents))', $mapper);

    // An entry the importer cannot place is a broken package, not a skip.
    $this->assertStringNotContainsString(
      "if (\$sourceRelativePath === null) {\n        continue;",
      $mapper
    );
  }

  #[Test]
  public function a_stylesheet_follows_its_site_when_the_handle_changes(): void
  {
    $rebase = new ReflectionMethod(ImportDataMapper::class, 'rebaseSiteAssetReferences');
    $rebase->setAccessible(true);
    $mapper = (new \ReflectionClass(ImportDataMapper::class))->newInstanceWithoutConstructor();

    $css = "@font-face{src:url('/site/default/fonts/a.woff2')}\n.x{background:url(/site/default/img/b.png)}";

    $this->assertSame(
      "@font-face{src:url('/site/imported/fonts/a.woff2')}\n.x{background:url(/site/imported/img/b.png)}",
      $rebase->invoke($mapper, $css, 'site/imported/css/site.css', 'default', 'imported'),
      'Copied files that keep the old handle in their URLs 404 on the imported site.'
    );
  }

  #[Test]
  public function binary_assets_and_unchanged_handles_are_left_alone(): void
  {
    $rebase = new ReflectionMethod(ImportDataMapper::class, 'rebaseSiteAssetReferences');
    $rebase->setAccessible(true);
    $mapper = (new \ReflectionClass(ImportDataMapper::class))->newInstanceWithoutConstructor();

    $font = "\x00\x01wOF2/site/default/not-a-reference";

    $this->assertSame(
      $font,
      $rebase->invoke($mapper, $font, 'site/imported/fonts/a.woff2', 'default', 'imported'),
      'Rewriting bytes inside a font file would corrupt it.'
    );

    $css = "url('/site/default/fonts/a.woff2')";
    $this->assertSame($css, $rebase->invoke($mapper, $css, 'site/default/css/site.css', 'default', 'default'));
    $this->assertSame($css, $rebase->invoke($mapper, $css, 'site/imported/css/site.css', '', 'imported'));
  }

  #[Test]
  public function the_export_honours_an_explicit_page_selection(): void
  {
    $builder = $this->source('SiteExportDataBuilder.php');

    $this->assertStringContainsString('?array $pageIds = null', $builder);
    $this->assertStringContainsString("\$query->whereIn('id', \$pageIds)", $builder);

    // Null keeps the whole site, so the CLI and the API export what they
    // always did while the picker only affects callers that use it.
    $this->assertStringContainsString('$pageIds !== null', $builder);
  }

  #[Test]
  public function the_site_asset_directory_has_a_size_ceiling(): void
  {
    $builder = $this->source('SiteExportDataBuilder.php');

    // The whole directory travels, and nobody audits what an operator drops
    // in there. Past the budget the export says so instead of producing a
    // package that cannot be uploaded.
    $this->assertStringContainsString('site_asset_max_bytes', $builder);
    $this->assertStringContainsString('over the', $builder);

    $config = (string) file_get_contents(dirname(__DIR__, 2).'/config/webblocks-cms.php');
    $this->assertStringContainsString('site_asset_max_bytes', $config);
  }

  #[Test]
  public function the_page_picker_strings_exist_in_every_shipped_locale(): void
  {
    foreach (['en', 'tr', 'de'] as $locale) {
      $lang = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/admin.php';

      foreach ([
        'pages_to_include',
        'pages_to_include_help',
        'select_all_pages',
        'select_published_pages',
        'select_no_pages',
      ] as $key) {
        $this->assertArrayHasKey($key, $lang['site_transfers'], sprintf('Missing %s in the %s strings.', $key, $locale));
        $this->assertNotSame('', trim((string) $lang['site_transfers'][$key]));
      }
    }
  }

  #[Test]
  public function the_site_model_can_hold_everything_the_import_writes(): void
  {
    // A field the export carries and the model does not fill is dropped
    // silently by Eloquent, which is the same invisible loss in a new place.
    $fillable = (new Site)->getFillable();

    foreach ([
      'display_name',
      'tagline',
      'seo_title',
      'seo_description',
      'seo_keywords',
      'contact_recipient_email',
      'public_theme_preset',
      'custom_head_html',
      'brand_accent',
      'brand_font_body',
    ] as $field) {
      $this->assertContains($field, $fillable, $field.' is imported but not fillable.');
    }
  }
}
