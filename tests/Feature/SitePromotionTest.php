<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\BuildsCloneableSite;
use Tests\TestCase;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageAsset;
use WebBlocks\Cms\Models\PublicSearchIndex;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteDomain;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\VisitorEvent;
use WebBlocks\Cms\Support\SitePromotion\SitePromotionApplier;
use WebBlocks\Cms\Support\SitePromotion\SitePromotionOptions;
use WebBlocks\Cms\Support\SitePromotion\SitePromotionPackageInspector;
use WebBlocks\Cms\Support\SitePromotion\SitePromotionPlanner;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteExportManager;
use WebBlocks\Cms\Support\System\SystemBackupManager;

class SitePromotionTest extends TestCase
{
  use BuildsCloneableSite;
  use RefreshDatabase;

  #[Test]
  public function super_admin_can_access_admin_sites_promote(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.sites.promote'));

    $response->assertOk();
    $response->assertSee('Site Promotion');
    $response->assertSee('Upload / Select Package');
    $response->assertSeeInOrder(['Run Dry Run', 'Cancel'], false);
    $response->assertSeeInOrder(['Apply Promotion', 'Cancel'], false);
  }

  #[Test]
  public function promote_screen_preselects_target_site_from_query_when_valid(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->create([
      'name' => 'Target Site',
      'handle' => 'target-site',
      'domain' => 'target.example.test',
    ]);

    $response = $this->actingAs($user)->get(route('admin.sites.promote', [
      'target_site_id' => $site->id,
    ]));

    $response->assertOk();
    $response->assertSee('<option value="'.$site->id.'" selected>', false);
  }

  #[Test]
  public function invalid_target_site_query_parameter_does_not_break_promotion_screen(): void
  {
    $user = User::factory()->superAdmin()->create();
    $site = Site::query()->create([
      'name' => 'Target Site',
      'handle' => 'target-site',
      'domain' => 'target.example.test',
    ]);

    $response = $this->actingAs($user)->get(route('admin.sites.promote', [
      'target_site_id' => 999999,
    ]));

    $response->assertOk();
    $response->assertDontSee('<option value="'.$site->id.'" selected>', false);
    $response->assertSee('Target site');
  }

  #[Test]
  public function non_super_admin_cannot_apply_promotion_in_v1(): void
  {
    $user = User::factory()->siteAdmin()->create();

    $response = $this->actingAs($user)->post(route('admin.sites.promote.apply'), [
      'plan_token' => 'fake-token',
    ]);

    $response->assertForbidden();
  }

  #[Test]
  public function dry_run_does_not_modify_target_content(): void
  {
    Storage::fake(SiteExportManager::ARCHIVE_DISK);
    Storage::fake('site-promotions');
    Storage::fake('public');

    [$sourceSite] = $this->seedCloneableSite(withFile: true);
    $targetSite = Site::query()->create([
      'name' => 'Target Site',
      'handle' => 'target-site',
      'domain' => 'target.example.test',
    ]);
    $existingPage = Page::query()->create([
      'site_id' => $targetSite->id,
      'title' => 'Target only',
      'slug' => 'target-only',
      'status' => Page::STATUS_PUBLISHED,
    ]);

    $archivePath = $this->exportPromotionPackage($sourceSite, true);

    $plan = app(SitePromotionPlanner::class)->plan($archivePath, SitePromotionOptions::fromArray([
      'target_site_id' => $targetSite->id,
      'strategy' => 'additive_update',
      'apply_assets' => true,
    ]));

    $this->assertSame('Target only', $existingPage->fresh()->title);
    $this->assertTrue($plan->canApply());
    $this->assertDatabaseHas('wbcms_pages', ['id' => $existingPage->id, 'site_id' => $targetSite->id, 'status' => Page::STATUS_PUBLISHED]);
  }

  #[Test]
  public function apply_requires_a_successful_dry_run_plan(): void
  {
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Apply requires a successful dry run plan.');

    app(SitePromotionApplier::class)->apply('missing-plan-token');
  }

