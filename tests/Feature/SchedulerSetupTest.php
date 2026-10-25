<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\Admin\MaintenanceCleanupController;
use WebBlocks\Cms\Http\Controllers\Admin\SystemHealthController;
use WebBlocks\Cms\Http\Requests\Admin\SystemHealthRequest;
use WebBlocks\Cms\Queries\SystemHealthQuery;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;
use WebBlocks\Cms\Support\System\SchedulerHost;
use WebBlocks\Cms\Support\System\SchedulerSetup;
use WebBlocks\Cms\Support\System\SystemMaintenanceReports;
use WebBlocks\Cms\Tests\TestCase;

class SchedulerSetupTest extends TestCase
{
  private FakeSchedulerHost $host;

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineRoutes($router): void
  {
    Route::get('/test-scheduler', [SystemHealthController::class, 'scheduler']);
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->host = new FakeSchedulerHost;
    $this->app->instance(SchedulerHost::class, $this->host);
  }

  private function service(): SchedulerSetup
  {
    return app(SchedulerSetup::class);
  }

  #[Test]
  public function inspection_does_not_write_cron_or_create_runtime_evidence_and_hides_other_cron_contents(): void
  {
    $this->host->text = "MAILTO=private-secret@example.test\n0 * * * * /private/task\n";
    $before = DB::table('wbcms_scheduler_health')->count();
    $this->assertSame(0, Artisan::call('webblocks:scheduler:setup', ['--json' => true]));
    $result = json_decode(Artisan::output(), true);
    $this->assertSame('ready', $result['status']);
    $this->assertStringNotContainsString('private-secret', Artisan::output());
    $this->assertSame([], $this->host->writes);
    $this->assertSame($before, DB::table('wbcms_scheduler_health')->count());
    $this->assertNull($result['instructions']['health']['last_seen_at']);
  }

  #[Test]
  public function explicit_install_preserves_existing_crontab_and_is_idempotent_without_claiming_runtime(): void
  {
    $original = "# existing\nMAILTO=private-secret@example.test\n0 1 * * * /usr/bin/true";
    $this->host->text = $original;
    $this->assertSame(0, Artisan::call('webblocks:scheduler:setup', ['--install' => true, '--yes' => true, '--json' => true]));
    $this->assertSame('installed_unverified', json_decode(Artisan::output(), true)['status']);
    $this->assertStringStartsWith($original."\n# WebBlocks CMS scheduler ", $this->host->text);
    $this->assertSame(1, substr_count($this->host->text, 'artisan schedule:run'));
    $this->assertNull(app(SchedulerHealth::class)->snapshot()['last_seen_at']);
    $this->assertSame(0, Artisan::call('webblocks:scheduler:setup', ['--install' => true, '--yes' => true, '--json' => true]));
    $this->assertSame('existing', json_decode(Artisan::output(), true)['status']);
    $this->assertCount(1, $this->host->writes);
    $this->assertStringNotContainsString('private-secret', Artisan::output());
  }

  #[Test]
  public function installer_requires_confirmation_in_noninteractive_mode(): void
  {
    $this->artisan('webblocks:scheduler:setup', ['--install' => true])->expectsConfirmation(__('webblocks-cms::scheduler.confirm'), 'no')->assertFailed();
    $this->assertSame([], $this->host->writes);
    $this->assertSame(1, Artisan::call('webblocks:scheduler:setup', ['--install' => true, '--json' => true, '--no-interaction' => true]));
    $this->assertSame('confirmation_required', json_decode(Artisan::output(), true)['status']);
    $this->assertSame([], $this->host->writes);
  }

  #[Test]
  public function root_wrong_account_unsupported_and_unreadable_crontabs_are_never_modified(): void
  {
    foreach ([['uid' => 0], ['uid' => 2000], ['cli' => false], ['supported' => false], ['readable' => false], ['writable' => false], ['php' => null]] as $change) {
      $this->host->values = array_replace(FakeSchedulerHost::CONTEXT, $change);
      $this->assertNotSame('ready', $this->service()->plan()['status']);
      $this->assertSame([], $this->host->writes);
    }
    $this->host->values = FakeSchedulerHost::CONTEXT;
    $this->host->readError = ['code' => 1, 'out' => '', 'error' => 'Permission denied: secret'];
    $this->assertSame('unavailable', $this->service()->plan()['status']);
    $this->host->readError = ['code' => 1, 'out' => '', 'error' => 'no crontab for application'];
    $this->assertSame('ready', $this->service()->plan()['status']);
    $this->assertSame([], $this->host->writes);
  }

