<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Resolving a public path for a non-default locale used to overwrite the
 * page's currentTranslation relation as a lookup side effect. The admin edit
 * screen resolves paths for every site locale to build the Translations
 * table, so after that loop the Settings form rendered the last locale's
 * title and slug instead of the default-locale values -- making it look as
 * if saving the English fields never worked.
 */
class PageDefaultTranslationDisplayTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function resolving_translation_paths_for_other_locales_keeps_default_locale_fields(): void
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    $english = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $turkish = Locale::query()->create(['code' => 'tr', 'name' => 'Türkçe', 'is_default' => false, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([
      $english->id => ['is_enabled' => true],
      $turkish->id => ['is_enabled' => true],
    ]);

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Archive',
      'slug' => 'archive',
      'status' => Page::STATUS_PUBLISHED,
    ]);
    $page->translations()->create([
      'locale_id' => $turkish->id,
      'name' => 'Arşiv',
      'slug' => 'arsiv',
      'path' => '/arsiv',
    ]);

    $page = $page->fresh(['translations.locale', 'site']);

    $statuses = $page->translationStatusForSite();

    $this->assertSame('/tr/arsiv', $statuses->firstWhere(fn (array $status) => $status['locale']->code === 'tr')['public_path']);
    $this->assertSame('Archive', $page->title);
    $this->assertSame('archive', $page->slug);
  }
}
