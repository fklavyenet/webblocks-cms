<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

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
      'pages' => ['admin/pages/index', ['pages.no_pages_filtered_help', 'pages.clear_filters']],
      'contact messages' => ['admin/contact-messages/index', ['contact_messages_index.no_messages_filtered_help', 'contact_messages_index.no_messages_found', 'contact_messages_index.clear_filters']],
      'blocks' => ['admin/blocks/index', ['blocks_index.empty_filtered_text', 'blocks_index.clear_filters']],
      'shared slots' => ['admin/shared-slots/index', ['shared_slots.empty_filtered_help', 'shared_slots.clear_filters']],
      'media' => ['admin/media/index', ['media_index.no_media_filtered_help']],
      'icons' => ['admin/system/icons/index', ['icons.empty_filtered_text', 'icons.clear_filters']],
      'backups' => ['admin/system/backups/index', ['backups.no_history_filtered_help', 'backups.no_backups_found', 'backups.clear_filters']],
      'engagement comments' => ['admin/engagement/comments', ['engagement.no_comments_filtered_help', 'engagement.clear_filters']],
      'engagement ratings' => ['admin/engagement/ratings', ['engagement.no_ratings_filtered_help', 'engagement.clear_filters']],
      'users' => ['admin/users/index', ['users.empty_filtered_help', 'users.clear_filters']],
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
        // Views reach their group either through a scoped closure or the full
        // dotted key, so match on the last segment.
        (string) last(explode('.', $key)),
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
    foreach (['en', 'de', 'tr'] as $locale) {
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
