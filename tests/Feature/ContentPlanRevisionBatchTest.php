<?php

namespace WebBlocks\Cms\Tests\Feature;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\SharedSlotRevision;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeApiAuthoringPolicy;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\Revisions\RevisionCaptureRecorder;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Tests\TestCase;

class ContentPlanRevisionBatchTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    $site = Site::query()->create(['name' => 'Synthetic', 'handle' => 'synthetic', 'is_primary' => true]);
    $locale = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->sync([$locale->id]);
    $layout = PageLayout::query()->create(['name' => 'Default', 'handle' => 'default', 'is_active' => true]);
    foreach (['header', 'main', 'footer'] as $index => $slug) {
      $slot = SlotType::query()->create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'published']);
      PageLayoutSlot::query()->create(['page_layout_id' => $layout->id, 'slot_type_id' => $slot->id, 'slot_name' => $slug, 'is_active' => true, 'is_required' => true, 'sort_order' => $index]);
    }
    BlockType::query()->create(['name' => 'Plain Text', 'slug' => 'plain_text', 'category' => 'content', 'source_type' => 'static', 'status' => 'published']);
  }

  private function sharedSlots(): array
  {
    return array_map(fn (string $slot): array => [
      'handle' => 'global-'.$slot, 'label' => 'Global '.ucfirst($slot), 'slot' => $slot, 'layout' => 'default',
      'blocks' => [['type' => 'plain_text', 'translations' => ['content' => $slot.' one']], ['type' => 'plain_text', 'translations' => ['content' => $slot.' two']]],
    ], ['header', 'footer']);
  }

  private function draftPlan(): array
  {
    return ['plan' => [
      'mode' => 'create_draft_page', 'site' => 'synthetic', 'locale' => 'en', 'layout' => 'default',
      'page' => ['title' => 'Batch', 'path' => '/batch', 'status' => 'draft'],
      'slots' => ['main' => array_map(fn (int $index): array => ['type' => 'plain_text', 'translations' => ['content' => 'Block '.$index]], range(1, 12))],
      'shared_slots' => $this->sharedSlots(),
      'page_slot_shared_slots' => [['page' => 'created', 'slot' => 'header', 'shared_slot' => 'global-header'], ['page' => 'created', 'slot' => 'footer', 'shared_slot' => 'global-footer']],
    ]];
  }

  #[Test]
  public function a_multi_block_multi_slot_plan_captures_only_complete_final_states(): void
  {
    $result = app(InternalContentPlanService::class)->apply($this->draftPlan());
    $this->assertTrue($result->ok, json_encode($result->errors));
    $page = Page::query()->findOrFail($result->data['page']['id']);
    $revision = $page->revisions()->sole();
    $this->assertCount(12, $revision->snapshot['blocks']);
    $this->assertSame('Block 12', $revision->snapshot['blocks'][11]['text_translations'][0]['content']);
    $shared = collect($revision->snapshot['slots'])->where('source_type', PageSlot::SOURCE_TYPE_SHARED_SLOT);
    $this->assertCount(2, $shared);
    $this->assertTrue($shared->every(fn (array $slot): bool => $slot['shared_slot_id'] !== null));
    $this->assertDatabaseCount('wbcms_page_revisions', 1);
    $this->assertDatabaseCount('wbcms_shared_slot_revisions', 2);
    foreach (SharedSlotRevision::all() as $slotRevision) {
      $this->assertCount(2, $slotRevision->snapshot['blocks']);
    }
    $this->assertDatabaseCount('wbcms_revision_capture_events', 3);
    $this->assertCount(1, array_filter($result->writes, fn (array $write): bool => $write['type'] === 'page_revision'));
  }

  #[Test]
  public function existing_page_assignments_have_one_safety_and_one_final_version_per_page(): void
  {
    $site = Site::query()->sole();
    $page = Page::query()->create(['site_id' => $site->id, 'title' => 'Existing', 'status' => Page::STATUS_DRAFT]);
    foreach (SlotType::all() as $slot) {
      PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'source_type' => PageSlot::SOURCE_TYPE_PAGE]);
    }
    $plan = ['plan' => [
      'site' => 'synthetic', 'locale' => 'en', 'shared_slots' => $this->sharedSlots(),
      'page_slot_shared_slots' => [['page' => $page->id, 'slot' => 'header', 'shared_slot' => 'global-header'], ['page' => $page->id, 'slot' => 'footer', 'shared_slot' => 'global-footer']],
    ]];
    $result = app(InternalContentPlanService::class)->apply($plan);
    $this->assertTrue($result->ok, json_encode($result->errors));
    $versions = PageRevision::query()->where('page_id', $page->id)->orderBy('id')->get();
    $this->assertCount(2, $versions);
    $this->assertCount(0, collect($versions[0]->snapshot['slots'])->where('source_type', PageSlot::SOURCE_TYPE_SHARED_SLOT));
    $this->assertCount(2, collect($versions[1]->snapshot['slots'])->where('source_type', PageSlot::SOURCE_TYPE_SHARED_SLOT));
  }

  #[Test]
  public function shared_slot_assignment_plans_preserve_the_human_only_block_boundary(): void
  {
    $slot = SharedSlot::query()->create(['site_id' => Site::query()->sole()->id, 'name' => 'Trusted', 'handle' => 'trusted', 'slot_name' => 'header', 'public_shell' => 'default', 'is_active' => true]);
    $source = app(SharedSlotSourcePageManager::class)->ensureFor($slot);
    BlockType::query()->create(['name' => 'HTML', 'slug' => 'html', 'source_type' => 'static', 'status' => 'published']);
    Block::query()->create(['page_id' => $source->id, 'type' => 'html', 'slot' => 'header', 'content' => '<p>Synthetic</p>']);
    $before = Page::query()->count();
    foreach (['shared_slot', 'shared_slot_id'] as $key) {
      $plan = $this->draftPlan();
      unset($plan['plan']['shared_slots']);
      $plan['plan']['page_slot_shared_slots'] = [['page' => 'created', 'slot' => 'header', $key => 'trusted']];
      $result = app(InternalContentPlanService::class)->apply($plan);
      $this->assertFalse($result->ok);
      $this->assertContains(BlockTypeApiAuthoringPolicy::ERROR_CODE, array_column($result->errors, 'code'));
      $this->assertSame($before, Page::query()->count());
      $this->assertDatabaseCount('wbcms_blocks', 1);
      $this->assertDatabaseCount('wbcms_page_revisions', 0);
      $this->assertDatabaseCount('wbcms_revision_capture_events', 0);
    }
  }

  #[Test]
  public function capture_failure_rolls_back_all_plan_content_and_audit_writes(): void
  {
    $recorder = Mockery::mock(RevisionCaptureRecorder::class);
    $recorder->shouldReceive('record')->once()->andThrow(new RuntimeException('Synthetic capture failure'));
    $this->app->instance(RevisionCaptureRecorder::class, $recorder);
    $plan = $this->draftPlan();
    unset($plan['plan']['shared_slots'], $plan['plan']['page_slot_shared_slots']);
    $result = app(InternalContentPlanService::class)->apply($plan);
    $this->assertFalse($result->ok);
    $this->assertDatabaseCount('wbcms_pages', 0);
    $this->assertDatabaseCount('wbcms_blocks', 0);
    $this->assertDatabaseCount('wbcms_page_revisions', 0);
    $this->assertDatabaseCount('wbcms_revision_capture_events', 0);
  }
}
