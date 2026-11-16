<?php

namespace WebBlocks\Cms\Tests\Unit;

use WebBlocks\Cms\Support\Sites\ExportImport\SiteTransferLog;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Tests\TestCase;

class SiteTransferLogTest extends TestCase
{
  public function test_existing_and_new_entries_display_in_the_viewers_language(): void
  {
    $filename = 'site (2).zip';
    $legacy = 'Archive created as '.$filename.'.';
    $this->assertSame($legacy, SiteTransferLog::entry('export_archive_created', ['file' => $filename]));
    $unknown = 'Additional diagnostics: custom worker message';
    $message = $legacy."\r\n".$unknown;

    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $archive = __('webblocks-cms::admin-feedback.transfer_log.export_archive_created', ['file' => $filename], $locale);
      $this->assertSame($archive."\r\n".$unknown, SiteTransferLog::display($message, $locale));
      if ($locale !== 'en') {
        $this->assertNotSame($legacy, $archive);
      }
    }
  }

  public function test_workflow_failure_displays_the_localized_phase_and_preserves_the_detail(): void
  {
    $detail = 'Diagnostic code: 42';
    $phase = __('webblocks-cms::admin.site_transfers.import_phases.pages', [], 'tr');
    $this->assertNotSame('webblocks-cms::admin.site_transfers.import_phases.pages', $phase);
    $expected = __('webblocks-cms::admin-feedback.transfer_log.import_failed_during', ['phase' => $phase, 'detail' => $detail], 'tr');

    $this->assertSame($expected, SiteTransferLog::display('Import failed during pages: '.$detail, 'tr'));
  }

  public function test_known_failure_details_saved_in_english_follow_the_viewers_language(): void
  {
    $detail = 'manifest.json is empty.';
    $localized = __('webblocks-cms::admin-feedback.panel_validation.import_entry_empty', ['path' => 'manifest.json'], 'fr');
    $phase = __('webblocks-cms::admin.site_transfers.import_phases.pages', [], 'fr');
    $expected = __('webblocks-cms::admin-feedback.transfer_log.import_failed_during', ['phase' => $phase, 'detail' => $localized], 'fr');

    $this->assertSame($localized, SiteTransferLog::display($detail, 'fr'));
    $this->assertSame($expected, SiteTransferLog::display('Import failed during pages: '.$detail, 'fr'));
  }
}