  #[Test]
  public function external_definitions_recent_heartbeats_and_ambiguous_entries_prevent_duplicate_installation(): void
  {
    $this->host->external = true;
    $this->assertSame('external', $this->service()->plan()['status']);
    $this->host->external = false;
    foreach (['* * * * * cd /srv/site-other && php artisan schedule:run', '* * * * * /usr/bin/php /srv/site/artisan schedule:run', '@reboot php artisan schedule:work'] as $text) {
      $this->host->text = $text;
      $this->assertSame('conflict', $this->service()->plan()['status']);
    }
    $line = '* * * * * '.$this->service()->instructions()['command'];
    $this->host->text = $line."\n".$line;
    $this->assertSame('conflict', $this->service()->plan()['status']);
    $this->host->text = '';
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => $event->description === 'webblocks-scheduler-heartbeat');
    $event->run($this->app);
    $this->assertSame('running', $this->service()->plan()['status']);
    $this->travel(6)->minutes();
    $this->assertSame('ready', $this->service()->plan()['status']);
    $this->assertSame([], $this->host->writes);
  }

  #[Test]
  public function changed_crontab_or_execution_context_aborts_before_write(): void
  {
    $setup = $this->service();
    $this->assertSame('ready', $setup->plan()['status']);
    $this->host->text = '# concurrently edited';
    $this->assertSame('changed', $setup->install()['status']);
    $this->assertSame([], $this->host->writes);
    $this->assertSame('ready', $setup->plan()['status']);
    $this->host->values['php'] = '/other/php';
    $this->assertSame('changed', $setup->install()['status']);
    $this->assertSame([], $this->host->writes);
    $this->assertSame('ready', $setup->plan()['status']);
    $this->host->external = true;
    $this->assertSame('external', $setup->install()['status']);
    $this->assertSame([], $this->host->writes);
  }

  #[Test]
  public function write_and_readback_failures_remain_explicit_and_do_not_attempt_a_blind_rollback(): void
  {
    $setup = $this->service();
    $this->host->writeCode = 1;
    $setup->plan();
    $this->assertSame('write_failed', $setup->install()['status']);
    $this->host->writeCode = 0;
    $this->host->readBackMismatch = true;
    $setup->plan();
    $this->assertSame('write_unverified', $setup->install()['status']);
    $this->assertCount(2, $this->host->writes);
    $this->assertNull(app(SchedulerHealth::class)->snapshot()['last_seen_at']);
  }

  #[Test]
  public function an_exception_after_write_never_claims_that_nothing_changed(): void
  {
    $this->host->throwAfterWrite = true;
    $this->assertSame(1, Artisan::call('webblocks:scheduler:setup', ['--install' => true, '--yes' => true, '--json' => true]));
    $result = json_decode(Artisan::output(), true);
    $this->assertSame('write_unverified', $result['status']);
    $this->assertCount(1, $this->host->writes);
    $this->assertStringNotContainsString('private-crontab-error', Artisan::output());
  }

  #[Test]
  public function lease_release_failure_does_not_hide_a_confirmed_crontab_write(): void
  {
    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('get')->once()->andReturn(true);
    $lock->shouldReceive('release')->once()->andThrow(new \RuntimeException('private-cache-error'));
    Cache::shouldReceive('lock')->with('cms:scheduler-setup', 60)->once()->andReturn($lock);
    $service = $this->service();
    $service->plan();
    $this->assertSame('installed_unverified', $service->install()['status']);
    $this->assertCount(1, $this->host->writes);
  }

  #[Test]
  public function instructions_quote_paths_and_escape_cron_percent_without_executing_shell_input(): void
  {
    $this->host->values['base'] = "/srv/a b'c%site";
    $commands = $this->service()->instructions();
    $this->assertStringContainsString("'\\''", $commands['command']);
    $this->assertStringContainsString('\\%site', $commands['command']);
    $this->assertStringNotContainsString('\\%site', $commands['install_command']);
    $this->host->values['base'] = "/srv/site\ninjected";
    $this->assertNull($this->service()->instructions()['command']);
    $this->assertSame([], $this->host->writes);
  }

  #[Test]
  public function setup_page_is_authorized_read_only_translated_and_uses_only_real_heartbeat_evidence(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(AuthenticatableUser::class, 'App\\Models\\User');
    }
    $user = Mockery::mock('App\\Models\\User')->makePartial();
    $user->forceFill(['id' => 99, 'name' => 'Synthetic Operator', 'is_active' => true]);
    $user->shouldReceive('can')->andReturn(false)->byDefault();
    $this->actingAs($user)->get('/test-scheduler')->assertForbidden();
    $user->shouldReceive('can')->with('access-system')->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(true);
    foreach (['en', 'tr', 'de', 'fr', 'es', 'it'] as $locale) {
      app()->setLocale($locale);
      $html = $this->get('/test-scheduler')->assertOk()->getContent();
      $this->assertStringContainsString('artisan webblocks:scheduler:setup --install', $html);
      $this->assertStringNotContainsString('scheduler.health_', $html);
      $this->assertStringNotContainsString('scheduler.description', $html);
    }
    $this->assertStringContainsString(route('admin.system.scheduler.index'), app(SystemHealthController::class)->index(app(SystemHealthRequest::class), app(SystemHealthQuery::class))->render());
    $this->assertSame(0, $this->host->reads);
    $this->assertSame([], $this->host->writes);
    $this->assertNull(app(SchedulerHealth::class)->snapshot()['last_seen_at']);
    $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('admin.system.scheduler.index')->methods());
  }

  #[Test]
  public function retention_table_browser_fixture_keeps_all_field_labels_and_policy_values(): void
  {
    app(SystemMaintenanceReports::class)->refresh();
    request()->merge(['tab' => 'settings']);
    $html = app(MaintenanceCleanupController::class)->index()->render();
    $dom = new \DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xpath = new \DOMXPath($dom);
    $this->assertSame(3, $xpath->query('//table[contains(@class,"wb-cms-policy-table")]')->length);
    $this->assertSame(0, $xpath->query('//fieldset|//legend')->length);
    foreach ($xpath->query('//input[@type="number"]') as $input) {
      $this->assertSame(1, $xpath->query('//label[@for="'.$input->getAttribute('id').'"]')->length);
      $this->assertSame(1, $xpath->query('//input[@name="'.$input->getAttribute('name').'"]')->length);
    }
    $this->assertSame(7, $xpath->query('//table[contains(@class,"wb-cms-policy-table")]//input[@type="checkbox"]')->length);
    $this->assertSame('14', $xpath->query('//input[@name="backup_cleanup_pre_update_days"]')->item(0)->getAttribute('value'));
    $this->assertSame('5', $xpath->query('//input[@name="backup_cleanup_keep_latest_pre_update"]')->item(0)->getAttribute('value'));
    $this->assertSame('24', $xpath->query('//input[@name="temporary_workspace_hours"]')->item(0)->getAttribute('value'));
    $this->assertSame(1, $xpath->query('//tr[@id="cleanup-page-revisions-policy"]/th[@scope="row"]')->length);
    if ($path = getenv('WEBBLOCKS_RETENTION_FIXTURE')) {
      file_put_contents($path, $html);
    }
    request()->setLaravelSession(session()->driver());
    session()->flashInput(['backup_cleanup_pre_update_days' => '9', 'backup_cleanup_keep_latest_pre_update' => '4', 'automatic' => ['temporary-workspaces' => '1']]);
    $retry = app(MaintenanceCleanupController::class)->index()->render();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$retry);
    $xpath = new \DOMXPath($dom);
    $this->assertSame('9', $xpath->query('//input[@name="backup_cleanup_pre_update_days"]')->item(0)->getAttribute('value'));
    $this->assertSame('4', $xpath->query('//input[@name="backup_cleanup_keep_latest_pre_update"]')->item(0)->getAttribute('value'));
    $this->assertSame(1, $xpath->query('//input[@name="automatic[temporary-workspaces]" and @type="checkbox" and @checked]')->length);
    $scheduler = view('webblocks-cms::admin.system.scheduler', ['setup' => $this->service()->instructions()])->render();
    if ($path = getenv('WEBBLOCKS_SCHEDULER_FIXTURE')) {
      file_put_contents($path, $scheduler);
    }
  }
}

