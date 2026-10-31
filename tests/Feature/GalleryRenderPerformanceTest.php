<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Eloquent\Collection as Models;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockGalleryItemTranslation;
use WebBlocks\Cms\Models\BlockMedia;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\TestCase;

class GalleryRenderPerformanceTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function image_count_does_not_multiply_locale_queries_and_output_keeps_item_copy(): void
  {
    [$block] = $this->fixture(100);
    $counts = [];
    foreach ([1, 20, 100] as $count) {
      $this->request();
      $sample = clone $block;
      $sample->setRelation('blockMedia', new Models($block->blockMedia->take($count)->all()));
      [$html, $counts[$count]] = $this->measure($sample, 'gallery-'.$count);
      $this->assertStringContainsString('alt="Turkish alt 1"', $html);
      $this->assertStringContainsString('Turkish caption 1', $html);
      $this->assertStringNotContainsString('>English caption 1<', $html);
      if ($count > 1) {
        $this->assertStringContainsString('English caption 2', $html, 'Missing visitor-language item copy keeps the default-language fallback.');
      }
      if ($count > 1) {
        $this->assertLessThan(strpos($html, 'synthetic/gallery-'.($count - 1).'.svg'), strpos($html, 'synthetic/gallery-0.svg'));
      }
      [$repeated, , $repeatQueries] = $this->measure($sample, 'gallery-'.$count.'-repeat');
      $this->assertSame(hash('sha256', $html), hash('sha256', $repeated));
      if (! getenv('CMS_PROFILE_BASELINE')) {
        $this->assertCount(0, array_filter($repeatQueries, fn ($q) => str_contains($q['query'], 'wbcms_locales') || str_contains($q['query'], 'wbcms_media')));
      }
    }
    if (! getenv('CMS_PROFILE_BASELINE')) {
      $this->assertSame($counts[1], $counts[20]);
      $this->assertSame($counts[1], $counts[100]);
    }
  }

  #[Test]
  public function locale_writes_and_rollback_invalidate_gallery_fallback_and_new_renders_read_media_changes(): void
  {
    [$block, $en, $tr] = $this->fixture(1);
    $item = $block->blockMedia->first();
    $block->setAttribute('requested_locale_code', 'unknown');
    $this->assertStringContainsString('English caption 1', $this->render($block));
    DB::beginTransaction();
    DB::table('wbcms_locales')->where('id', $en->id)->update(['is_default' => false]);
    DB::table('wbcms_locales')->where('id', $tr->id)->update(['is_default' => true]);
    $this->assertSame('Turkish caption 1', $block->resolvedGalleryItemTranslation($item)->caption);
    DB::rollBack();
    $this->assertSame($en->id, Page::defaultLocaleId());
    $this->assertSame('English caption 1', $block->resolvedGalleryItemTranslation($item)->caption);
    $media = $item->media;
    $media->update(['visibility' => 'private']);
    $this->request();
    $fresh = $block->fresh(['blockType', 'page.site', 'blockMedia.media', 'blockMedia.galleryItemTranslations']);
    $fresh->setAttribute('requested_locale_code', 'tr');
    $this->assertStringNotContainsString('synthetic/gallery-0.svg', $this->render($fresh));
    $media->delete();
    $fresh = $block->fresh(['blockType', 'page.site', 'blockMedia.media', 'blockMedia.galleryItemTranslations']);
    $this->assertCount(0, $fresh->galleryAssets());
  }

  #[Test]
  public function media_order_deduplication_and_legacy_fallback_are_preserved(): void
  {
    [$block] = $this->fixture(2);
    BlockMedia::create(['block_id' => $block->id, 'media_id' => $block->blockMedia->first()->media_id, 'role' => 'gallery_item', 'position' => 3]);
    $fresh = $block->fresh(['blockType', 'page.site', 'blockMedia.media', 'blockMedia.galleryItemTranslations']);
    $this->assertSame($block->galleryMediaIds(), $fresh->galleryAssets()->pluck('id')->all());
    $fresh->setRelation('blockMedia', new Models);
    $fresh->setAttribute('settings', ['asset_ids' => [$block->blockMedia->last()->media_id, $block->blockMedia->first()->media_id]]);
    $html = $this->render($fresh);
    $this->assertLessThan(strpos($html, 'synthetic/gallery-0.svg'), strpos($html, 'synthetic/gallery-1.svg'));
  }

  private function fixture(int $count): array
  {
    $en = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $tr = Locale::create(['code' => 'tr', 'name' => 'Turkish', 'is_enabled' => true]);
    $site = Site::create(['name' => 'Synthetic gallery', 'handle' => 'gallery-fixture', 'is_primary' => true]);
    $site->locales()->sync([$en->id => ['is_enabled' => true], $tr->id => ['is_enabled' => true]]);
    $page = Page::create(['site_id' => $site->id, 'status' => 'published']);
    $type = BlockType::firstOrCreate(['slug' => 'gallery'], ['name' => 'Gallery', 'is_active' => true]);
    $block = Block::create(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'gallery', 'status' => 'published', 'settings' => ['captions_mode' => 'below', 'lightbox_enabled' => false]]);
    for ($index = 0; $index < $count; $index++) {
      $media = Media::create(['disk' => 'public', 'path' => 'synthetic/gallery-'.$index.'.svg', 'filename' => 'gallery-'.$index.'.svg', 'mime_type' => 'image/svg+xml', 'kind' => 'image', 'visibility' => 'public']);
      $item = BlockMedia::create(['block_id' => $block->id, 'media_id' => $media->id, 'role' => 'gallery_item', 'position' => $index]);
      foreach ($index % 2 === 0 ? [[$en, 'English'], [$tr, 'Turkish']] : [[$en, 'English']] as [$locale, $name]) {
        BlockGalleryItemTranslation::create(['block_media_id' => $item->id, 'locale_id' => $locale->id, 'alt_text' => $name.' alt '.($index + 1), 'caption' => $name.' caption '.($index + 1)]);
      }
    }
    $block = $block->fresh(['blockType', 'page.site', 'blockMedia.media', 'blockMedia.galleryItemTranslations']);
    $block->setAttribute('requested_locale_code', 'tr');

    return [$block, $en, $tr];
  }

  private function request(): void
  {
    app()->instance('request', Request::create('/tr/synthetic-gallery'));
    app()->forgetScopedInstances();
  }

  private function render(Block $block): string
  {
    return view('webblocks-cms::pages.partials.blocks.gallery', ['block' => $block])->render();
  }

  private function measure(Block $block, string $scenario): array
  {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $start = hrtime(true);
    try {
      $html = $this->render($block);
      $queries = DB::getQueryLog();
    } finally {
      DB::disableQueryLog();
    }
    if ($report = getenv('CMS_PERFORMANCE_REPORT')) {
      file_put_contents($report, json_encode(['scenario' => $scenario, 'queries' => count($queries), 'locale_queries' => count(array_filter($queries, fn ($q) => str_contains($q['query'], 'wbcms_locales'))), 'media_queries' => count(array_filter($queries, fn ($q) => str_contains($q['query'], 'wbcms_media'))), 'render_ms' => round((hrtime(true) - $start) / 1e6, 3), 'html_sha256' => hash('sha256', $html)], JSON_THROW_ON_ERROR)."\n", FILE_APPEND);
    }

    return [$html, count($queries), $queries];
  }
}
