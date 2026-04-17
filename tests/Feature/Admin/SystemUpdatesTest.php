<?php

namespace Tests\Feature\Admin;

use App\Models\SystemUpdateRun;
use App\Models\User;
use App\Support\System\InstalledVersionStore;
use App\Support\System\SystemUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemUpdatesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function updates_page_shows_update_available_state(): void
    {
        config()->set('app.version', '0.1.0');
        config()->set('cms.latest_version', '0.1.1');
        config()->set('cms.release_notes.0.1.1', ['Added update center.']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

        $response->assertOk();
        $response->assertSee('System Updates');
        $response->assertSee('0.1.0');
        $response->assertSee('0.1.1');
        $response->assertSee('Update available');
        $response->assertSee('Added update center.');
        $response->assertSee('Update now');
    }

    #[Test]
    public function updates_page_shows_up_to_date_state(): void
    {
        config()->set('app.version', '0.1.1');
        config()->set('cms.latest_version', '0.1.1');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

        $response->assertOk();
        $response->assertSee('Up to date');
        $response->assertSee('Up to date');
        $response->assertSee('Up to date</button>', false);
    }

    #[Test]
    public function backup_confirmation_is_required_before_running_update(): void
    {
        config()->set('app.version', '0.1.0');
        config()->set('cms.latest_version', '0.1.1');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.system.updates.run'), []);

        $response->assertSessionHasErrors('confirm_backup');
    }

    #[Test]
    public function update_run_success_path_records_run_and_shows_success_message(): void
    {
        config()->set('app.version', '0.1.0');
        config()->set('cms.latest_version', '0.1.1');

        $user = User::factory()->create();

        $mock = Mockery::mock(SystemUpdater::class);
        $mock->shouldReceive('run')->once()->andReturn([
            'run' => SystemUpdateRun::query()->create([
                'from_version' => '0.1.0',
                'to_version' => '0.1.1',
                'status' => 'success',
                'output' => 'Updated APP_VERSION to 0.1.1.',
            ]),
            'output' => ['Updated APP_VERSION to 0.1.1.'],
        ]);

        $this->app->instance(SystemUpdater::class, $mock);

        $response = $this->actingAs($user)->post(route('admin.system.updates.run'), [
            'confirm_backup' => '1',
        ]);

        $response->assertRedirect(route('admin.system.updates.index'));

        $followUp = $this->actingAs($user)->get(route('admin.system.updates.index'));
        $followUp->assertSee('Update completed successfully.');
        $followUp->assertSee('Updated APP_VERSION to 0.1.1.');
    }

    #[Test]
    public function update_run_failure_path_shows_error_and_leaves_app_accessible(): void
    {
        config()->set('app.version', '0.1.0');
        config()->set('cms.latest_version', '0.1.1');

        $user = User::factory()->create();

        SystemUpdateRun::query()->create([
            'from_version' => '0.1.0',
            'to_version' => '0.1.1',
            'status' => 'failed',
            'output' => 'Update failed: migrate failed.',
        ]);

        $mock = Mockery::mock(SystemUpdater::class);
        $mock->shouldReceive('run')->once()->andThrow(new \RuntimeException('migrate failed.'));
        $this->app->instance(SystemUpdater::class, $mock);

        $response = $this->actingAs($user)->from(route('admin.system.updates.index'))->post(route('admin.system.updates.run'), [
            'confirm_backup' => '1',
        ]);

        $response->assertRedirect(route('admin.system.updates.index'));

        $followUp = $this->actingAs($user)->get(route('admin.system.updates.index'));
        $followUp->assertSee('Update Failed');
        $followUp->assertSee('migrate failed.');
        $followUp->assertSee('Update failed: migrate failed.');
    }

    #[Test]
    public function installed_version_store_updates_existing_env_line(): void
    {
        $envPath = base_path('.env');
        $originalContents = file_exists($envPath) ? file_get_contents($envPath) : false;

        try {
            file_put_contents($envPath, "APP_NAME=WebBlocks CMS\nAPP_VERSION=0.1.0\n");

            app(InstalledVersionStore::class)->update('0.1.1');

            $this->assertStringContainsString('APP_VERSION=0.1.1', (string) file_get_contents($envPath));
        } finally {
            if ($originalContents === false) {
                @unlink($envPath);
            } else {
                file_put_contents($envPath, $originalContents);
            }
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