  #[Test]
  public function apply_creates_a_safety_backup_before_content_changes(): void
  {
    Storage::fake(SiteExportManager::ARCHIVE_DISK);
    Storage::fake('site-promotions');
    Storage::fake('public');

    [$sourceSite] = $this->seedCloneableSite(withFile: true);
    $targetSite = Site::query()->create([
      'name' => 'Target Site',
      'handle' => 'target-site',
      'domain' => 'target.example.test',
    ]);

    $archivePath = $this->exportPromotionPackage($sourceSite, true);
    $plan = app(SitePromotionPlanner::class)->plan($archivePath, SitePromotionOptions::fromArray([
      'target_site_id' => $targetSite->id,
      'strategy' => 'additive_update',
      'apply_assets' => true,
    ]));

    $result = app(SitePromotionApplier::class)->apply($plan->token);

    $this->assertInstanceOf(SystemBackup::class, $result->safetyBackup);
    $this->assertSame(SystemBackup::STATUS_COMPLETED, $result->safetyBackup?->status);
    $this->assertDatabaseHas('wbcms_system_backups', ['id' => $result->safetyBackup?->id]);
  }

  #[Test]
  public function apply_preserves_gallery_item_translation_rows_when_promoting_block_media(): void
  {
    Storage::fake(SiteExportManager::ARCHIVE_DISK);
    Storage::fake('site-promotions');
    Storage::fake('public');

    [$sourceSite, $heroAsset] = $this->seedCloneableSite(withFile: true);
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
    $turkish = Locale::query()->where('code', 'tr')->firstOrFail();
    $sourceAbout = Page::query()
      ->where('site_id', $sourceSite->id)
      ->whereHas('translations', fn ($query) => $query
        ->where('locale_id', $defaultLocale->id)
        ->where('slug', 'about'))
      ->firstOrFail();

    $this->addGalleryBlockToPage($sourceAbout, $heroAsset, $defaultLocale, $turkish);

    $targetSite = Site::query()->create([
      'name' => 'Target Site',
      'handle' => 'target-site',
      'domain' => 'target.example.test',
    ]);

    $archivePath = $this->exportPromotionPackage($sourceSite, true);
    $plan = app(SitePromotionPlanner::class)->plan($archivePath, SitePromotionOptions::fromArray([
      'target_site_id' => $targetSite->id,
      'strategy' => 'additive_update',
      'apply_assets' => true,
    ]));

    app(SitePromotionApplier::class)->apply($plan->token);

    $targetAbout = Page::query()
      ->where('site_id', $targetSite->id)
      ->whereHas('translations', fn ($query) => $query
        ->where('locale_id', $defaultLocale->id)
        ->where('slug', 'about'))
      ->firstOrFail();
    $targetGallery = $targetAbout->blocks()->where('type', 'gallery')->firstOrFail();
    $targetGalleryItem = $targetGallery->blockAssets()->where('role', 'gallery_item')->firstOrFail();

    $this->assertDatabaseHas('wbcms_block_gallery_item_translations', [
      'block_media_id' => $targetGalleryItem->id,
      'locale_id' => $defaultLocale->id,
      'caption' => 'Gallery caption',
    ]);
    $this->assertDatabaseHas('wbcms_block_gallery_item_translations', [
      'block_media_id' => $targetGalleryItem->id,
      'locale_id' => $turkish->id,
      'caption' => 'Galeri aciklamasi',
      'overlay_text' => 'Galeri katman metni',
    ]);
  }

  #[Test]
  public function apply_blocks_if_safety_backup_creation_fails(): void
  {
    Storage::fake(SiteExportManager::ARCHIVE_DISK);
    Storage::fake('site-promotions');
    Storage::fake('public');

    [$sourceSite] = $this->seedCloneableSite(withFile: true);
    $targetSite = Site::query()->create([
      'name' => 'Target Site',
      'handle' => 'target-site',
      'domain' => 'target.example.test',
    ]);

    $archivePath = $this->exportPromotionPackage($sourceSite, true);
    $plan = app(SitePromotionPlanner::class)->plan($archivePath, SitePromotionOptions::fromArray([
      'target_site_id' => $targetSite->id,
      'strategy' => 'additive_update',
      'apply_assets' => true,
    ]));

    $backupManager = Mockery::mock(SystemBackupManager::class);
    $backupManager->shouldReceive('createManualBackup')->once()->andThrow(new RuntimeException('Backup failed intentionally.'));
    $this->app->instance(SystemBackupManager::class, $backupManager);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Backup failed intentionally.');

    app(SitePromotionApplier::class)->apply($plan->token);
  }

