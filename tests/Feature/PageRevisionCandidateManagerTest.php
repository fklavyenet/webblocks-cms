<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevisionCandidate;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Pages\PageRevisionCandidateManager;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Sites\ExportImport\ExportablePages;
use WebBlocks\Cms\Tests\TestCase;

class PageRevisionCandidateManagerTest extends TestCase
{
  use RefreshDatabase;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(PageRevisionCandidateTestUser::class, 'App\\Models\\User');
    }

    parent::setUp();
  }

  #[Test]
  public function candidate_preview_does_not_change_source_until_explicit_apply(): void
  {
    [$page, $actor] = $this->pageAndActor();
    $revisions = $this->app->make(PageRevisionManager::class);
    $saved = $revisions->capture($page->fresh(), $actor, 'Saved version');
    $page->defaultTranslation()->forceFill(['name' => 'Current title'])->save();
    $page->touch();

    $manager = $this->app->make(PageRevisionCandidateManager::class);
    $candidate = $manager->create($page->fresh(), $saved, $actor);

    $this->assertSame('Current title', $page->fresh()->title);
    $this->assertSame('Original title', $candidate->candidatePage->defaultTranslation()?->name);
    $this->assertTrue($candidate->candidatePage->isRevisionRestoreCandidate());
    $this->assertSame(Page::STATUS_DRAFT, $candidate->candidatePage->status);
    $this->assertStringContainsString('/version-previews/page-', (string) $candidate->candidatePage->defaultTranslation()?->path);
    $exportableIds = collect($this->app->make(ExportablePages::class)->grouped())
      ->flatten(1)
      ->pluck('id');
    $this->assertFalse($exportableIds->contains($candidate->candidate_page_id));

    $manager->apply($candidate, $actor);

    $this->assertSame('Original title', $page->fresh()->title);
    $this->assertDatabaseHas('wbcms_page_revision_candidates', ['id' => $candidate->id, 'status' => PageRevisionCandidate::STATUS_APPLIED, 'candidate_page_id' => null]);
  }

  #[Test]
  public function candidate_is_rejected_when_source_changes_after_preview_was_prepared(): void
  {
    [$page, $actor] = $this->pageAndActor();
    $saved = $this->app->make(PageRevisionManager::class)->capture($page->fresh(), $actor, 'Saved version');
    $manager = $this->app->make(PageRevisionCandidateManager::class);
    $candidate = $manager->create($page->fresh(), $saved, $actor);
    $page->forceFill(['updated_at' => now()->addMinute()])->saveQuietly();

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('current page changed');

    $manager->apply($candidate, $actor);
  }

  #[Test]
  public function discarding_candidate_leaves_source_untouched(): void
  {
    [$page, $actor] = $this->pageAndActor();
    $saved = $this->app->make(PageRevisionManager::class)->capture($page->fresh(), $actor, 'Saved version');
    $manager = $this->app->make(PageRevisionCandidateManager::class);
    $candidate = $manager->create($page->fresh(), $saved, $actor);
    $candidatePageId = $candidate->candidate_page_id;

    $manager->discard($candidate);

    $this->assertSame('Original title', $page->fresh()->title);
    $this->assertDatabaseMissing('wbcms_pages', ['id' => $candidatePageId]);
    $this->assertDatabaseHas('wbcms_page_revision_candidates', ['id' => $candidate->id, 'status' => PageRevisionCandidate::STATUS_DISCARDED]);
  }

  private function pageAndActor(): array
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    Locale::query()->create(['name' => 'English', 'code' => 'en', 'is_default' => true, 'is_enabled' => true]);
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Original title',
      'slug' => 'original-title',
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_PUBLISHED,
    ]);

    $userClass = 'App\\Models\\User';

    return [$page, new $userClass];
  }
}

class PageRevisionCandidateTestUser extends AuthenticatableUser {}
