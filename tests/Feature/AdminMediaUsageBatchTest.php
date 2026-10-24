<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockMedia;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Blocks\MobileBlockMedia;
use WebBlocks\Cms\Support\Media\MediaUsageResolver;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

class AdminMediaUsageBatchTest extends TestCase
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

  private function media(string $name): Media
  {
    return Media::create(['filename' => $name.'.txt', 'original_name' => $name.'.txt', 'title' => $name, 'disk' => 'public', 'path' => 'synthetic/'.$name.'.txt', 'kind' => 'document', 'mime_type' => 'text/plain', 'size' => 10]);
  }

  #[Test]
  public function all_six_usage_sources_preserve_order_labels_branding_precedence_and_site_visibility(): void
  {
    if (! class_exists(User::class)) {
      class_alias(MaintenanceTestUser::class, User::class);
    }
    $actor = $this->cmsActor(User::forceCreate(['name' => 'Reader', 'email' => 'reader@example.test', 'password' => 'unused', 'role' => 'editor', 'is_active' => true]));
    $locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $first = $this->media('first');
    $second = $this->media('second');
    $unused = $this->media('unused');
    $site = Site::create(['name' => 'Northstar', 'handle' => 'northstar', 'favicon_media_id' => $first->id, 'social_image_media_id' => $first->id]);
    Site::create(['name' => 'Harbor', 'handle' => 'harbor', 'favicon_media_id' => $second->id, 'social_image_media_id' => $first->id]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Reference page', 'slug' => 'reference', 'status' => 'draft']);
    $type = BlockType::create(['name' => 'Download', 'slug' => 'download', 'status' => 'published']);
    $block = Block::create(['page_id' => $page->id, 'block_type_id' => $type->id, 'media_id' => $first->id, 'title' => 'Reference download', 'status' => 'draft']);
    foreach (['gallery_item', 'attachment', MobileBlockMedia::ROLE] as $role) {
      BlockMedia::create(['block_id' => $block->id, 'media_id' => $first->id, 'role' => $role]);
    }
    $page->translations()->where('locale_id', $locale->id)->update(['og_image_media_id' => $first->id]);
    $resolver = app(MediaUsageResolver::class);
    $media = collect([$first, $second, $unused]);
    $all = $resolver->resolveMany($media);
    $this->assertCount(7, $all[$first->id]);
    $this->assertSame(['Download block', 'Gallery block', 'Button attachment'], $all[$first->id]->pluck('context')->take(3)->all());
    $this->assertSame(['Site favicon', 'Site social image', 'SEO social image'], $all[$first->id]->pluck('context')->slice(4)->values()->all());
    $this->assertSame('Reference page', $all[$first->id]->first()['page_title']);
    $this->assertSame('Reference download', $all[$first->id]->first()['label']);
    $this->assertSame('Site favicon', $all[$second->id]->sole()['context']);
    $this->assertTrue($all[$unused->id]->isEmpty());
    $this->assertSame($all[$first->id]->all(), $resolver->resolve($first)->all());
    $role = CmsRole::create(['name' => 'Media reader', 'handle' => 'custom-media-reader']);
    foreach (['cms.panel.access', 'cms.media.read'] as $permission) {
      DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $role->id, 'permission' => $permission]);
    }
    $actor->sites()->attach($site, ['role_id' => $role->id]);
    $visible = $resolver->resolveMany($media, $actor);
    $this->assertCount(6, $visible[$first->id]);
    $this->assertTrue($visible[$second->id]->isEmpty());
    foreach ($visible[$first->id] as $usage) {
      $this->assertArrayNotHasKey('_site_id', $usage);
      $this->assertArrayNotHasKey('_media_id', $usage);
      $this->assertNotSame('Harbor', $usage['label']);
    }
    DB::table('wbcms_acl_role_permissions')->where('role_id', $role->id)->where('permission', 'cms.media.read')->delete();
    $this->assertTrue($resolver->resolveMany($media, $actor)->every(fn ($usages) => $usages->isEmpty()));
  }

  #[Test]
  public function unused_media_batch_queries_do_not_grow_per_visible_row(): void
  {
    $media = collect(range(1, 25))->map(fn ($i) => $this->media('unused-'.$i));
    $resolver = app(MediaUsageResolver::class);
    $resolver->resolveMany($media->take(1));
    $single = $this->queries(fn () => $resolver->resolveMany($media->take(1)));
    $batch = $this->queries(function () use ($resolver, $media): void {
      $resolved = $resolver->resolveMany($media);
      $this->assertCount(25, $resolved);
      $this->assertTrue($resolved->every(fn ($usages) => $usages->isEmpty()));
    });
    $this->assertCount(6, $single);
    $this->assertCount(count($single), $batch);
    $this->assertSame([], $this->queries(fn () => $this->assertTrue($resolver->resolveMany(collect())->isEmpty())));
  }

  #[Test]
  public function used_media_loads_page_titles_and_block_types_in_batches(): void
  {
    Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site = Site::create(['name' => 'Northstar', 'handle' => 'northstar']);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Shared reference page', 'slug' => 'reference', 'status' => 'draft']);
    $type = BlockType::create(['name' => 'Text', 'slug' => 'text', 'status' => 'published']);
    $media = collect(range(1, 25))->map(function ($i) use ($page, $type): Media {
      $media = $this->media('used-'.$i);
      Block::create(['page_id' => $page->id, 'block_type_id' => $type->id, 'media_id' => $media->id, 'title' => 'Reference '.$i, 'status' => 'draft']);

      return $media;
    });
    $resolver = app(MediaUsageResolver::class);
    $resolver->resolveMany($media->take(1));
    $single = $this->queries(fn () => $resolver->resolveMany($media->take(1)));
    $batch = $this->queries(function () use ($resolver, $media): void {
      $rows = $resolver->resolveMany($media);
      $this->assertCount(25, $rows);
      foreach ($rows as $usages) {
        $this->assertCount(1, $usages);
        $this->assertSame('Shared reference page', $usages->sole()['page_title']);
        $this->assertSame('Text', $usages->sole()['context']);
      }
    });
    $this->assertLessThanOrEqual(count($single) + 5, count($batch));
  }

  private function queries(callable $work): array
  {
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
      $work();

      return DB::getQueryLog();
    } finally {
      DB::disableQueryLog();
    }
  }
}
