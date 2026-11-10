<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Tests\TestCase;

class ApiBlockAncestorPlacementTest extends TestCase
{
  private Site $site;

  private SlotType $slot;

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->site = Site::create(['name' => 'Studio', 'handle' => 'studio', 'is_primary' => true]);
    $locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $this->site->locales()->sync([$locale->id => ['is_enabled' => true]]);
    $this->slot = SlotType::create(['name' => 'Main', 'slug' => 'main', 'status' => 'published']);
    $layout = PageLayout::create(['name' => 'Default', 'handle' => 'default', 'is_active' => true]);
    $layout->layoutSlots()->create(['slot_type_id' => $this->slot->id, 'slot_name' => 'main', 'is_active' => true]);
    app(CoreBlockTypeCatalogSyncer::class)->sync();
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic placement editor', capabilities: ['content.apply', 'content.validate', 'content.publish', 'shared-slots.write'])->plainToken;
    $this->withToken($token);
  }

  #[Test]
  public function plans_validate_the_complete_nested_tree(): void
  {
    $brand = ['type' => 'navbar-brand', 'translations' => ['title' => 'Studio']];
    $container = ['type' => 'container', 'children' => [['type' => 'cluster', 'children' => [$brand]]]];
    $plan = ['site' => 'studio', 'locale' => 'en', 'layout' => 'default', 'page' => ['title' => 'Studio', 'path' => '/studio']];
    $invalid = ['plan' => $plan + ['slots' => ['main' => [$container]]]];
    foreach (['validate', 'apply'] as $operation) {
      $this->postJson('/webadmin/api/content/'.$operation, $invalid)->assertUnprocessable()
        ->assertJsonPath('errors.0.path', 'plan.slots.main.0.children.0.children.0.type');
    }
    $this->assertDatabaseEmpty('wbcms_blocks');

    $navbar = ['type' => 'sticky-navbar', 'children' => [$container]];
    $this->postJson('/webadmin/api/content/apply', ['plan' => $plan + ['slots' => ['main' => [$navbar]]]])->assertCreated();
    $this->assertTrue(Block::where('type', 'navbar-brand')->sole()->parent->hasNavbarAncestorOrSelf());
  }

  #[Test]
  public function adding_to_page_and_shared_slots_checks_persisted_ancestors_without_blocking_copy_edits(): void
  {
    $page = $this->page('draft');
    $sharedSlot = SharedSlot::create(['site_id' => $this->site->id, 'handle' => 'shared', 'name' => 'Shared', 'slot_name' => 'main', 'is_active' => true]);
    $sharedPage = app(SharedSlotSourcePageManager::class)->ensureFor($sharedSlot);

    foreach ([[$page, '/webadmin/api/pages/'.$page->id.'/slots/main/blocks'], [$sharedPage, '/webadmin/api/shared-slots/'.$sharedSlot->id.'/blocks']] as [$owner, $url]) {
      $invalid = $this->block($owner, 'plain_text');
      $parent = $this->block($owner, 'container', $invalid);
      $existing = $this->block($owner, 'plain_text', $parent);
      if ($owner->isSharedSlotSourcePage()) {
        app(SharedSlotSourcePageManager::class)->rebuildAssignments($sharedSlot);
      }
      $count = $owner->blocks()->count();

      $this->postJson($url, ['type' => 'plain_text', 'parent_id' => $parent->id, 'translations' => ['content' => 'Rejected']])
        ->assertUnprocessable()->assertJsonPath('errors.0.path', 'block.parent_id');
      $this->assertSame($count, $owner->blocks()->count());
      $this->patchJson('/webadmin/api/blocks/'.$existing->id, ['translations' => ['content' => 'Edited in place']])->assertOk();
      $this->assertSame($parent->id, $existing->fresh()->parent_id);
      $this->assertSame('Edited in place', $existing->textTranslations()->sole()->content);

      $navbar = $this->block($owner, 'sticky-navbar');
      $container = $this->block($owner, 'container', $navbar);
      $this->postJson($url, ['type' => 'navbar-brand', 'parent_id' => $container->id, 'translations' => ['title' => 'Studio']])
        ->assertCreated()->assertJsonPath('block.parent_id', $container->id);
    }
  }

  #[Test]
  public function page_slot_append_rejects_a_foreign_slot_ancestor_and_a_cycle(): void
  {
    $page = $this->page('draft');
    $url = '/webadmin/api/pages/'.$page->id.'/slots/main/blocks';
    $otherSlot = SlotType::create(['name' => 'Aside', 'slug' => 'aside', 'status' => 'published']);
    $ancestor = $this->block($page, 'container');
    $parent = $this->block($page, 'container', $ancestor);
    $payload = ['type' => 'plain_text', 'parent_id' => $parent->id, 'translations' => ['content' => 'Rejected']];

    $ancestor->update(['slot_type_id' => $otherSlot->id, 'slot' => 'aside']);
    $this->postJson($url, $payload)->assertUnprocessable()->assertJsonPath('errors.0.path', 'block.parent_id');

    $page->blocks()->whereKey($ancestor->id)->update(['slot_type_id' => $this->slot->id, 'slot' => 'main', 'parent_id' => $parent->id]);
    $this->postJson($url, $payload)->assertUnprocessable()->assertJsonPath('errors.0.path', 'block.parent_id');
    $this->assertSame(2, $page->blocks()->count());
  }

  #[Test]
  public function promotion_checks_the_descendants_of_a_moved_block_and_preserves_unmoved_legacy_content(): void
  {
    $source = $this->page('published');
    $navbar = $this->block($source, 'sticky-navbar');
    $destination = $this->block($source, 'sticky-navbar');
    $branch = $this->block($source, 'container', $navbar);
    $this->block($source, 'navbar-brand', $branch);
    $legacy = $this->block($source, 'navbar-brand');
    $created = $this->postJson('/webadmin/api/content/apply', ['plan' => [
      'mode' => 'create_staged_update_for_published_page', 'source_page_id' => $source->id,
      'expected_source_path' => '/published', 'managed_slots' => ['main'],
    ]])->assertCreated();
    $draft = Page::findOrFail($created->json('data.staged_page.id'));
    $draftBranch = $draft->blocks()->where('revision_key', $branch->revision_key)->sole();
    $draftLegacy = $draft->blocks()->where('revision_key', $legacy->revision_key)->sole();
    $this->patchJson('/webadmin/api/blocks/'.$draftLegacy->id, ['translations' => ['title' => 'Legacy edited']])->assertOk();
    $promote = ['plan' => [
      'mode' => 'promote_staged_page_update', 'staged_page_id' => $draft->id,
      'expected_source_page_id' => $source->id, 'expected_source_path' => '/published', 'promote_slots' => ['main'],
    ]];
    $draftBranch->update(['parent_id' => null]);

    foreach (['validate', 'apply'] as $operation) {
      $this->postJson('/webadmin/api/content/'.$operation, $promote)->assertUnprocessable()->assertJsonPath('errors.0.path', 'plan.promote_slots');
    }
    $this->assertSame($navbar->id, $branch->fresh()->parent_id);
    $this->assertDatabaseHas('wbcms_pages', ['id' => $draft->id]);

    $draftDestination = $draft->blocks()->where('revision_key', $destination->revision_key)->sole();
    $draftBranch->update(['parent_id' => $draftDestination->id]);
    $this->postJson('/webadmin/api/content/apply', $promote)->assertCreated();
    $this->assertSame($destination->revision_key, $source->blocks()->where('revision_key', $branch->revision_key)->sole()->parent->revision_key);
    $promoted = $source->blocks()->where('revision_key', $legacy->revision_key)->sole();
    $this->assertNull($promoted->parent_id);
    $this->assertSame('Legacy edited', $promoted->textTranslations()->sole()->title);
  }

  #[Test]
  public function promotion_requires_a_retained_complete_split_to_keep_both_children(): void
  {
    $source = $this->page('published');
    $split = $this->block($source, 'split');
    $outgoing = $this->block($source, 'plain_text', $split);
    $staying = $this->block($source, 'plain_text', $split);
    $incoming = $this->block($source, 'plain_text');
    $created = $this->postJson('/webadmin/api/content/apply', ['plan' => [
      'mode' => 'create_staged_update_for_published_page', 'source_page_id' => $source->id,
      'expected_source_path' => '/published', 'managed_slots' => ['main'],
    ]])->assertCreated();
    $draft = Page::findOrFail($created->json('data.staged_page.id'));
    $draftBlocks = $draft->blocks()->get()->keyBy('revision_key');
    $promote = ['plan' => [
      'mode' => 'promote_staged_page_update', 'staged_page_id' => $draft->id,
      'expected_source_page_id' => $source->id, 'expected_source_path' => '/published', 'promote_slots' => ['main'],
    ]];
    $draftBlocks[$outgoing->revision_key]->update(['parent_id' => null]);

    foreach (['validate', 'apply'] as $operation) {
      $this->postJson('/webadmin/api/content/'.$operation, $promote)->assertUnprocessable()
        ->assertJsonPath('errors.0.path', 'plan.promote_slots')
        ->assertJsonPath('errors.0.message', 'A complete Split must keep two direct children when applying a staged update.');
    }
    $this->assertSame($split->id, $outgoing->fresh()->parent_id);
    $this->assertSame(2, $split->children()->count());
    $this->assertDatabaseHas('wbcms_pages', ['id' => $draft->id]);

    $draftBlocks[$incoming->revision_key]->update(['parent_id' => $draftBlocks[$split->revision_key]->id]);
    $this->postJson('/webadmin/api/content/validate', $promote)->assertOk();
    $this->postJson('/webadmin/api/content/apply', $promote)->assertCreated();
    $promoted = $source->blocks()->get()->keyBy('revision_key');
    $this->assertNull($promoted[$outgoing->revision_key]->parent_id);
    $this->assertEqualsCanonicalizing(
      [$staying->revision_key, $incoming->revision_key],
      $promoted[$split->revision_key]->children()->pluck('revision_key')->all(),
    );
  }

  private function page(string $status): Page
  {
    $page = Page::create(['site_id' => $this->site->id, 'title' => 'Page', 'slug' => $status, 'status' => $status]);
    $page->slots()->create(['slot_type_id' => $this->slot->id, 'source_type' => 'page']);

    return $page;
  }

  private function block(Page $page, string $type, ?Block $parent = null): Block
  {
    return $page->blocks()->create([
      'type' => $type, 'block_type_id' => BlockType::where('slug', $type)->value('id'),
      'slot_type_id' => $this->slot->id, 'slot' => 'main', 'parent_id' => $parent?->id,
      'status' => $page->status,
    ]);
  }
}
