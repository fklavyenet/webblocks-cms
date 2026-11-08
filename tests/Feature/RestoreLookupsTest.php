<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Blocks\BlockCopyCatalog;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Revisions\RestoreLookups;
use WebBlocks\Cms\Support\Translations\SiteLocaleValidation;
use WebBlocks\Cms\Tests\TestCase;

class RestoreLookupsTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function unrelated_writes_do_not_repeat_default_locale_or_catalog_reads(): void
  {
    $locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $type = BlockType::create(['name' => 'Type', 'slug' => 'lookup-type']);
    $resolver = app(LocaleResolver::class);
    $queries = 0;
    DB::listen(function ($event) use (&$queries): void {
      if (str_starts_with($event->sql, 'select') && (str_contains($event->sql, 'wbcms_locales') || str_contains($event->sql, 'wbcms_block_types'))) {
        $queries++;
      }
    });
    DB::transaction(fn () => app(RestoreLookups::class)->during(function () use ($resolver, $locale, $type): void {
      for ($i = 0; $i < 6; $i++) {
        $copy = $resolver->default();
        $this->assertSame($locale->id, $copy->id);
        $this->assertSame('en', $copy->code);
        $copy->code = 'memory-only';
        $catalog = app(BlockCopyCatalog::class)->find(BlockType::class, $type->id);
        $this->assertSame('lookup-type', $catalog->slug);
        DB::table('wbcms_pages')->where('id', -1)->update(['status' => 'draft']);
      }
    }));
    $this->assertSame(3, $queries, 'Two default-locale queries and one catalog query.');
  }

  #[Test]
  public function locale_and_catalog_writes_and_rollback_discard_the_restore_read_set(): void
  {
    $english = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $turkish = Locale::create(['code' => 'tr', 'name' => 'Turkish', 'is_default' => false, 'is_enabled' => true]);
    $type = BlockType::create(['name' => 'Type', 'slug' => 'before']);
    $resolver = app(LocaleResolver::class);
    $catalog = app(BlockCopyCatalog::class);
    DB::transaction(fn () => app(RestoreLookups::class)->during(function () use ($english, $turkish, $type, $resolver, $catalog): void {
      $this->assertSame($english->id, $resolver->default()->id);
      $this->assertSame('before', $catalog->find(BlockType::class, $type->id)->slug);
      try {
        DB::transaction(function () use ($english, $turkish, $type, $resolver, $catalog): void {
          DB::table('wbcms_locales')->where('id', $english->id)->update(['is_default' => false]);
          DB::table('wbcms_locales')->where('id', $turkish->id)->update(['is_default' => true]);
          DB::table('wbcms_block_types')->where('id', $type->id)->update(['slug' => 'after']);
          $this->assertSame($turkish->id, $resolver->default()->id);
          $this->assertSame('after', $catalog->find(BlockType::class, $type->id)->slug);
          throw new RuntimeException('Synthetic lookup rollback');
        });
      } catch (RuntimeException) {
      }
      $this->assertSame($english->id, $resolver->default()->id);
      $this->assertSame('before', $catalog->find(BlockType::class, $type->id)->slug);
    }));
    DB::table('wbcms_locales')->where('id', $english->id)->update(['code' => 'de']);
    $this->assertSame('de', $resolver->default()->code, 'The restore scope must not escape into later work.');
  }

  #[Test]
  public function retrieved_listeners_keep_native_locale_and_catalog_hydration(): void
  {
    Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $type = BlockType::create(['name' => 'Type', 'slug' => 'listener-type']);
    $locales = $types = 0;
    Locale::retrieved(function () use (&$locales): void {
      $locales++;
    });
    BlockType::retrieved(function () use (&$types): void {
      $types++;
    });
    DB::transaction(fn () => app(RestoreLookups::class)->during(function () use ($type): void {
      for ($i = 0; $i < 3; $i++) {
        DB::table('wbcms_pages')->where('id', -1)->update(['status' => 'draft']);
        app(LocaleResolver::class)->default();
        app(BlockCopyCatalog::class)->find(BlockType::class, $type->id);
      }
    }));
    $this->assertSame(6, $locales, 'Native resolution hydrates the configured-code lookup and the selected locale each time.');
    $this->assertSame(3, $types);
  }

  #[Test]
  public function a_membership_write_during_its_read_cannot_seed_stale_validation_results(): void
  {
    $site = Site::create(['name' => 'Lookup site', 'handle' => 'lookup-site']);
    $locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    $changed = false;
    DB::listen(function ($event) use (&$changed, $site, $locale): void {
      if (! $changed && str_starts_with($event->sql, 'select') && str_contains($event->sql, 'site_enabled')) {
        $changed = true;
        DB::table('wbcms_site_locales')->where('site_id', $site->id)->where('locale_id', $locale->id)->update(['is_enabled' => false]);
      }
    });
    DB::transaction(fn () => app(RestoreLookups::class)->during(function () use ($site, $locale): void {
      $this->assertFalse(app(SiteLocaleValidation::class)->allows($site->id, $locale->id));
      $this->assertFalse(app(SiteLocaleValidation::class)->allows($site->id, $locale->id));
    }));
  }

  #[Test]
  public function a_default_change_during_hydration_does_not_survive_in_either_read_set(): void
  {
    $english = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $turkish = Locale::create(['code' => 'tr', 'name' => 'Turkish', 'is_default' => false, 'is_enabled' => true]);
    $changed = false;
    DB::listen(function ($event) use (&$changed, $english, $turkish): void {
      if (! $changed && str_starts_with($event->sql, 'select * from "wbcms_locales"') && str_contains($event->sql, 'order by case')) {
        $changed = true;
        DB::table('wbcms_locales')->where('id', $english->id)->update(['is_default' => false]);
        DB::table('wbcms_locales')->where('id', $turkish->id)->update(['is_default' => true]);
      }
    });
    DB::transaction(fn () => app(RestoreLookups::class)->during(function () use ($english, $turkish): void {
      $this->assertSame($english->id, app(LocaleResolver::class)->default()->id, 'The first native result predates the callback write.');
      $this->assertSame($turkish->id, app(LocaleResolver::class)->default()->id);
      $this->assertSame($turkish->id, app(LocaleResolver::class)->default()->id);
    }));
  }
}
