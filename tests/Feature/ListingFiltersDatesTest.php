<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The shared `listing-filters` partial (Pages, Media, Comments, Navigation all
 * `@include` it) only ever supported a search box and dropdowns. A plugin
 * screen wanting a date filter had no matching field type to reuse, which is
 * exactly the kind of gap that pushes a plugin toward inventing its own
 * one-off filter UI instead of the CMS's own standard. `dates` closes it: the
 * same shape as `selects` (id/name/label/value, optional submitOnChange), one
 * new field type, fully optional so every existing caller renders unchanged.
 */
class ListingFiltersDatesTest extends TestCase
{
  #[Test]
  public function it_renders_a_date_input_for_each_entry(): void
  {
    $html = view('webblocks-cms::admin.partials.listing-filters', [
      'action' => '/webadmin/example',
      'dates' => [
        ['id' => 'example_date', 'name' => 'date', 'label' => 'Date', 'value' => '2026-08-03'],
      ],
    ])->render();

    $this->assertStringContainsString('class="wb-filter-bar wb-items-end"', $html);
    $this->assertStringContainsString('id="example_date"', $html);
    $this->assertStringContainsString('name="date"', $html);
    $this->assertStringContainsString('type="date"', $html);
    $this->assertStringContainsString('value="2026-08-03"', $html);
  }

  #[Test]
  public function a_date_field_can_auto_submit_on_change(): void
  {
    $html = view('webblocks-cms::admin.partials.listing-filters', [
      'action' => '/webadmin/example',
      'dates' => [
        ['id' => 'example_date', 'name' => 'date', 'label' => 'Date', 'value' => '', 'submitOnChange' => true],
      ],
    ])->render();

    $this->assertStringContainsString('onchange="this.form.submit()"', $html);
  }

  #[Test]
  public function omitting_dates_renders_exactly_as_before(): void
  {
    $html = view('webblocks-cms::admin.partials.listing-filters', [
      'action' => '/webadmin/example',
      'selects' => [
        ['id' => 'example_status', 'name' => 'status', 'label' => 'Status', 'selected' => '', 'options' => ['open' => 'Open']],
      ],
    ])->render();

    $this->assertStringNotContainsString('type="date"', $html);
    $this->assertStringContainsString('class="wb-filter-select"', $html);
  }
}