  #[Test]
  public function preserved_areas_are_not_imported_or_overwritten(): void
  {
    Storage::fake(SiteExportManager::ARCHIVE_DISK);
    Storage::fake('site-promotions');
    Storage::fake('public');

    [$sourceSite] = $this->seedCloneableSite(withFile: true);
    $targetSite = Site::query()->create([
      'name' => 'Target Site',
      'handle' => 'target-site',
      'domain' => 'target.example.test',
    ]);
    SiteDomain::query()->create([
      'site_id' => $targetSite->id,
      'domain' => 'live.target.example.test',
      'is_primary' => true,
      'redirect_to_primary' => false,
      'status' => SiteDomain::STATUS_ACTIVE,
    ]);
    $user = User::factory()->siteAdmin()->create();
    $page = Page::query()->create([
      'site_id' => $targetSite->id,
      'title' => 'Message Page',
      'slug' => 'message-page',
      'status' => Page::STATUS_PUBLISHED,
    ]);
    ContactMessage::query()->create([
      'page_id' => $page->id,
      'name' => 'Visitor',
      'email' => 'visitor@example.test',
      'subject' => 'Hello',
      'message' => 'Keep me',
      'status' => 'new',
    ]);
    VisitorEvent::query()->create([
      'site_id' => $targetSite->id,
      'page_id' => $page->id,
      'path' => '/message-page',
      'tracking_mode' => VisitorEvent::TRACKING_MODE_BASIC,
      'visited_at' => now(),
    ]);

    $archivePath = $this->exportPromotionPackage($sourceSite, true);
    $plan = app(SitePromotionPlanner::class)->plan($archivePath, SitePromotionOptions::fromArray([
      'target_site_id' => $targetSite->id,
      'strategy' => 'additive_update',
      'apply_assets' => true,
    ]));

    app(SitePromotionApplier::class)->apply($plan->token);

    $this->assertDatabaseHas('wbcms_site_domains', ['site_id' => $targetSite->id, 'domain' => 'live.target.example.test']);
    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertDatabaseHas('wbcms_contact_messages', ['page_id' => $page->id, 'email' => 'visitor@example.test']);
    $this->assertDatabaseHas('wbcms_visitor_events', ['site_id' => $targetSite->id, 'path' => '/message-page']);
  }

  #[Test]
  public function additive_update_creates_and_updates_source_content_but_does_not_remove_extra_target_pages(): void
  {
    Storage::fake(SiteExportManager::ARCHIVE_DISK);
    Storage::fake('site-promotions');
    Storage::fake('public');

    [$sourceSite] = $this->seedCloneableSite(withFile: true);
    $targetSite = Site::query()->create([
      'name' => 'Target Site',
      'handle' => 'target-site',
      'domain' => 'target.example.test',
    ]);
    $extraPage = Page::query()->create([
      'site_id' => $targetSite->id,
      'title' => 'Extra target page',
      'slug' => 'extra-target-page',
      'status' => Page::STATUS_PUBLISHED,
    ]);

    $archivePath = $this->exportPromotionPackage($sourceSite, true);
    $plan = app(SitePromotionPlanner::class)->plan($archivePath, SitePromotionOptions::fromArray([
      'target_site_id' => $targetSite->id,
      'strategy' => 'additive_update',
      'apply_assets' => true,
    ]));
    app(SitePromotionApplier::class)->apply($plan->token);

    $promotedPage = Page::query()
      ->where('site_id', $targetSite->id)
      ->whereHas('translations', fn ($query) => $query->where('slug', 'about'))
      ->first();

    $this->assertNotNull($promotedPage);
    $this->assertDatabaseHas('wbcms_pages', ['id' => $extraPage->id, 'site_id' => $targetSite->id]);
  }

