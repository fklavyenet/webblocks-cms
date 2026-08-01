<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The asset picker's only real form field is a hidden input, which Chrome's
 * accessibility audit does not accept as a <label for> target ("Incorrect
 * use of <label for=FORM_ELEMENT>" — the id "doesn't match any element",
 * because a hidden input is never a valid label target even though it does
 * exist in the DOM). Every caller that pairs an external <label> with this
 * partial must point at the picker's actual interactive control — the
 * "Choose/Replace" trigger button, id="{inputId}_open" — not the bare
 * inputId, which only exists on the hidden input.
 */
class AssetPickerLabelAccessibilityTest extends TestCase
{
  private function read(string $relativePath): string
  {
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
  }

  #[Test]
  public function the_picker_trigger_button_carries_the_open_suffixed_id_in_every_branch(): void
  {
    $partial = $this->read('resources/views/admin/media/asset-picker-panel.blade.php');

    $this->assertSame(
      3,
      substr_count($partial, 'id="{{ $pickerInputId }}_open"'),
      'Expected the trigger button in all three layout branches (selector-card, compact, full-card) to carry id="{inputId}_open".'
    );
  }

  /**
   * @return array<string, array{0: string, 1: string}>
   */
  public static function labelledPickerCallers(): array
  {
    return [
      'sites form — favicon' => ['resources/views/admin/sites/form.blade.php', 'favicon_media_id_open'],
      'sites form — social image' => ['resources/views/admin/sites/form.blade.php', 'social_image_media_id_open'],
      'page translations form — og image' => ['resources/views/admin/pages/translations/form.blade.php', 'og_image_media_id_open'],
    ];
  }

  #[Test]
  #[DataProvider('labelledPickerCallers')]
  public function the_caller_label_targets_the_trigger_button_not_the_hidden_input(string $view, string $expectedFor): void
  {
    $source = $this->read($view);
    $bareId = str_replace('_open', '', $expectedFor);

    $this->assertStringContainsString(
      'for="'.$expectedFor.'"',
      $source,
      sprintf('%s must label the picker trigger button (id="%s"), not the hidden input.', $view, $expectedFor)
    );
    $this->assertDoesNotMatchRegularExpression(
      '/<label\b[^>]*\bfor="'.preg_quote($bareId, '/').'"/',
      $source,
      sprintf('%s still has a <label for="%s"> pointing at the hidden input — Chrome rejects that as a match.', $view, $bareId)
    );
  }
}
