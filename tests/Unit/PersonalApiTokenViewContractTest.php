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

  #[Test]
  public function personal_tokens_reuse_the_system_token_page_structure(): void
  {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/profile/api-tokens.blade.php');

    $this->assertStringContainsString("\$systemText('quick_start')", $source);
    $this->assertStringContainsString('api-tokens.partials.capability-checkboxes', $source);
    $this->assertStringContainsString('wb-api-token-capability-groups', $source);
    $this->assertStringContainsString('wb-table wb-table-striped wb-table-hover', $source);
    $this->assertStringContainsString('admin.partials.pagination', $source);
    $this->assertStringContainsString('admin.profile.api-tokens.update', $source);
    $this->assertStringContainsString('activity-personal-api-token-', $source);
    $this->assertStringContainsString('$token->activityLogs', $source);
  }
}
