<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The import screen now carries the progress modal and the script that drives
 * it. A directive Blade fails to recognise survives verbatim into the compiled
 * view and leaves its variables undefined, which here would mean a Run import
 * button that opens an empty modal and never starts anything.
 */
class SiteImportViewCompilesTest extends TestCase
{
  private function source(): string
  {
    return (string) file_get_contents(
      dirname(__DIR__, 2).'/resources/views/admin/site-transfers/imports/show.blade.php'
    );
  }

  #[Test]
  public function the_import_view_compiles_with_no_directive_left_behind(): void
  {
    $compiled = app('blade.compiler')->compileString($this->source());

    foreach (['@php', '@endphp', '@if', '@endif', '@foreach', '@endforeach', '@push', '@endpush', '@json', '@csrf', '@method'] as $directive) {
      $this->assertStringNotContainsString(
        $directive,
        $compiled,
        sprintf('%s survived compilation, so the view renders it as text.', $directive)
      );
    }
  }

  #[Test]
  public function the_phase_label_map_reaches_the_script(): void
  {
    $source = $this->source();

    // The map is built from SiteImportPlan::keys() rather than written out by
    // hand, so a phase added later cannot show up in the modal as a raw key.
    $this->assertStringContainsString('SiteImportPlan::keys()', $source);
    $this->assertStringContainsString('@json($importPhaseLabels)', $source);
    $this->assertStringContainsString("site_transfers.import_phases.'.\$phase", $source);
  }

  #[Test]
  public function the_modal_uses_the_ui_progress_primitive_rather_than_its_own_css(): void
  {
    $source = $this->source();

    $this->assertStringContainsString('wb-progress-bar', $source);
    $this->assertStringContainsString('wb-progress-bar-fill', $source);
    $this->assertStringNotContainsString('<style', $source);
  }

  #[Test]
  public function the_progress_modal_cannot_be_dismissed_while_a_step_is_running(): void
  {
    $source = $this->source();

    // Leaving is safe — every step has committed — but a stray click that
    // closes the overlay would stop the browser driving the remaining steps
    // with no sign that anything stopped.
    $this->assertStringContainsString("addEventListener('wb:overlay:close-request'", $source);
    $this->assertStringContainsString('event.preventDefault()', $source);
  }

  #[Test]
  public function a_locked_step_is_waited_out_rather_than_retried_against(): void
  {
    $source = $this->source();

    // 409 means another tab holds the per-import lock. Its work is this run's
    // work, so the answer is to wait, not to race it.
    $this->assertStringContainsString('result.status === 409', $source);
    $this->assertStringContainsString('setTimeout(run, 2000)', $source);
  }

  #[Test]
  public function the_run_card_serves_an_interrupted_import_too(): void
  {
    $source = $this->source();

    $this->assertStringContainsString('$siteImport->isValidated() || $siteImport->isResumable()', $source);
    $this->assertStringContainsString('site_transfers.resume_import', $source);
    $this->assertStringContainsString('site_transfers.discard_partial_import', $source);
    $this->assertStringContainsString('admin.site-transfers.imports.discard', $source);
  }

  #[Test]
  public function the_import_strings_exist_in_every_shipped_locale(): void
  {
    $keys = [
      'importing_title',
      'import_keep_open',
      'import_busy',
      'import_close_and_review',
      'resume_import',
      'resume_notice_title',
      'resume_notice_body',
      'discard_partial_import',
      'discard_partial_import_help',
    ];

    foreach (['en', 'tr', 'de', 'es', 'it'] as $locale) {
      $lang = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/admin.php';

      foreach ($keys as $key) {
        $this->assertArrayHasKey($key, $lang['site_transfers'], sprintf('Missing %s in the %s strings.', $key, $locale));
        $this->assertNotSame('', trim((string) $lang['site_transfers'][$key]));
      }

      $this->assertStringContainsString(':percent', (string) $lang['site_transfers']['resume_notice_body']);
    }
  }
}
