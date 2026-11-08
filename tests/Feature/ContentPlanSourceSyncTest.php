<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Tests\TestCase;

class ContentPlanSourceSyncTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  #[DataProvider('publicDocumentNames')]
  public function public_document_names_keep_their_source_binding(string $name): void
  {
    $site = $this->site();
    $metadata = $this->metadata($name);
    $service = app(InternalContentPlanService::class);
    $plan = $this->plan($site, $metadata);

    $validated = $service->validate($plan);
    $this->assertTrue($validated->ok, json_encode($validated->errors));
    $this->assertSame($metadata, $validated->normalizedPlan['page_settings']['source_sync']);
    $this->assertSame(0, Page::count(), 'Validation must not create a page.');

    $applied = $service->apply($plan);
    $this->assertTrue($applied->ok, json_encode($applied->errors));
    $page = Page::findOrFail($applied->data['page']['id']);
    $this->assertSame($metadata, $page->setting('source_sync'));
    $this->assertSame($metadata, $applied->data['page']['source_sync']);
  }

  public static function publicDocumentNames(): array
  {
    return [
      'personal API token canonical filename' => ['personal-ai-tokens.md'],
      'token guide' => ['token-management.md'],
      'secret guide' => ['secret-management.md'],
      'password guide' => ['password-reset.md'],
      'environment guide' => ['environment-variables.md'],
    ];
  }

  #[Test]
  #[TestWith([false])]
  #[TestWith([true])]
  public function promotion_preserves_inherited_and_explicit_source_bindings(bool $explicit): void
  {
    $site = $this->site();
    $service = app(InternalContentPlanService::class);
    $created = $service->apply($this->plan($site, $this->metadata('original.md')));
    $this->assertTrue($created->ok, json_encode($created->errors));
    $source = Page::findOrFail($created->data['page']['id']);
    $source->blocks()->each(fn ($block) => $block->update(['status' => 'published']));
    $source->update(['status' => 'published']);
    $stage = $service->apply(['plan' => [
      'mode' => 'create_staged_update_for_published_page', 'source_page_id' => $source->id,
      'expected_source_path' => '/source-sync', 'managed_slots' => ['main'],
    ]]);
    $this->assertTrue($stage->ok, json_encode($stage->errors));
    $draft = Page::findOrFail($stage->data['staged_page']['id']);
    $metadata = $this->metadata('personal-ai-tokens.md');
    // Existing staged content already has a binding; promotion must retain it.
    $draft->update(['settings' => array_replace($draft->settings, ['source_sync' => $metadata])]);
    $promotion = ['plan' => [
      'mode' => 'promote_staged_page_update', 'staged_page_id' => $draft->id,
      'expected_source_page_id' => $source->id, 'expected_source_path' => '/source-sync', 'promote_slots' => ['main'],
    ]];
    if ($explicit) {
      $promotion['plan']['source_sync'] = $metadata;
    }

    $validated = $service->validate($promotion);
    $this->assertTrue($validated->ok, json_encode($validated->errors));
    $this->assertSame($metadata, $validated->normalizedPlan['page_settings']['source_sync']);
    $this->assertSame($this->metadata('original.md'), $source->fresh()->setting('source_sync'));
    $this->assertNotNull($draft->fresh());
    $promoted = $service->apply($promotion);
    $this->assertTrue($promoted->ok, json_encode($promoted->errors));
    $this->assertSame($metadata, $source->fresh()->setting('source_sync'));
    $this->assertNull(Page::find($draft->id));
  }

  #[Test]
  public function topic_words_are_allowed_in_metadata_types_and_extra_fields_remain_rejected(): void
  {
    $site = $this->site();
    $service = app(InternalContentPlanService::class);
    foreach (['token_reference', 'secret_reference', 'password_reference'] as $type) {
      $metadata = array_replace($this->metadata('reference.md'), ['type' => $type]);
      $result = $service->validate($this->plan($site, $metadata));
      $this->assertTrue($result->ok, json_encode($result->errors));
      $this->assertSame($type, $result->normalizedPlan['page_settings']['source_sync']['type']);
    }
    $metadata['api_token'] = 'synthetic-extra-value';
    $result = $service->apply($this->plan($site, $metadata));
    $this->assertFalse($result->ok);
    $this->assertContains('plan.page.settings.source_sync', array_column($result->errors, 'path'));
    $this->assertStringNotContainsString('synthetic-extra-value', json_encode($result->toArray()));
    $this->assertSame(0, Page::count());
  }

  #[Test]
  #[DataProvider('unsafeValues')]
  public function credential_values_and_private_environment_files_are_rejected_without_echoing_them(string $field, string $value): void
  {
    $site = $this->site();
    $metadata = $this->metadata('reference.md');
    $metadata[$field] = $value;
    $service = app(InternalContentPlanService::class);
    $plan = $this->plan($site, $metadata);

    foreach ([$service->validate($plan), $service->apply($plan)] as $result) {
      $this->assertFalse($result->ok);
      $this->assertContains('plan.page.settings.source_sync.'.$field, array_column($result->errors, 'path'));
      $this->assertStringNotContainsString(json_encode($value), json_encode($result->toArray()));
    }
    $this->assertSame(0, Page::count());
  }

  public static function unsafeValues(): array
  {
    return [
      'assigned token' => ['source_id', 'token=synthetic-value'],
      'assigned password' => ['source_id', 'password:synthetic-value'],
      'assigned environment secret' => ['type', 'CLIENT_SECRET=synthetic-value'],
      'assigned application key' => ['source_id', 'APP_KEY=base64:'.base64_encode(str_repeat('a', 32))],
      'JSON secret' => ['source_id', '{"api_key":"synthetic-value"}'],
      'bearer authorization' => ['source_id', 'Bearer '.str_repeat('a', 40)],
      'basic authorization' => ['type', 'Basic '.base64_encode('synthetic:credential')],
      'private key' => ['source_id', '-----BEGIN PRIVATE KEY-----synthetic-----END PRIVATE KEY-----'],
      'native CMS token' => ['source_id', 'wbcms_'.str_repeat('a', 64)],
      'Stripe-shaped secret' => ['source_id', 'sk_live_'.str_repeat('a', 32)],
      'GitHub-shaped token' => ['source_id', 'ghp_'.str_repeat('a', 36)],
      'JWT-shaped token' => ['source_id', 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJmaXh0dXJlIn0.c3ludGhldGljLXNpZ25hdHVyZQ'],
      'query credential' => ['source_path', 'docs/reference.md?access_token=synthetic-value'],
      'URL userinfo' => ['source_path', 'https://reader:synthetic@example.test/reference.md'],
      'private environment file' => ['source_path', '.env'],
      'nested environment file' => ['source_path', 'docs/.env.production'],
      'environment source identity' => ['source_id', 'manual:docs/.env'],
    ];
  }

  #[Test]
  #[DataProvider('invalidPaths')]
  public function path_and_string_boundaries_remain_enforced(string $path): void
  {
    $site = $this->site();
    $metadata = $this->metadata('reference.md');
    $metadata['source_path'] = $path;
    $result = app(InternalContentPlanService::class)->validate($this->plan($site, $metadata));
    $this->assertFalse($result->ok);
    $this->assertContains('plan.page.settings.source_sync.source_path', array_column($result->errors, 'path'));
    $this->assertSame(0, Page::count());
  }

  public static function invalidPaths(): array
  {
    return [
      'absolute' => ['/docs/reference.md'], 'parent traversal' => ['../reference.md'],
      'nested traversal' => ['docs/../reference.md'], 'backslash' => ['docs\\reference.md'],
      'empty' => [''], 'control' => ["docs/reference\0.md"], 'length' => [str_repeat('a', 241)],
    ];
  }

  private function site(): Site
  {
    $locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site = Site::create(['handle' => 'manual', 'name' => 'Synthetic manuals', 'is_primary' => true]);
    $site->locales()->sync([$locale->id => ['is_enabled' => true]]);
    $slot = SlotType::create(['slug' => 'main', 'name' => 'Main', 'status' => 'published']);
    $layout = PageLayout::create(['handle' => 'default', 'name' => 'Default', 'is_active' => true]);
    PageLayoutSlot::create(['page_layout_id' => $layout->id, 'slot_type_id' => $slot->id, 'slot_name' => 'main', 'is_active' => true]);
    BlockType::create(['slug' => 'plain_text', 'name' => 'Plain text', 'source_type' => 'static', 'status' => 'published']);

    return $site;
  }

  private function metadata(string $name): array
  {
    return [
      'type' => 'markdown_documentation', 'source_id' => 'manual:docs/'.$name, 'source_path' => 'docs/'.$name,
      'source_sha256' => hash('sha256', 'Synthetic documentation'), 'managed_slots' => ['main'], 'last_synced_at' => '2026-11-08T12:00:00+00:00',
    ];
  }

  private function plan(Site $site, array $metadata): array
  {
    return ['plan' => [
      'site' => $site->id, 'locale' => 'en', 'layout' => 'default',
      'page' => ['title' => 'Source sync', 'path' => '/source-sync', 'settings' => ['source_sync' => $metadata]],
      'slots' => ['main' => [['type' => 'plain_text', 'translations' => ['content' => 'Synthetic documentation']]]],
    ]];
  }
}
