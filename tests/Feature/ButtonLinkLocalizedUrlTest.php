<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Tests\TestCase;

/**
 * A button_link stored as "/products" used to send every locale's visitor to
 * the default-locale page. The public renderer now rewrites internal links to
 * the same page's path in the render locale via
 * PageRouteResolver::localizedPublicUrl(), and falls back to the stored URL
 * whenever the rewrite cannot improve on it. The stored value itself is
 * untouched — the admin form and the CTA synchronizer keep reading it raw.
 */
class ButtonLinkLocalizedUrlTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function an_internal_link_follows_the_render_locale(): void
  {
    $site = $this->seedSite('a');

    $html = $this->renderButton($site, url: '/target-a', renderLocale: 'tr');

    $this->assertStringContainsString('href="/tr/hedef-a"', $html);
  }

  #[Test]
  public function the_default_locale_keeps_the_stored_path(): void
  {
    $site = $this->seedSite('b');

    $html = $this->renderButton($site, url: '/target-b', renderLocale: 'en');

    $this->assertStringContainsString('href="/target-b"', $html);
  }

  #[Test]
  public function a_query_and_fragment_survive_the_rewrite(): void
  {
    $site = $this->seedSite('c');

    $html = $this->renderButton($site, url: '/target-c?plan=pro#pricing', renderLocale: 'tr');

    $this->assertStringContainsString('href="/tr/hedef-c?plan=pro#pricing"', $html);
  }

  #[Test]
  public function an_already_prefixed_path_resolves_back_through_the_page(): void
  {
    $site = $this->seedSite('d');

    $html = $this->renderButton($site, url: '/tr/hedef-d', renderLocale: 'en');

    $this->assertStringContainsString('href="/target-d"', $html);
  }

  #[Test]
  public function external_and_unresolvable_urls_stay_untouched(): void
  {
    $site = $this->seedSite('e');

    $this->assertStringContainsString(
      'href="https://example.com/target-e"',
      $this->renderButton($site, url: 'https://example.com/target-e', renderLocale: 'tr'),
    );
    $this->assertStringContainsString(
      'href="/no-such-page"',
      $this->renderButton($site, url: '/no-such-page', renderLocale: 'tr'),
    );
  }

  #[Test]
  public function a_missing_translation_in_the_render_locale_falls_back_to_the_stored_url(): void
  {
    $site = $this->seedSite('f', withTurkishTranslation: false);

    $html = $this->renderButton($site, url: '/target-f', renderLocale: 'tr');

    $this->assertStringContainsString('href="/target-f"', $html);
  }

  #[Test]
  public function the_stored_value_is_what_the_admin_form_still_reads(): void
  {
    $site = $this->seedSite('g');
    $block = $this->makeButton($site, '/target-g');

    $this->assertSame('/target-g', $block->buttonLinkUrl());
  }

  public static function editorialLinkBlockProvider(): array
  {
    return [
      'button' => ['button'],
      'card' => ['card'],
      'column item' => ['column_item'],
      'link list item' => ['link-list-item'],
      'stat card' => ['stat-card'],
    ];
  }

  #[Test]
  #[DataProvider('editorialLinkBlockProvider')]
  public function editorial_link_blocks_follow_the_render_locale(string $type): void
  {
    $suffix = 'family-'.str_replace('_', '-', $type);
    $site = $this->seedSite($suffix);
    $block = $this->makeEditorialLinkBlock($site, $type, '/target-'.$suffix);
    $block->setAttribute('render_locale_code', 'tr');
    $block->setRelation('renderPage', $block->page->setRelation('site', $site));

    $html = view('webblocks-cms::pages.partials.blocks.'.$type, ['block' => $block])->render();

    $this->assertStringContainsString('href="/tr/hedef-'.$suffix.'"', $html);
  }

  private function seedSite(string $suffix, bool $withTurkishTranslation = true): Site
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test-'.$suffix, 'is_primary' => true]);
    $english = Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $turkish = Locale::query()->firstOrCreate(['code' => 'tr'], ['name' => 'Türkçe', 'is_default' => false, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$english->id => ['is_enabled' => true], $turkish->id => ['is_enabled' => true]]);

    $target = Page::query()->create(['site_id' => $site->id, 'slug' => 'target-'.$suffix, 'status' => Page::STATUS_PUBLISHED]);
    $target->translations()->create([
      'site_id' => $site->id,
      'locale_id' => $english->id,
      'name' => 'Target',
      'slug' => 'target-'.$suffix,
      'path' => '/target-'.$suffix,
    ]);

    if ($withTurkishTranslation) {
      $target->translations()->create([
        'site_id' => $site->id,
        'locale_id' => $turkish->id,
        'name' => 'Hedef',
        'slug' => 'hedef-'.$suffix,
        'path' => '/hedef-'.$suffix,
      ]);
    }

    return $site;
  }

  private function makeButton(Site $site, string $url): Block
  {
    $hostPage = Page::query()->create(['site_id' => $site->id, 'slug' => 'host-'.$site->handle, 'status' => Page::STATUS_PUBLISHED]);
    $mainSlotTypeId = SlotType::query()->firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published', 'sort_order' => 0])->id;

    return Block::create([
      'page_id' => $hostPage->id,
      'type' => 'button_link',
      'slot_type_id' => $mainSlotTypeId,
      'sort_order' => 0,
      'title' => 'Go',
      'settings' => json_encode(['url' => $url]),
      'status' => 'published',
    ]);
  }

  private function renderButton(Site $site, string $url, string $renderLocale): string
  {
    $block = $this->makeButton($site, $url);
    $block->setAttribute('render_locale_code', $renderLocale);
    $block->setRelation('renderPage', $block->page->setRelation('site', $site));

    return view('webblocks-cms::pages.partials.blocks.button_link', ['block' => $block])->render();
  }

  private function makeEditorialLinkBlock(Site $site, string $type, string $url): Block
  {
    $hostPage = Page::query()->create(['site_id' => $site->id, 'slug' => 'host-'.$type.'-'.$site->handle, 'status' => Page::STATUS_PUBLISHED]);
    $mainSlotTypeId = SlotType::query()->firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published', 'sort_order' => 0])->id;

    return Block::create([
      'page_id' => $hostPage->id,
      'type' => $type,
      'slot_type_id' => $mainSlotTypeId,
      'sort_order' => 0,
      'title' => 'Go',
      'content' => 'Description',
      'meta' => $type === 'card' ? 'Go' : null,
      'url' => $type === 'card' ? null : $url,
      'settings' => $type === 'card' ? json_encode(['url' => $url]) : null,
      'status' => 'published',
    ]);
  }
}
