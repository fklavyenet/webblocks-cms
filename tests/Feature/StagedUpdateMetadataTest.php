<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Tests\TestCase;

class StagedUpdateMetadataTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('webblocks-cms.routes.public', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function creating_an_update_draft_copies_physical_dimensions_per_gallery_placement(): void
  {
    [$source, $slot, $locales, $media] = $this->fixtures();
    $dimensions = [[400000, 400000], [500000, 200000], [null, null], [null, 800000]];
    $gallery = $this->physicalGallery($source, $slot, $locales, $media, $dimensions);
    $draft = $this->createUpdateDraftThroughApi($source);

    $draftGallery = $draft->blocks()->where('type', 'gallery')->sole();
    $this->assertNotSame($gallery->id, $draftGallery->id);
    $this->assertPhysicalGallery($draftGallery, $locales, $media, $dimensions);
    $this->assertPhysicalGallery($gallery->fresh(), $locales, $media, $dimensions);
    $this->assertRenderedDimensions($draft, $dimensions);
  }

  #[Test]
  public function promoting_an_update_draft_publishes_the_dimensions_saved_in_the_draft(): void
  {
    [$source, $slot, $locales, $media] = $this->fixtures();
    $original = [[400000, 400000], [500000, 200000], [null, null], [null, 800000]];
    $gallery = $this->physicalGallery($source, $slot, $locales, $media, $original);
    $draft = $this->createUpdateDraftThroughApi($source);
    $draftGallery = $draft->blocks()->where('type', 'gallery')->sole();
    $edited = [[800000, 800000], [200000, 500000], [null, null], [600000, null]];
    $edits = [];
    foreach ($edited as $position => [$width, $height]) {
      $edits[] = [
        'media_id' => $media->id, 'sort_order' => $position,
        'physical_width_um' => $width, 'physical_height_um' => $height,
      ];
    }
    $this->patchJson('/webadmin/api/blocks/'.$draftGallery->id, ['gallery_items' => $edits])->assertOk();
    $draftTranslation = $draft->translationForLocale($locales[1]);
    $this->patchJson('/webadmin/api/pages/'.$draft->id.'/translations/'.$draftTranslation->id, [
      'seo_description' => 'Deutsch mit Galerie',
    ])->assertOk()->assertJsonPath('translation.seo_description', 'Deutsch mit Galerie');
    $this->get('/webadmin/api/pages/'.$draft->id.'/render?format=html&locale=de')
      ->assertOk()->assertSee('<meta name="description" content="Deutsch mit Galerie">', false);
    $this->assertPhysicalGallery($draftGallery->fresh(), $locales, $media, $edited);
    $this->assertPhysicalGallery($gallery->fresh(), $locales, $media, $original);

    $this->postJson('/webadmin/api/content/apply', ['plan' => [
      'mode' => 'promote_staged_page_update', 'staged_page_id' => $draft->id,
      'expected_source_page_id' => $source->id, 'expected_source_path' => '/source', 'promote_slots' => ['main'],
    ]])->assertCreated();

    $this->assertNull($draft->fresh());
    $this->assertSame('Deutsch mit Galerie', $source->fresh()->translationForLocale($locales[1])->seo_description);
    $this->assertNull($source->fresh()->translationForLocale($locales[0])->seo_description);
    $this->get('http://example.test/de/source')->assertOk()->assertSee('<meta name="description" content="Deutsch mit Galerie">', false);
    $this->assertPhysicalGallery($source->blocks()->where('type', 'gallery')->sole(), $locales, $media, $edited);
    $this->assertRenderedDimensions($source, $edited);
  }

  #[Test]
  public function api_metadata_only_promotion_can_clear_one_field_without_replacing_blocks(): void
  {
    [$source, $slot, [$english, $german], $media] = $this->fixtures();
    $source->site->update(['seo_description' => 'Site fallback']);
    $source->translationForLocale($english)->update(['seo_description' => 'Keep English']);
    $published = $source->translationForLocale($german);
    $published->update(['seo_description' => 'Remove German', 'og_description' => 'Keep Open Graph']);
    $gallery = $this->physicalGallery($source, $slot, [$english, $german], $media, [[400000, 400000]]);
    $draft = $this->createUpdateDraftThroughApi($source);
    $translation = $draft->translationForLocale($german);
    $endpoint = '/webadmin/api/pages/'.$draft->id.'/translations/'.$translation->id;
    $promotion = ['plan' => [
      'mode' => 'promote_staged_page_update', 'staged_page_id' => $draft->id,
      'expected_source_path' => '/source', 'promote_slots' => [],
    ]];
    $this->postJson('/webadmin/api/content/apply', $promotion)->assertUnprocessable()
      ->assertJsonPath('errors.0.path', 'plan.promote_slots');
    $this->patchJson($endpoint, ['seo_description' => null])->assertOk()->assertJsonPath('translation.seo_description', null);
    $this->get('/webadmin/api/pages/'.$draft->id.'/render?format=html&locale=de')
      ->assertOk()->assertSee('<meta name="description" content="Site fallback">', false);
    $this->assertSame('Remove German', $published->fresh()->seo_description);

    $this->postJson('/webadmin/api/content/apply', $promotion)
      ->assertCreated()->assertJsonPath('normalized_plan.staged_update.promote_slots', []);

    $this->assertNull($published->fresh()->seo_description);
    $this->assertSame('Keep Open Graph', $published->fresh()->og_description);
    $this->assertSame('Keep English', $source->fresh()->translationForLocale($english)->seo_description);
    $this->assertSame($gallery->id, $source->blocks()->sole()->id);
    $this->assertModelMissing($draft);
    $this->get('http://example.test/de/source')->assertOk()->assertSee('<meta name="description" content="Site fallback">', false);
  }

  #[Test]
  public function api_staged_metadata_rejects_a_changed_source_a_missing_baseline_or_a_disabled_locale(): void
  {
    [$source, $slot, [$english, $german], $media] = $this->fixtures();
    $published = $source->translationForLocale($german);
    $published->update(['seo_description' => 'Original']);
    $gallery = $this->physicalGallery($source, $slot, [$english, $german], $media, [[400000, 400000]]);
    $draft = $this->createUpdateDraftThroughApi($source);
    $translation = $draft->translationForLocale($german);
    $endpoint = '/webadmin/api/pages/'.$draft->id.'/translations/'.$translation->id;
    $this->patchJson($endpoint, ['seo_description' => '   '])->assertOk()->assertJsonPath('translation.seo_description', null);
    $promotion = ['plan' => [
      'mode' => 'promote_staged_page_update', 'staged_page_id' => $draft->id,
      'expected_source_path' => '/source', 'promote_slots' => ['main'],
    ]];

    $source->site->locales()->updateExistingPivot($german->id, ['is_enabled' => false]);
    $this->patchJson($endpoint, ['seo_description' => 'Disabled'])->assertUnprocessable();
    $this->postJson('/webadmin/api/content/apply', $promotion)->assertUnprocessable()
      ->assertJsonPath('errors.0.path', 'plan.translation_changes.locale');
    $source->site->locales()->updateExistingPivot($german->id, ['is_enabled' => true]);

    $published->update(['seo_description' => 'Changed after draft creation']);
    $this->postJson('/webadmin/api/content/apply', $promotion)->assertUnprocessable()
      ->assertJsonPath('errors.0.path', 'plan.translation_changes.source_page');
    $this->assertSame('Changed after draft creation', $published->fresh()->seo_description);

    $published->update(['seo_description' => 'Original']);
    DB::table('wbcms_staged_page_bases')->where('page_id', $draft->id)->delete();
    $this->postJson('/webadmin/api/content/apply', $promotion)->assertUnprocessable()
      ->assertJsonPath('errors.0.path', 'plan.translation_changes.source_page');
    $this->assertSame('Original', $published->fresh()->seo_description);
    $this->assertSame($gallery->id, $source->blocks()->sole()->id);
    $this->assertModelExists($draft);
  }

  #[Test]
  public function gallery_patch_selects_existing_placements_and_rolls_back_ambiguous_updates(): void
  {
    [$source, $slot, $locales, $media] = $this->fixtures();
    $original = [[400000, 200000], [500000, 300000]];
    $this->physicalGallery($source, $slot, $locales, $media, $original);
    $draft = $this->createUpdateDraftThroughApi($source);
    $gallery = $draft->blocks()->where('type', 'gallery')->sole();
    $url = '/webadmin/api/blocks/'.$gallery->id;
    $first = ['media_id' => $media->id, 'sort_order' => 0, 'physical_width_um' => 800000];

    $this->getJson($url)->assertOk()->assertJsonPath('block.gallery_items.1.sort_order', 1);
    $this->patchJson($url, ['gallery_items' => [array_replace($first, ['physical_width_um' => true])]])
      ->assertUnprocessable()->assertJsonPath('errors.0.path', 'gallery_items.0.physical_width_um');
    $this->patchJson($url, ['gallery_items' => [
      $first, ['media_id' => $media->id, 'physical_height_um' => 500000],
    ]])->assertUnprocessable()->assertJsonPath('errors.0.path', 'gallery_items.1');
    $this->assertPhysicalGallery($gallery->fresh(), $locales, $media, $original);

    $this->patchJson($url, ['locale' => 'de', 'gallery_items' => [$first + ['caption' => 'Geändert']]])
      ->assertOk()->assertJsonPath('block.gallery_items.0.physical_width_um', 800000);
    $items = $gallery->fresh()->galleryItems();
    $this->assertSame([800000, 200000], [$items[0]->physical_width_um, $items[0]->physical_height_um]);
    $this->assertSame([500000, 300000], [$items[1]->physical_width_um, $items[1]->physical_height_um]);
    $this->assertSame('Geändert', $items[0]->galleryItemTranslations()->where('locale_id', $locales[1]->id)->sole()->caption);
    $this->assertSame($this->itemCopy('de', 0)['alt_text'], $items[0]->galleryItemTranslations()->where('locale_id', $locales[1]->id)->sole()->alt_text);
    $this->assertSame($this->itemCopy('en', 0)['caption'], $items[0]->galleryItemTranslations()->where('locale_id', $locales[0]->id)->sole()->caption);
  }

  #[Test]
  public function replacing_a_gallery_preserves_repeated_media_and_each_placements_copy(): void
  {
    [$source, , $locales, $media] = $this->fixtures();
    $draft = $this->createUpdateDraftThroughApi($source);
    $this->postJson('/webadmin/api/content/apply', ['plan' => [
      'mode' => 'replace_staged_page_update', 'staged_page_id' => $draft->id,
      'expected_source_page_id' => $source->id, 'expected_source_path' => '/source',
      'locale' => 'de', 'replace_slots' => ['main' => [[
        'type' => 'gallery', 'settings' => ['variant' => 'physical-scale'],
        'gallery_items' => [
          ['media_id' => $media->id, 'sort_order' => 1, 'physical_width_um' => 800000, 'physical_height_um' => 600000] + $this->itemCopy('de', 1),
          ['media_id' => $media->id, 'sort_order' => 0, 'physical_width_um' => 400000, 'physical_height_um' => 200000] + $this->itemCopy('de', 0),
        ],
      ]]],
    ]])->assertCreated();

    $items = $draft->blocks()->where('type', 'gallery')->sole()->galleryItems();
    $this->assertSame([$media->id, $media->id], $items->pluck('media_id')->all());
    $this->assertSame([400000, 800000], $items->pluck('physical_width_um')->all());
    $this->assertSame([200000, 600000], $items->pluck('physical_height_um')->all());
    foreach ($items as $position => $item) {
      $translation = $item->galleryItemTranslations()->sole();
      $this->assertSame($locales[1]->id, $translation->locale_id);
      $this->assertSame($this->itemCopy('de', $position), $translation->only(['alt_text', 'caption', 'overlay_title', 'overlay_text']));
    }
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
    $form->settings = json_encode(['consent_required' => true, 'send_email_notification' => false, 'privacy_notice_url' => '/privacy', 'show_name' => false]);
    $form->save();
    foreach ($locales as $locale) {
      $form->contactFormTranslations()->create(['locale_id' => $locale->id, 'title' => 'Contact', 'submit_label' => 'Send', 'success_message' => 'Thanks', 'consent_label' => $locale->code.' notice', 'subject_label' => $locale->code.' custom subject']);
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
        ['type' => 'gallery', 'settings' => ['captions_mode' => 'below', 'variant' => 'physical-scale', 'gallery_vertical_alignment' => 'top'],
          'gallery_items' => [['media_id' => $media->id, 'physical_width_um' => 600000, 'physical_height_um' => 800000] + $this->itemCopy('de', 0)]],
        ['type' => 'contact_form', 'settings' => ['consent_required' => true, 'send_email_notification' => false], 'translations' => ['title' => 'Kontakt', 'submit_label' => 'Senden', 'success_message' => 'Danke', 'consent_label' => 'Ich stimme zu.']],
      ]],
    ]]);
    $this->assertTrue($created->ok, json_encode($created->errors));
    $staged = Page::findOrFail($initial->data['staged_page']['id']);
    $this->assertSame('Ich stimme zu.', $staged->blocks()->where('type', 'contact_form')->firstOrFail()->contactFormTranslations()->where('locale_id', $locales[1]->id)->value('consent_label'));
    $this->promote($staged, $page);
    $image = $page->blocks()->where('type', 'image')->firstOrFail()->imageTranslations()->where('locale_id', $locales[1]->id)->firstOrFail();
    $this->assertSame('Blaues Bild', $image->alt_text);
    $this->assertSame('top', $page->blocks()->where('type', 'gallery')->firstOrFail()->galleryVerticalAlignment());
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

  private function physicalGallery(Page $page, SlotType $slot, array $locales, Media $media, array $dimensions): Block
  {
    $gallery = $this->block($page, $slot, 'gallery');
    $gallery->update(['settings' => [
      'variant' => 'physical-scale', 'gallery_unit' => 'cm', 'gallery_vertical_alignment' => 'center',
      'captions_mode' => 'below', 'lightbox_enabled' => true,
    ]]);
    // The same media can appear more than once with independent item data.
    foreach (array_reverse($dimensions, true) as $position => [$width, $height]) {
      $item = $gallery->blockAssets()->create([
        'media_id' => $media->id, 'role' => 'gallery_item', 'position' => $position,
        'physical_width_um' => $width, 'physical_height_um' => $height,
      ]);
      foreach ($locales as $locale) {
        $item->galleryItemTranslations()->create(['locale_id' => $locale->id] + $this->itemCopy($locale->code, $position));
      }
    }

    return $gallery;
  }

  private function createUpdateDraftThroughApi(Page $source): Page
  {
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic gallery editor', capabilities: ['content.apply', 'content.publish', 'content.read'])->plainToken;
    $this->withToken($token);
    $created = $this->postJson('/webadmin/api/content/apply', ['plan' => [
      'mode' => 'create_staged_update_for_published_page', 'source_page_id' => $source->id,
      'expected_source_path' => '/source', 'managed_slots' => ['main'],
    ]])->assertCreated();

    return Page::findOrFail($created->json('data.staged_page.id'));
  }

  private function assertPhysicalGallery(Block $gallery, array $locales, Media $media, array $dimensions): void
  {
    $this->assertSame('physical-scale', $gallery->galleryVariant());
    $this->assertSame('cm', $gallery->setting('gallery_unit'));
    $this->assertSame('center', $gallery->galleryVerticalAlignment());
    $this->assertSame('below', $gallery->galleryCaptionsMode());
    $this->assertTrue($gallery->galleryLightboxEnabled());

    $items = $gallery->galleryItems()->load('galleryItemTranslations');
    $this->assertCount(count($dimensions), $items);
    foreach ($items as $position => $item) {
      $this->assertSame($position, $item->position);
      $this->assertSame($media->id, $item->media_id);
      $this->assertSame($dimensions[$position], [$item->physical_width_um, $item->physical_height_um]);
      $this->assertCount(count($locales), $item->galleryItemTranslations);
      foreach ($locales as $locale) {
        $this->assertSame($this->itemCopy($locale->code, $position), $item->galleryItemTranslations->firstWhere('locale_id', $locale->id)->only(['alt_text', 'caption', 'overlay_title', 'overlay_text']));
      }
    }
  }

  private function assertRenderedDimensions(Page $page, array $dimensions): void
  {
    $response = $this->get('/webadmin/api/pages/'.$page->id.'/render?format=html&locale=de')->assertOk();
    preg_match_all('/data-wb-gallery-physical-width="(\d*)"\s+data-wb-gallery-physical-height="(\d*)"/', $response->getContent(), $matches, PREG_SET_ORDER);
    $this->assertSame(
      array_map(fn (array $pair): array => array_map(strval(...), $pair), $dimensions),
      array_map(fn (array $match): array => array_slice($match, 1), $matches),
    );
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
    $this->assertFalse($form->setting('show_name'));
    foreach ($locales as $locale) {
      $this->assertSame($locale->code.' custom subject', $form->contactFormTranslations()->where('locale_id', $locale->id)->value('subject_label'));
    }
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
