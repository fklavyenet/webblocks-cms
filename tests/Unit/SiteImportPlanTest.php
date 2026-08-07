<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteImportPlan;

/**
 * Guards for the chunked import's phase order and progress arithmetic.
 *
 * The order is a contract in two directions: a stored cursor names a phase, so
 * renaming or reordering strands every import that is mid-flight; and three of
 * the positions carry correctness rather than taste, which is what most of
 * this file is about.
 */
class SiteImportPlanTest extends TestCase
{
  /**
   * @return array<string, mixed>
   */
  private function payload(): array
  {
    return [
      'media' => array_fill(0, 59, ['id' => 1]),
      'pages' => array_fill(0, 72, ['id' => 1]),
      'blocks' => array_fill(0, 7726, ['id' => 1]),
      'block_text_translations' => array_fill(0, 4526, ['id' => 1]),
      'page_slots' => array_fill(0, 290, ['id' => 1]),
    ];
  }

  #[Test]
  public function the_domain_is_written_last_so_a_half_import_is_never_reachable(): void
  {
    $keys = SiteImportPlan::keys();

    // A site is only addressable through a SiteDomain row and Site has no
    // published flag, so this position is the entire safety story for an
    // interrupted import.
    $this->assertSame('domains', end($keys));
    $this->assertNull(SiteImportPlan::next('domains'));
  }

  #[Test]
  public function the_search_index_is_built_after_all_content_and_before_the_domain(): void
  {
    $keys = SiteImportPlan::keys();
    $index = array_search('search_index', $keys, true);

    $this->assertNotFalse($index);

    foreach (['blocks', 'block_text_translations', 'navigation', 'shared_slot_assignments'] as $contentPhase) {
      $this->assertLessThan(
        $index,
        array_search($contentPhase, $keys, true),
        sprintf('%s must be indexed, so it has to run before the index is built.', $contentPhase)
      );
    }

    $this->assertLessThan(array_search('domains', $keys, true), $index);
  }

  #[Test]
  public function translation_storage_is_normalised_only_after_every_translation_list(): void
  {
    $keys = SiteImportPlan::keys();
    $normalise = array_search('block_translation_storage', $keys, true);

    $this->assertNotFalse($normalise);

    // The writer gives any block without a translation a canonical row. Run it
    // while translations are still arriving and it fills in blocks whose real
    // rows have not landed, so the next batch collides on the (block, locale)
    // unique index. It has to be last of the group, and its own phase.
    foreach ([
      'block_text_translations',
      'block_button_translations',
      'block_image_translations',
      'block_contact_form_translations',
    ] as $translationPhase) {
      $this->assertLessThan(
        $normalise,
        array_search($translationPhase, $keys, true),
        sprintf('%s must be complete before canonical rows are filled in.', $translationPhase)
      );
    }
  }

  #[Test]
  public function blocks_are_created_before_their_parents_are_linked(): void
  {
    $keys = SiteImportPlan::keys();

    $this->assertLessThan(
      array_search('block_parents', $keys, true),
      array_search('blocks', $keys, true),
      'Parent linking needs the full block map, which only exists once every slice has run.'
    );
  }

  #[Test]
  public function only_list_phases_can_stop_mid_way(): void
  {
    foreach (SiteImportPlan::keys() as $phase) {
      $listKey = SiteImportPlan::listKey($phase);

      $this->assertSame($listKey !== null, SiteImportPlan::isChunkable($phase));
    }

    $this->assertTrue(SiteImportPlan::isChunkable('blocks'));
    $this->assertFalse(SiteImportPlan::isChunkable('site'));
  }

  #[Test]
  public function file_copying_phases_take_smaller_slices_than_insert_phases(): void
  {
    // Each asset row extracts from the zip and writes to disk; a 250-row slice
    // of those would blow past the step budget in one uninterruptible call.
    $this->assertLessThan(SiteImportPlan::chunkSize('blocks'), SiteImportPlan::chunkSize('assets'));
    $this->assertLessThan(SiteImportPlan::chunkSize('blocks'), SiteImportPlan::chunkSize('page_assets'));
  }

  #[Test]
  public function the_index_rebuild_is_the_one_phase_outside_a_transaction(): void
  {
    $this->assertFalse(SiteImportPlan::needsTransaction('search_index'));

    foreach (array_diff(SiteImportPlan::keys(), ['search_index']) as $phase) {
      $this->assertTrue(SiteImportPlan::needsTransaction($phase), $phase.' must commit as a unit.');
    }
  }

  #[Test]
  public function progress_counts_rows_rather_than_phases(): void
  {
    $payload = $this->payload();
    $unitPhases = count(array_filter(SiteImportPlan::PHASES, static fn ($list) => $list === null));

    // The block list drives three phases — create, link parents, normalise
    // translation storage — so its rows count three times. Each pass is real
    // work over every row, and collapsing them would stall the bar for two
    // thirds of the longest stretch of the import.
    $blockPhases = count(array_filter(SiteImportPlan::PHASES, static fn ($list) => $list === 'blocks'));
    $this->assertSame(3, $blockPhases);

    $expected = 59 + 72 + (7726 * $blockPhases) + 4526 + 290 + $unitPhases;

    $this->assertSame($expected, SiteImportPlan::total($payload));
    $this->assertGreaterThan(count(SiteImportPlan::keys()) * 10, SiteImportPlan::total($payload));
  }

  #[Test]
  public function completed_units_accumulate_across_phases_and_clamp_within_one(): void
  {
    $payload = $this->payload();
    $first = SiteImportPlan::first();

    $this->assertSame(0, SiteImportPlan::completedUnits($first, 0, $payload));

    $atBlocks = SiteImportPlan::completedUnits('blocks', 0, $payload);
    $this->assertSame($atBlocks + 250, SiteImportPlan::completedUnits('blocks', 250, $payload));

    // An offset past the end of a list must not report more than the list holds.
    $this->assertSame(
      $atBlocks + 7726,
      SiteImportPlan::completedUnits('blocks', 99999, $payload)
    );
  }

  #[Test]
  public function an_unknown_phase_is_not_treated_as_a_cursor(): void
  {
    $this->assertFalse(SiteImportPlan::isKnown(null));
    $this->assertFalse(SiteImportPlan::isKnown('renamed_in_a_later_release'));
    $this->assertTrue(SiteImportPlan::isKnown('blocks'));
    $this->assertNull(SiteImportPlan::next('renamed_in_a_later_release'));
  }

  #[Test]
  public function every_phase_is_named_in_every_shipped_locale(): void
  {
    // The modal shows the phase it is on. A phase added without labels would
    // surface a raw key like block_translation_storage to the operator.
    foreach (['en', 'tr', 'de', 'es', 'it', 'fr'] as $locale) {
      $lang = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/admin.php';
      $labels = $lang['site_transfers']['import_phases'] ?? [];

      $this->assertArrayHasKey('starting', $labels, $locale.' is missing the pre-first-step label.');

      foreach (SiteImportPlan::keys() as $phase) {
        $this->assertArrayHasKey($phase, $labels, sprintf('%s has no %s label.', $locale, $phase));
        $this->assertNotSame('', trim((string) $labels[$phase]));
      }
    }
  }
}
