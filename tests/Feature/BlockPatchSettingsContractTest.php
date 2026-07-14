<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeContractRegistry;
use WebBlocks\Cms\Support\InternalContentApi\BlockSettingsPatchPolicy;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The existing-block PATCH endpoint keeps its own hand-written allowlist of
 * settings fields, separate from the contract registry that the published
 * content contract is built from. When the two drift, the contract advertises a
 * field the API then refuses, which is how the Link List styles shipped in
 * 1.40.10 as unwritable through the API.
 *
 * The allowlist is only the gate: a field also needs its own sanitizer, or it
 * passes the gate and is silently dropped instead of stored.
 */
class BlockPatchSettingsContractTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function patching_a_link_list_stores_the_style_settings(): void
  {
    $block = $this->seedBlock('link-list');

    $settings = $this->mergeSettings($block, ['row_layout' => 'stacked', 'list_frame' => 'cards']);

    $this->assertSame('stacked', $settings['row_layout'] ?? null);
    $this->assertSame('cards', $settings['list_frame'] ?? null);
  }

  #[Test]
  public function patching_a_link_list_can_return_it_to_the_default_style(): void
  {
    $block = $this->seedBlock('link-list', ['row_layout' => 'stacked', 'list_frame' => 'cards']);

    $settings = $this->mergeSettings($block, ['row_layout' => 'index', 'list_frame' => 'joined']);

    $this->assertArrayNotHasKey('row_layout', $settings);
    $this->assertArrayNotHasKey('list_frame', $settings);
  }

  #[Test]
  public function patching_a_link_list_drops_unknown_style_values(): void
  {
    $block = $this->seedBlock('link-list');

    $settings = $this->mergeSettings($block, ['row_layout' => 'sideways', 'list_frame' => 'bubbles']);

    $this->assertArrayNotHasKey('row_layout', $settings);
    $this->assertArrayNotHasKey('list_frame', $settings);
  }

  #[Test]
  public function patching_still_rejects_a_field_the_block_type_does_not_support(): void
  {
    // Opening the two Link List styles must not open the gate generally: a
    // Contact Form recipient is exactly what this allowlist exists to refuse.
    $block = $this->seedBlock('link-list');

    try {
      $this->mergeSettings($block, ['recipient_email' => 'someone@example.com']);
      $this->fail('An unsupported settings field must be rejected.');
    } catch (HttpResponseException $exception) {
      $response = $exception->getResponse();
      $payload = json_decode((string) $response->getContent(), true);

      $this->assertSame(422, $response->getStatusCode());
      $this->assertSame('unsupported_block_settings_fields', $payload['code'] ?? null);
      $this->assertSame(['settings.recipient_email'], $payload['blocked_fields'] ?? null);
    }
  }

  #[Test]
  public function the_link_list_styles_the_contract_advertises_are_patchable(): void
  {
    // Regression: the published contract listed these two fields while the PATCH
    // allowlist rejected them with unsupported_block_settings_fields, so an API
    // client was told to use a field the API refused.
    $block = $this->seedBlock('link-list');
    $advertised = collect(app(BlockTypeContractRegistry::class)->resolve($block->blockType)->sharedSettingsFields)
      ->filter(fn (string $field) => str_starts_with($field, 'settings.'))
      ->map(fn (string $field) => substr($field, strlen('settings.')))
      ->values()
      ->all();

    $this->assertSame(['row_layout', 'list_frame'], $advertised);
    $settings = $this->mergeSettings($block, ['row_layout' => 'stacked', 'list_frame' => 'cards']);

    foreach ($advertised as $field) {
      $this->assertArrayHasKey($field, $settings, 'The contract advertises settings.'.$field.', so PATCH must accept it.');
    }
  }

  #[Test]
  public function patching_an_icon_block_delegates_to_the_shared_icon_normalizers(): void
  {
    // Regression: an existing block's icon could not be changed through the API
    // at all. The create path normalized icons since 1.40.7, but PATCH refused
    // the fields, so the contract advertised icons the endpoint rejected.
    $this->seedIcon();
    $block = $this->seedBlock('link-list-item');

    $settings = $this->mergeSettings($block, [
      'icon_slug' => 'Rocket',
      'icon_tone' => 'brand',
      'badge_tone' => 'success',
    ]);

    $this->assertSame('rocket', $settings['icon_slug'] ?? null, 'The slug must be normalized by the shared owner.');
    $this->assertSame('brand', $settings['icon_tone'] ?? null);
    $this->assertSame('success', $settings['badge_tone'] ?? null);
  }

  #[Test]
  public function patching_rejects_an_icon_the_catalog_does_not_have(): void
  {
    $this->seedIcon();
    $block = $this->seedBlock('link-list-item');

    try {
      $this->mergeSettings($block, ['icon_slug' => 'not-a-real-icon']);
      $this->fail('An unknown icon must be rejected rather than stored.');
    } catch (HttpResponseException $exception) {
      $this->assertSame(422, $exception->getResponse()->getStatusCode());
    }
  }

  #[Test]
  public function patching_a_hero_stores_the_layout(): void
  {
    // The split layout shipped in 1.40.6 but could not be selected on an
    // existing hero through the API.
    $block = $this->seedBlock('hero');

    $settings = $this->mergeSettings($block, ['layout' => 'split', 'title_tag' => 'h2']);

    $this->assertSame('split', $settings['layout'] ?? null);
    $this->assertSame('h2', $settings['title_tag'] ?? null);
  }

  #[Test]
  public function patching_a_hero_drops_a_layout_the_renderer_does_not_know(): void
  {
    $block = $this->seedBlock('hero', ['layout' => 'split']);

    $settings = $this->mergeSettings($block, ['layout' => 'diagonal', 'title_tag' => 'h7']);

    $this->assertArrayNotHasKey('layout', $settings);
    $this->assertArrayNotHasKey('title_tag', $settings);
  }

  #[Test]
  public function patching_a_grid_stores_the_layout_settings(): void
  {
    $block = $this->seedBlock('grid');

    $settings = $this->mergeSettings($block, [
      'columns' => '3',
      'gap' => '6',
      'layout_name' => 'Feature row',
      'alternate_media_text_sections' => true,
      'alternate_start' => 'media_left',
    ]);

    $this->assertSame('3', $settings['columns'] ?? null);
    $this->assertSame('6', $settings['gap'] ?? null);
    $this->assertSame('Feature row', $settings['layout_name'] ?? null);
    $this->assertTrue($settings['alternate_media_text_sections'] ?? false);
    $this->assertSame('media_left', $settings['alternate_start'] ?? null);
  }

  #[Test]
  public function patching_a_grid_drops_values_the_admin_form_cannot_produce(): void
  {
    $block = $this->seedBlock('grid');

    $settings = $this->mergeSettings($block, ['columns' => '7', 'gap' => '99', 'alternate_start' => 'sideways']);

    $this->assertArrayNotHasKey('columns', $settings);
    $this->assertArrayNotHasKey('gap', $settings);
    $this->assertArrayNotHasKey('alternate_start', $settings);
  }

  #[Test]
  public function patching_a_grid_clears_the_alternating_start_when_alternating_is_off(): void
  {
    // The admin drops both together, so an API write must not leave a start
    // behind that nothing reads.
    $block = $this->seedBlock('grid', ['alternate_media_text_sections' => true, 'alternate_start' => 'media_left']);

    $settings = $this->mergeSettings($block, ['alternate_media_text_sections' => false]);

    $this->assertArrayNotHasKey('alternate_media_text_sections', $settings);
    $this->assertArrayNotHasKey('alternate_start', $settings);
  }

  #[Test]
  public function every_contract_settings_field_is_either_patchable_or_explicitly_closed(): void
  {
    // The gate and the published contract are separate hand-written lists, and
    // this is what stops them drifting: a field added to the contract must be
    // made patchable or recorded as closed with a reason. Neither is allowed to
    // happen by omission, which is how 1.40.10 shipped an unwritable setting.
    $undecided = [];

    foreach ($this->contractSettingsFields() as $type => $fields) {
      foreach ($fields as $field) {
        if (BlockSettingsPatchPolicy::isClosed($type, $field)) {
          continue;
        }

        if (! in_array($field, $this->patchableFieldsFor($type), true)) {
          $undecided[] = $type.'.'.$field;
        }
      }
    }

    $this->assertSame([], $undecided, 'These contract fields are neither patchable nor recorded in BlockSettingsPatchPolicy::CLOSED.');
  }

  #[Test]
  public function the_closed_list_only_names_fields_the_contract_declares(): void
  {
    // A stale entry here would quietly excuse a field that no longer exists.
    $contract = $this->contractSettingsFields();
    $stale = [];

    foreach (BlockSettingsPatchPolicy::CLOSED as $type => $fields) {
      foreach (array_keys($fields) as $field) {
        if (! in_array($field, $contract[$type] ?? [], true)) {
          $stale[] = $type.'.'.$field;
        }
      }
    }

    $this->assertSame([], $stale, 'These closed entries name fields the contract no longer declares.');
  }

  #[Test]
  public function the_deliberately_closed_fields_are_about_other_peoples_data(): void
  {
    // Everything else is closed only for want of a sanitizer. These decide
    // where form submissions are delivered and whether they are retained, which
    // is an operator decision rather than a content one.
    $deliberate = [];

    foreach (BlockSettingsPatchPolicy::CLOSED as $type => $fields) {
      foreach ($fields as $field => $reason) {
        if ($reason === BlockSettingsPatchPolicy::CLOSED_DELIBERATE) {
          $deliberate[] = $type.'.'.$field;
        }
      }
    }

    sort($deliberate);

    $this->assertSame([
      // Shows commenter names publicly: other people's data, not presentation.
      'comments.show_author_name',
      'contact_form.recipient_email',
      'contact_form.send_email_notification',
      'contact_form.store_submissions',
    ], $deliberate);
  }

  /**
   * @return array<string, list<string>>
   */
  private function contractSettingsFields(): array
  {
    $registry = app(BlockTypeContractRegistry::class);
    $fields = [];

    foreach ($registry->publishedCoreContracts() as $contract) {
      $declared = collect($contract->sharedSettingsFields)
        ->filter(fn (string $field) => str_starts_with($field, 'settings.'))
        ->map(fn (string $field) => substr($field, strlen('settings.')))
        ->values()
        ->all();

      if ($declared !== []) {
        $fields[$contract->slug] = $declared;
      }
    }

    return $fields;
  }

  /**
   * @return list<string>
   */
  /**
   * The gate and the value rules refuse with different codes, and only the gate
   * decides whether a field is patchable at all. An empty probe value is invalid
   * for some fields on purpose: icon_tone clears with `default`, so `` is a
   * rejected value rather than a rejected field.
   */
  private function patchableFieldsFor(string $type): array
  {
    $block = Block::query()->where('type', $type)->first() ?? $this->seedBlock($type);
    $patchable = [];

    foreach ($this->contractSettingsFields()[$type] ?? [] as $field) {
      try {
        $this->mergeSettings($block, [$field => '']);
        $patchable[] = $field;
      } catch (HttpResponseException $exception) {
        $payload = json_decode((string) $exception->getResponse()->getContent(), true);

        if (($payload['code'] ?? null) !== 'unsupported_block_settings_fields') {
          $patchable[] = $field;
        }
      }
    }

    return $patchable;
  }

  private function seedIcon(): void
  {
    IconCatalogItem::query()->firstOrCreate(['slug' => 'rocket'], [
      'source' => 'webblocks-ui',
      'label' => 'Rocket',
      'css_class' => 'wb-icon-rocket',
      'contexts' => ['content'],
      'is_active' => true,
    ]);
  }

  private function mergeSettings(Block $block, array $incoming): array
  {
    $request = Request::create('/webadmin/api/blocks/'.$block->id, 'PATCH', ['settings' => $incoming]);
    $method = new ReflectionMethod(InternalContentResourceController::class, 'mergeSettings');

    return $method->invoke(app(InternalContentResourceController::class), $block, $request);
  }

  private function seedBlock(string $slug, array $settings = []): Block
  {
    // Idempotent: the contract sweep seeds a block for every declared type.
    $site = Site::query()->firstOrCreate(['handle' => 'test'], ['name' => 'Test', 'is_primary' => true]);
    Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $slotType = SlotType::query()->firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->firstOrCreate(['site_id' => $site->id, 'slug' => 'home'], ['status' => Page::STATUS_DRAFT]);
    PageSlot::query()->firstOrCreate(['page_id' => $page->id, 'slot_type_id' => $slotType->id], ['sort_order' => 0]);
    $blockType = BlockType::query()->firstOrCreate(['slug' => $slug], [
      'name' => str($slug)->headline()->toString(), 'category' => 'navigation', 'source_type' => 'static',
      'is_system' => false, 'is_container' => true, 'sort_order' => 0, 'status' => 'published',
    ]);

    return Block::query()->create([
      'page_id' => $page->id, 'type' => $slug, 'block_type_id' => $blockType->id,
      'source_type' => 'static', 'slot' => $slotType->slug, 'slot_type_id' => $slotType->id,
      'sort_order' => 0, 'status' => 'published',
      'settings' => $settings === [] ? null : json_encode($settings),
    ]);
  }
}
