<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The v3 System Updates screen vocabulary must stay complete in every admin
 * locale: en/de/tr/es/it expose identical `updates.*` key sets, and the keys the
 * v3 screen depends on (plus retired two-phase keys) are pinned here.
 */
class AdminUpdatesLangParityTest extends TestCase
{
  #[Test]
  public function the_updates_sections_expose_identical_key_sets_across_locales(): void
  {
    $keysByLocale = [];

    foreach (['en', 'de', 'tr', 'es', 'it', 'fr'] as $locale) {
      $keysByLocale[$locale] = $this->dotKeys($this->updatesSection($locale));
    }

    $this->assertNotSame([], $keysByLocale['en']);
    foreach (['de', 'tr', 'es', 'it', 'fr'] as $locale) {
      $this->assertSame($keysByLocale['en'], $keysByLocale[$locale], "$locale updates.* keys must match en.");
    }
  }

  #[Test]
  public function the_v3_vocabulary_is_present_and_the_two_phase_vocabulary_is_gone(): void
  {
    $keys = $this->dotKeys($this->updatesSection('en'));

    foreach ([
      'check_button',
      'update_button',
      'available_help',
      'backup_note',
      'server_backup_advisory',
      'server_backup_advisory_link',
      'whats_new',
      'whats_in_this',
      'installed_version',
      'last_checked',
      'updating_title',
      'updating_body',
      'waiting_body',
      'keep_open',
      'history_title',
      'view_log',
      'log_title',
      'preflight_title',
      'statuses.restored',
      // Historic rows must keep rendering.
      'statuses.pending',
      'statuses.cancelled',
    ] as $required) {
      $this->assertContains($required, $keys, "Expected updates.$required to exist.");
    }

    foreach ([
      'blockers.busy',
      'blockers.no_newer_release_ready',
      'blockers.missing_package_url',
      'continue_update',
      'backup_protection',
      'backup_protection_help',
      'backup_name',
      'backup_size',
      'download_backup_before_install',
      'download_support_report',
      'download_prepared_backup',
      'created',
    ] as $dead) {
      $this->assertNotContains($dead, $keys, "updates.$dead belongs to the retired two-phase flow.");
    }
  }

  private function updatesSection(string $locale): array
  {
    $lang = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/admin.php';

    $this->assertIsArray($lang['updates'] ?? null, "The $locale admin lang file must keep an updates section.");

    return $lang['updates'];
  }

  /**
   * @return list<string>
   */
  private function dotKeys(array $values, string $prefix = ''): array
  {
    $keys = [];

    foreach ($values as $key => $value) {
      $dotKey = $prefix === '' ? (string) $key : $prefix.'.'.$key;

      if (is_array($value)) {
        $keys = array_merge($keys, $this->dotKeys($value, $dotKey));

        continue;
      }

      $keys[] = $dotKey;
    }

    sort($keys);

    return $keys;
  }
}
