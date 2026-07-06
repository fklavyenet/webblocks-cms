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
      ->assertExitCode(0);
  }

  #[Test]
  public function admin_translation_audit_discovers_new_admin_view_families(): void
  {
    $this->artisan('webblocks:admin-translation-audit', [
      '--locale' => 'de',
      '--limit' => 1,
      '--json' => true,
    ])
      ->expectsOutputToContain('admin/site-transfers/exports/index.blade.php')
      ->assertExitCode(0);
  }

  #[Test]
  public function admin_translation_audit_can_fail_only_new_missing_phrases_outside_the_baseline(): void
  {
    $baselinePath = storage_path('framework/testing-admin-translation-baseline.json');

    file_put_contents($baselinePath, json_encode([
      'accepted_missing' => [
        [
          'file' => 'admin/blocks/types/columns.blade.php',
          'phrase' => 'Variant',
        ],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $this->artisan('webblocks:admin-translation-audit', [
      '--locale' => 'de',
      '--limit' => 1,
      '--baseline' => $baselinePath,
      '--strict' => true,
    ])
      ->expectsOutputToContain('New missing outside baseline:')
      ->expectsOutputToContain('Strict admin translation audit failed:')
      ->assertExitCode(1);
  }

  #[Test]
  public function admin_translation_audit_strict_mode_accepts_the_tracked_baseline(): void
  {
    $this->artisan('webblocks:admin-translation-audit', [
      '--locale' => 'de',
      '--limit' => 1,
      '--baseline' => 'packages/webblocks-cms/resources/translation-quality/admin-translation-audit-baseline.json',
      '--strict' => true,
    ])
      ->expectsOutputToContain('New missing outside baseline: 0')
      ->assertExitCode(0);
  }
}
