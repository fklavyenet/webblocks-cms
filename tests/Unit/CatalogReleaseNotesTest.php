<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Plugins\Catalog\CatalogRelease;

class CatalogReleaseNotesTest extends TestCase
{
  public function test_markdown_bullets_and_wrapped_lines_become_separate_items(): void
  {
    $release = CatalogRelease::fromArray(['summary' => 'Global notifications', 'release_notes' => "## 0.4.0\n\n- Show unread conversation badges\n  on every admin page.\n- Notify new visitor messages.\n\n- Preserve unread state."]);

    $this->assertSame(['Show unread conversation badges on every admin page.', 'Notify new visitor messages.', 'Preserve unread state.'], $release->noteItems());
  }

  public function test_structured_highlights_take_priority_and_duplicates_of_summary_are_removed(): void
  {
    $release = CatalogRelease::fromArray(['summary' => 'Global notifications', 'highlights' => ['Global notifications', 'Show badges.', 'Show badges.', 'Notify arrivals.'], 'notes' => '- Raw technical notes']);

    $this->assertSame(['Show badges.', 'Notify arrivals.'], $release->noteItems());
  }

  public function test_plain_paragraphs_ordered_lists_and_empty_notes_remain_readable(): void
  {
    $this->assertSame(['First paragraph wraps across lines.', 'Second paragraph.'], CatalogRelease::fromArray(['notes' => "First paragraph\nwraps across lines.\n\nSecond paragraph."])->noteItems());
    $this->assertSame(['First change', 'Second change'], CatalogRelease::fromArray(['notes' => "1. First change\n2) Second change"])->noteItems());
    $this->assertSame([], CatalogRelease::fromArray([])->noteItems());
    $this->assertSame([], CatalogRelease::fromArray(['summary' => 'One change', 'notes' => '- One   change'])->noteItems());
  }
}
