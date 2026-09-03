<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PersonalApiTokenViewContractTest extends TestCase
{
  #[Test]
  public function profile_page_header_actions_use_the_shared_html_contract(): void
  {
    foreach (['profile/edit.blade.php', 'profile/api-tokens.blade.php'] as $view) {
      $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/'.$view);

      $this->assertStringContainsString("'actions' => '<a href=", $source);
      $this->assertStringNotContainsString("'actions' => [[", $source);
    }
  }
}
