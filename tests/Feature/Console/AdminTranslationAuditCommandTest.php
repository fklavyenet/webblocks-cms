<?php

namespace Tests\Feature\Console;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminTranslationAuditCommandTest extends TestCase
{
  #[Test]
  public function admin_translation_audit_reports_static_admin_html_fallback_coverage(): void
  {
    $this->artisan('webblocks:admin-translation-audit', [
      '--locale' => 'de',
      '--limit' => 5,
    ])
      ->expectsOutputToContain('Admin translation audit for locale [de]')
      ->expectsOutputToContain('Coverage:')
      ->expectsOutputToContain('Most common missing phrases:')
      ->assertExitCode(0);
  }
}
