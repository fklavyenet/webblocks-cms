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
    $this->assertStringContainsString('wb-field wb-stack wb-gap-3', $source);
    $this->assertStringNotContainsString('<fieldset class="wb-field"><legend class="wb-label">{{ $text(\'network_controls\') }}</legend>', $source);
  }

  #[Test]
  public function token_copy_feedback_is_rendered_beside_each_copy_button(): void
  {
    $systemView = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/system/api-tokens/index.blade.php');
    $profileView = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/profile/api-tokens.blade.php');
    $script = file_get_contents(dirname(__DIR__, 2).'/public/cms/js/admin/api-token-copy.js');

    $this->assertSame(2, substr_count($systemView, 'data-wb-api-token-copy-feedback'));
    $this->assertSame(3, substr_count($profileView, 'data-wb-api-token-copy-feedback'));
    $this->assertStringContainsString("button.parentElement.querySelector('[data-wb-api-token-copy-feedback]')", $script);
    $this->assertStringContainsString('feedback.wbCopyFeedbackTimer', $script);
  }
}
