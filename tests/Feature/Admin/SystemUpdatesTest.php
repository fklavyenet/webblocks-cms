<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use WebBlocks\Cms\Support\System\SystemUpdateInspector;
use WebBlocks\Cms\Support\System\Updates\UpdateCheckResult;
use WebBlocks\Cms\Support\System\Updates\UpdateCommandRunner;
use WebBlocks\Cms\Support\System\Updates\UpdateCommandRunner as PackageUpdateCommandRunner;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\System\Updates\UpdateServerClient;
use WebBlocks\Cms\Support\System\Updates\UpdateServerClient as PackageUpdateServerClient;
use WebBlocks\Cms\Support\WebBlocks;
use ZipArchive;

class SystemUpdatesTest extends TestCase
{
  use RefreshDatabase;

  private array $temporaryDirectories = [];

  private ?FakeUpdateCommandRunner $fakeCommandRunner = null;

  #[Test]
  public function system_updates_page_renders(): void
  {
    $user = User::factory()->superAdmin()->create();
    app(InstalledVersionStore::class)->persist('0.1.4');
    $this->mockClientResult('up_to_date', 'Already up to date', 'This install already matches the latest published release for the selected channel.', true, WebBlocks::version());

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();
    $response->assertSee('System Updates');
    $response->assertSee('Up to date');
    $response->assertSee('Current CMS Version');
    $response->assertSee(WebBlocks::version());
    $response->assertSee(WebBlocks::version());
    $response->assertDontSee('Latest Published Version');
    $response->assertSee('Update Status');
    $response->assertSee('Release');
    $response->assertSee('Readiness');
    $response->assertSee('Update History');
    $response->assertDontSee('<strong>Actions</strong>', false);
    $response->assertSee('Check again');
    $response->assertDontSee('Recent Backup');
    $response->assertSee('Readiness');
    $response->assertSee('WebBlocks CMS v'.WebBlocks::version());
    $response->assertSee('data-webblocks-updates-layout="standard"', false);
    $response->assertSee('data-webblocks-updates-card="summary"', false);
    $response->assertSee('data-webblocks-updates-card="safety-summary"', false);
    $response->assertSee('data-webblocks-updates-card="details"', false);
    $response->assertSee('data-webblocks-updates-card="history"', false);
    $response->assertDontSee('data-webblocks-updates-card="install"', false);
    $response->assertDontSee('data-webblocks-updates-card="diagnostics"', false);
    $response->assertDontSee('data-webblocks-updates-accordion="package-safety"', false);
    $response->assertSee('Download support report');
    $response->assertSeeInOrder([
      'data-webblocks-updates-card="summary"',
      'Update Status',
      'data-webblocks-updates-card="safety-summary"',
      'data-webblocks-updates-card="details"',
      'Release',
      'Readiness',
      'data-webblocks-updates-card="history"',
      'Update History',
    ], false);
    $response->assertDontSee('<details', false);
    $response->assertDontSee('<summary', false);
    $this->assertFalse(File::isDirectory(base_path('.github')));

    $installHtml = $this->cardHtml($response->getContent(), 'Update Status');
    $this->assertStringNotContainsString('Stored installed version', $installHtml);
    $this->assertStringNotContainsString('Effective installed version', $installHtml);
    $this->assertStringNotContainsString('Source checkout notice', $installHtml);
    $this->assertStringNotContainsString('Published at', $installHtml);
  }

  #[Test]
  public function disabled_client_state_is_honest_when_no_installed_version_has_been_recorded_yet(): void
  {
    $user = User::factory()->superAdmin()->create();

    config()->set('webblocks-updates.enabled', false);
    config()->set('app.version', '0.1.8');

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();
    $response->assertSee('Update server unavailable / invalid response');
    $response->assertSee(WebBlocks::version());
    $response->assertSee('The CMS update client is disabled in configuration.');
  }

  #[Test]
  public function check_flow_shows_correct_state_from_mocked_client_response(): void
  {
    $user = User::factory()->superAdmin()->create();
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

    $response = $this->actingAs($user)->get(route('admin.system.updates.check'));

    $response->assertRedirect(route('admin.system.updates.index'));
    $response->assertSessionHas('status', 'Update 99.0.0 is available.');

    $followUp = $this->actingAs($user)->get(route('admin.system.updates.index'));
    $followUp->assertSee('Update available');
    $followUp->assertSee('Install update');
    $followUp->assertDontSee('Download package');
    $followUp->assertSee('Check again');
    $followUp->assertSee('Latest Published Version');
    $followUp->assertSee('A pre-update backup will be created automatically before installation.');
    $followUp->assertSee('Download the backup before installation starts');
    $followUp->assertDontSee('Automatic backup is not created before update in this version.');
  }

  #[Test]
  public function update_available_state_renders_summary_and_install_update(): void
  {
    $user = User::factory()->superAdmin()->create();
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '99.0.0');

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();
    $response->assertSee('Update Status');
    $response->assertSee('A newer published release is available from the configured update server.');
    $response->assertSee('Current CMS Version');
    $response->assertSee('Latest Published Version');
    $response->assertSee(WebBlocks::version());
    $response->assertSee('99.0.0');
    $response->assertSee('Install update');
    $response->assertDontSee('<strong>Actions</strong>', false);

    $html = $response->getContent();
    $installHtml = $this->cardHtml($html, 'Update Status');
    $optionsHeaderPosition = strpos($html, '<h2 class="wb-card-title">Update Status</h2>');
    $buttonPosition = strpos($html, 'data-default-label="Install update"');

