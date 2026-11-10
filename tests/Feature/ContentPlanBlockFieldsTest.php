<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Tests\TestCase;

class ContentPlanBlockFieldsTest extends TestCase
{
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
    $site = Site::create(['name' => 'Synthetic studio', 'handle' => 'synthetic-studio', 'is_primary' => true]);
    $locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->sync([$locale->id => ['is_enabled' => true]]);
    $layout = PageLayout::create(['name' => 'Default', 'handle' => 'default', 'is_active' => true]);
    $slot = SlotType::create(['name' => 'Main', 'slug' => 'main', 'status' => 'published']);
    $layout->layoutSlots()->create(['slot_type_id' => $slot->id, 'slot_name' => 'main', 'is_active' => true]);
    app(CoreBlockTypeCatalogSyncer::class)->sync();
    $this->withToken(app(CmsApiTokenIssuer::class)->issue('Synthetic content planner', capabilities: ['content.validate', 'content.apply'])->plainToken);
  }

  #[Test]
  public function plans_write_mobile_images_and_cookie_variants_without_changing_legacy_plan_precedence(): void
  {
    $desktop = $this->media('desktop', Media::KIND_IMAGE);
    $mobile = $this->media('mobile', Media::KIND_IMAGE);
    $image = ['type' => 'image', 'media_id' => $desktop->id];
    $cookie = ['type' => 'cookie-settings'];
    $plan = $this->draftPlan([
      ['type' => 'section', 'children' => [$image + ['mobile_media_id' => $mobile->id], $image]],
      $cookie + ['variant' => 'secondary'],
      $cookie + ['settings' => ['variant' => 'primary']],
      $cookie + ['variant' => 'primary', 'settings' => ['variant' => 'text']],
    ]);

    $this->postJson('/webadmin/api/content/validate', $plan)->assertOk();
    $this->assertDatabaseEmpty('wbcms_pages');
    $created = $this->postJson('/webadmin/api/content/apply', $plan)->assertCreated();
    $page = Page::findOrFail($created->json('data.page.id'));
    [$withMobile, $withoutMobile] = $page->blocks()->where('type', 'image')->orderBy('sort_order')->get()->all();
    $this->assertSame($desktop->id, $withMobile->media_id);
    $this->assertSame($mobile->id, $withMobile->mobileMedia()?->id);
    $this->assertNull($withoutMobile->mobileMedia());
    $this->assertSame(['secondary', 'primary', 'text'], $page->blocks()->where('type', 'cookie-settings')->orderBy('sort_order')->pluck('variant')->all());

    $this->postJson('/webadmin/api/pages/'.$page->id.'/slots/main/blocks', $cookie + ['variant' => 'secondary', 'settings' => ['variant' => 'primary']])
      ->assertCreated()->assertJsonPath('block.variant', 'secondary');

    $this->postJson('/webadmin/api/content/apply', ['plan' => [
      'mode' => 'replace_existing_draft_page',
      'page' => ['id' => $page->id, 'expected_path' => '/planned-page'],
      'replace_slots' => ['main' => [$image + ['mobile_media_id' => null], $cookie + ['variant' => 'primary']]],
    ]])->assertCreated();
    $replacedImage = $page->blocks()->where('type', 'image')->sole();
    $this->assertSame($desktop->id, $replacedImage->media_id);
    $this->assertNull($replacedImage->mobileMedia());
    $this->assertSame('primary', $page->blocks()->where('type', 'cookie-settings')->sole()->variant);
  }

  #[Test]
  public function invalid_mobile_images_and_cookie_variants_are_rejected_before_writing_the_plan(): void
  {
    $image = $this->media('mobile', Media::KIND_IMAGE);
    $video = $this->media('clip', Media::KIND_VIDEO);
    $invalidBlocks = [
      [['type' => 'image', 'mobile_media_id' => $video->id], 'mobile_media_id'],
      [['type' => 'image', 'mobile_media_id' => $video->id + 1], 'mobile_media_id'],
      [['type' => 'plain_text', 'mobile_media_id' => $image->id], 'mobile_media_id'],
      [['type' => 'image', 'settings' => ['mobile_media_id' => $image->id]], 'settings.mobile_media_id'],
      [['type' => 'cookie-settings', 'variant' => 'unsupported'], 'settings.variant'],
    ];

    foreach ($invalidBlocks as [$block, $field]) {
      $plan = $this->draftPlan([['type' => 'cookie-settings'], $block]);
      foreach (['validate', 'apply'] as $operation) {
        $this->postJson('/webadmin/api/content/'.$operation, $plan)
          ->assertUnprocessable()->assertJsonPath('errors.0.path', 'plan.slots.main.1.'.$field);
      }
    }

    $this->assertDatabaseEmpty('wbcms_pages');
    $this->assertDatabaseEmpty('wbcms_blocks');
    $this->assertDatabaseEmpty('wbcms_block_media');
  }

  private function draftPlan(array $blocks): array
  {
    return ['plan' => [
      'site' => 'synthetic-studio', 'locale' => 'en', 'layout' => 'default',
      'page' => ['title' => 'Planned page', 'path' => '/planned-page'],
      'slots' => ['main' => $blocks],
    ]];
  }

  private function media(string $name, string $kind): Media
  {
    $filename = $name.($kind === Media::KIND_IMAGE ? '.jpg' : '.mp4');

    return Media::create([
      'disk' => 'public', 'path' => 'media/'.$filename, 'filename' => $filename,
      'kind' => $kind, 'mime_type' => $kind === Media::KIND_IMAGE ? 'image/jpeg' : 'video/mp4', 'visibility' => 'public',
    ]);
  }
}
