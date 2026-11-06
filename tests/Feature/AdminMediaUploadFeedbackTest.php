<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

class AdminMediaUploadFeedbackTest extends TestCase
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

  public function test_picker_receipt_confirms_a_stored_upload_and_native_form_still_redirects(): void
  {
    $this->actingAs($this->cmsActor(new MaintenanceTestUser(['role' => 'super_admin'])));
    $disk = Storage::fake('public');
    $url = route('admin.media.store');

    $this->post($url, [], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->assertSame(0, Media::query()->count());

    $this->post($url, ['file' => UploadedFile::fake()->image('picker.png')], ['Accept' => 'application/json'])
      ->assertCreated()->assertExactJson(['uploaded' => true]);
    $disk->assertExists(Media::query()->where('original_name', 'picker.png')->sole()->path);

    $this->post($url, ['file' => UploadedFile::fake()->image('library.png')])
      ->assertRedirectToRoute('admin.media.index')->assertSessionHas('status');
    $disk->assertExists(Media::query()->where('original_name', 'library.png')->sole()->path);
  }
}
