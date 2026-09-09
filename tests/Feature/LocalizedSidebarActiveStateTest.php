<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Tests\TestCase;

class LocalizedSidebarActiveStateTest extends TestCase
{
  #[Test]
  public function a_localized_manual_sidebar_item_activates_and_opens_its_parent_group(): void
  {
    $request = Request::create('/de/guides/sign-in/?source=docs');
    $this->app->instance('request', $request);

    $item = Mockery::mock(Block::class)->makePartial();
    $item->forceFill([
      'id' => 12,
      'title' => 'Sign in',
      'status' => 'published',
      'sort_order' => 0,
      'settings' => ['url' => '/guides/sign-in', 'active_mode' => 'path'],
    ]);
    $item->shouldReceive('localizedPublicUrl')
      ->with('/guides/sign-in')
      ->andReturn('/de/guides/sign-in');
    $item->shouldReceive('isSidebarNavItem')->andReturnTrue();

    $group = new Block;
    $group->forceFill([
      'id' => 11,
      'title' => 'Guides',
      'settings' => ['initially_open' => false],
    ]);
    $group->setRelation('children', new Collection([$item]));

    $html = view('webblocks-cms::pages.partials.blocks.sidebar-nav-group', [
      'block' => $group,
    ])->render();

    $this->assertStringContainsString('class="wb-nav-group is-open"', $html);
    $this->assertStringContainsString('data-wb-nav-group-open', $html);
    $this->assertStringContainsString('class="wb-nav-group-toggle is-active"', $html);
    $this->assertStringContainsString('aria-expanded="true"', $html);
    $this->assertStringContainsString('href="/de/guides/sign-in"', $html);
    $this->assertStringContainsString('class="wb-nav-group-item is-active"', $html);
    $this->assertStringContainsString('aria-current="page"', $html);
    $this->assertStringNotContainsString('id="wb-nav-group-items-11" hidden', $html);
  }
}
