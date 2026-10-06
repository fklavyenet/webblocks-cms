<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Http\Requests\Admin\BlockRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use WebBlocks\Cms\Tests\TestCase;

class SliderContentPositionTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  public static function middlePositions(): array
  {
    return [
      'slider center left' => ['slider', 'center-left', 'wb-slider-content-start'],
      'slider center right' => ['slider', 'center-right', 'wb-slider-content-end'],
      'slide center left' => ['slide', 'center-left', 'wb-slider-content-start'],
      'slide center right' => ['slide', 'center-right', 'wb-slider-content-end'],
    ];
  }

  #[Test]
  #[DataProvider('middlePositions')]
  public function an_admin_selection_survives_save_and_public_rendering(string $type, string $position, string $class): void
  {
    [$page, $slot, $types, $parent] = $this->seedContext();
    $this->app->instance(AdminAuthorization::class, new class extends AdminAuthorization
    {
      public function normalizeAllowedMediaId($user, ?int $mediaId): ?int
      {
        return null;
      }

      public function filterAllowedMediaIds($user, array $mediaIds): array
      {
        return [];
      }
    });
    $request = BlockRequest::create('/webadmin/blocks', 'POST', array_filter([
      'page_id' => $page->id, 'slot_type_id' => $slot->id, 'block_type_id' => $types[$type]->id,
      'parent_id' => $type === 'slide' ? $parent->id : null,
      'sort_order' => 0, 'status' => 'published', $type.'_content_position' => $position,
    ], fn ($value) => $value !== null));
    $request->setContainer($this->app);
    $request->setRouteResolver(fn () => null);
    $request->validateResolved();
    $block = app(BlockPayloadWriter::class)->save(new Block, $page, $request->validatedData());
    $this->assertSame($position, $block->fresh()->setting('content_position'));
    $block->setRelation('children', collect());
    $html = view('webblocks-cms::pages.partials.blocks.'.$type, ['block' => $block])->render();
    $this->assertStringContainsString($class, $html);
    $form = view('webblocks-cms::admin.blocks.settings.'.$type, ['block' => $block])->render();
    $this->assertMatchesRegularExpression('/value="'.preg_quote($position, '/').'"\s+selected/', $form);
  }

  #[Test]
  #[DataProvider('middlePositions')]
  public function content_plans_and_existing_block_updates_keep_middle_positions(string $type, string $position, string $class): void
  {
    [$page, $slot, , $parent] = $this->seedContext();
    $operations = app(InternalContentApiOperations::class);
    $errors = $warnings = [];
    $normalized = $operations->normalizeBlock([
      'type' => $type, 'settings' => ['content_position' => $position],
      'children' => $type === 'slider' ? [['type' => 'slide']] : [],
    ], 'block', null, $errors, $warnings);
    $this->assertSame([], $errors);
    $block = $operations->createPageSlotBlock($page, $slot, $normalized, 'en', $type === 'slide' ? $parent : null, 0);
    $this->assertSame($position, $block->setting('content_position'));

    $block->update(['settings' => json_encode(['content_position' => 'top-left'])]);
    $response = app(InternalContentResourceController::class)->updateBlock(Request::create('/', 'PATCH', [
      'settings' => ['content_position' => $position],
    ]), $block);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame($position, $response->getData(true)['block']['settings']['content_position']);
    $this->assertSame($class, $block->fresh()->sliderContentPositionClass());
  }

  #[Test]
  public function slides_inherit_a_middle_position_and_can_override_it(): void
  {
    [, , , $slider] = $this->seedContext();
    $slider->settings = ['content_position' => 'center-left'];
    $slide = new Block(['type' => 'slide']);
    $slide->setRelation('parent', $slider);
    $slide->setRelation('children', collect());
    $html = view('webblocks-cms::pages.partials.blocks.slide', ['block' => $slide])->render();
    $this->assertStringContainsString('wb-slider-content-start', $html);

    $slide->settings = ['content_position' => 'center-right'];
    $html = view('webblocks-cms::pages.partials.blocks.slide', ['block' => $slide])->render();
    $this->assertStringContainsString('wb-slider-content-end', $html);
    $this->assertStringNotContainsString('wb-slider-content-start', $html);
  }

  #[Test]
  public function both_settings_forms_expose_nine_positions_with_translated_labels(): void
  {
    $this->seedContext();
    foreach (['slider', 'slide'] as $type) {
      $html = view('webblocks-cms::admin.blocks.settings.'.$type, ['block' => new Block(['type' => $type])])->render();
      preg_match('/<select[^>]+name="'.$type.'_content_position"[^>]*>(.*?)<\/select>/s', $html, $match);
      $this->assertSame(9, substr_count($match[1], '<option'));
      foreach (['en', 'tr', 'de', 'fr', 'es', 'it'] as $locale) {
        foreach (['center_left', 'center_right'] as $key) {
          $translationKey = 'admin.blocks.'.$type.'_settings.'.$key;
          $this->assertNotSame($translationKey, app(CmsTranslator::class)->get($translationKey, $locale));
        }
      }
    }
  }

  private function seedContext(): array
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $slot = SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'home', 'status' => Page::STATUS_DRAFT]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'sort_order' => 0]);
    $types = [];
    foreach (['slider', 'slide'] as $type) {
      $types[$type] = BlockType::query()->create([
        'name' => ucfirst($type), 'slug' => $type, 'category' => 'content',
        'source_type' => 'static', 'is_container' => true, 'sort_order' => 0, 'status' => 'published',
      ]);
    }
    $slider = Block::query()->create([
      'page_id' => $page->id, 'block_type_id' => $types['slider']->id, 'type' => 'slider',
      'source_type' => 'static', 'slot_type_id' => $slot->id, 'slot' => 'main',
      'sort_order' => 0, 'status' => 'published',
    ]);

    return [$page, $slot, $types, $slider];
  }
}
