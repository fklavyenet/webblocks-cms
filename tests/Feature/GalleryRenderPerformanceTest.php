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
use WebBlocks\Cms\Support\BlockTypes\BlockSettingsSchema;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Tests\TestCase;

class GalleryRenderPerformanceTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function custom_gallery_settings_round_trip_through_relational_storage_and_rendering(): void
  {
    [$block] = $this->fixture(2);
    $block = $block->fresh();
    $block->update(['settings' => [...$block->decodedSettings(), 'variant' => 'weighted', 'columns' => '3', 'gallery_weights' => [1, 2.5]]]);
    $block = $block->fresh();
    $this->assertSame([1.0, 2.5, 1], $block->galleryWeights());
    $this->assertStringContainsString('data-wb-gallery-weights="1 2.5 1"', $this->render($block));
    $this->assertSame(2, DB::table('wbcms_block_gallery_weights')->count());

    app(PageRevisionManager::class)->capture($block->page, force: true);
    $this->assertSame(2, DB::table('wbcms_snapshot_block_gallery_weights')->count());

    $block->update(['settings' => [...$block->decodedSettings(), 'variant' => 'equal-height', 'columns' => '4']]);
    $html = $this->render($block->fresh());
    $this->assertStringContainsString('wb-gallery--equal-height', $html);
    $this->assertStringContainsString('data-wb-gallery-items-per-row="4"', $html);
    $this->assertStringNotContainsString('data-wb-gallery-weights=', $html);
    $fragment = view('webblocks-cms::admin.blocks.settings.gallery', [
      'block' => $block->fresh(),
      'inputPrefix' => 'blocks[7]',
    ])->render();
    $this->assertStringContainsString('name="blocks[7][gallery_variant]" value="equal-height" data-cms-gallery-variant checked', $fragment);
    $this->assertStringContainsString('name="blocks[7][gallery_weights][]"', $fragment);
    $this->assertStringContainsString('>Column 2<', $fragment);
  }

  #[Test]
  public function physical_dimensions_are_rendered_and_restored_with_the_gallery_item(): void
  {
    [$block] = $this->fixture(3);
    $block = $block->fresh();
    $block->update(['settings' => [...$block->decodedSettings(), 'variant' => 'physical-scale', 'gallery_unit' => 'cm']]);
    foreach ($block->galleryItems() as $index => $item) {
      $size = $index === 2 ? 800000 : 400000;
      $item->update(['physical_width_um' => $size, 'physical_height_um' => $size]);
    }
    $html = $this->render($block->fresh());
    $this->assertStringContainsString('wb-gallery--physical-scale', $html);
    $this->assertSame(1, substr_count($html, 'class="wb-gallery-row"'));
    $this->assertSame(2, substr_count($html, 'data-wb-gallery-physical-width="400000"'));
    $this->assertStringContainsString('data-wb-gallery-physical-height="800000"', $html);
    $revisions = app(PageRevisionManager::class);
    $page = $block->page;
    $revision = $revisions->capture($page, force: true);
    $candidate = Page::create(['site_id' => $page->site_id, 'title' => 'Candidate', 'slug' => 'candidate', 'status' => 'draft']);
    $revisions->applySnapshotToCandidate($candidate, $revision->fresh()->snapshot, ['source_page_id' => $page->id, 'revision_id' => $revision->id]);
    $restored = $candidate->blocks()->where('type', 'gallery')->firstOrFail();
    $this->assertSame('physical-scale', $restored->galleryVariant());
    $this->assertSame([400000, 400000, 800000], $restored->galleryItems()->map(fn ($item) => $item->galleryDimensions()['physical_width_um'])->all());
  }

  #[Test]
  public function gallery_schema_validates_an_ordered_list_of_weights(): void
  {
    $fields = app(BlockSettingsSchema::class)->forType('gallery')['fields'];
    $this->assertContains('weighted', $fields['variant']['enum']);
    $this->assertContains('equal-height', $fields['variant']['enum']);
    $rule = $fields['gallery_weights']['rule'];
    $valid = false;
    $this->assertSame([1.0, 2.5], BlockSettingsSchema::normalizeValue([1, '2.5'], $rule, $valid));
    $this->assertTrue($valid);
    foreach ([[0], [1, -2.5], [2 => 1], [1, 1, 1, 1, 1, 1]] as $invalid) {
      BlockSettingsSchema::normalizeValue($invalid, $rule, $valid);
      $this->assertFalse($valid);
    }
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
