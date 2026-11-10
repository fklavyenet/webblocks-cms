<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Media\RemoteMediaFetcher;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

class MediaMetadataLimitsTest extends TestCase
{
  private const LIMITS = ['title' => 255, 'alt_text' => 255, 'caption' => 2000, 'description' => 2000];

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function api_patch_counts_characters_and_rejects_the_whole_update_instead_of_truncating(): void
  {
    $media = $this->media();
    $url = '/webadmin/api/media/'.$media->id;
    $headers = $this->apiHeaders('media.write');
    $atLimit = array_map(fn (int $limit): string => str_repeat('ü', $limit), self::LIMITS);

    $this->patchJson($url, $atLimit, $headers)->assertOk();
    foreach (self::LIMITS as $field => $limit) {
      $this->patchJson($url, [$field => str_repeat('ü', $limit + 1)] + ['title' => 'Must not be saved'], $headers)
        ->assertUnprocessable()->assertJsonPath('code', 'invalid_media_metadata')->assertJsonPath('errors.0.path', $field);
    }

    $this->assertSame($atLimit, $media->fresh()->only(array_keys(self::LIMITS)));
  }

  #[Test]
  public function panel_and_api_preserve_old_copy_when_it_is_resubmitted_or_omitted(): void
  {
    $caption = '  '.str_repeat('ü', 2001)."\nSecond line  ";
    $description = str_repeat('D', 2001);
    $media = $this->media(['caption' => $caption, 'description' => $description]);
    $this->signInToPanel();
    $panel = route('admin.media.update', $media);
    $api = '/webadmin/api/media/'.$media->id;
    $headers = $this->apiHeaders('media.write');

    $this->put($panel, ['title' => 'Panel title', 'caption' => str_replace("\n", "\r\n", trim($caption)), 'description' => $description])
      ->assertRedirectToRoute('admin.media.index')->assertSessionHasNoErrors();
    $this->assertSame('Panel title', $media->fresh()->title);
    $this->putJson($panel, ['title' => 'Must not be saved', 'caption' => $caption.'changed'])
      ->assertUnprocessable()->assertJsonValidationErrors('caption');
    $this->assertSame('Panel title', $media->fresh()->title);

    $this->patchJson($api, ['title' => 'API title', 'description' => $description], $headers)->assertOk();
    $media->refresh();
    $this->assertSame('API title', $media->title);
    $this->assertSame($caption, $media->caption);
    $this->assertSame($description, $media->description);

    $this->patchJson($api, ['caption' => 'Shorter caption', 'description' => null], $headers)->assertOk();
    $this->assertSame('Shorter caption', $media->fresh()->caption);
    $this->assertNull($media->fresh()->description);
  }

  #[Test]
  public function panel_and_api_validate_uploads_and_remote_imports_before_side_effects(): void
  {
    $disk = Storage::fake('public');
    $this->mock(RemoteMediaFetcher::class)->shouldNotReceive('fetch');
    $this->signInToPanel();
    $headers = $this->apiHeaders('media.upload');
    $file = UploadedFile::fake()->image('photo.png');
    $tooLong = str_repeat('a', 2001);
    $panelHeaders = ['Accept' => 'application/json'];

    $this->post(route('admin.media.store'), ['file' => $file, 'caption' => $tooLong], $panelHeaders)
      ->assertUnprocessable()->assertJsonValidationErrors('caption');
    $this->post('/webadmin/api/media', ['file' => $file, 'description' => $tooLong], $headers)
      ->assertUnprocessable()->assertJsonPath('errors.0.path', 'description');
    $remote = ['source_url' => 'https://media.example.test/photo.png', 'description' => $tooLong];
    $this->postJson(route('admin.media.fetch'), $remote)->assertUnprocessable()->assertJsonValidationErrors('description');
    $this->postJson('/webadmin/api/media/fetch', $remote, $headers)->assertUnprocessable()->assertJsonPath('errors.0.path', 'description');
    $this->assertDatabaseEmpty('wbcms_media');
    $this->assertSame([], $disk->allFiles());

    $atLimit = array_map(fn (int $limit): string => str_repeat('ü', $limit), self::LIMITS);
    $this->post(route('admin.media.store'), ['file' => $file] + $atLimit, $panelHeaders)->assertCreated();
    $this->post('/webadmin/api/media', ['file' => UploadedFile::fake()->image('api.png')] + $atLimit, $headers)->assertCreated();
    $this->assertCount(2, Media::all());
    foreach (Media::all() as $media) {
      $this->assertSame($atLimit, $media->only(array_keys(self::LIMITS)));
    }
  }

  #[Test]
  public function replacement_keeps_the_existing_file_on_invalid_input_and_preserves_legacy_metadata_on_success(): void
  {
    $disk = Storage::fake('public');
    $description = str_repeat('d', 2001);
    $media = $this->media(['description' => $description]);
    $oldPath = $media->path;
    $disk->put($oldPath, 'Original file');
    $url = '/webadmin/api/media/'.$media->id.'/replace';
    $headers = $this->apiHeaders('media.replace');

    $this->post($url, ['file' => UploadedFile::fake()->image('replacement.png'), 'caption' => str_repeat('a', 2001)], $headers)
      ->assertUnprocessable()->assertJsonPath('errors.0.path', 'caption');
    $this->assertSame([$oldPath], $disk->allFiles());
    $this->assertSame($oldPath, $media->fresh()->path);

    $this->post($url, ['file' => UploadedFile::fake()->image('replacement.png'), 'description' => $description], $headers)
      ->assertOk()->assertJsonPath('media.id', $media->id);
    $disk->assertMissing($oldPath);
    $disk->assertExists($media->fresh()->path);
    $this->assertSame($description, $media->fresh()->description);
  }

  private function apiHeaders(string $capability): array
  {
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic media metadata', capabilities: [$capability])->plainToken;

    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
  }

  private function signInToPanel(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(MaintenanceTestUser::class, 'App\\Models\\User');
    }
    $class = 'App\\Models\\User';
    $this->actingAs($this->cmsActor((new $class)->forceFill(['role' => 'super_admin', 'is_active' => true])));
  }

  private function media(array $metadata = []): Media
  {
    return Media::create($metadata + [
      'disk' => 'public', 'path' => 'media/images/original.png', 'filename' => 'original.png',
      'kind' => Media::KIND_IMAGE, 'mime_type' => 'image/png', 'visibility' => 'public',
    ]);
  }
}
