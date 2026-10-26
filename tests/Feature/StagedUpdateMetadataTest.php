<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageRenderController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockGalleryItemTranslation;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Tests\TestCase;

class StagedUpdateMetadataTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function staging_and_promotion_preserve_every_gallery_item_locale_and_contact_notice(): void
  {
    [$page, $slot, $locales, $media] = $this->fixtures();
    $gallery = $this->block($page, $slot, 'gallery');
    $gallery->settings = json_encode(['captions_mode' => 'below', 'lightbox_enabled' => true]);
    $gallery->save();
    // Two placements of the same media must remain independent records.
    foreach ([0, 1] as $position) {
      $item = $gallery->blockAssets()->create(['media_id' => $media->id, 'role' => 'gallery_item', 'position' => $position]);
      foreach ($locales as $locale) {
        $item->galleryItemTranslations()->create(['locale_id' => $locale->id] + $this->itemCopy($locale->code, $position));
      }
    }
    $form = $this->block($page, $slot, 'contact_form');
    $form->settings = json_encode(['consent_required' => true, 'send_email_notification' => false, 'privacy_notice_url' => '/privacy']);
    $form->save();
    foreach ($locales as $locale) {
      $form->contactFormTranslations()->create(['locale_id' => $locale->id, 'title' => 'Contact', 'submit_label' => 'Send', 'success_message' => 'Thanks', 'consent_label' => $locale->code.' notice']);
    }

    $service = app(InternalContentPlanService::class);
    $created = $service->apply(['plan' => ['mode' => 'create_staged_update_for_published_page', 'source_page_id' => $page->id, 'expected_source_path' => '/source', 'managed_slots' => ['main']]]);
    $this->assertTrue($created->ok, json_encode($created->errors));
    $staged = Page::findOrFail($created->data['staged_page']['id']);
    $this->assertMetadata($staged, $locales, $media);
    $this->assertMetadata($page->fresh(), $locales, $media);
    $this->assertNotSame($gallery->id, $staged->blocks()->where('type', 'gallery')->firstOrFail()->id);

    $this->promote($staged, $page);
    $this->assertNull(Page::find($staged->id));
    $this->assertMetadata($page->fresh(), $locales, $media);
    $this->assertSame(1, Media::count());
    $this->assertSame(4, BlockGalleryItemTranslation::count());
    $this->assertRendered($page, 'en', 'en caption 0 — 60 × 80 cm', 'en notice');
    $this->assertRendered($page, 'de', 'de caption 0 — 60 × 80 cm', 'de notice');
  }

  #[Test]
  public function content_plan_replacement_carries_native_image_copy_gallery_copy_and_consent_through_publication(): void
  {
    [$page, $slot, $locales, $media] = $this->fixtures();
    $initial = app(InternalContentPlanService::class)->apply(['plan' => ['mode' => 'create_staged_update_for_published_page', 'source_page_id' => $page->id, 'expected_source_path' => '/source', 'managed_slots' => ['main']]]);
    $this->assertTrue($initial->ok, json_encode($initial->errors));
    $created = app(InternalContentPlanService::class)->apply(['plan' => [
      'mode' => 'replace_staged_page_update', 'staged_page_id' => $initial->data['staged_page']['id'],
      'expected_source_page_id' => $page->id, 'expected_source_path' => '/source', 'locale' => 'de',
      'replace_slots' => ['main' => [
        ['type' => 'image', 'media_id' => $media->id, 'translations' => ['alt_text' => 'Blaues Bild', 'caption' => 'Einzelwerk']],
        ['type' => 'gallery', 'settings' => ['captions_mode' => 'below'], 'gallery_items' => [['media_id' => $media->id] + $this->itemCopy('de', 0)]],
        ['type' => 'contact_form', 'settings' => ['consent_required' => true, 'send_email_notification' => false], 'translations' => ['title' => 'Kontakt', 'submit_label' => 'Senden', 'success_message' => 'Danke', 'consent_label' => 'Ich stimme zu.']],
      ]],
    ]]);
    $this->assertTrue($created->ok, json_encode($created->errors));
    $staged = Page::findOrFail($initial->data['staged_page']['id']);
    $this->assertSame('Ich stimme zu.', $staged->blocks()->where('type', 'contact_form')->firstOrFail()->contactFormTranslations()->where('locale_id', $locales[1]->id)->value('consent_label'));
    $this->promote($staged, $page);
    $image = $page->blocks()->where('type', 'image')->firstOrFail()->imageTranslations()->where('locale_id', $locales[1]->id)->firstOrFail();
    $this->assertSame('Blaues Bild', $image->alt_text);
    $this->assertSame('Einzelwerk', $image->caption);
    $this->assertRendered($page, 'de', 'de caption 0 — 60 × 80 cm', 'Ich stimme zu.');
  }

  #[Test]
  public function incremental_page_and_shared_slot_creation_carry_native_translation_fields(): void
  {
    [$page, $slot, $locales, $media] = $this->fixtures();
    $shared = SharedSlot::create(['site_id' => $page->site_id, 'name' => 'Shared', 'handle' => 'shared', 'slot_name' => 'main', 'is_active' => true]);
    $operations = app(InternalContentApiOperations::class);
    foreach ([false, true] as $sharedSlot) {
      foreach ([
        ['type' => 'contact_form', 'translations' => ['submit_label' => 'Send', 'success_message' => 'Thanks', 'consent_label' => 'I agree.']],
        ['type' => 'contact_form', 'settings' => ['consent_label' => 'Existing settings notice'], 'translations' => ['submit_label' => 'Send', 'success_message' => 'Thanks']],
        ['type' => 'image', 'media_id' => $media->id, 'translations' => ['title' => 'Ignored alias', 'subtitle' => 'Ignored alt alias', 'caption' => 'Work title', 'alt_text' => 'Blue painting']],
      ] as $input) {
        $errors = $warnings = [];
        $payload = $operations->normalizeBlock($input, 'block', null, $errors, $warnings);
        $this->assertSame([], $errors);
        $block = $sharedSlot
          ? $operations->createSharedSlotBlock($shared, $payload, 'en', null, 0)
          : $operations->createPageSlotBlock($page, $slot, $payload, 'en', null, 0);
        if ($input['type'] === 'contact_form') {
          $row = $block->contactFormTranslations()->where('locale_id', $locales[0]->id)->firstOrFail();
          $this->assertSame($input['translations']['consent_label'] ?? $input['settings']['consent_label'], $row->consent_label);
          $this->assertArrayNotHasKey('consent_label', $block->decodedSettings());
          $this->assertSame('Send', $row->submit_label);
          $this->assertSame('Thanks', $row->success_message);
        } else {
          $row = $block->imageTranslations()->where('locale_id', $locales[0]->id)->firstOrFail();
          $this->assertSame('Work title', $row->caption);
          $this->assertSame('Blue painting', $row->alt_text);
        }
      }
    }
  }

  private function fixtures(): array
  {
    $locales = [];
    foreach (['en', 'de'] as $code) {
      $locales[] = Locale::create(['code' => $code, 'name' => $code, 'is_default' => $code === 'en', 'is_enabled' => true]);
    }
    $site = Site::create(['handle' => 'example', 'name' => 'Example', 'domain' => 'example.test', 'is_primary' => true]);
    $site->locales()->sync(array_map(fn ($locale) => $locale->id, $locales));
    $slot = SlotType::create(['slug' => 'main', 'name' => 'Main', 'status' => 'published']);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Source', 'slug' => 'source', 'status' => Page::STATUS_PUBLISHED]);
    $page->translations()->updateOrCreate(['locale_id' => $locales[1]->id], ['name' => 'Source', 'slug' => 'source', 'path' => '/source']);
    PageSlot::create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'source_type' => PageSlot::SOURCE_TYPE_PAGE]);
    foreach (['gallery', 'image', 'contact_form'] as $type) {
      BlockType::create(['slug' => $type, 'name' => $type, 'category' => 'content', 'source_type' => 'static', 'status' => 'published']);
    }
    $media = Media::create(['disk' => 'public', 'path' => 'example.jpg', 'filename' => 'example.jpg', 'original_name' => 'example.jpg', 'mime_type' => 'image/jpeg', 'kind' => Media::KIND_IMAGE, 'visibility' => 'public']);

    return [$page->fresh(['site.locales', 'translations.locale', 'slots.slotType']), $slot, $locales, $media];
  }

  private function block(Page $page, SlotType $slot, string $type): Block
  {
    return Block::create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'slot' => $slot->slug, 'block_type_id' => BlockType::where('slug', $type)->value('id'), 'type' => $type, 'status' => 'published']);
  }

  private function itemCopy(string $locale, int $position): array
  {
    return ['alt_text' => $locale.' blue painting '.$position, 'caption' => $locale.' caption '.$position.' — 60 × 80 cm', 'overlay_title' => $locale.' overlay '.$position, 'overlay_text' => $locale." details\n".$position];
  }

  private function assertMetadata(Page $page, array $locales, Media $media): void
  {
    $gallery = $page->blocks()->where('type', 'gallery')->firstOrFail();
    $items = $gallery->blockAssets()->orderBy('position')->get();
    $this->assertCount(2, $items);
    foreach ($items as $position => $item) {
      $this->assertSame($media->id, $item->media_id);
      $this->assertSame('gallery_item', $item->role);
      $this->assertSame($position, (int) $item->position);
      $this->assertCount(2, $item->galleryItemTranslations);
      foreach ($locales as $locale) {
        $this->assertSame($this->itemCopy($locale->code, $position), $item->galleryItemTranslations->firstWhere('locale_id', $locale->id)->only(['alt_text', 'caption', 'overlay_title', 'overlay_text']));
      }
    }
    $form = $page->blocks()->where('type', 'contact_form')->firstOrFail();
    $this->assertSame('/privacy', $form->setting('privacy_notice_url'));
    foreach ($locales as $locale) {
      $this->assertSame($locale->code.' notice', $form->contactFormTranslations()->where('locale_id', $locale->id)->value('consent_label'));
    }
  }

  private function promote(Page $staged, Page $source): void
  {
    $result = app(InternalContentPlanService::class)->apply(['plan' => ['mode' => 'promote_staged_page_update', 'staged_page_id' => $staged->id, 'expected_source_page_id' => $source->id, 'expected_source_path' => '/source', 'promote_slots' => ['main']]]);
    $this->assertTrue($result->ok, json_encode($result->errors));
  }

  private function assertRendered(Page $page, string $locale, string $caption, string $notice): void
  {
    view()->share('errors', new ViewErrorBag);
    $request = Request::create('/webadmin/api/pages/'.$page->id.'/render', 'GET', ['format' => 'html', 'locale' => $locale]);
    $html = (string) app(InternalPageRenderController::class)->show($request, $page->fresh())->getContent();
    $this->assertStringContainsString($caption, $html);
    $this->assertStringContainsString($notice, $html);
    $this->assertStringContainsString('name="consent"', $html);
    $this->assertMatchesRegularExpression('/<input[^>]*name="consent"[^>]*required/s', $html);
  }
}
