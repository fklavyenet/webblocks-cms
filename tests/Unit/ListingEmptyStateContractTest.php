<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A filterable listing has two empty states that read very differently: the
 * site has nothing yet, or the current search hid everything it does have.
 * Telling a site with fifteen pages to "create your first page" sends the
 * operator to the wrong action, so each listing branches on its filters and
 * offers a way back.
 */
class ListingEmptyStateContractTest extends TestCase
{
  /**
   * @return array<string, array{0: string, 1: array<int, string>}>
   */
  public static function listings(): array
  {
    return [
      'pages' => ['admin/pages/index', ['pages.no_pages_filtered_help', 'common.reset']],
      'contact messages' => ['admin/contact-messages/index', ['contact_messages_index.no_messages_filtered_help', 'contact_messages_index.no_messages_found', 'common.reset']],
      'blocks' => ['admin/blocks/index', ['blocks_index.empty_filtered_text', 'common.reset']],
      'shared slots' => ['admin/shared-slots/index', ['shared_slots.empty_filtered_help', 'common.reset']],
      'media' => ['admin/media/index', ['media_index.no_media_filtered_help', 'common.reset']],
      'icons' => ['admin/system/icons/index', ['icons.empty_filtered_text', 'common.reset']],
      'backups' => ['admin/system/backups/index', ['backups.no_history_filtered_help', 'backups.no_backups_found', 'common.reset']],
      'engagement comments' => ['admin/engagement/comments', ['engagement.no_comments_filtered_help', 'common.reset']],
      'engagement ratings' => ['admin/engagement/ratings', ['engagement.no_ratings_filtered_help', 'common.reset']],
      'users' => ['admin/users/index', ['users.empty_filtered_help', 'common.reset']],
    ];
  }

  /**
   * @param  array<int, string>  $keys
   */
  #[Test]
  #[DataProvider('listings')]
  public function a_filterable_listing_tells_a_filtered_result_apart_from_an_empty_one(string $view, array $keys): void
  {
    $markup = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/'.$view.'.blade.php');
    $emptyState = $this->emptyStateMarkup($markup);

    $this->assertStringContainsString('hasActiveFilters', $emptyState, $view.' does not branch its empty state on the active filters.');

    foreach ($keys as $key) {
      $this->assertStringContainsString(
        // Screen-specific strings use scoped closures. The shared action uses
        // the full key so a reset URL alone cannot satisfy the assertion.
        $key === 'common.reset' ? $key : (string) last(explode('.', $key)),
        $emptyState,
        $view.' never renders ['.$key.'].',
      );
    }
  }

  /**
   * @param  array<int, string>  $keys
   */
  #[Test]
  #[DataProvider('listings')]
  public function every_filtered_empty_state_string_is_translated_in_each_locale(string $view, array $keys): void
  {
    foreach (['en', 'de', 'tr', 'es', 'it', 'fr'] as $locale) {
      $translations = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/admin.php';

      foreach ($keys as $key) {
        $value = $translations;

        foreach (explode('.', $key) as $segment) {
          $this->assertIsArray($value, $locale.' is missing the group for ['.$key.'].');
          $this->assertArrayHasKey($segment, $value, $locale.' is missing ['.$key.'].');
          $value = $value[$segment];
        }

        $this->assertIsString($value);
        $this->assertNotSame('', trim($value), $locale.' has an empty string for ['.$key.'].');
      }
    }
  }

  /**
   * The listing's own empty state, so a match cannot come from an unrelated
   * empty block elsewhere in the same view.
   */
  private function emptyStateMarkup(string $markup): string
  {
    $offset = strpos($markup, 'wb-empty');

    $this->assertIsInt($offset, 'The view has no empty state at all.');

    $last = strrpos($markup, 'wb-empty');

    return substr($markup, $offset, ($last - $offset) + 1200);
  }
}
