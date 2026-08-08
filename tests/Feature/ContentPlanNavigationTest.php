<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The plan normalizer used to hard-code link_type to custom_url and drop
 * page_id. A menu with a group or a page link came back as a flat list of
 * custom URLs, and because the discarded fields left url empty, the failure
 * surfaced as a URL validation error on a field the caller never sent — so the
 * obvious next move was to invent a URL rather than learn the real limitation.
 * Building a dropdown meant one POST per item followed by a PATCH per item to
 * restore link_type, page_id and parent_id.
 *
 * These tests hold the normalizer to the same link-type semantics the
 * navigation endpoints already enforce, and to creating nested children in one
 * pass.
 */
class ContentPlanNavigationTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function operations(): InternalContentApiOperations
  {
    return $this->app->make(InternalContentApiOperations::class);
  }

  private function site(): Site
  {
    return Site::query()->firstOrCreate(['handle' => 'test'], ['name' => 'Test', 'is_primary' => true]);
  }

  private function normalize(array $payload, array &$errors, ?Site $site = null): ?array
  {
    return $this->operations()->normalizeNavigationItem($payload, $site ?? $this->site(), 'primary', 'plan.navigation_menus.0.items.0', $errors);
  }

  #[Test]
  public function a_group_item_keeps_its_link_type_and_is_not_asked_for_a_url(): void
  {
    $errors = [];

    $item = $this->normalize(['label' => 'Docs', 'link_type' => 'group'], $errors);

    $this->assertSame([], $errors, 'A group has no destination, so no URL error is appropriate.');
    $this->assertSame(NavigationItem::LINK_GROUP, $item['link_type']);
    $this->assertSame('', $item['url']);
  }

  #[Test]
  public function a_page_item_resolves_page_id_instead_of_a_url(): void
  {
    $page = $this->seedPage();
    $errors = [];

    $item = $this->normalize(['label' => 'About', 'link_type' => 'page', 'page_id' => $page->id], $errors);

    $this->assertSame([], $errors);
    $this->assertSame(NavigationItem::LINK_PAGE, $item['link_type']);
    $this->assertSame($page->id, $item['page_id']);
  }

  #[Test]
  public function a_page_item_without_a_page_id_is_told_which_field_is_missing(): void
  {
    $errors = [];

    $this->normalize(['label' => 'About', 'link_type' => 'page'], $errors);

    $paths = array_column($errors, 'path');

    $this->assertContains('plan.navigation_menus.0.items.0.page_id', $paths);
    $this->assertNotContains(
      'plan.navigation_menus.0.items.0.url',
      $paths,
      'Reporting a URL error for a page link is the misdirection this fixes.'
    );
  }

  #[Test]
  public function a_page_from_another_site_is_refused(): void
  {
    $page = $this->seedPage();
    $other = Site::query()->create(['name' => 'Other', 'handle' => 'other']);
    $errors = [];

    $this->normalize(['label' => 'About', 'link_type' => 'page', 'page_id' => $page->id], $errors, $other);

    $this->assertContains('plan.navigation_menus.0.items.0.page_id', array_column($errors, 'path'));
  }

  #[Test]
  public function an_unsupported_link_type_names_the_supported_ones(): void
  {
    $errors = [];

    $this->normalize(['label' => 'Docs', 'link_type' => 'anchor', 'url' => '/docs'], $errors);

    $this->assertSame('plan.navigation_menus.0.items.0.link_type', $errors[0]['path']);
    $this->assertStringContainsString('custom_url', $errors[0]['message']);
  }

  #[Test]
  public function children_are_only_accepted_under_a_group(): void
  {
    $errors = [];

    $this->normalize([
      'label' => 'Docs',
      'url' => '/docs',
      'children' => [['label' => 'Intro', 'url' => '/docs/intro']],
    ], $errors);

    $this->assertContains('plan.navigation_menus.0.items.0.children', array_column($errors, 'path'));
  }

  #[Test]
  public function a_plan_creates_the_dropdown_in_one_pass(): void
  {
    $site = $this->site();
    $errors = [];

    $normalized = [
      'site' => ['id' => $site->id, 'handle' => $site->handle],
      'handle' => 'primary',
      'label' => 'Primary',
      'items' => [
        $this->normalize([
          'label' => 'Docs',
          'link_type' => 'group',
          'children' => [
            ['label' => 'Intro', 'url' => '/docs/intro'],
            ['label' => 'Guide', 'url' => '/docs/guide'],
          ],
        ], $errors),
      ],
    ];

    $this->assertSame([], $errors);

    $this->operations()->createNavigationMenu($normalized);

    $group = NavigationItem::query()->where('title', 'Docs')->firstOrFail();
    $children = NavigationItem::query()->where('parent_id', $group->id)->orderBy('position')->get();

    $this->assertSame(NavigationItem::LINK_GROUP, $group->link_type);
    $this->assertCount(2, $children, 'Both children must be linked to the group without a follow-up PATCH.');
    $this->assertSame(['Intro', 'Guide'], $children->pluck('title')->all());
  }

  private function seedPage(): Page
  {
    $site = $this->site();
    $locale = Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);

    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'about', 'status' => Page::STATUS_PUBLISHED]);
    $page->translations()->create(['locale_id' => $locale->id, 'name' => 'About', 'slug' => 'about', 'path' => '/about']);

    return $page->fresh();
  }
}
