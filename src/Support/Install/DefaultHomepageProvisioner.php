<?php

namespace WebBlocks\Cms\Support\Install;

use WebBlocks\Cms\Models\Layout;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Pages\PageLayoutCatalog;

/**
 * Creates the published page a fresh install serves at `/`.
 *
 * Every install path needs one: without a published home page the public
 * runtime falls through to the host application's Laravel welcome view. What
 * this provisions is structural only — layout, slots, and the default-locale
 * translation. Filling it with content is StarterContentInstaller's job, so an
 * install can ship a starter page or an intentionally empty one from the same
 * baseline.
 */
class DefaultHomepageProvisioner
{
  private const SLOT_SLUGS = ['header', 'main', 'sidebar', 'footer'];

  public function provision(Site $site): Page
  {
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
    $layout = Layout::query()->firstOrCreate(
      ['slug' => 'default-layout'],
      ['name' => 'Default Layout']
    );

    $homePage = $this->existingHomePage($site, $defaultLocale) ?? Page::query()->create([
      'site_id' => $site->id,
      'page_type' => 'default',
      'status' => Page::STATUS_PUBLISHED,
      'layout_id' => $layout->id,
      'published_at' => now(),
      'settings' => ['public_shell' => PageLayoutCatalog::handles()[0] ?? 'default'],
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $homePage->id, 'locale_id' => $defaultLocale->id],
      [
        'site_id' => $site->id,
        'name' => $site->display_name ?: $site->name,
        'slug' => 'home',
        'path' => '/',
      ],
    );

    foreach (self::SLOT_SLUGS as $index => $slotSlug) {
      $slotTypeId = SlotType::query()->where('slug', $slotSlug)->value('id');

      if (! $slotTypeId) {
        continue;
      }

      PageSlot::query()->updateOrCreate(
        [
          'page_id' => $homePage->id,
          'slot_type_id' => $slotTypeId,
        ],
        [
          'source_type' => PageSlot::SOURCE_TYPE_PAGE,
          'sort_order' => ($index + 1) * 10,
        ],
      );
    }

    $layoutHandle = PageLayout::query()->where('handle', 'default')->value('handle') ?? 'default';
    $settings = is_array($homePage->settings) ? $homePage->settings : [];
    $settings['public_shell'] = $layoutHandle;
    $homePage->forceFill([
      'settings' => $settings,
      'layout_id' => $layout->id,
      'status' => Page::STATUS_PUBLISHED,
      'published_at' => $homePage->published_at ?? now(),
    ])->save();

    return $homePage->fresh(['slots.slotType', 'translations.locale']) ?? $homePage;
  }

  /**
   * The site's page at `/`, identified by its default-locale path.
   *
   * Never by "first published page of this site": on a fresh install those are
   * the same row, but this also runs from a seeder an operator can invoke on a
   * site full of content, and there the loose match would adopt an unrelated
   * live page and rewrite its slug and path to the home page's.
   */
  private function existingHomePage(Site $site, Locale $defaultLocale): ?Page
  {
    $pageId = PageTranslation::query()
      ->where('site_id', $site->id)
      ->where('locale_id', $defaultLocale->id)
      ->where('path', '/')
      ->value('page_id');

    return $pageId ? Page::query()->find($pageId) : null;
  }
}
