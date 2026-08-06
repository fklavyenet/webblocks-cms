<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalNavigationController;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Item updates used to re-validate fields the caller never sent against the
 * item's stored values, so legacy rows that predate today's rules could not
 * be updated at all: pre-tree-editor rows carry position 0 (below the
 * minimum of 1), and old menus may nest children under a page-type parent
 * (only groups may hold children now). Untouched fields are no longer
 * re-validated; the rules still apply to values the caller actually sends.
 */
class NavigationItemLegacyUpdateTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function an_item_with_position_zero_can_be_updated_without_sending_a_sort_order(): void
  {
    $site = $this->seedSite();
    $item = $this->makeItem($site, ['title' => 'Home', 'position' => 0]);

    $response = $this->updateItem($item, ['label' => 'Start']);

    $this->assertTrue($response->getData(true)['ok']);
    $item->refresh();
    $this->assertSame('Start', $item->getRawOriginal('title'));
    $this->assertSame(0, (int) $item->position);
  }

  #[Test]
  public function an_explicitly_sent_sort_order_below_one_is_still_rejected(): void
  {
    $site = $this->seedSite();
    $item = $this->makeItem($site, ['title' => 'Home', 'position' => 0]);

    $response = $this->updateItem($item, ['label' => 'Start', 'sort_order' => 0]);

    $data = $response->getData(true);
    $this->assertFalse($data['ok']);
    $this->assertSame('navigation_item.sort_order', $data['errors'][0]['path']);
    $this->assertSame('Home', $item->refresh()->getRawOriginal('title'));
  }

  #[Test]
  public function a_child_of_a_page_type_parent_can_be_updated_when_the_parent_is_untouched(): void
  {
    $site = $this->seedSite();
    $parent = $this->makePageItem($site, ['title' => 'Approach', 'position' => 1]);
    $child = $this->makeItem($site, ['title' => 'Archive', 'position' => 2, 'parent_id' => $parent->id]);

    $response = $this->updateItem($child, ['label' => 'Archives']);

    $this->assertTrue($response->getData(true)['ok']);
    $child->refresh();
    $this->assertSame('Archives', $child->getRawOriginal('title'));
    $this->assertSame($parent->id, (int) $child->parent_id);
  }

  #[Test]
  public function a_child_of_a_page_type_parent_can_be_updated_when_the_same_parent_id_is_resent(): void
  {
    $site = $this->seedSite();
    $parent = $this->makePageItem($site, ['title' => 'Approach', 'position' => 1]);
    $child = $this->makeItem($site, ['title' => 'Archive', 'position' => 2, 'parent_id' => $parent->id]);

    $response = $this->updateItem($child, ['label' => 'Archives', 'parent_id' => $parent->id]);

    $this->assertTrue($response->getData(true)['ok']);
    $this->assertSame('Archives', $child->refresh()->getRawOriginal('title'));
  }

  #[Test]
  public function reassigning_an_item_under_a_page_type_parent_is_still_rejected(): void
  {
    $site = $this->seedSite();
    $pageItem = $this->makePageItem($site, ['title' => 'Approach', 'position' => 1]);
    $item = $this->makeItem($site, ['title' => 'Archive', 'position' => 2]);

    $response = $this->updateItem($item, ['parent_id' => $pageItem->id]);

    $data = $response->getData(true);
    $this->assertFalse($data['ok']);
    $this->assertSame('navigation_item.parent_id', $data['errors'][0]['path']);
    $this->assertNull($item->refresh()->parent_id);
  }

  private function seedSite(): Site
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    $english = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$english->id => ['is_enabled' => true]]);

    return $site;
  }

  private function makeItem(Site $site, array $attributes): NavigationItem
  {
    return NavigationItem::query()->create([
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'link_type' => NavigationItem::LINK_CUSTOM_URL,
      'url' => '/',
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
      ...$attributes,
    ]);
  }

  private function makePageItem(Site $site, array $attributes): NavigationItem
  {
    $page = Page::query()->create([
      'site_id' => $site->id,
      'slug' => 'approach',
      'status' => Page::STATUS_DRAFT,
    ]);

    return NavigationItem::query()->create([
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'link_type' => NavigationItem::LINK_PAGE,
      'page_id' => $page->id,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
      ...$attributes,
    ]);
  }

  private function updateItem(NavigationItem $item, array $payload)
  {
    $request = Request::create('/webadmin/api', 'PATCH', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

    return $this->app->make(InternalNavigationController::class)
      ->updateItem($request, $item->menu_key, $item);
  }
}
