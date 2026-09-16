<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

class AdminModalStructureTest extends TestCase
{
  #[Test]
  public function form_actions_can_submit_a_sibling_modal_body_form(): void
  {
    $html = $this->blade(<<<'BLADE'
      <x-webblocks-cms::admin.form-actions
        cancel-url="/cancel"
        submit-label="Save"
        form="example-modal-form"
        container-class="wb-modal-footer"
      />
      BLADE);

    $html->assertSee('class="wb-modal-footer"', false);
    $html->assertSee('type="submit" class="wb-btn wb-btn-primary"', false);
    $html->assertSee('form="example-modal-form"', false);
  }

  #[Test]
  public function shared_form_actions_localize_default_labels_for_the_admin_locale(): void
  {
    config(['app.locale' => 'de']);

    $html = $this->blade(<<<'BLADE'
      <x-webblocks-cms::admin.form-actions cancel-url="/cancel" delete-href="/delete" />
      BLADE);

    $html->assertSee('Speichern');
    $html->assertSee('Abbrechen');
    $html->assertSee('Löschen');
    $html->assertDontSee('>Cancel<', false);
  }

  #[Test]
  public function block_and_navigation_editors_keep_footer_outside_the_modal_body(): void
  {
    $root = dirname(__DIR__, 2).'/resources/views/admin';
    $slotModal = file_get_contents($root.'/pages/partials/slot-block-modal.blade.php');
    $navigationModal = file_get_contents($root.'/navigation/partials/modal.blade.php');

    $this->assertStringContainsString('class="wb-modal-body wb-stack wb-gap-4"', $slotModal);
    $this->assertStringContainsString('form="slot-block-editor-form"', $slotModal);
    $this->assertStringNotContainsString("'actionsContainerClass' => 'wb-modal-footer", $slotModal);

    $this->assertStringContainsString('class="wb-modal-body wb-stack wb-gap-4"', $navigationModal);
    $this->assertStringContainsString(':form="$modalId.\'-form\'"', $navigationModal);
    $this->assertStringNotContainsString("'formActionsContainerClass' => 'wb-modal-footer", $navigationModal);
  }
}