class FakeSchedulerHost extends SchedulerHost
{
  public const CONTEXT = ['base' => '/srv/site', 'php' => '/usr/bin/php8.4', 'cli' => true, 'supported' => true, 'uid' => 1000, 'owner' => 1000, 'readable' => true, 'writable' => true];

  public array $values = self::CONTEXT;

  public string $text = '';

  public array $writes = [];

  public int $reads = 0;

  public bool $external = false;

  public ?array $readError = null;

  public int $writeCode = 0;

  public bool $readBackMismatch = false;

  public bool $throwAfterWrite = false;

  public function context(): array
  {
  return $this->values;
  }

  public function externalDefinition(string $base): bool
  {
  return $this->external;
  }

  public function crontab(?string $input = null): array
  {
    if ($input === null) {
      if ($this->throwAfterWrite && $this->writes !== []) {
        throw new \RuntimeException('private-crontab-error');
      }
      $this->reads++;

      return $this->readError ?? ['code' => 0, 'out' => $this->text, 'error' => ''];
    }
    $this->writes[] = $input;
    if ($this->writeCode === 0) {
      $this->text = $this->readBackMismatch ? '# changed after write' : $input;
    }

    return ['code' => $this->writeCode, 'out' => '', 'error' => 'private error details'];
  }
}
