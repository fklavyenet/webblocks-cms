<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WebBlocks\Cms\Http\Controllers\Admin\SharedSlotController;
use WebBlocks\Cms\Http\Requests\Admin\BlockRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Tests\TestCase;

/**
 * A Navbar Brand may only be *placed* under a Navbar, but a site's footer can
 * hold one that an importer or content plan put there, and it renders publicly
 * every day. The admin re-ran the placement rule on every save, so that block
 * could never have its own text edited again: the parent select had no option
 * it was allowed to offer, fell back to "no parent", and the save was rejected
 * as if the editor had asked to detach it.
 *
 * Editing a field is not a placement. These tests hold that line from both
 * ends: an unmoved block saves, a moved one is still checked.
 */
class BlockEditPlacementTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function a_block_saves_while_it_stays_where_it_already_is(): void
  {
    [$brand, $container] = $this->seedBrandInsidePlainContainer();

    $errors = $this->validationErrors($brand, ['parent_id' => $container->id]);

    $this->assertSame([], $errors, 'Saving a block that is not moving must not be treated as a placement.');
  }

  #[Test]
  public function moving_the_block_somewhere_new_is_still_refused(): void
  {
    [$brand] = $this->seedBrandInsidePlainContainer();
    $otherContainer = $this->seedBlock('container', $brand->page_id, $brand->slot_type_id);

    $errors = $this->validationErrors($brand, ['parent_id' => $otherContainer->id]);

    $this->assertArrayHasKey('parent_id', $errors);
    $this->assertStringContainsString('Navbar', $errors['parent_id'][0]);
  }

  #[Test]
  public function changing_the_block_type_in_place_is_still_checked(): void
  {
    // Same parent, different type: that is a placement question again, and a
    // card header belongs under a card rather than a plain container.
    [$brand] = $this->seedBrandInsidePlainContainer();
    $cardHeader = $this->seedBlockType('card_header');

    $errors = $this->validationErrors($brand, ['block_type_id' => $cardHeader->id]);

    $this->assertArrayHasKey('parent_id', $errors);
  }

  #[Test]
  public function the_parent_select_still_offers_the_block_its_own_parent(): void
  {
    // Without this the form silently posts "no parent" and the save fails on a
    // detach the editor never asked for.
    [$brand, $container] = $this->seedBrandInsidePlainContainer();
    $blocks = Block::query()->where('page_id', $brand->page_id)->with('children')->get();

    $method = new ReflectionMethod(SharedSlotController::class, 'slotParentBlocks');
    $options = $method->invoke($this->app->make(SharedSlotController::class), $blocks, $brand);

    $this->assertContains($container->id, collect($options)->pluck('id')->all());
  }

  /**
   * @return array<string, list<string>>
   */
  private function validationErrors(Block $block, array $overrides = []): array
  {
    $payload = [
      'page_id' => $block->page_id,
      'parent_id' => $block->parent_id,
      'block_type_id' => $block->block_type_id,
      'slot_type_id' => $block->slot_type_id,
      'sort_order' => $block->sort_order,
      'status' => 'published',
      'title' => 'fklavye.net',
    ] + $overrides;
    $payload = $overrides + $payload;

    $request = BlockRequest::create('/webadmin/blocks/'.$block->id, 'PUT', $payload);
    $request->setContainer($this->app);

    $route = new Route(['PUT'], '/webadmin/blocks/{block}', []);
    $route->bind($request);
    $route->setParameter('block', $block);
    $request->setRouteResolver(fn () => $route);

    $validator = (new ReflectionMethod(BlockRequest::class, 'getValidatorInstance'))->invoke($request);

    return $validator->errors()->toArray();
  }

  /**
   * @return array{0: Block, 1: Block}
   */
  private function seedBrandInsidePlainContainer(): array
  {
    $site = Site::query()->firstOrCreate(['handle' => 'test'], ['name' => 'Test', 'is_primary' => true]);
    $locale = Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    $slotType = SlotType::query()->firstOrCreate(['slug' => 'footer'], ['name' => 'Footer', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->firstOrCreate(['site_id' => $site->id, 'slug' => 'home'], ['status' => Page::STATUS_DRAFT]);
    PageSlot::query()->firstOrCreate(['page_id' => $page->id, 'slot_type_id' => $slotType->id], ['sort_order' => 0]);

    $container = $this->seedBlock('container', $page->id, $slotType->id);
    $brand = $this->seedBlock('navbar-brand', $page->id, $slotType->id, $container->id);

    return [$brand, $container];
  }

  private function seedBlockType(string $slug): BlockType
  {
    return BlockType::query()->firstOrCreate(['slug' => $slug], [
      'name' => str($slug)->headline()->toString(),
      'category' => 'layout',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => $slug === 'container',
      'sort_order' => 0,
      'status' => 'published',
    ]);
  }

  private function seedBlock(string $slug, int $pageId, int $slotTypeId, ?int $parentId = null): Block
  {
    $blockType = $this->seedBlockType($slug);

    return Block::query()->create([
      'page_id' => $pageId,
      'parent_id' => $parentId,
      'type' => $slug,
      'block_type_id' => $blockType->id,
      'source_type' => 'static',
      'slot' => 'footer',
      'slot_type_id' => $slotTypeId,
      'sort_order' => 0,
      'status' => 'published',
    ]);
  }
}
