<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeContractRegistry;
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

  private function mergeSettings(Block $block, array $incoming): array
  {
    $request = Request::create('/webadmin/api/blocks/'.$block->id, 'PATCH', ['settings' => $incoming]);
    $method = new ReflectionMethod(InternalContentResourceController::class, 'mergeSettings');

    return $method->invoke(app(InternalContentResourceController::class), $block, $request);
  }

  private function seedBlock(string $slug, array $settings = []): Block
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $slotType = SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'home', 'status' => Page::STATUS_DRAFT]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slotType->id, 'sort_order' => 0]);
    $blockType = BlockType::query()->create([
      'name' => 'Link List', 'slug' => $slug, 'category' => 'navigation', 'source_type' => 'static',
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