  #[Test]
  public function mirror_mode_reports_extra_target_content_as_removable_and_requires_explicit_mirror_selection(): void
  {
    Storage::fake(SiteExportManager::ARCHIVE_DISK);
    Storage::fake('site-promotions');
    Storage::fake('public');

    [$sourceSite] = $this->seedCloneableSite(withFile: true);
    $targetSite = Site::query()->create([
      'name' => 'Target Site',
      'handle' => 'target-site',
      'domain' => 'target.example.test',
    ]);
    $extraPage = Page::query()->create([
      'site_id' => $targetSite->id,
      'title' => 'Extra target page',
      'slug' => 'extra-target-page',
      'status' => Page::STATUS_PUBLISHED,
    ]);

    $archivePath = $this->exportPromotionPackage($sourceSite, true);
    $additivePlan = app(SitePromotionPlanner::class)->plan($archivePath, SitePromotionOptions::fromArray([
      'target_site_id' => $targetSite->id,
      'strategy' => 'additive_update',
      'apply_assets' => true,
    ]));
    $mirrorPlan = app(SitePromotionPlanner::class)->plan($archivePath, SitePromotionOptions::fromArray([
      'target_site_id' => $targetSite->id,
      'strategy' => 'mirror',
      'apply_assets' => true,
    ]));

    $this->assertSame([], $additivePlan->operations['pages']['archive']);
    $this->assertNotEmpty($mirrorPlan->operations['pages']['archive']);

    app(SitePromotionApplier::class)->apply($mirrorPlan->token);

    $this->assertDatabaseHas('wbcms_pages', ['id' => $extraPage->id, 'site_id' => $targetSite->id, 'status' => Page::STATUS_ARCHIVED]);
  }

  #[Test]
  public function page_assets_are_included_in_the_plan_and_constrained_to_site_paths(): void
  {
    Storage::fake(SiteExportManager::ARCHIVE_DISK);
    Storage::fake('site-promotions');
    Storage::fake('public');

    [$sourceSite] = $this->seedCloneableSite(withFile: true);
    $page = Page::query()
      ->where('site_id', $sourceSite->id)
      ->whereHas('translations', fn ($query) => $query->where('slug', 'about'))
      ->firstOrFail();
    PageAsset::query()->create([
      'page_id' => $page->id,
      'type' => PageAsset::TYPE_CSS,
      'path' => '/site/webblocks-ui/pages/about/page.css',
      'load_position' => PageAsset::LOAD_POSITION_HEAD,
      'is_enabled' => true,
      'sort_order' => 0,
    ]);

    $archivePath = $this->exportPromotionPackage($sourceSite, true);
    $targetSite = Site::query()->create([
      'name' => 'Target Site',
      'handle' => 'target-site',
      'domain' => 'target.example.test',
    ]);

    $plan = app(SitePromotionPlanner::class)->plan($archivePath, SitePromotionOptions::fromArray([
      'target_site_id' => $targetSite->id,
      'strategy' => 'additive_update',
      'apply_assets' => true,
    ]));

    $this->assertGreaterThan(0, count($plan->operations['page_assets']['create']) + count($plan->operations['page_assets']['update']));
    $this->assertSame(0, count($plan->errors));
  }