    $this->assertStringContainsString('Current CMS Version', $installHtml);
    $this->assertStringContainsString('Latest Published Version', $installHtml);
    $this->assertStringContainsString('Published Date', $installHtml);
    $this->assertIsInt($optionsHeaderPosition);
    $this->assertIsInt($buttonPosition);
    $this->assertGreaterThan($optionsHeaderPosition, $buttonPosition);
  }

  #[Test]
  public function install_update_card_renders_update_action_after_backup_option_when_update_is_available(): void
  {
    $user = User::factory()->superAdmin()->create();
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '99.0.0');

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();
    $response->assertSee('<h2 class="wb-card-title">Update Status</h2>', false);
    $response->assertSee('Package Safety Details');
    $response->assertSee('data-webblocks-updates-accordion="package-safety"', false);
    $response->assertSee('Download the backup before installation starts');
    $response->assertSee('A pre-update backup will be created automatically before installation.');
    $response->assertSee('Install update');

    $html = $response->getContent();
    $optionsHeaderPosition = strpos($html, '<h2 class="wb-card-title">Update Status</h2>');
    $checkboxPosition = strpos($html, 'name="download_pre_update_backup"', $optionsHeaderPosition);
    $backupHelpPosition = strpos($html, 'A pre-update backup will be created automatically before installation.', $optionsHeaderPosition);
    $buttonPosition = strpos($html, 'data-default-label="Install update"', $optionsHeaderPosition);
    $detailsPosition = strpos($html, '<h2 class="wb-card-title">Release</h2>');

    $this->assertIsInt($optionsHeaderPosition);
    $this->assertIsInt($checkboxPosition);
    $this->assertIsInt($backupHelpPosition);
    $this->assertIsInt($buttonPosition);
    $this->assertIsInt($detailsPosition);
    $this->assertLessThan($checkboxPosition, $buttonPosition);
    $this->assertLessThan($checkboxPosition, $backupHelpPosition);
    $this->assertGreaterThan($optionsHeaderPosition, $detailsPosition);
    $this->assertGreaterThan($optionsHeaderPosition, $buttonPosition);
  }

  #[Test]
  public function updates_page_renders_structured_release_metadata(): void
  {
    $user = User::factory()->superAdmin()->create();
    $this->mockClientResult(
      state: 'update_available',
      label: 'Update available',
      message: 'A newer published release is available from the configured update server.',
      releaseOverrides: [
        'release_details' => [
          'title' => 'Operator-friendly release details',
          'summary' => 'This release improves update review before installation.',
          'groups' => [
            [
              'key' => 'highlights',
              'label' => 'Highlights',
              'items' => ['Richer System Updates summaries', 'Grouped release metadata'],
            ],
            [
              'key' => 'fixes',
              'label' => 'Fixes',
              'items' => ['Keeps low-level package details collapsed'],
            ],
            [
              'key' => 'compatibility_notes',
              'label' => 'Compatibility notes',
              'items' => ['Requires a package-native WebBlocks CMS install.'],
            ],
            [
              'key' => 'migration_notes',
              'label' => 'Migration notes',
              'items' => ['Runs package update migrations after extraction.'],
            ],
            [
              'key' => 'asset_notes',
              'label' => 'Asset notes',
              'items' => ['Refreshes shipped CMS static assets.'],
            ],
            [
              'key' => 'operator_notes',
              'label' => 'Operator notes',
              'items' => ['Read the details before running Update now'],
            ],
            [
              'key' => 'technical_notes',
              'label' => 'Technical notes',
              'items' => ['Artifact checksum remains verified before install.'],
            ],
          ],
          'fallback_notes' => [],
          'has_notes' => true,
        ],
      ],
    );

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();
    $response->assertDontSee('<strong>Release Preview</strong>', false);
    $response->assertSee('data-webblocks-updates-panel="release"', false);
    $response->assertSee('<h2 class="wb-card-title">Release</h2>', false);
    $response->assertDontSee('What&#039;s included', false);
    $response->assertSee('Operator-friendly release details');
    $response->assertSee('This release improves update review before installation.');
    $response->assertSee('Highlights');
    $response->assertSee('Richer System Updates summaries');
    $response->assertSee('Fixes and changes');
    $response->assertSee('Keeps low-level package details collapsed');
    $response->assertSee('Compatibility notes');
    $response->assertSee('Requires a package-native WebBlocks CMS install.');
    $response->assertSee('Migration notes');
    $response->assertSee('Runs package update migrations after extraction.');
    $response->assertSee('Asset notes');
    $response->assertSee('Refreshes shipped CMS static assets.');
    $response->assertSee('Operator notes');
    $response->assertSee('Read the details before running Update now');
    $response->assertSee('Technical notes');
    $response->assertSee('Artifact checksum remains verified before install.');
    $response->assertSee('Readiness');
    $response->assertDontSee('<summary><strong>Release notes</strong></summary>', false);
  }

  #[Test]
  public function updates_page_renders_plain_release_notes_fallback(): void
  {
    $user = User::factory()->superAdmin()->create();
    $this->mockClientResult(
      state: 'update_available',
      label: 'Update available',
      message: 'A newer published release is available from the configured update server.',
      releaseOverrides: [
        'description' => "Release v1.32.83 no build-chain boundary\nNo Vite, npm, or Tailwind assumptions return.",
        'changelog' => null,
        'release_details' => null,
      ],
    );

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();
    $response->assertDontSee('<strong>Release Preview</strong>', false);
    $response->assertSee('Release v1.32.83 no build-chain boundary');
    $response->assertSee('Release');
    $response->assertSee('No Vite, npm, or Tailwind assumptions return.');
  }

  #[Test]
  public function updates_page_renders_missing_release_notes_empty_state(): void
  {
    $user = User::factory()->superAdmin()->create();
    $this->mockClientResult(
      state: 'update_available',
      label: 'Update available',
      message: 'A newer published release is available from the configured update server.',
      releaseOverrides: [
        'description' => null,
        'changelog' => null,
        'release_details' => [
          'title' => null,
          'summary' => null,
          'groups' => [],
          'fallback_notes' => [],
          'has_notes' => false,
        ],
      ],
    );

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();
    $response->assertSee('No release notes were provided for this release.');
  }

  #[Test]
  public function updates_page_escapes_unsafe_release_metadata(): void
  {
    $user = User::factory()->superAdmin()->create();
    $this->mockClientResult(
      state: 'update_available',
      label: 'Update available',
      message: 'A newer published release is available from the configured update server.',
      releaseOverrides: [
        'release_details' => [
          'title' => '<script>alert("title")</script>',
          'summary' => '<strong>Summary</strong>',
          'groups' => [
            [
              'key' => 'highlights',
              'label' => 'Highlights',
              'items' => ['<script>alert("item")</script>'],
            ],
          ],
          'fallback_notes' => [],
          'has_notes' => true,
        ],
      ],
    );

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();
    $response->assertDontSee('<script>alert("title")</script>', false);
    $response->assertDontSee('<script>alert("item")</script>', false);
    $response->assertDontSee('<strong>Summary</strong>', false);
    $response->assertSee('&lt;script&gt;alert(&quot;title&quot;)&lt;/script&gt;', false);
    $response->assertSee('&lt;strong&gt;Summary&lt;/strong&gt;', false);
  }

  #[Test]
  public function page_can_show_update_server_unavailable_state(): void
  {
    $user = User::factory()->superAdmin()->create();
    $this->mockClientResult('server_unreachable', 'Update server unavailable', 'The update server could not be reached within the configured timeout.', false, null, null, 'server_unreachable', 'timeout');

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();
    $response->assertSee('Update server unavailable');
    $response->assertSee('Server detail');
    $response->assertSee('Readiness');
    $response->assertSee('Update Status');
    $response->assertDontSee('<strong>Actions</strong>', false);
  }

  #[Test]
  public function unavailable_update_states_do_not_show_enabled_update_now_button(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->mockClientResult('up_to_date', 'Already up to date', 'This install is already on the latest published release.', true, WebBlocks::version());

    $upToDateResponse = $this->actingAs($user)->get(route('admin.system.updates.index'));
    $upToDateResponse->assertOk();
    $upToDateResponse->assertSee('This install already matches the latest published release.');
    $upToDateResponse->assertSee('Up to date');
    $upToDateResponse->assertSee('Update Status');
    $upToDateResponse->assertSee('<h2 class="wb-card-title">Update Status</h2>', false);
    $upToDateResponse->assertSee('No newer release is ready for this install.');
    $upToDateResponse->assertDontSee('name="download_pre_update_backup"', false);
  }

  #[Test]
  public function update_summary_does_not_show_actionable_update_when_current_version_equals_latest_version(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, WebBlocks::version());

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();
    $response->assertSee('Up to date');
    $response->assertSee('This install already matches the latest published release.');
    $response->assertDontSee('Latest Published Version');
    $response->assertSee('No newer release is ready for this install.');
    $response->assertDontSee('data-default-label="Install update"', false);
    $response->assertDontSee('name="download_pre_update_backup"', false);
  }

  #[Test]
  public function local_version_newer_than_latest_published_release_is_not_actionable(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->mockClientResult('up_to_date', 'Already up to date', 'This install is newer than the latest published release for the selected channel.', true, '0.1.0');

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();
    $response->assertSee('Local/source version is newer');
    $response->assertSee('Current CMS Version');
    $response->assertSee('Latest Published Version');
    $response->assertSee('This codebase is ahead of the latest published package on the selected channel.');
    $response->assertSee('Update Status');
    $response->assertSee('<h2 class="wb-card-title">Update Status</h2>', false);
    $response->assertSee('No newer release is ready for this install.');
  }

  #[Test]
  public function incompatible_update_states_do_not_show_enabled_update_now_button(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->mockClientResult(
      state: 'incompatible',
      label: 'Incompatible update available',
      message: 'A newer release is available but this install does not meet its requirements.',
      compatibility: ['status' => 'incompatible', 'reasons' => ['Requires PHP 8.4 or newer.']],
    );

    $incompatibleResponse = $this->actingAs($user)->get(route('admin.system.updates.index'));
    $incompatibleResponse->assertOk();
    $incompatibleResponse->assertSee('Incompatible update available');
    $incompatibleResponse->assertSee('Requires PHP 8.4 or newer.');
    $incompatibleResponse->assertSee('Update Status');
    $incompatibleResponse->assertSee('<h2 class="wb-card-title">Update Status</h2>', false);
    $incompatibleResponse->assertSee('Requires PHP 8.4 or newer.');
  }

  #[Test]
  public function failed_historical_run_does_not_force_update_available_or_main_latest_run_warning(): void
  {
    $user = User::factory()->superAdmin()->create();
    SystemUpdateRun::query()->create([
      'from_version' => '1.32.80',
      'to_version' => WebBlocks::version(),
      'status' => SystemUpdateRun::STATUS_FAILED,
      'summary' => 'Historical failed update',
      'output' => 'Old failure output',
      'started_at' => CarbonImmutable::parse('2026-05-30 13:00:00'),
      'finished_at' => CarbonImmutable::parse('2026-05-30 13:01:00'),
      'duration_ms' => 1000,
      'triggered_by_user_id' => $user->id,
    ]);
    $this->mockClientResult('up_to_date', 'Already up to date', 'This install is already on the latest published release.', true, WebBlocks::version());

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();
    $response->assertSee('This install already matches the latest published release.');
    $response->assertDontSee('Update available');
    $response->assertSee('<h2 class="wb-card-title">Update History</h2>', false);
    $response->assertSee('Historical failed update');
    $response->assertSee('1.32.80 → '.WebBlocks::version());
  }

  #[Test]
  public function failed_run_for_current_actionable_target_remains_visible_as_latest_run(): void
  {
    $user = User::factory()->superAdmin()->create();
    SystemUpdateRun::query()->create([
      'from_version' => WebBlocks::version(),
      'to_version' => '99.0.0',
      'status' => SystemUpdateRun::STATUS_FAILED,
      'summary' => 'Current target failed update',
      'output' => 'Current failure output',
      'started_at' => now()->subHour(),
      'finished_at' => now()->subHour()->addMinute(),
      'duration_ms' => 1000,
      'triggered_by_user_id' => $user->id,
    ]);
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '99.0.0');

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();
    $response->assertSee('<h2 class="wb-card-title">Update History</h2>', false);
    $response->assertSee(WebBlocks::version().' → 99.0.0');
    $response->assertSee('failed');
    $response->assertSee('View run details');
    $response->assertDontSee('wb-icon wb-icon-trash', false);
    $response->assertDontSee('Delete update history entry');
    $response->assertDontSee('<summary>Output</summary>', false);
  }

  #[Test]
  public function update_history_table_and_delete_actions_are_not_rendered_on_main_screen(): void
  {
    $user = User::factory()->superAdmin()->create();
    SystemUpdateRun::query()->create([
      'from_version' => '1.32.90',
      'to_version' => WebBlocks::version(),
      'status' => SystemUpdateRun::STATUS_SUCCESS,
      'summary' => 'Updated successfully.',
      'started_at' => now()->subMinute(),
      'finished_at' => now(),
      'duration_ms' => 1000,
      'triggered_by_user_id' => $user->id,
    ]);
    $this->mockClientResult('up_to_date', 'Already up to date', 'This install is already on the latest published release.', true, WebBlocks::version());

    $this->actingAs($user)
      ->get(route('admin.system.updates.index'))
      ->assertOk()
      ->assertSee('data-webblocks-updates-card="summary"', false)
      ->assertSee('data-webblocks-updates-card="details"', false)
      ->assertSee('data-webblocks-updates-card="history"', false)
      ->assertSee('Last update run')
      ->assertSee('View run details')
      ->assertSee('<h2 class="wb-card-title">Update History</h2>', false)
      ->assertSee('<table class="wb-table wb-table-striped wb-table-hover">', false)
      ->assertDontSee('<td class="wb-table-actions">', false)
      ->assertDontSee('Delete update history entry')
      ->assertDontSee('wb-icon-trash', false);
  }

  #[Test]
  public function support_report_download_sanitizes_sensitive_update_run_details(): void
  {
    $user = User::factory()->superAdmin()->create();
    SystemUpdateRun::query()->create([
      'from_version' => '1.32.90',
      'to_version' => WebBlocks::version(),
      'status' => SystemUpdateRun::STATUS_FAILED,
      'summary' => 'Failed with token=super-secret',
      'output' => 'Path '.base_path().' token=super-secret'.PHP_EOL.'Stack trace:'.PHP_EOL.'#0 /private/path/File.php(12): boom',
      'started_at' => now()->subMinute(),
      'finished_at' => now(),
      'duration_ms' => 1000,
      'triggered_by_user_id' => $user->id,
    ]);
    $this->mockClientResult('up_to_date', 'Already up to date', 'This install is already on the latest published release.', true, WebBlocks::version());

    $response = $this->actingAs($user)->get(route('admin.system.updates.support-report'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/json');
    $response->assertHeader('content-disposition');
    $response->assertSee('"current_version"', false);
    $response->assertSee('"last_run"', false);
    $response->assertSee('token=[redacted]', false);
    $response->assertSee('[base_path]', false);
    $response->assertDontSee('super-secret', false);
    $response->assertDontSee(base_path(), false);
    $response->assertDontSee('Stack trace:', false);
    $response->assertDontSee('#0 /private/path/File.php', false);
  }

  #[Test]
  public function update_run_prune_keeps_latest_runs_and_unresolved_latest_failure(): void
  {
    config()->set('webblocks-updates.runs.keep', 5);

    $runs = $this->createUpdateHistoryRuns(7, User::factory()->superAdmin()->create());
    $failedRun = SystemUpdateRun::query()->create([
      'from_version' => '9.0.00',
      'to_version' => '9.0.01',
      'status' => SystemUpdateRun::STATUS_FAILED,
      'summary' => 'Unresolved failure.',
      'started_at' => now()->subDays(2),
      'finished_at' => now()->subDays(2)->addMinute(),
      'duration_ms' => 1000,
    ]);

    $this->artisan('webblocks:updates:prune-runs')
      ->assertExitCode(0);

    $this->assertDatabaseHas('system_update_runs', ['id' => $failedRun->id]);
    $this->assertDatabaseMissing('system_update_runs', ['id' => $runs[1]->id]);
    $this->assertDatabaseMissing('system_update_runs', ['id' => $runs[2]->id]);
    $this->assertDatabaseMissing('system_update_runs', ['id' => $runs[3]->id]);

    $this->assertSame(5, SystemUpdateRun::query()->count());
  }

  #[Test]
  public function successful_update_flow_persists_version_records_run_and_updates_sidebar(): void
  {
    $user = User::factory()->superAdmin()->create();
    app(InstalledVersionStore::class)->persist('0.1.0');
    Storage::fake('backups');

    [$targetRoot, $archivePath, $checksum] = $this->prepareSuccessfulUpdateScenario('0.2.0');

    config()->set('webblocks-updates.installer.target_path', $targetRoot);
    $this->bindFakeCommandRunner();
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '0.2.0', ['status' => 'compatible', 'reasons' => []], null, null, 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip', $checksum);

    Http::fake([
      'https://updates.example.test/downloads/*' => Http::response(File::get($archivePath), 200, ['Content-Type' => 'application/zip']),
    ]);

    $response = $this->actingAs($user)->post(route('admin.system.updates.store'));

    $response->assertRedirect(route('admin.system.updates.index'));
    $response->assertSessionHas('status', 'Updated to 0.2.0 successfully.');

    $this->assertSame(WebBlocks::version(), app(InstalledVersionStore::class)->currentVersion());
    $this->assertSame('root-artisan', trim((string) File::get($targetRoot.'/artisan')));
    $this->assertSame('root-bootstrap', trim((string) File::get($targetRoot.'/bootstrap/app.php')));
    $this->assertSame('APP_NAME=Original', trim((string) File::get($targetRoot.'/.env')));
    $this->assertSame('runtime-data', trim((string) File::get($targetRoot.'/storage/app/public/user.txt')));
    $this->assertSame('runtime-cache', trim((string) File::get($targetRoot.'/bootstrap/cache/config.php')));
    $this->assertSame("<?php\n\nreturn ['source' => 'runtime-project'];\n", File::get($targetRoot.'/project/config/sites.php'));
    $this->assertSame("<?php\n\nreturn ['source' => 'runtime-auth'];\n", File::get($targetRoot.'/app/Models/User.php'));
    $this->assertSame('runtime-config-override', trim((string) File::get($targetRoot.'/config/app.php')));
    $this->assertSame('runtime-root-migration', trim((string) File::get($targetRoot.'/database/migrations/2026_01_01_000000_runtime.php')));
    $this->assertSame('site-override', trim((string) File::get($targetRoot.'/public/site/default/site.css')));
    $this->assertSame('old package update exception', trim((string) File::get($targetRoot.'/packages/webblocks-cms/src/Support/System/Updates/UpdateException.php')));
    $this->assertSame('stale file', trim((string) File::get($targetRoot.'/packages/webblocks-cms/src/Legacy/StaleFile.php')));
    $this->assertSame('old-package-css', trim((string) File::get($targetRoot.'/packages/webblocks-cms/public/cms/admin.css')));
    $this->assertSame('bulk-actions-js', trim((string) File::get($targetRoot.'/public/cms/js/admin/listing-bulk-actions.js')));
    $this->assertSame('brand-logo', trim((string) File::get($targetRoot.'/public/cms/brand/logo-mark.svg')));
    $this->assertFalse(File::exists($targetRoot.'/public/cms/index.php'));
    $this->assertSame('new package update exception', trim((string) File::get($targetRoot.'/vendor/fklavyenet/webblocks-cms/src/Support/System/Updates/UpdateException.php')));
    $this->assertSame('bulk-actions-js', trim((string) File::get($targetRoot.'/vendor/fklavyenet/webblocks-cms/public/cms/js/admin/listing-bulk-actions.js')));
    $this->assertSame('brand-logo', trim((string) File::get($targetRoot.'/vendor/fklavyenet/webblocks-cms/public/cms/brand/logo-mark.svg')));
    $this->assertStringContainsString('adminBrowserTitle($adminBrowserTitle ?? $title ?? null)', (string) File::get($targetRoot.'/vendor/fklavyenet/webblocks-cms/resources/views/layouts/admin.blade.php'));
    $this->assertStringContainsString("'Admin Dashboard'", (string) File::get($targetRoot.'/vendor/fklavyenet/webblocks-cms/src/Support/System/SystemSettings.php'));
    $this->assertStringContainsString('webblocks-cms::admin.site-transfers.exports.index', (string) File::get($targetRoot.'/vendor/fklavyenet/webblocks-cms/src/Http/Controllers/Admin/SiteExportController.php'));
    $this->assertStringNotContainsString("view('admin/site-transfers/exports/index'", (string) File::get($targetRoot.'/vendor/fklavyenet/webblocks-cms/src/Http/Controllers/Admin/SiteExportController.php'));
    $this->assertFileExists($targetRoot.'/vendor/fklavyenet/webblocks-cms/docs/ai-page-building-guide.md');
    $this->assertFileExists($targetRoot.'/vendor/fklavyenet/webblocks-cms/docs/internal-content-api.md');
    $this->assertFileDoesNotExist($targetRoot.'/vendor/fklavyenet/webblocks-cms/packages/webblocks-cms/src/Support/System/Updates/UpdateException.php');
    $this->assertFileDoesNotExist($targetRoot.'/vendor/fklavyenet/webblocks-cms/artisan');
    $this->assertFileDoesNotExist($targetRoot.'/vendor/fklavyenet/webblocks-cms/app');
    $this->assertFileDoesNotExist($targetRoot.'/vendor/fklavyenet/webblocks-cms/bootstrap');
    $this->assertFileDoesNotExist($targetRoot.'/vendor/fklavyenet/webblocks-cms/packages');
    $this->assertFileDoesNotExist($targetRoot.'/vendor/fklavyenet/webblocks-cms/plugins');
    $this->assertFileDoesNotExist($targetRoot.'/vendor/fklavyenet/webblocks-cms/tests');
    $this->assertStringContainsString('fklavyenet/webblocks-cms/src', (string) File::get($targetRoot.'/vendor/composer/autoload_psr4.php'));
    $this->assertStringContainsString('fklavyenet/webblocks-cms/database/seeders', (string) File::get($targetRoot.'/vendor/composer/autoload_psr4.php'));
    $this->assertStringContainsString('fklavyenet/webblocks-cms/src', (string) File::get($targetRoot.'/vendor/composer/autoload_static.php'));
    $this->assertStringContainsString('fklavyenet/webblocks-cms/database/seeders', (string) File::get($targetRoot.'/vendor/composer/autoload_static.php'));
    $this->assertStringContainsString('"WebBlocks\\\\Cms\\\\": "src/"', (string) File::get($targetRoot.'/vendor/composer/installed.json'));
    $this->assertStringContainsString('"WebBlocks\\\\Cms\\\\Database\\\\Seeders\\\\": "database/seeders/"', (string) File::get($targetRoot.'/vendor/composer/installed.json'));
    $this->assertStringNotContainsString('packages/webblocks-cms', (string) File::get($targetRoot.'/vendor/composer/autoload_psr4.php'));
    $this->assertStringNotContainsString('packages/webblocks-cms', (string) File::get($targetRoot.'/vendor/composer/autoload_static.php'));
    $this->assertStringNotContainsString('packages/webblocks-cms', (string) File::get($targetRoot.'/vendor/composer/installed.json'));
    $this->assertStringNotContainsString('WebBlocks\\\\Cms\\\\Plugins\\\\WebBlocksUiManager\\\\', (string) File::get($targetRoot.'/vendor/composer/installed.json'));
    $this->assertSame('DISABLED', $this->readGitConfig($targetRoot, 'remote.origin.pushurl'));

    $run = SystemUpdateRun::query()->latest()->first();
    $this->assertNotNull($run);
    $this->assertSame(SystemUpdateRun::STATUS_SUCCESS, $run->status);
    $this->assertSame(WebBlocks::version(), $run->from_version);
    $this->assertSame('0.2.0', $run->to_version);
    $this->assertStringContainsString('Using PHP binary: php', (string) $run->output);
    $this->assertStringContainsString('Package checksum verified', (string) $run->output);
    $this->assertStringContainsString('Replaced vendor/fklavyenet/webblocks-cms with package artifact contents.', (string) $run->output);
    $this->assertStringContainsString('Normalized repo-shaped vendor package root to the flat canonical Composer package root.', (string) $run->output);
    $this->assertStringNotContainsString('Replaced packages/webblocks-cms with package artifact contents.', (string) $run->output);
    $this->assertStringNotContainsString('Replaced vendor/fklavyenet/webblocks-cms/packages/webblocks-cms with package artifact contents.', (string) $run->output);
    $this->assertStringContainsString('Synced package public/cms assets into public/cms runtime compatibility path.', (string) $run->output);
    $this->assertStringContainsString('Removed retired public/cms/index.php front-controller handoff.', (string) $run->output);
    $this->assertStringContainsString('composer dump-autoload --no-interaction --optimize', (string) $run->output);
    $this->assertStringNotContainsString('composer install', (string) $run->output);
    $this->assertStringContainsString('Normalized Composer installed package metadata for WebBlocks CMS flat package paths.', (string) $run->output);
    $this->assertStringContainsString('Normalized Composer autoload metadata for WebBlocks CMS flat package paths:', (string) $run->output);
    $this->assertStringContainsString('Pre-update backup created:', (string) $run->output);
    $this->assertStringContainsString('Migration strategy: package-native update migrations.', (string) $run->output);
    $this->assertStringContainsString('No package update migrations found; host application migrations were skipped.', (string) $run->output);
    $this->assertStringContainsString('Disabled git push for origin while keeping fetch updates enabled.', (string) $run->output);
    $this->assertStringContainsString('Post-update version verified as 0.2.0 from canonical WebBlocks version source.', (string) $run->output);
    $this->assertStringNotContainsString('php artisan migrate --force', (string) $run->output);
    $this->assertCommandOrder([
      'php artisan config:clear',
      'php artisan view:clear',
      'php artisan cache:clear',
    ]);
    $this->assertStringNotContainsString('php artisan db:seed --class=Database\\Seeders\\CoreCatalogSeeder --force', (string) $run->output);
    $this->assertStringNotContainsString('php artisan block-types:sync-core --force', (string) $run->output);
    $this->assertStringNotContainsString('php artisan webblocks:catalog-repair', (string) $run->output);

    $backup = SystemBackup::query()->latest()->first();
    $this->assertNotNull($backup);
    $this->assertSame(SystemBackup::TYPE_PRE_UPDATE, $backup->type);
    $this->assertSame(SystemBackup::STATUS_COMPLETED, $backup->status);

    $sidebar = $this->actingAs($user)->get(route('admin.system.updates.index'));
    $sidebar->assertSee('WebBlocks CMS v'.WebBlocks::version());
    $sidebar->assertDontSee('Download package');
  }

  #[Test]
  public function package_native_update_replaces_active_composer_vendor_package_root_views_and_helpers(): void
  {
    $user = User::factory()->superAdmin()->create();
    app(InstalledVersionStore::class)->persist('1.32.76');
    Storage::fake('backups');

    [$targetRoot, $archivePath, $checksum] = $this->prepareSuccessfulUpdateScenario('1.32.77');
    File::deleteDirectory($targetRoot.'/vendor/fklavyenet/webblocks-cms');
    File::ensureDirectoryExists($targetRoot.'/vendor/fklavyenet/webblocks-cms/src/Support/System/Updates');
    File::ensureDirectoryExists($targetRoot.'/vendor/fklavyenet/webblocks-cms/src/Support/System');
    File::ensureDirectoryExists($targetRoot.'/vendor/fklavyenet/webblocks-cms/resources/views/layouts');
    File::put($targetRoot.'/vendor/composer/autoload_psr4.php', "<?php\n\nreturn [\n    'WebBlocks\\\\Cms\\\\' => [__DIR__.'/../fklavyenet/webblocks-cms/src'],\n];\n");
    File::put($targetRoot.'/vendor/fklavyenet/webblocks-cms/composer.json', json_encode([
      'name' => 'fklavyenet/webblocks-cms',
      'autoload' => [
        'psr-4' => [
          'WebBlocks\\Cms\\' => 'src/',
        ],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    File::put($targetRoot.'/vendor/fklavyenet/webblocks-cms/src/Support/System/Updates/UpdateException.php', "old vendor root update exception\n");
    File::put($targetRoot.'/vendor/fklavyenet/webblocks-cms/src/Support/System/SystemSettings.php', "<?php\n\nreturn 'old title helper';\n");
    File::put($targetRoot.'/vendor/fklavyenet/webblocks-cms/resources/views/layouts/admin.blade.php', '<title>{{ $title ?? "old" }}</title>');

    config()->set('webblocks-updates.installer.target_path', $targetRoot);
    $this->bindFakeCommandRunner();
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '1.32.77', ['status' => 'compatible', 'reasons' => []], null, null, 'https://updates.example.test/downloads/webblocks-cms-1.32.77.zip', $checksum);

    Http::fake([
      'https://updates.example.test/downloads/*' => Http::response(File::get($archivePath), 200, ['Content-Type' => 'application/zip']),
    ]);

    $response = $this->actingAs($user)->post(route('admin.system.updates.store'));

    $response->assertRedirect(route('admin.system.updates.index'));
    $response->assertSessionHas('status', 'Updated to 1.32.77 successfully.');

    $this->assertSame('new package update exception', trim((string) File::get($targetRoot.'/vendor/fklavyenet/webblocks-cms/src/Support/System/Updates/UpdateException.php')));
    $this->assertStringContainsString('adminBrowserTitle($adminBrowserTitle ?? $title ?? null)', (string) File::get($targetRoot.'/vendor/fklavyenet/webblocks-cms/resources/views/layouts/admin.blade.php'));
    $this->assertStringContainsString("'Admin Dashboard'", (string) File::get($targetRoot.'/vendor/fklavyenet/webblocks-cms/src/Support/System/SystemSettings.php'));
    $this->assertStringNotContainsString('old title helper', (string) File::get($targetRoot.'/vendor/fklavyenet/webblocks-cms/src/Support/System/SystemSettings.php'));

    $run = SystemUpdateRun::query()->latest()->firstOrFail();
    $this->assertStringContainsString('Replaced vendor/fklavyenet/webblocks-cms with package artifact contents.', (string) $run->output);
    $this->assertStringContainsString('php artisan view:clear', (string) $run->output);
    $this->assertStringContainsString('php artisan config:clear', (string) $run->output);
    $this->assertStringContainsString('php artisan cache:clear', (string) $run->output);
  }

  #[Test]
  public function failed_update_flow_keeps_version_old_records_failure_and_recovers_maintenance(): void
  {
    $user = User::factory()->superAdmin()->create();
    app(InstalledVersionStore::class)->persist('0.1.0');
    Storage::fake('backups');

    [$targetRoot, $archivePath, $checksum] = $this->prepareSuccessfulUpdateScenario('0.2.0');

    config()->set('webblocks-updates.installer.target_path', $targetRoot);
    $runner = $this->bindFakeCommandRunner([
      'php artisan config:clear' => 1,
    ]);
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '0.2.0', ['status' => 'compatible', 'reasons' => []], null, null, 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip', $checksum);

    Http::fake([
      'https://updates.example.test/downloads/*' => Http::response(File::get($archivePath), 200, ['Content-Type' => 'application/zip']),
    ]);

    $response = $this->actingAs($user)->from(route('admin.system.updates.index'))->post(route('admin.system.updates.store'));

    $response->assertRedirect(route('admin.system.updates.index'));
    $response->assertSessionHasErrors(['system_update']);
    $this->assertSame(WebBlocks::version(), app(InstalledVersionStore::class)->currentVersion());

    $run = SystemUpdateRun::query()->latest()->first();
    $this->assertNotNull($run);
    $this->assertSame(SystemUpdateRun::STATUS_FAILED, $run->status);
    $this->assertStringContainsString('Command failed: php artisan config:clear', (string) $run->output);
    $this->assertStringNotContainsString('php-fpm', (string) $run->output);
    $this->assertContains('php artisan up', $runner->commands);
  }

  #[Test]
  public function update_apply_records_success_only_after_post_apply_code_version_matches_target(): void
  {
    $user = User::factory()->superAdmin()->create();
    Storage::fake('backups');

    [$targetRoot, $archivePath, $checksum] = $this->prepareSuccessfulUpdateScenario('0.2.0');

    config()->set('webblocks-updates.installer.target_path', $targetRoot);
    $this->bindFakeCommandRunner();
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '0.2.0', ['status' => 'compatible', 'reasons' => []], null, null, 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip', $checksum);

    Http::fake([
      'https://updates.example.test/downloads/*' => Http::response(File::get($archivePath), 200, ['Content-Type' => 'application/zip']),
    ]);

    $this->actingAs($user)
      ->post(route('admin.system.updates.store'))
      ->assertRedirect(route('admin.system.updates.index'))
      ->assertSessionHas('status', 'Updated to 0.2.0 successfully.');

    $run = SystemUpdateRun::query()->latest()->firstOrFail();

    $this->assertSame(SystemUpdateRun::STATUS_SUCCESS, $run->status);
    $this->assertSame('0.2.0', app(InstalledVersionStore::class)->storedVersion());
    $this->assertStringContainsString('Post-update version verified as 0.2.0 from canonical WebBlocks version source.', (string) $run->output);
  }

  #[Test]
  public function failed_post_apply_version_verification_does_not_record_success(): void
  {
    $user = User::factory()->superAdmin()->create();
    Storage::fake('backups');

    [$targetRoot, $archivePath, $checksum] = $this->prepareSuccessfulUpdateScenario('0.1.9');

    config()->set('webblocks-updates.installer.target_path', $targetRoot);
    $runner = $this->bindFakeCommandRunner();
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '0.2.0', ['status' => 'compatible', 'reasons' => []], null, null, 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip', $checksum);

    Http::fake([
      'https://updates.example.test/downloads/*' => Http::response(File::get($archivePath), 200, ['Content-Type' => 'application/zip']),
    ]);

    $response = $this->actingAs($user)
      ->from(route('admin.system.updates.index'))
      ->post(route('admin.system.updates.store'));

    $response->assertRedirect(route('admin.system.updates.index'));
    $response->assertSessionHasErrors(['system_update' => 'The update package was applied, but the installed CMS version still does not match the target release. The run was not marked successful; restore the pre-update backup or review filesystem and cache state before retrying.']);
    $response->assertSessionMissing('status');

    $run = SystemUpdateRun::query()->latest()->firstOrFail();

    $this->assertSame(SystemUpdateRun::STATUS_FAILED, $run->status);
    $this->assertSame('The update package was applied, but the installed CMS version still does not match the target release. The run was not marked successful; restore the pre-update backup or review filesystem and cache state before retrying.', $run->summary);
    $this->assertStringContainsString('Update failed: Expected WebBlocks CMS 0.2.0 after update, but vendor/fklavyenet/webblocks-cms/src/Support/WebBlocks.php reports 0.1.9.', (string) $run->output);
    $this->assertStringNotContainsString('Installed version persisted as 0.2.0', (string) $run->output);
    $this->assertNull(app(InstalledVersionStore::class)->storedVersion());
    $this->assertContains('php artisan up', $runner->commands);
  }

  #[Test]
  public function success_flash_cannot_appear_when_current_code_version_did_not_advance(): void
  {
    $user = User::factory()->superAdmin()->create();
    Storage::fake('backups');

    [$targetRoot, $archivePath, $checksum] = $this->prepareSuccessfulUpdateScenario(WebBlocks::version());

    config()->set('webblocks-updates.installer.target_path', $targetRoot);
    $this->bindFakeCommandRunner();
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '99.0.0', ['status' => 'compatible', 'reasons' => []], null, null, 'https://updates.example.test/downloads/webblocks-cms-99.0.0.zip', $checksum);

    Http::fake([
      'https://updates.example.test/downloads/*' => Http::response(File::get($archivePath), 200, ['Content-Type' => 'application/zip']),
    ]);

    $response = $this->actingAs($user)
      ->from(route('admin.system.updates.index'))
      ->post(route('admin.system.updates.store'));

    $response->assertRedirect(route('admin.system.updates.index'));
    $response->assertSessionHasErrors(['system_update']);
    $response->assertSessionMissing('status');

    $run = SystemUpdateRun::query()->latest()->firstOrFail();

    $this->assertSame(SystemUpdateRun::STATUS_FAILED, $run->status);
    $this->assertStringNotContainsString('Updated to 99.0.0 successfully.', (string) $run->summary);
    $this->assertStringNotContainsString('Installed version persisted as 99.0.0', (string) $run->output);
  }

  #[Test]
  public function package_native_update_skips_pending_host_users_migration_and_continues_post_update_steps(): void
  {
    $user = User::factory()->superAdmin()->create();
    app(InstalledVersionStore::class)->persist('1.32.20');
    Storage::fake('backups');

    [$targetRoot, $archivePath, $checksum] = $this->prepareSuccessfulUpdateScenario('1.32.21');
    File::put($targetRoot.'/composer.json', json_encode(['name' => 'fklavyenet/webblocks-cms'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    File::put($targetRoot.'/database/migrations/0001_01_01_000000_create_users_table.php', "<?php\n\nthrow new RuntimeException('host users migration should not run');\n");

    config()->set('webblocks-updates.installer.target_path', $targetRoot);
    $runner = $this->bindFakeCommandRunner();
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '1.32.21', ['status' => 'compatible', 'reasons' => []], null, null, 'https://updates.example.test/downloads/webblocks-cms-1.32.21.zip', $checksum);

    Http::fake([
      'https://updates.example.test/downloads/*' => Http::response(File::get($archivePath), 200, ['Content-Type' => 'application/zip']),
    ]);

    $response = $this->actingAs($user)->post(route('admin.system.updates.store'));

    $response->assertRedirect(route('admin.system.updates.index'));
    $response->assertSessionHas('status', 'Updated to 1.32.21 successfully.');

    $run = SystemUpdateRun::query()->latest()->firstOrFail();
    $output = (string) $run->output;

    $this->assertSame(SystemUpdateRun::STATUS_SUCCESS, $run->status);
    $this->assertStringContainsString('Migration strategy: package-native update migrations.', $output);
    $this->assertStringContainsString('No package update migrations found; host application migrations were skipped.', $output);
    $this->assertStringNotContainsString('Migration strategy: source-maintained root migrations.', $output);
    $this->assertStringNotContainsString('0001_01_01_000000_create_users_table', $output);
    $this->assertNotContains('php artisan migrate --force', $runner->commands);
    $this->assertNotContains('php artisan db:seed --class=Database\\Seeders\\CoreCatalogSeeder --force', $runner->commands);
    $this->assertNotContains('php artisan block-types:sync-core --force', $runner->commands);
    $this->assertNotContains('php artisan webblocks:catalog-repair --all', $runner->commands);
    $this->assertContains('php artisan cache:clear', $runner->commands);
    $this->assertSame(WebBlocks::version(), app(InstalledVersionStore::class)->currentVersion());
  }

  #[Test]
  public function normal_update_apply_does_not_run_catalog_repair_commands(): void
  {
    $user = User::factory()->superAdmin()->create();
    app(InstalledVersionStore::class)->persist('0.1.0');
    Storage::fake('backups');

    [$targetRoot, $archivePath, $checksum] = $this->prepareSuccessfulUpdateScenario('0.2.0');

    config()->set('webblocks-updates.installer.target_path', $targetRoot);
    $runner = $this->bindFakeCommandRunner();
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '0.2.0', ['status' => 'compatible', 'reasons' => []], null, null, 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip', $checksum);

    Http::fake([
      'https://updates.example.test/downloads/*' => Http::response(File::get($archivePath), 200, ['Content-Type' => 'application/zip']),
    ]);

    $response = $this->actingAs($user)->post(route('admin.system.updates.store'));

    $response->assertRedirect(route('admin.system.updates.index'));
    $response->assertSessionHas('status', 'Updated to 0.2.0 successfully.');

    $this->assertNotContains('php artisan db:seed --class=Database\\Seeders\\CoreCatalogSeeder --force', $runner->commands);
    $this->assertNotContains('php artisan block-types:sync-core --force', $runner->commands);
    $this->assertNotContains('php artisan webblocks:catalog-repair --all', $runner->commands);
  }

  #[Test]
  public function second_update_cannot_start_while_lock_is_held(): void
  {
    $user = User::factory()->superAdmin()->create();
    app(InstalledVersionStore::class)->persist('0.1.0');
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

    $lock = Cache::lock((string) config('webblocks-updates.installer.lock_name', 'system-updates:run'), 30);
    $this->assertTrue($lock->get());

    try {
      $response = $this->actingAs($user)->from(route('admin.system.updates.index'))->post(route('admin.system.updates.store'));

      $response->assertRedirect(route('admin.system.updates.index'));
      $response->assertSessionHasErrors(['system_update']);
      $this->assertDatabaseCount('system_update_runs', 0);
    } finally {
      $lock->release();
    }
  }

  #[Test]
  public function update_page_renders_backup_guarantee_and_not_old_warning_text(): void
  {
    $user = User::factory()->superAdmin()->create();
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();
    $response->assertSee('Download the backup before installation starts');
    $response->assertSee('A pre-update backup will be created automatically before installation.');
    $response->assertDontSee('Automatic backup is not created before update in this version.');
  }

  #[Test]
  public function download_backup_checkbox_remains_inside_the_update_form(): void
  {
    $user = User::factory()->superAdmin()->create();
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

    $response = $this->actingAs($user)->get(route('admin.system.updates.index'));

    $response->assertOk();

    $html = $response->getContent();
    $formPosition = strpos($html, 'action="'.route('admin.system.updates.store').'"');
    $csrfPosition = strpos($html, 'name="_token"', $formPosition);
    $checkboxPosition = strpos($html, 'name="download_pre_update_backup"', $formPosition);
    $buttonPosition = strpos($html, 'form="webblocks-update-install-form"');
    $formEndPosition = strpos($html, '</form>', $formPosition);

    $this->assertIsInt($formPosition);
    $this->assertIsInt($csrfPosition);
    $this->assertIsInt($checkboxPosition);
    $this->assertIsInt($buttonPosition);
    $this->assertIsInt($formEndPosition);
    $this->assertLessThan($formEndPosition, $csrfPosition);
    $this->assertLessThan($formEndPosition, $checkboxPosition);
    $this->assertLessThan($formPosition, $buttonPosition);
    $this->assertStringContainsString('form="webblocks-update-install-form"', $html);
  }

  #[Test]
  public function update_does_not_install_if_pre_update_backup_creation_fails(): void
  {
    $user = User::factory()->superAdmin()->create();
    app(InstalledVersionStore::class)->persist('0.1.0');
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

    $backupManager = Mockery::mock(SystemBackupManager::class);
    $backupManager->shouldReceive('createPreUpdateBackup')->once()->andThrow(new \RuntimeException('backup disk unavailable'));
    $this->app->instance(SystemBackupManager::class, $backupManager);

    $response = $this->actingAs($user)
      ->from(route('admin.system.updates.index'))
      ->post(route('admin.system.updates.store'));

    $response->assertRedirect(route('admin.system.updates.index'));
    $response->assertSessionHasErrors(['system_update' => 'Update was not installed because the pre-update backup could not be created.']);
    $this->assertSame(WebBlocks::version(), app(InstalledVersionStore::class)->currentVersion());

    $run = SystemUpdateRun::query()->latest()->first();

    $this->assertNotNull($run);
    $this->assertSame(SystemUpdateRun::STATUS_FAILED, $run->status);
    $this->assertSame('Update was not installed because the pre-update backup could not be created.', $run->summary);
    $this->assertStringContainsString('Pre-update backup failed before update apply started.', (string) $run->output);
    $this->assertStringContainsString('Failure detail: backup disk unavailable', (string) $run->output);
  }

  #[Test]
  public function pre_update_backup_failure_persists_sanitized_failure_detail_without_secrets(): void
  {
    $user = User::factory()->superAdmin()->create();
    app(InstalledVersionStore::class)->persist('0.1.0');
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

    $backupManager = Mockery::mock(SystemBackupManager::class);
    $backupManager->shouldReceive('createPreUpdateBackup')
      ->once()
      ->andThrow(new \RuntimeException('Database dump failed with password=supersecret --defaults-extra-file=/tmp/mysql.cnf in '.storage_path('app/backups')));
    $this->app->instance(SystemBackupManager::class, $backupManager);

    $response = $this->actingAs($user)
      ->from(route('admin.system.updates.index'))
      ->post(route('admin.system.updates.store'));

    $response->assertRedirect(route('admin.system.updates.index'));
    $response->assertSessionHasErrors(['system_update' => 'Update was not installed because the pre-update backup could not be created.']);

    $run = SystemUpdateRun::query()->latest()->firstOrFail();

    $this->assertStringContainsString('Failure detail: Database dump failed with password=[redacted] --defaults-extra-file=[redacted] in [storage_path]', (string) $run->output);
    $this->assertStringNotContainsString('supersecret', (string) $run->output);
    $this->assertStringNotContainsString('/tmp/mysql.cnf', (string) $run->output);
    $this->assertStringNotContainsString(storage_path('app/backups'), (string) $run->output);
  }

  #[Test]
  public function checked_download_checkbox_creates_backup_and_shows_pending_continue_screen(): void
  {
    $user = User::factory()->superAdmin()->create();
    app(InstalledVersionStore::class)->persist('0.1.0');
    Storage::fake('backups');
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

    $response = $this->actingAs($user)->post(route('admin.system.updates.store'), [
      'download_pre_update_backup' => '1',
    ]);

    $response->assertRedirect(route('admin.system.updates.index'));

    $run = SystemUpdateRun::query()->latest()->firstOrFail();
    $backup = SystemBackup::query()->latest()->firstOrFail();

    $this->assertSame(SystemUpdateRun::STATUS_PENDING, $run->status);
    $this->assertSame(SystemBackup::TYPE_PRE_UPDATE, $backup->type);

    $followUp = $this->actingAs($user)->get(route('admin.system.updates.index'));
    $followUp->assertSee('Pre-update backup created.');
    $followUp->assertSee('Download backup');
    $followUp->assertSee('Continue update');
    $followUp->assertSee('Cancel');
    $followUp->assertSee((string) $backup->archive_filename);
  }

  #[Test]
  public function continue_update_installs_the_original_pending_target_version(): void
  {
    $user = User::factory()->superAdmin()->create();
    app(InstalledVersionStore::class)->persist('0.1.0');
    Storage::fake('backups');

    [$targetRoot, $archivePath, $checksum] = $this->prepareSuccessfulUpdateScenario('0.2.0');

    config()->set('webblocks-updates.installer.target_path', $targetRoot);
    $this->bindFakeCommandRunner();
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.', true, '0.2.0', ['status' => 'compatible', 'reasons' => []], null, null, 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip', $checksum);

    Http::fake([
      'https://updates.example.test/downloads/*' => Http::response(File::get($archivePath), 200, ['Content-Type' => 'application/zip']),
    ]);

    $this->actingAs($user)->post(route('admin.system.updates.store'), [
      'download_pre_update_backup' => '1',
    ]);

    $response = $this->actingAs($user)->post(route('admin.system.updates.continue'));

    $response->assertRedirect(route('admin.system.updates.index'));
    $response->assertSessionHas('status', 'Updated to 0.2.0 successfully.');
    $this->assertSame(WebBlocks::version(), app(InstalledVersionStore::class)->currentVersion());
  }

  #[Test]
  public function stale_pending_update_cannot_be_continued(): void
  {
    $user = User::factory()->superAdmin()->create();

    Cache::put('system-updates:pending', [
      'run_id' => 99999,
      'from_version' => '0.1.0',
      'to_version' => '0.2.0',
      'backup_id' => 99999,
      'release' => ['version' => '0.2.0'],
      'download_url' => 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip',
    ], now()->addHour());

    $response = $this->actingAs($user)
      ->from(route('admin.system.updates.index'))
      ->post(route('admin.system.updates.continue'));

    $response->assertRedirect(route('admin.system.updates.index'));
    $response->assertSessionHasErrors(['system_update']);
  }

  #[Test]
  public function pending_update_can_be_cancelled_without_installing(): void
  {
    $user = User::factory()->superAdmin()->create();
    app(InstalledVersionStore::class)->persist('0.1.0');
    Storage::fake('backups');
    $this->mockClientResult('update_available', 'Update available', 'A newer published release is available from the configured update server.');

    $this->actingAs($user)->post(route('admin.system.updates.store'), [
      'download_pre_update_backup' => '1',
    ]);

    $response = $this->actingAs($user)->post(route('admin.system.updates.cancel'));

    $response->assertRedirect(route('admin.system.updates.index'));
    $response->assertSessionHas('status', 'Pending update cancelled. The pre-update backup was kept.');
    $this->assertSame(WebBlocks::version(), app(InstalledVersionStore::class)->currentVersion());
    $this->assertSame(SystemUpdateRun::STATUS_CANCELLED, SystemUpdateRun::query()->latest()->firstOrFail()->status);
  }

  private function createUpdateHistoryRuns(int $count, User $user): array
  {
    $baseStartedAt = CarbonImmutable::parse('2026-05-30 12:00:00');
    $runs = [];

    for ($index = 1; $index <= $count; $index++) {
      $runs[$index] = SystemUpdateRun::query()->create([
        'from_version' => sprintf('8.0.%02d', $index),
        'to_version' => sprintf('9.0.%02d', $index),
        'status' => SystemUpdateRun::STATUS_SUCCESS,
        'summary' => 'History run '.$index,
        'started_at' => $baseStartedAt->addMinutes($index),
        'finished_at' => $baseStartedAt->addMinutes($index)->addMinute(),
        'duration_ms' => 1000,
        'triggered_by_user_id' => $user->id,
      ]);
    }

    return $runs;
  }

  private function mockClientResult(
    string $state,
    string $label,
    string $message,
    bool $serverReachable = true,
    ?string $latestVersion = '99.0.0',
    ?array $compatibility = null,
    ?string $errorCode = null,
    ?string $errorMessage = null,
    ?string $downloadUrl = null,
    ?string $checksum = 'abcdef123456',
    array $releaseOverrides = [],
  ): void {
    $release = null;

    if ($latestVersion) {
      $release = array_replace_recursive([
        'version' => $latestVersion,
        'name' => 'WebBlocks CMS '.$latestVersion,
        'description' => 'Stability and admin improvements.',
        'changelog' => 'Compact changelog text here.',
        'download_url' => $downloadUrl ?? 'https://updates.example.test/downloads/webblocks-cms-'.$latestVersion.'.zip',
        'checksum_sha256' => $checksum,
        'published_at' => '2026-04-19T10:00:00Z',
        'is_critical' => false,
        'is_security' => false,
        'requirements' => [
          'min_php_version' => '8.3.0',
          'min_laravel_version' => '13.0.0',
          'supported_from_version' => '0.1.0',
          'supported_until_version' => null,
        ],
      ], $releaseOverrides);
    }

    $client = Mockery::mock(UpdateServerClient::class);
    $client->shouldReceive('check')->andReturn(new UpdateCheckResult(
      state: $state,
      label: $label,
      message: $message,
      badgeClass: $state === 'server_unreachable' ? 'wb-status-danger' : 'wb-status-info',
      serverReachable: $serverReachable,
      apiVersion: '1',
      serverUrl: 'https://updates.example.test',
      product: 'webblocks-cms',
      channel: 'stable',
      installedVersion: '0.1.0',
      latestVersion: $latestVersion,
      updateAvailable: $state === 'update_available',
      compatibility: $compatibility ?? ['status' => 'compatible', 'reasons' => []],
      release: $release,
      errorCode: $errorCode,
      errorMessage: $errorMessage,
      checkedAt: CarbonImmutable::now(),
    ));

    $this->app->instance(UpdateServerClient::class, $client);
    $this->app->instance(PackageUpdateServerClient::class, $client);
    $this->app->forgetInstance(SystemUpdateInspector::class);
  }

  private function cardHtml(string $html, string $heading): string
  {
    $start = strpos($html, '<h2 class="wb-card-title">'.$heading.'</h2>');

    $this->assertIsInt($start, 'Expected to find card heading ['.$heading.'].');

    $nextCard = strpos($html, '<section class="wb-card"', $start + strlen($heading));

    return $nextCard === false
      ? substr($html, $start)
      : substr($html, $start, $nextCard - $start);
  }

  private function prepareSuccessfulUpdateScenario(string $packageVersion = '0.2.0'): array
  {
    $targetRoot = $this->makeTemporaryDirectory('target-root');
    File::ensureDirectoryExists($targetRoot.'/bootstrap/cache');
    File::ensureDirectoryExists($targetRoot.'/storage/app/public');
    File::ensureDirectoryExists($targetRoot.'/app/Models');
    File::ensureDirectoryExists($targetRoot.'/config');
    File::ensureDirectoryExists($targetRoot.'/database/migrations');
    File::ensureDirectoryExists($targetRoot.'/public/cms');
    File::ensureDirectoryExists($targetRoot.'/public/site/default');
    File::ensureDirectoryExists($targetRoot.'/packages/webblocks-cms/src/Support/System/Updates');
    File::ensureDirectoryExists($targetRoot.'/packages/webblocks-cms/src/Legacy');
    File::ensureDirectoryExists($targetRoot.'/packages/webblocks-cms/public/cms');
    File::ensureDirectoryExists($targetRoot.'/vendor/composer');
    File::ensureDirectoryExists($targetRoot.'/vendor/fklavyenet/webblocks-cms/app');
    File::ensureDirectoryExists($targetRoot.'/vendor/fklavyenet/webblocks-cms/bootstrap');
    File::ensureDirectoryExists($targetRoot.'/vendor/fklavyenet/webblocks-cms/packages/webblocks-cms/src/Http/Controllers/Admin');
    File::ensureDirectoryExists($targetRoot.'/vendor/fklavyenet/webblocks-cms/packages/webblocks-cms/src/Support/System/Updates');
    File::ensureDirectoryExists($targetRoot.'/vendor/fklavyenet/webblocks-cms/plugins');
    File::ensureDirectoryExists($targetRoot.'/vendor/fklavyenet/webblocks-cms/tests');
    File::put($targetRoot.'/artisan', "root-artisan\n");
    File::put($targetRoot.'/bootstrap/app.php', "root-bootstrap\n");
    File::put($targetRoot.'/composer.json', json_encode(['name' => 'test/install-shell'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    File::put($targetRoot.'/.env', "APP_NAME=Original\n");
    File::put($targetRoot.'/storage/app/public/user.txt', "runtime-data\n");
    File::put($targetRoot.'/bootstrap/cache/config.php', "runtime-cache\n");
    File::put($targetRoot.'/app/Models/User.php', "<?php\n\nreturn ['source' => 'runtime-auth'];\n");
    File::put($targetRoot.'/config/app.php', "runtime-config-override\n");
    File::put($targetRoot.'/database/migrations/2026_01_01_000000_runtime.php', "runtime-root-migration\n");
    File::put($targetRoot.'/public/cms/index.php', "retired handoff\n");
    File::put($targetRoot.'/public/site/default/site.css', "site-override\n");
    File::ensureDirectoryExists($targetRoot.'/project/config');
    File::put($targetRoot.'/project/config/sites.php', "<?php\n\nreturn ['source' => 'runtime-project'];\n");
    File::put($targetRoot.'/packages/webblocks-cms/src/Support/System/Updates/UpdateException.php', "old package update exception\n");
    File::put($targetRoot.'/packages/webblocks-cms/src/Legacy/StaleFile.php', "stale file\n");
    File::put($targetRoot.'/packages/webblocks-cms/public/cms/admin.css', "old-package-css\n");
    File::put($targetRoot.'/vendor/composer/autoload_psr4.php', "<?php\n\nreturn [\n    'WebBlocks\\\\Cms\\\\' => [__DIR__.'/../fklavyenet/webblocks-cms/packages/webblocks-cms/src'],\n    'WebBlocks\\\\Cms\\\\Database\\\\Seeders\\\\' => [__DIR__.'/../fklavyenet/webblocks-cms/packages/webblocks-cms/database/seeders'],\n];\n");
    File::put($targetRoot.'/vendor/composer/autoload_static.php', "<?php\n\nclass ComposerStaticInitWebBlocksTest\n{\n  public static \$prefixDirsPsr4 = [\n    'WebBlocks\\\\\\\\Cms\\\\\\\\' => [__DIR__ . '/..' . '/fklavyenet/webblocks-cms/packages/webblocks-cms/src'],\n    'WebBlocks\\\\\\\\Cms\\\\\\\\Database\\\\\\\\Seeders\\\\\\\\' => [__DIR__ . '/..' . '/fklavyenet/webblocks-cms/packages/webblocks-cms/database/seeders'],\n  ];\n}\n");
    File::put($targetRoot.'/vendor/composer/installed.json', json_encode([
      'packages' => [
        [
          'name' => 'fklavyenet/webblocks-cms',
          'version' => '1.32.142.0',
          'autoload' => [
            'psr-4' => [
              'WebBlocks\\Cms\\' => 'packages/webblocks-cms/src/',
              'WebBlocks\\Cms\\Database\\Seeders\\' => 'packages/webblocks-cms/database/seeders/',
              'WebBlocks\\Cms\\Plugins\\WebBlocksUiManager\\' => 'plugins/webblocks-ui-manager/src/',
            ],
          ],
          'extra' => [
            'laravel' => [
              'providers' => [
                'WebBlocks\\Cms\\WebBlocksCmsServiceProvider',
              ],
            ],
          ],
        ],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    File::put($targetRoot.'/vendor/fklavyenet/webblocks-cms/artisan', "vendor repo artisan\n");
    File::put($targetRoot.'/vendor/fklavyenet/webblocks-cms/app/Legacy.php', "<?php\n");
    File::put($targetRoot.'/vendor/fklavyenet/webblocks-cms/bootstrap/app.php', "<?php\n");
    File::put($targetRoot.'/vendor/fklavyenet/webblocks-cms/tests/LegacyTest.php', "<?php\n");
    File::put($targetRoot.'/vendor/fklavyenet/webblocks-cms/packages/webblocks-cms/composer.json', json_encode([
      'name' => 'fklavyenet/webblocks-cms',
      'autoload' => [
        'psr-4' => [
          'WebBlocks\\Cms\\' => 'src/',
        ],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    File::put($targetRoot.'/vendor/fklavyenet/webblocks-cms/packages/webblocks-cms/src/Support/System/Updates/UpdateException.php', "old active vendor update exception\n");
    File::put($targetRoot.'/vendor/fklavyenet/webblocks-cms/packages/webblocks-cms/src/Http/Controllers/Admin/SiteExportController.php', "<?php\n\nreturn view('admin/site-transfers/exports/index');\n");
    $this->runProcess(['git', 'init'], $targetRoot);
    $this->runProcess(['git', 'remote', 'add', 'origin', 'git@github.com:fklavyenet/webblocks-cms.git'], $targetRoot);

    $archiveDirectory = $this->makeTemporaryDirectory('release-archive');
    $archivePath = $archiveDirectory.'/webblocks-cms-'.$packageVersion.'.zip';
    $archive = new ZipArchive;
    $this->assertTrue($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
    $archive->addFromString('composer.json', json_encode([
      'name' => 'fklavyenet/webblocks-cms',
      'version' => $packageVersion,
      'autoload' => [
        'psr-4' => [
          'WebBlocks\\Cms\\' => 'src/',
          'WebBlocks\\Cms\\Database\\Seeders\\' => 'database/seeders/',
        ],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $archive->addFromString('src/Support/WebBlocks.php', "<?php\n\nnamespace WebBlocks\\Cms\\Support;\n\nfinal class WebBlocks\n{\n  public const VERSION = '".$packageVersion."';\n}\n");
    $archive->addFromString('src/Support/System/Updates/UpdateException.php', "new package update exception\n");
    $archive->addFromString('src/Support/System/Updates/UpdateInstaller.php', "package update installer\n");
    $archive->addFromString('src/Support/System/Updates/SystemUpdater.php', "package system updater\n");
    $archive->addFromString('src/Support/System/SystemSettings.php', "<?php\n\nif (\$screenTitle === 'Admin Dashboard') {\n  return 'Dashboard';\n}\n\nreturn \$screenTitle.' - '.\$productName;\n");
    $archive->addFromString('src/WebBlocksCmsServiceProvider.php', "<?php\n\nnamespace WebBlocks\\Cms;\n\nclass WebBlocksCmsServiceProvider\n{\n}\n");
    $archive->addFromString('src/Http/Controllers/Admin/SiteExportController.php', "<?php\n\nreturn view('webblocks-cms::admin.site-transfers.exports.index');\n");
    $archive->addFromString('src/Http/Controllers/Admin/SiteImportController.php', "<?php\n\nreturn view('webblocks-cms::admin.site-transfers.imports.index');\n");
    $archive->addFromString('config/webblocks-updates.php', "<?php\n");
    $archive->addFromString('resources/views/admin/system/updates.blade.php', '<div>package updates</div>');
    $archive->addFromString('resources/views/layouts/admin.blade.php', "{{ app(SystemSettings::class)->adminBrowserTitle(\$adminBrowserTitle ?? \$title ?? null) }}\n");
    $archive->addFromString('resources/views/admin/dashboard.blade.php', "@extends('webblocks-cms::layouts.admin', ['title' => 'Admin Dashboard', 'heading' => 'Dashboard'])\n");
    $archive->addFromString('database/seeders/CoreCatalogSeeder.php', "<?php\n");
    $archive->addFromString('docs/ai-page-building-guide.md', "canonical AI guide\n");
    $archive->addFromString('docs/internal-content-api.md', "internal content api docs\n");
    $archive->addFromString('routes/admin.php', "<?php\n");
    $archive->addFromString('public/cms/admin.css', "package-css\n");
    $archive->addFromString('public/cms/brand/logo-mark.svg', "brand-logo\n");
    $archive->addFromString('public/cms/js/admin/listing-bulk-actions.js', "bulk-actions-js\n");
    $archive->addFromString('stubs/starter/README.md', "package stub\n");
    $archive->close();

    return [$targetRoot, $archivePath, hash_file('sha256', $archivePath)];
  }

  private function bindFakeCommandRunner(array $exitCodes = []): FakeUpdateCommandRunner
  {
    $runner = new FakeUpdateCommandRunner($exitCodes);
    $this->app->instance(UpdateCommandRunner::class, $runner);
    $this->app->instance(PackageUpdateCommandRunner::class, $runner);
    $this->fakeCommandRunner = $runner;

    return $runner;
  }

  private function makeTemporaryDirectory(string $prefix): string
  {
    $path = storage_path('app/testing-system-updates/'.$prefix.'-'.Str::uuid());
    File::ensureDirectoryExists($path);
    $this->temporaryDirectories[] = $path;

    return $path;
  }

  private function assertCommandOrder(array $expectedCommands): void
  {
    $this->assertNotNull($this->fakeCommandRunner);

    $commands = $this->fakeCommandRunner->commands;
    $lastIndex = -1;

    foreach ($expectedCommands as $expectedCommand) {
      $index = array_search($expectedCommand, $commands, true);

      $this->assertIsInt($index, 'Failed asserting that command ['.$expectedCommand.'] was executed.');
      $this->assertGreaterThan($lastIndex, $index, 'Failed asserting command order for ['.$expectedCommand.'].');

      $lastIndex = $index;
    }
  }

  private function readGitConfig(string $repositoryPath, string $key): ?string
  {
    $process = new Process(['git', 'config', '--get', $key], $repositoryPath);
    $process->run();

    if (! $process->isSuccessful()) {
      return null;
    }

    $output = trim($process->getOutput());

    return $output === '' ? null : $output;
  }

  private function runProcess(array $command, string $workingDirectory): void
  {
    $process = new Process($command, $workingDirectory);
    $process->mustRun();
  }

  protected function tearDown(): void
  {
    foreach ($this->temporaryDirectories as $directory) {
      File::deleteDirectory($directory);
    }

    Mockery::close();

    parent::tearDown();
  }
}

class FakeUpdateCommandRunner extends UpdateCommandRunner
{
  public array $commands = [];

  public function __construct(
    private readonly array $exitCodes = [],
  ) {}

  public function run(array $command, string $workingDirectory, array &$output): void
  {
    $formatted = implode(' ', array_map(static function (string $part): string {
      return $part === PHP_BINARY ? 'php' : $part;
    }, $command));

    $this->commands[] = $formatted;
    $output[] = '$ '.$formatted;
    $output[] = 'Simulated command in '.$workingDirectory;

    if (($this->exitCodes[$formatted] ?? 0) !== 0) {
      throw new UpdateException(
        'The update command sequence failed. Review the latest update log for details.',
        'Command failed: '.$formatted,
      );
    }
  }

  public function phpBinary(): string
  {
    return 'php';
  }
}
