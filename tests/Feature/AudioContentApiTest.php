<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

class AudioContentApiTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists(User::class)) {
      class_alias(MaintenanceTestUser::class, User::class);
    }
    $site = Site::create(['name' => 'Synthetic', 'handle' => 'synthetic', 'is_primary' => true]);
    foreach (['en', 'tr'] as $code) {
      $locale = Locale::create(['code' => $code, 'name' => $code, 'is_default' => $code === 'en', 'is_enabled' => true]);
      $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    }
    $layout = PageLayout::create(['name' => 'Default', 'handle' => 'default', 'is_active' => true]);
    $slot = SlotType::create(['name' => 'Main', 'slug' => 'main', 'status' => 'published']);
    PageLayoutSlot::create(['page_layout_id' => $layout->id, 'slot_type_id' => $slot->id, 'slot_name' => 'main', 'is_active' => true, 'is_required' => true]);
    BlockType::create(['name' => 'Audio', 'slug' => 'audio', 'category' => 'content', 'source_type' => 'static', 'status' => 'published']);
  }

  private function media(string $mime = 'audio/mpeg', string $kind = 'other'): Media
  {
    return Media::create(['disk' => 'public', 'path' => 'synthetic/audio.mp3', 'filename' => 'audio.mp3', 'mime_type' => $mime, 'size' => 123, 'kind' => $kind, 'visibility' => 'public']);
  }

  private function plan(int $mediaId): array
  {
    return ['plan' => ['mode' => 'create_draft_page', 'site' => 'synthetic', 'locale' => 'en', 'layout' => 'default',
      'page' => ['title' => 'Audio page', 'path' => '/audio-page', 'status' => 'draft'],
      'slots' => ['main' => [['type' => 'audio', 'media_id' => $mediaId, 'translations' => ['title' => 'Audio lesson']]]],
    ]];
  }

  private function request(array $data, string $method = 'PATCH'): Request
  {
    $request = Request::create('/webadmin/api/blocks', $method, [], [], [], [], json_encode($data));
    $request->headers->set('Content-Type', 'application/json');

    return $request;
  }

  #[Test]
  public function audio_assignment_keeps_token_capability_and_site_boundaries(): void
  {
    $first = $this->media();
    $this->assertTrue(app(InternalContentPlanService::class)->apply($this->plan($first->id))->ok);
    $block = Block::where('type', 'audio')->sole();
    $second = $this->media('audio/ogg');
    $issuer = app(CmsApiTokenIssuer::class);
    $read = $issuer->issue('Synthetic reader', capabilities: ['content.read'])->plainToken;
    $this->withToken($read)->patchJson('/webadmin/api/blocks/'.$block->id, ['media_id' => $second->id])->assertForbidden();
    $creator = User::forceCreate(['name' => 'Synthetic API operator', 'email' => 'api@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true]);
    $other = Site::create(['name' => 'Other', 'handle' => 'other']);
    $outside = $issuer->issue('Synthetic other site', creator: $creator, capabilities: ['content.apply'], tokenType: 'personal', allowedSiteIds: [$other->id])->plainToken;
    $this->withToken($outside)->patchJson('/webadmin/api/blocks/'.$block->id, ['media_id' => $second->id])->assertForbidden();
    $this->assertSame($first->id, $block->fresh()->media_id);
    $writer = $issuer->issue('Synthetic writer', creator: $creator, capabilities: ['content.apply'], tokenType: 'personal', allowedSiteIds: [$block->page->site_id])->plainToken;
    $this->withToken($writer)->patchJson('/webadmin/api/blocks/'.$block->id, ['media_id' => $second->id])->assertOk();
    $this->assertSame($second->id, $block->fresh()->media_id);
  }

  #[Test]
  public function plans_and_incremental_writes_keep_audio_media_and_localized_copy(): void
  {
    $first = $this->media();
    $result = app(InternalContentPlanService::class)->apply($this->plan($first->id));
    $this->assertTrue($result->ok, json_encode($result->errors));
    $block = Block::where('type', 'audio')->sole();
    $this->assertSame($first->id, $block->media_id);
    $this->assertStringContainsString('<audio controls', view('webblocks-cms::pages.partials.blocks.audio', compact('block'))->render());
    $second = $this->media('audio/ogg');
    $controller = app(InternalContentResourceController::class);
    $response = $controller->updateBlock($this->request(['media_id' => $second->id, 'locale' => 'tr', 'translations' => ['title' => 'Ses dersi']]), $block);
    $this->assertSame(200, $response->getStatusCode(), $response->getContent());
    $this->assertSame($second->id, $block->fresh()->media_id);
    $this->assertSame('Ses dersi', $block->textTranslations()->where('locale_id', Locale::where('code', 'tr')->value('id'))->sole()->title);
    $response = $controller->storeSlotBlock($this->request(['type' => 'audio', 'asset_id' => $first->id, 'locale' => 'en', 'translations' => ['title' => 'Second lesson']], 'POST'), $block->page, 'main');
    $this->assertSame(201, $response->getStatusCode(), $response->getContent());
    $this->assertSame($first->id, Block::findOrFail($response->getData(true)['block']['id'])->media_id);
    $response = $controller->updateBlock($this->request(['media_id' => null]), $block->fresh());
    $this->assertSame(200, $response->getStatusCode());
    $this->assertNull($block->fresh()->media_id);
  }

  #[Test]
  public function incompatible_and_missing_media_are_rejected_without_mutation(): void
  {
    $valid = $this->media();
    $this->assertTrue(app(InternalContentPlanService::class)->apply($this->plan($valid->id))->ok);
    $block = Block::where('type', 'audio')->sole();
    $controller = app(InternalContentResourceController::class);
    foreach ([$this->media('application/octet-stream'), $this->media('image/png', 'image'), $this->media('video/mp4', 'video'), $this->media('audio/'), $this->media('audio/mpeg', 'document')] as $invalid) {
      $result = app(InternalContentPlanService::class)->validate($this->plan($invalid->id));
      $this->assertFalse($result->ok);
      $this->assertContains('plan.slots.main.0.media_id', array_column($result->errors, 'path'));
      $response = $controller->updateBlock($this->request(['media_id' => $invalid->id]), $block->fresh());
      $this->assertSame(422, $response->getStatusCode());
      $response = $controller->storeSlotBlock($this->request(['type' => 'audio', 'media_id' => $invalid->id], 'POST'), $block->page, 'main');
      $this->assertSame(422, $response->getStatusCode());
      $this->assertSame($valid->id, $block->fresh()->media_id);
      $this->assertDatabaseCount('wbcms_blocks', 1);
    }
    $response = $controller->updateBlock($this->request(['media_id' => 999999]), $block);
    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame($valid->id, $block->fresh()->media_id);
  }

  #[Test]
  public function staged_audio_updates_preserve_the_published_source(): void
  {
    $first = $this->media();
    $service = app(InternalContentPlanService::class);
    $this->assertTrue($service->apply($this->plan($first->id))->ok);
    $source = Page::sole();
    $source->update(['status' => 'published']);
    $result = $service->apply(['plan' => ['mode' => 'create_staged_update_for_published_page', 'source_page_id' => $source->id, 'expected_source_path' => '/audio-page', 'managed_slots' => ['main']]]);
    $this->assertTrue($result->ok, json_encode($result->errors));
    $staged = Page::findOrFail($result->data['staged_page']['id']);
    $second = $this->media('audio/ogg');
    $result = $service->apply(['plan' => ['mode' => 'replace_existing_draft_page', 'site' => 'synthetic', 'locale' => 'en', 'page' => ['id' => $staged->id, 'expected_path' => $staged->publicPath('en')], 'replace_slots' => ['main' => [['type' => 'audio', 'media_id' => $second->id, 'translations' => ['title' => 'Revised lesson']]]]]]);
    $this->assertTrue($result->ok, json_encode($result->errors));
    $this->assertSame($second->id, $staged->blocks()->sole()->media_id);
    $this->assertSame($first->id, $source->blocks()->sole()->media_id);
    $this->assertSame('published', $source->fresh()->status);
  }
}