  #[Test]
  public function search_rebuild_refreshes_target_search_rows_after_apply(): void
  {
    Storage::fake(SiteExportManager::ARCHIVE_DISK);
    Storage::fake('site-promotions');
    Storage::fake('public');

    [$sourceSite] = $this->seedCloneableSite(withFile: true);
    $targetSite = Site::query()->create([
      'name' => 'Target Site',
      'handle' => 'target-site',
      'domain' => 'target.example.test',
    ]);
    $stalePage = Page::query()->create([
      'site_id' => $targetSite->id,
      'title' => 'Stale Search Page',
      'slug' => 'stale-search-page',
      'status' => Page::STATUS_DRAFT,
    ]);

    PublicSearchIndex::query()->create([
      'site_id' => $targetSite->id,
      'locale_id' => $targetSite->locales()->firstOrFail()->id,
      'page_id' => $stalePage->id,
      'title' => 'Old',
      'excerpt' => 'Old',
      'url' => '/old',
      'content' => 'Old',
      'indexed_at' => now(),
    ]);

    $archivePath = $this->exportPromotionPackage($sourceSite, true);
    $plan = app(SitePromotionPlanner::class)->plan($archivePath, SitePromotionOptions::fromArray([
      'target_site_id' => $targetSite->id,
      'strategy' => 'additive_update',
      'apply_assets' => true,
    ]));
    $result = app(SitePromotionApplier::class)->apply($plan->token);

    $this->assertGreaterThanOrEqual(1, $result->searchIndexed);
    $this->assertDatabaseMissing('wbcms_public_search_index', ['site_id' => $targetSite->id, 'page_id' => $stalePage->id]);
    $this->assertDatabaseHas('wbcms_public_search_index', ['site_id' => $targetSite->id]);
  }

  #[Test]
  public function invalid_package_is_rejected(): void
  {
    Storage::fake('site-promotions');

    Storage::disk('site-promotions')->put('uploads/broken.zip', 'not-a-zip');

    $this->expectException(RuntimeException::class);

    app(SitePromotionPackageInspector::class)->inspectStoredArchive('uploads/broken.zip');
  }

  #[Test]
  public function missing_locale_compatibility_is_reported_and_will_create_missing_locales(): void
  {
    Storage::fake(SiteExportManager::ARCHIVE_DISK);
    Storage::fake('site-promotions');
    Storage::fake('public');

    [$sourceSite] = $this->seedCloneableSite(withFile: true);
    $archivePath = $this->exportPromotionPackage($sourceSite, true);
    Locale::query()->where('code', 'tr')->delete();
    $targetSite = Site::query()->create([
      'name' => 'Target Site',
      'handle' => 'target-site',
      'domain' => 'target.example.test',
    ]);

    $plan = app(SitePromotionPlanner::class)->plan($archivePath, SitePromotionOptions::fromArray([
      'target_site_id' => $targetSite->id,
      'strategy' => 'additive_update',
      'apply_assets' => true,
    ]));

    $this->assertContains('tr', $plan->localeSummary['missing']);
    $this->assertTrue((bool) $plan->localeSummary['will_create_missing']);
  }

  #[Test]
  public function shared_slot_backed_page_slots_are_mapped_safely(): void
  {
    Storage::fake(SiteExportManager::ARCHIVE_DISK);
    Storage::fake('site-promotions');
    Storage::fake('public');

    [$sourceSite, , $sharedSlot] = $this->seedCloneableSite(withFile: true);
    $targetSite = Site::query()->create([
      'name' => 'Target Site',
      'handle' => 'target-site',
      'domain' => 'target.example.test',
    ]);

    $archivePath = $this->exportPromotionPackage($sourceSite, true);
    $plan = app(SitePromotionPlanner::class)->plan($archivePath, SitePromotionOptions::fromArray([
      'target_site_id' => $targetSite->id,
      'strategy' => 'additive_update',
      'apply_assets' => true,
    ]));
    app(SitePromotionApplier::class)->apply($plan->token);

    $this->assertDatabaseHas('wbcms_shared_slots', ['site_id' => $targetSite->id, 'handle' => $sharedSlot->handle]);
    $promotedAboutPage = Page::query()
      ->where('site_id', $targetSite->id)
      ->whereHas('translations', fn ($query) => $query->where('slug', 'about'))
      ->firstOrFail();
    $this->assertDatabaseHas('wbcms_page_slots', [
      'page_id' => $promotedAboutPage->id,
      'source_type' => 'shared_slot',
    ]);
  }

  private function exportPromotionPackage(Site $site, bool $includesMedia): string
  {
    $export = app(SiteExportManager::class)->export($site, $includesMedia);
    $archivePath = Storage::disk($export->archive_disk)->path($export->archive_path);
    $inspection = app(SitePromotionPackageInspector::class)->inspectUpload(new UploadedFile($archivePath, basename($archivePath), 'application/zip', null, true));

    return $inspection->archivePath;
  }
}
