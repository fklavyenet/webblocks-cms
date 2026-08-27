<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Tests\TestCase;

class StagedUpdateLifecycleTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function successful_promotion_deletes_the_technical_staged_page_and_keeps_source_versions(): void
  {
    $source = $this->seedPublishedPage();
    $service = app(InternalContentPlanService::class);

    $created = $service->apply($this->createPlan($source));
    $this->assertTrue($created->ok, json_encode($created->errors, JSON_PRETTY_PRINT));
    $stagedPageId = $created->data['staged_page']['id'];

    $promoted = $service->apply([
      'plan' => [
        'mode' => 'promote_staged_page_update',
        'staged_page_id' => $stagedPageId,
        'expected_source_page_id' => $source->id,
        'expected_source_path' => '/source',
        'promote_slots' => ['main'],
      ],
    ]);

    $this->assertTrue($promoted->ok, json_encode($promoted->errors, JSON_PRETTY_PRINT));
    $this->assertNull(Page::query()->find($stagedPageId));
    $this->assertSame([
      'id' => $stagedPageId,
      'state' => 'promoted',
      'deleted' => true,
      'promoted_to_page_id' => $source->id,
    ], $promoted->data['staged_update']);
    $this->assertSame(Page::STATUS_PUBLISHED, $source->fresh()->status);
    $this->assertGreaterThanOrEqual(2, PageRevision::query()->where('page_id', $source->id)->count());
  }

  #[Test]
  public function active_staged_draft_can_be_discarded_without_the_general_page_delete_flow(): void
  {
    $source = $this->seedPublishedPage();
    $created = app(InternalContentPlanService::class)->apply($this->createPlan($source));
    $staged = Page::query()->findOrFail($created->data['staged_page']['id']);

    $response = app(InternalContentResourceController::class)->discardStagedUpdate($staged);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($response->getData(true)['ok']);
    $this->assertNull(Page::query()->find($staged->id));
    $this->assertNotNull(Page::query()->find($source->id));
  }

  #[Test]
  public function discard_rejects_an_ordinary_draft_and_is_advertised_as_content_apply(): void
  {
    $ordinaryDraft = Page::query()->create([
      'site_id' => $this->seedPublishedPage()->site_id,
      'title' => 'Ordinary draft',
      'slug' => 'ordinary-draft',
      'status' => Page::STATUS_DRAFT,
    ]);

    $response = app(InternalContentResourceController::class)->discardStagedUpdate($ordinaryDraft);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('page_not_active_staged_update', $response->getData(true)['errors'][0]['code']);
    $this->assertNotNull($ordinaryDraft->fresh());

    $paths = app(InternalApiDiscoveryController::class)->openapi()->getData(true)['paths'];
    $this->assertSame('content.apply', $paths['/pages/{page}/staged-update']['delete']['x-required-capability']);
  }

  #[Test]
  public function prune_command_only_deletes_legacy_promoted_archived_staged_pages(): void
  {
    $source = $this->seedPublishedPage();
    $legacy = Page::query()->create([
      'site_id' => $source->site_id,
      'title' => 'Legacy staged update',
      'slug' => 'legacy-staged-update',
      'status' => Page::STATUS_ARCHIVED,
      'settings' => [
        'staged_update' => [
          'type' => 'published_page_update',
          'state' => 'promoted',
          'source_page_id' => $source->id,
        ],
      ],
    ]);
    $ordinaryArchive = Page::query()->create([
      'site_id' => $source->site_id,
      'title' => 'Ordinary archive',
      'slug' => 'ordinary-archive',
      'status' => Page::STATUS_ARCHIVED,
    ]);

    $this->artisan('webblocks:staged-updates:prune', ['--dry-run' => true])
      ->expectsOutput('eligible=1 deleted=0')
      ->assertSuccessful();
    $this->assertNotNull($legacy->fresh());

    $this->artisan('webblocks:staged-updates:prune')
      ->expectsOutput('eligible=1 deleted=1')
      ->assertSuccessful();
    $this->assertNull($legacy->fresh());
    $this->assertNotNull($ordinaryArchive->fresh());
  }

  private function seedPublishedPage(): Page
  {
    $site = Site::query()->firstOrCreate(['handle' => 'default'], ['name' => 'Default', 'is_primary' => true]);
    $locale = Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$locale->id]);
    $main = SlotType::query()->firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Source',
      'slug' => 'source',
      'status' => Page::STATUS_PUBLISHED,
    ]);
    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $main->id,
      'source_type' => PageSlot::SOURCE_TYPE_PAGE,
      'sort_order' => 0,
    ]);

    return $page->fresh(['site.locales', 'translations.locale', 'slots.slotType']);
  }

  private function createPlan(Page $source): array
  {
    return [
      'plan' => [
        'mode' => 'create_staged_update_for_published_page',
        'source_page_id' => $source->id,
        'expected_source_path' => '/source',
        'managed_slots' => ['main'],
      ],
    ];
  }
}
