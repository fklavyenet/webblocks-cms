<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockButtonTranslation;
use WebBlocks\Cms\Models\BlockContactFormTranslation;
use WebBlocks\Cms\Models\BlockImageTranslation;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Tests\TestCase;

/**
 * PATCH /webadmin/api/blocks/{block} used to write every translated field into
 * the text translation table, whichever family the block belonged to. A button
 * or contact form edit was accepted with 200 and stored where the renderer
 * never reads, and a contact form's submit_label and success_message had no API
 * path at all — which is how a translated site kept an English "Send message"
 * button on an otherwise translated contact page.
 */
class BlockPatchTranslationFamilyTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function patching_a_contact_form_writes_its_own_translation_row(): void
  {
    $block = $this->seedBlock('contact_form');

    $response = $this->patchBlock($block, [
      'locale' => 'de',
      'translations' => [
        'title' => 'Kontakt',
        'content' => 'Schreiben Sie uns.',
        'submit_label' => 'Nachricht senden',
        'success_message' => 'Danke!',
      ],
    ]);

    $this->assertSame(200, $response->getStatusCode());

    $translation = BlockContactFormTranslation::query()
      ->where('block_id', $block->id)
      ->where('locale_id', $this->locale('de')->id)
      ->first();

    $this->assertNotNull($translation, 'The German contact form translation must be stored.');
    $this->assertSame('Kontakt', $translation->title);
    $this->assertSame('Schreiben Sie uns.', $translation->content);
    $this->assertSame('Nachricht senden', $translation->submit_label);
    $this->assertSame('Danke!', $translation->success_message);

    $this->assertSame(
      0,
      BlockTextTranslation::query()->where('block_id', $block->id)->count(),
      'A contact form edit must not leave rows in the text translation table.',
    );
  }

  #[Test]
  public function patching_a_contact_form_in_a_second_locale_keeps_the_default_one(): void
  {
    // The renderer falls back to the default locale, so a translation written
    // for German must not be the only row the block has.
    $block = $this->seedBlock('contact_form', ['title' => 'Contact', 'content' => 'Write to us.']);

    $this->patchBlock($block, ['locale' => 'de', 'translations' => ['submit_label' => 'Nachricht senden']]);

    $default = BlockContactFormTranslation::query()
      ->where('block_id', $block->id)
      ->where('locale_id', $this->locale('en')->id)
      ->first();

    $this->assertNotNull($default, 'The default locale row must exist alongside the new translation.');
    $this->assertSame('Send message', $default->submit_label);
  }

  #[Test]
  public function a_contact_form_translation_is_readable_after_it_is_written(): void
  {
    // Nothing could translate a field it could not first read back.
    $block = $this->seedBlock('contact_form');

    $this->patchBlock($block, ['locale' => 'de', 'translations' => ['submit_label' => 'Nachricht senden']]);

    $payload = app(InternalContentResourceController::class)->block($block->fresh())->getData(true);
    $rows = collect($payload['block']['translations']['contact_form'] ?? []);

    $this->assertNotEmpty($rows, 'Contact form translations must be exposed on block reads.');
    $this->assertContains('Nachricht senden', $rows->pluck('submit_label')->all());
  }

  #[Test]
  public function patching_a_button_writes_the_button_translation_row(): void
  {
    $block = $this->seedBlock('button');

    $this->patchBlock($block, ['locale' => 'de', 'translations' => ['title' => 'Mehr erfahren']]);

    $translation = BlockButtonTranslation::query()
      ->where('block_id', $block->id)
      ->where('locale_id', $this->locale('de')->id)
      ->first();

    $this->assertNotNull($translation);
    $this->assertSame('Mehr erfahren', $translation->title);
    $this->assertSame(0, BlockTextTranslation::query()->where('block_id', $block->id)->count());
  }

  #[Test]
  public function patching_a_text_block_still_writes_the_text_translation_row(): void
  {
    $block = $this->seedBlock('header');

    $this->patchBlock($block, ['locale' => 'de', 'translations' => ['title' => 'Überschrift', 'subtitle' => 'Untertitel']]);

    $translation = BlockTextTranslation::query()
      ->where('block_id', $block->id)
      ->where('locale_id', $this->locale('de')->id)
      ->first();

    $this->assertNotNull($translation);
    $this->assertSame('Überschrift', $translation->title);
    $this->assertSame('Untertitel', $translation->subtitle);
  }

  #[Test]
  public function patching_an_image_accepts_the_translation_column_names(): void
  {
    $block = $this->seedBlock('image');

    $this->patchBlock($block, ['locale' => 'de', 'translations' => ['caption' => 'Bildunterschrift', 'alt_text' => 'Ein Foto']]);

    $translation = BlockImageTranslation::query()
      ->where('block_id', $block->id)
      ->where('locale_id', $this->locale('de')->id)
      ->first();

    $this->assertNotNull($translation);
    $this->assertSame('Bildunterschrift', $translation->caption);
    $this->assertSame('Ein Foto', $translation->alt_text);
  }

  #[Test]
  public function a_field_the_family_does_not_own_is_refused_instead_of_stored(): void
  {
    $block = $this->seedBlock('contact_form');

    $response = $this->patchBlock($block, ['translations' => ['subtitle' => 'Nope']]);
    $payload = $response->getData(true);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('unsupported_block_translation_fields', $payload['code']);
    $this->assertStringContainsString('submit_label', $payload['message']);
    $this->assertSame(0, BlockTextTranslation::query()->where('block_id', $block->id)->count());
  }

  #[Test]
  public function a_foreign_family_envelope_is_refused(): void
  {
    $block = $this->seedBlock('header');

    $response = $this->patchBlock($block, ['translations' => ['image' => ['alt_text' => 'Nope']]]);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('unsupported_block_translation_fields', $response->getData(true)['code']);
  }

  #[Test]
  public function a_block_type_without_a_translation_family_refuses_translations(): void
  {
    $block = $this->seedBlock('section');

    $response = $this->patchBlock($block, ['translations' => ['title' => 'Nope']]);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('unsupported_block_translations', $response->getData(true)['code']);
    $this->assertSame(0, BlockTextTranslation::query()->where('block_id', $block->id)->count());
  }

  #[Test]
  public function an_empty_value_the_writer_would_ignore_is_refused(): void
  {
    // The writer reads an empty submit_label as "not submitted" and keeps the
    // stored text, so accepting one would report success and change nothing.
    $block = $this->seedBlock('contact_form');

    $response = $this->patchBlock($block, ['locale' => 'de', 'translations' => ['submit_label' => '  ']]);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('empty_block_translation_field', $response->getData(true)['code']);
  }

  #[Test]
  public function a_locale_the_site_does_not_publish_is_reported_as_a_validation_error(): void
  {
    // The translation models throw on this, which would surface as a 500.
    $block = $this->seedBlock('contact_form');
    Locale::query()->firstOrCreate(['code' => 'fr'], ['name' => 'French', 'is_default' => false, 'is_enabled' => true]);

    $response = $this->patchBlock($block, ['locale' => 'fr', 'translations' => ['submit_label' => 'Envoyer']]);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('invalid_block_translation_locale', $response->getData(true)['code']);
  }

  private function patchBlock(Block $block, array $payload): JsonResponse
  {
    $request = Request::create('/webadmin/api/blocks/'.$block->id, 'PATCH', [], [], [], [], json_encode($payload));
    $request->headers->set('Content-Type', 'application/json');

    return app(InternalContentResourceController::class)->updateBlock($request, $block);
  }

  private function locale(string $code): Locale
  {
    return Locale::query()->where('code', $code)->firstOrFail();
  }

  private function seedBlock(string $slug, array $attributes = []): Block
  {
    $site = Site::query()->firstOrCreate(['handle' => 'test'], ['name' => 'Test', 'is_primary' => true]);
    $english = Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $german = Locale::query()->firstOrCreate(['code' => 'de'], ['name' => 'German', 'is_default' => false, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$english->id => ['is_enabled' => true], $german->id => ['is_enabled' => true]]);
    $slotType = SlotType::query()->firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->firstOrCreate(['site_id' => $site->id, 'slug' => 'home'], ['status' => Page::STATUS_DRAFT]);
    PageSlot::query()->firstOrCreate(['page_id' => $page->id, 'slot_type_id' => $slotType->id], ['sort_order' => 0]);
    $blockType = BlockType::query()->firstOrCreate(['slug' => $slug], [
      'name' => str($slug)->headline()->toString(), 'category' => 'content', 'source_type' => 'static',
      'is_system' => false, 'is_container' => false, 'sort_order' => 0, 'status' => 'published',
    ]);

    return Block::query()->create([
      'page_id' => $page->id, 'type' => $slug, 'block_type_id' => $blockType->id,
      'source_type' => 'static', 'slot' => $slotType->slug, 'slot_type_id' => $slotType->id,
      'sort_order' => 0, 'status' => 'published',
    ] + $attributes);
  }
}
