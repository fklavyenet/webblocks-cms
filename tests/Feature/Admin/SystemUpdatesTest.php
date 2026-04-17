<?php

namespace Tests\Feature\Admin;

use App\Models\SystemSetting;
use App\Models\SystemUpdateRun;
use App\Models\User;
use App\Support\System\InstalledVersionStore;
use App\Support\System\RemoteManifestUpdateSource;
use App\Support\System\SystemUpdateInspector;
use App\Support\System\SystemUpdater;
use App\Support\System\UpdateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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
        config()->set('cms.update.source', 'local');
        config()->set('cms.latest_version', '0.1.1');
        config()->set('cms.release_notes.0.1.1', ['Added update center.']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

        $response->assertOk();
        $response->assertSee('System Updates');
        $response->assertSee('0.1.0');
        $response->assertSee('0.1.1');
        $response->assertSee('Ready to update');
        $response->assertSee('Added update center.');
        $response->assertSee('Local');
        $response->assertSee('stable');
        $response->assertSee('Update now');
        $response->assertSee('Diagnostics');
        $response->assertSee('Database connection');
        $response->assertSee('Version persistence');
    }

    #[Test]
    public function updates_page_shows_up_to_date_state(): void
    {
        config()->set('app.version', '0.1.1');
        config()->set('cms.update.source', 'local');
        config()->set('cms.latest_version', '0.1.1');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

        $response->assertOk();
        $response->assertSee('Already up to date');
        $response->assertSee('This install already matches the latest configured CMS version.');
        $response->assertDontSee('Update now');
    }

    #[Test]
    public function updates_page_still_loads_when_update_log_table_is_missing(): void
    {
        config()->set('app.version', '0.1.0');
        config()->set('cms.update.source', 'local');
        config()->set('cms.latest_version', '0.1.1');

        Schema::drop('system_update_runs');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

        $response->assertOk();
        $response->assertSee('System Updates');
        $response->assertDontSee('Latest Update Run');
    }

    #[Test]
    public function check_again_action_refreshes_status_page(): void
    {
        config()->set('cms.update.source', 'local');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.system.updates.check'));

        $response->assertRedirect(route('admin.system.updates.index'));

        $followUp = $this->actingAs($user)->get(route('admin.system.updates.index'));
        $followUp->assertSee('Update status refreshed.');
        $followUp->assertSee('Last checked at');
    }

    #[Test]
    public function update_is_blocked_when_version_persistence_diagnostic_fails(): void
    {
        config()->set('app.version', '0.1.0');
        config()->set('cms.update.source', 'local');
        config()->set('cms.latest_version', '0.1.1');

        Schema::drop('system_settings');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->from(route('admin.system.updates.index'))->post(route('admin.system.updates.run'), [
            'confirm_backup' => '1',
        ]);

        $response->assertRedirect(route('admin.system.updates.index'));

        $followUp = $this->actingAs($user)->get(route('admin.system.updates.index'));
        $followUp->assertSee('Update Failed');
        $followUp->assertSee('Resolve the blocked diagnostics before running the update.');
    }

    #[Test]
    public function updates_page_can_show_maintenance_mode_blocked_state(): void
    {
        $user = User::factory()->create();

        $mock = Mockery::mock(SystemUpdateInspector::class);
        $mock->shouldReceive('report')->andReturn([
            'checked_at' => now(),
            'version' => [
                'up_to_date' => false,
                'current_version' => '0.1.0',
                'latest_version' => '0.1.1',
                'release_notes' => [],
                'channel' => 'stable',
                'published_at' => null,
                'source' => 'local',
                'fallback_warning' => null,
                'last_checked_at' => now(),
            ],
            'diagnostics' => [],
            'eligibility' => [
                'state' => 'maintenance_mode',
                'label' => 'Maintenance mode active',
                'message' => 'Bring the application back up before starting a new update run.',
                'can_update' => false,
                'badge_class' => 'wb-status-danger',
            ],
        ]);

        $this->app->instance(SystemUpdateInspector::class, $mock);

        $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

        $response->assertOk();
        $response->assertSee('Maintenance mode active');
    }

    #[Test]
    public function backup_confirmation_is_required_before_running_update(): void
    {
        config()->set('app.version', '0.1.0');
        config()->set('cms.update.source', 'local');
        config()->set('cms.latest_version', '0.1.1');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.system.updates.run'), []);

        $response->assertSessionHasErrors('confirm_backup');
    }

    #[Test]
    public function update_run_success_path_records_run_and_shows_success_message(): void
    {
        config()->set('app.version', '0.1.0');
        config()->set('cms.update.source', 'local');
        config()->set('cms.latest_version', '0.1.1');

        $user = User::factory()->create();

        $mock = Mockery::mock(SystemUpdater::class);
        $mock->shouldReceive('run')->once()->andReturn([
            'run' => SystemUpdateRun::query()->create([
                'from_version' => '0.1.0',
                'to_version' => '0.1.1',
                'status' => 'success_with_warnings',
                'summary' => 'Update completed with warnings.',
                'output' => 'Persisted installed version 0.1.1 in system settings.',
                'warning_count' => 1,
                'started_at' => now()->subSecond(),
                'finished_at' => now(),
                'duration_ms' => 200,
                'triggered_by_user_id' => $user->id,
            ]),
            'output' => ['Persisted installed version 0.1.1 in system settings.'],
            'warnings' => ['cache:clear warning: Cache store failed.'],
            'summary' => 'Update completed with warnings.',
        ]);

        $this->app->instance(SystemUpdater::class, $mock);

        $response = $this->actingAs($user)->post(route('admin.system.updates.run'), [
            'confirm_backup' => '1',
        ]);

        $response->assertRedirect(route('admin.system.updates.index'));

        $followUp = $this->actingAs($user)->get(route('admin.system.updates.index'));
        $followUp->assertSee('Update completed with warnings.');
        $followUp->assertSee('Persisted installed version 0.1.1 in system settings.');
        $followUp->assertSee('Warnings: 1');
    }

    #[Test]
    public function update_run_failure_path_shows_error_and_leaves_app_accessible(): void
    {
        config()->set('app.version', '0.1.0');
        config()->set('cms.update.source', 'local');
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
        config()->set('app.version', '0.1.0');

        app(InstalledVersionStore::class)->persist('0.1.1');

        $this->assertSame('0.1.1', SystemSetting::query()->where('key', InstalledVersionStore::VERSION_KEY)->value('value'));
        $this->assertSame('0.1.1', app(InstalledVersionStore::class)->currentVersion());
    }

    #[Test]
    public function installed_version_store_falls_back_to_config_when_no_persisted_value_exists(): void
    {
        config()->set('app.version', '0.1.0');

        $this->assertSame('0.1.0', app(InstalledVersionStore::class)->currentVersion());
    }

    #[Test]
    public function remote_manifest_success_is_used_when_remote_source_is_enabled(): void
    {
        config()->set('app.version', '0.1.0');
        config()->set('cms.update.source', 'remote');
        config()->set('cms.update.manifest_url', 'https://updates.example.test/webblocks-cms/manifest.json');
        config()->set('cms.update.channel', 'stable');

        Http::fake([
            'https://updates.example.test/webblocks-cms/manifest.json' => Http::response([
                'version' => '0.1.2',
                'channel' => 'stable',
                'release_notes' => ['Added System Updates V2.'],
                'published_at' => '2026-04-18T12:00:00Z',
            ]),
        ]);

        $status = app(UpdateChecker::class)->status();

        $this->assertSame('0.1.2', $status['latest_version']);
        $this->assertSame('remote', $status['source']);
        $this->assertSame('stable', $status['channel']);
        $this->assertSame(['Added System Updates V2.'], $status['release_notes']);
        $this->assertNull($status['fallback_warning']);
    }

    #[Test]
    public function remote_manifest_failure_falls_back_to_local_source(): void
    {
        config()->set('app.version', '0.1.0');
        config()->set('cms.update.source', 'remote');
        config()->set('cms.update.manifest_url', 'https://updates.example.test/webblocks-cms/manifest.json');
        config()->set('cms.latest_version', '0.1.1');
        config()->set('cms.release_notes.0.1.1', ['Local fallback notes.']);

        Http::fake([
            'https://updates.example.test/webblocks-cms/manifest.json' => Http::response([], 500),
        ]);

        $status = app(UpdateChecker::class)->status(true);

        $this->assertSame('0.1.1', $status['latest_version']);
        $this->assertSame('local', $status['source']);
        $this->assertSame(['Local fallback notes.'], $status['release_notes']);
        $this->assertSame('Could not reach update server. Using local update data.', $status['fallback_warning']);
    }

    #[Test]
    public function remote_manifest_response_is_cached(): void
    {
        config()->set('cms.update.source', 'remote');
        config()->set('cms.update.manifest_url', 'https://updates.example.test/webblocks-cms/manifest.json');

        Http::fake([
            'https://updates.example.test/webblocks-cms/manifest.json' => Http::response([
                'version' => '0.1.2',
                'channel' => 'stable',
                'release_notes' => ['Cached notes.'],
                'published_at' => '2026-04-18T12:00:00Z',
            ]),
        ]);

        Cache::forget(RemoteManifestUpdateSource::CACHE_KEY);

        $checker = app(UpdateChecker::class);

        $checker->status(true);
        $checker->status();

        Http::assertSentCount(1);
    }

    #[Test]
    public function channel_mismatch_uses_local_fallback(): void
    {
        config()->set('cms.update.source', 'remote');
        config()->set('cms.update.manifest_url', 'https://updates.example.test/webblocks-cms/manifest.json');
        config()->set('cms.update.channel', 'stable');
        config()->set('cms.latest_version', '0.1.1');

        Http::fake([
            'https://updates.example.test/webblocks-cms/manifest.json' => Http::response([
                'version' => '0.2.0-beta1',
                'channel' => 'beta',
                'release_notes' => ['Beta note.'],
                'published_at' => '2026-04-18T12:00:00Z',
            ]),
        ]);

        $status = app(UpdateChecker::class)->status(true);

        $this->assertSame('local', $status['source']);
        $this->assertStringContainsString('does not match configured channel stable', (string) $status['fallback_warning']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
