<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Blocks\SetBlockMobileMedia;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Http\Requests\Admin\BlockRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\Blocks\MobileBlockMedia;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeContractRegistry;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Support\Media\MediaUsageResolver;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteExportDataBuilder;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use WebBlocks\Cms\Tests\TestCase;

class MobileBlockMediaTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  public static function supportedTypes(): array
  {
    return array_combine(MobileBlockMedia::BLOCK_TYPES, array_map(fn ($slug) => [$slug], MobileBlockMedia::BLOCK_TYPES));
  }

  #[Test]
  #[DataProvider('supportedTypes')]
  public function admin_can_save_preserve_replace_and_remove_a_mobile_image(string $type): void
  {
    [$page, $slot, $blockType, $parent] = $this->seedContext($type);
    $desktop = $this->seedImage('desktop');
    $mobile = $this->seedImage('mobile');
    $replacement = $this->seedImage('replacement');
    $payload = [
      'page_id' => $page->id, 'slot_type_id' => $slot->id,
      'block_type_id' => $blockType->id, 'parent_id' => $parent?->id,
      'sort_order' => 0, 'status' => 'published', 'title' => 'Example',
      'url' => $type === 'link-list-item' ? '/guide' : null,
      'media_id' => $desktop->id, 'mobile_media_id' => $mobile->id,
    ];

    $writer = app(BlockPayloadWriter::class);
    $block = $writer->save(new Block, $page, $this->adminData($payload));
    $this->assertSame($mobile->id, $block->mobileMedia()?->id);
    $this->assertSame($desktop->id, $block->media_id);

    // Other edits (including locale saves) must preserve an omitted mobile field.
    unset($payload['mobile_media_id']);
    $writer->save($block, $page, $this->adminData($payload, $block));
    $this->assertSame($mobile->id, $block->mobileMedia()?->id);

    $payload['mobile_media_id'] = $replacement->id;
    $writer->save($block, $page, $this->adminData($payload, $block));
    $this->assertSame($replacement->id, $block->mobileMedia()?->id);
    $this->assertSame(1, $block->blockMedia()->where('role', MobileBlockMedia::ROLE)->count());

    $payload['mobile_media_id'] = '';
    $writer->save($block, $page, $this->adminData($payload, $block));
    $this->assertNull($block->mobileMedia());
    $this->assertSame($desktop->id, $block->fresh()->media_id);
  }

  #[Test]
  #[DataProvider('supportedTypes')]
  public function content_plan_and_patch_round_trip_the_mobile_image(string $type): void
  {
    [$page, $slot, , $parent] = $this->seedContext($type);
    $desktop = $this->seedImage('desktop');
    $mobile = $this->seedImage('mobile');
    $replacement = $this->seedImage('replacement');
    $operations = app(InternalContentApiOperations::class);
    $errors = $warnings = [];
    if (in_array($type, ['section', 'card'], true)) {
      $this->seedContext('plain_text');
      if ($type === 'card') {
        $this->seedContext('card_body');
      }
    }
    $normalized = $operations->normalizeBlock([
      'type' => $type, 'media_id' => $desktop->id, 'mobile_media_id' => $mobile->id,
      'children' => $type === 'card' ? [['type' => 'card_body', 'children' => [['type' => 'plain_text', 'translations' => ['content' => 'Example']]]]] : ($type === 'section' ? [['type' => 'plain_text', 'translations' => ['content' => 'Example']]] : []),
    ], 'block', null, $errors, $warnings);
    $this->assertSame([], $errors);
    $block = $operations->createPageSlotBlock($page, $slot, $normalized, 'en', $parent, 0);
    $this->assertSame($mobile->id, $block->mobileMedia()?->id);

    $controller = app(InternalContentResourceController::class);
    $response = $controller->updateBlock(Request::create('/', 'PATCH', ['mobile_media_id' => $replacement->id]), $block);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame($replacement->id, $response->getData(true)['block']['mobile_media_id']);
    $this->assertSame($replacement->id, $response->getData(true)['block']['mobile_media']['id']);
    $this->assertSame($desktop->id, $block->fresh()->media_id);

    $response = $controller->updateBlock(Request::create('/', 'PATCH', []), $block);
    $this->assertSame($replacement->id, $response->getData(true)['block']['mobile_media_id']);
    $response = $controller->updateBlock(Request::create('/', 'PATCH', ['mobile_media_id' => null]), $block);
    $this->assertNull($response->getData(true)['block']['mobile_media_id']);
    $this->assertNull($response->getData(true)['block']['mobile_media']);
  }

  #[Test]
  #[DataProvider('supportedTypes')]
  public function public_renderers_select_mobile_images_and_keep_the_default(string $type): void
  {
    [$page, $slot, $blockType, $parent] = $this->seedContext($type);
    $desktop = $this->seedImage('desktop');
    $mobile = $this->seedImage('mobile', 600, 900);
    $block = $this->seedBlock($page, $slot, $blockType, $desktop, $parent);
    $defaultHtml = $this->render($block);
    $this->assertStringContainsString('media/desktop.jpg', $defaultHtml);
    $this->assertStringNotContainsString('<picture>', $defaultHtml);
    $this->assertStringNotContainsString('--wb-background-media-mobile-image', $defaultHtml);

    app(SetBlockMobileMedia::class)->execute($block, $mobile->id);
    $html = $this->render($block);
    $this->assertStringContainsString('media/mobile.jpg', $html);
    $this->assertStringContainsString('media/desktop.jpg', $html);
    if (in_array($type, ['slide', 'image', 'link-list-item'], true)) {
      $this->assertStringContainsString('<picture>', $html);
      $this->assertStringContainsString('media="(max-width: 768px)"', $html);
      $this->assertMatchesRegularExpression('/<source[^>]*width="600"[^>]*height="900"/s', $html);
    } else {
      $this->assertStringContainsString('--wb-background-media-mobile-image:', $html);
    }

    $mobile->update(['visibility' => 'private']);
    $block->unsetRelation('blockAssets');
    $this->assertSame($defaultHtml, $this->render($block));
    $mobile->delete();
    $block->unsetRelation('blockAssets');
    $this->assertSame($defaultHtml, $this->render($block));
  }

  #[Test]
  public function split_hero_uses_a_picture_and_does_not_paint_a_background(): void
  {
    [$page, $slot, $type] = $this->seedContext('hero');
    $block = $this->seedBlock($page, $slot, $type, $this->seedImage('desktop'));
    $block->settings = ['layout' => 'split'];
    app(SetBlockMobileMedia::class)->execute($block, $this->seedImage('mobile')->id);
    $html = $this->render($block);
    $this->assertStringContainsString('<picture>', $html);
    $this->assertStringContainsString('media/mobile.jpg', $html);
    $this->assertStringNotContainsString('wb-background-media', $html);
  }

  #[Test]
  public function invalid_assignments_are_refused_by_plans_and_patch_without_mutation(): void
  {
    [$page, $slot, $type] = $this->seedContext('image');
    $desktop = $this->seedImage('desktop');
    $mobile = $this->seedImage('mobile');
    $video = $this->seedImage('video');
    $video->update(['kind' => Media::KIND_VIDEO]);
    $block = $this->seedBlock($page, $slot, $type, $desktop);
    app(SetBlockMobileMedia::class)->execute($block, $mobile->id);
    foreach ([$video->id, 999999, '1invalid', [$mobile->id]] as $invalid) {
      $errors = $warnings = [];
      app(InternalContentApiOperations::class)->normalizeBlock([
        'type' => 'image', 'mobile_media_id' => $invalid,
      ], 'block', null, $errors, $warnings);
      $this->assertSame('block.mobile_media_id', $errors[0]['path']);
      $response = app(InternalContentResourceController::class)->updateBlock(Request::create('/', 'PATCH', [
        'mobile_media_id' => $invalid, 'url' => '/must-not-change',
      ]), $block);
      $this->assertSame(422, $response->getStatusCode());
      $this->assertSame($mobile->id, $block->fresh()->mobileMedia()?->id);
      $this->assertSame('/guide', $block->fresh()->url);
    }

    [$page, $slot, $type] = $this->seedContext('plain_text');
    $text = $this->seedBlock($page, $slot, $type, null);
    $response = app(InternalContentResourceController::class)->updateBlock(Request::create('/', 'PATCH', [
      'mobile_media_id' => $mobile->id,
    ]), $text);
    $this->assertSame(422, $response->getStatusCode());
    $errors = $warnings = [];
    app(InternalContentApiOperations::class)->normalizeBlock([
      'type' => 'plain_text', 'mobile_media_id' => $mobile->id,
    ], 'block', null, $errors, $warnings);
    $this->assertSame('block.mobile_media_id', $errors[0]['path']);
  }

  #[Test]
  public function admin_refuses_non_image_mobile_media(): void
  {
    [$page, $slot, $type] = $this->seedContext('image');
    $video = $this->seedImage('video');
    $video->update(['kind' => Media::KIND_VIDEO]);
    $this->expectException(ValidationException::class);
    $this->adminData([
      'page_id' => $page->id, 'slot_type_id' => $slot->id, 'block_type_id' => $type->id,
      'sort_order' => 0, 'status' => 'published', 'mobile_media_id' => $video->id,
    ]);
  }

  #[Test]
  public function mobile_media_is_tracked_exported_and_preserved_in_revision_candidates(): void
  {
    [$page, $slot, $type] = $this->seedContext('image');
    $desktop = $this->seedImage('desktop');
    $mobile = $this->seedImage('mobile');
    $block = $this->seedBlock($page, $slot, $type, $desktop);
    app(SetBlockMobileMedia::class)->execute($block, $mobile->id);
    RouteFacade::get('/webadmin/blocks/{block}/edit', fn () => '')->name('admin.blocks.edit');
    $this->assertTrue(app(MediaUsageResolver::class)->isUsed($mobile));

    $export = app(SiteExportDataBuilder::class)->build($page->site, true);
    $this->assertContains($mobile->id, array_column($export['media'], 'id'));
    $row = collect($export['block_media'])->firstWhere('role', MobileBlockMedia::ROLE);
    $this->assertSame($mobile->id, $row['media_id']);

    $manager = app(PageRevisionManager::class);
    $snapshot = $manager->snapshotForInspection($page);
    $candidate = Page::query()->create(['site_id' => $page->site_id, 'slug' => 'candidate', 'status' => Page::STATUS_DRAFT]);
    $manager->applySnapshotToCandidate($candidate, $snapshot, ['source_page_id' => $page->id, 'revision_id' => 1]);
    $this->assertSame($mobile->id, $candidate->blocks()->first()->mobileMedia()?->id);
  }

  #[Test]
  #[DataProvider('supportedTypes')]
  public function mobile_media_is_discoverable_in_block_contracts_and_admin_forms(string $type): void
  {
    $this->seedContext($type);
    $contract = app(BlockTypeContractRegistry::class)->resolve($type)->toAuditArray();
    $this->assertContains('mobile_media_id', $contract['shared_settings_fields']);
    $this->assertStringContainsString('768px', $contract['renderer_root_contract']);
    $html = view('webblocks-cms::admin.blocks.types.'.$type, [
      'block' => new Block(['type' => $type]), 'selectedMobileAsset' => $this->seedImage('mobile'),
    ])->render();
    $this->assertStringContainsString('name="mobile_media_id"', $html);
    $this->assertStringContainsString('Mobile image (optional)', $html);
  }

  private function render(Block $block): string
  {
    request()->attributes->remove('_wb_content_header_count');
    $block->setRelation('children', collect());

    return view('webblocks-cms::pages.partials.blocks.'.$block->typeSlug(), ['block' => $block])->render();
  }

  private function adminData(array $payload, ?Block $block = null): array
  {
    $this->app->instance(AdminAuthorization::class, new class extends AdminAuthorization
    {
      public function normalizeAllowedMediaId($user, ?int $mediaId): ?int
      {
        return $mediaId > 0 ? $mediaId : null;
      }

      public function filterAllowedMediaIds($user, array $mediaIds): array
      {
        return array_values(array_filter(array_map('intval', $mediaIds), fn ($id) => $id > 0));
      }
    });
    $route = new Route(['POST'], '/webadmin/blocks/{block}', fn () => null);
    $route->bind(Request::create('/webadmin/blocks/'.($block?->id ?? 0)));
    $route->setParameter('block', $block);
    $request = BlockRequest::create('/webadmin/blocks', 'POST', array_filter($payload, fn ($value) => $value !== null));
    $request->setContainer($this->app);
    $request->setRedirector($this->app['redirect']);
    $request->setRouteResolver(fn () => $route);
    $request->validateResolved();

    return $request->validatedData();
  }

  private function seedImage(string $name, int $width = 1440, int $height = 900): Media
  {
    return Media::query()->create([
      'disk' => 'public', 'path' => 'media/'.$name.'.jpg', 'filename' => $name.'.jpg',
      'mime_type' => 'image/jpeg', 'kind' => Media::KIND_IMAGE, 'visibility' => 'public',
      'width' => $width, 'height' => $height,
    ]);
  }

  private function seedContext(string $slug): array
  {
    $site = Site::query()->firstOrCreate(['handle' => 'test'], ['name' => 'Test', 'is_primary' => true]);
    Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $slot = SlotType::query()->firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'home-'.Page::query()->count(), 'status' => Page::STATUS_DRAFT]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'sort_order' => 0]);
    $type = BlockType::query()->firstOrCreate(['slug' => $slug], [
      'name' => ucfirst($slug), 'category' => 'content', 'source_type' => 'static',
      'is_system' => false, 'is_container' => in_array($slug, ['hero', 'section', 'card', 'cta', 'content_header', 'slide', 'slider']),
      'sort_order' => 0, 'status' => 'published',
    ]);
    $parent = null;
    if ($slug === 'slide') {
      $sliderType = BlockType::query()->firstOrCreate(['slug' => 'slider'], [
        'name' => 'Slider', 'category' => 'content', 'source_type' => 'static',
        'is_container' => true, 'sort_order' => 0, 'status' => 'published',
      ]);
      $parent = $this->seedBlock($page, $slot, $sliderType, null);
    }

    return [$page, $slot, $type, $parent];
  }

  private function seedBlock(Page $page, SlotType $slot, BlockType $type, ?Media $media, ?Block $parent = null): Block
  {
    return Block::query()->create([
      'page_id' => $page->id, 'block_type_id' => $type->id, 'type' => $type->slug,
      'source_type' => 'static', 'slot' => $slot->slug, 'slot_type_id' => $slot->id,
      'parent_id' => $parent?->id, 'status' => 'published', 'sort_order' => 0,
      'media_id' => $media?->id, 'title' => 'Example', 'url' => '/guide',
    ]);
  }
}
