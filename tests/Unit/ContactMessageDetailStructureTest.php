<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContactMessageDetailStructureTest extends TestCase
{
  #[Test]
  public function every_detail_card_presents_its_fields_in_a_webblocks_ui_table(): void
  {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/contact-messages/show.blade.php');

    $this->assertSame(5, substr_count($view, '<table class="wb-table wb-table-striped">'));
    $this->assertSame(22, substr_count($view, '<th scope="row">'));
    $this->assertStringContainsString(
      '<tr><th scope="row">{{ $adminText(\'message\') }}</th><td><div class="wb-contact-message-body">{{ $message->message }}</div></td></tr>',
      $view,
    );
    $this->assertStringNotContainsString('wb-detail-list', $view);
  }
}
