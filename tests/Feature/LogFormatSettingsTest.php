<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Queries\PanelNotificationQuery;
use WebBlocks\Cms\Support\Logging\CmsLogTap;
use WebBlocks\Cms\Support\Logging\ExceptionLogFormatter;
use WebBlocks\Cms\Support\Logging\LogFormatSettings;
use WebBlocks\Cms\Support\Logging\SelectableLogFormatter;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\TestCase;

class LogFormatSettingsTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('logging.channels.fixture-file', ['driver' => 'single', 'path' => sys_get_temp_dir().'/cms-log-unused.log', 'level' => 'warning']);
    $app['config']->set('logging.channels.fixture-remote', ['driver' => 'monolog', 'handler' => NullHandler::class]);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $this->mock(PanelNotificationQuery::class)->shouldReceive('forUser')->andReturn(null);
  }

  private function actor(string $role = 'super_admin', bool $active = true): User
  {
    $user = (new LogFormatUser)->forceFill(['id' => 1, 'name' => 'Synthetic operator', 'email' => 'operator@example.test', 'is_active' => $active, 'role' => $role]);

    return $this->cmsActor($user, []);
  }

  public function test_only_active_super_administrators_can_save_valid_formats_and_other_settings_are_preserved(): void
  {
    foreach (['site_admin', 'editor'] as $role) {
      $this->actingAs($this->actor($role))->putJson('/webadmin/system/settings', ['section' => 'logging', 'log_format' => 'compact'])->assertForbidden();
    }
    $this->actingAs($this->actor('super_admin', false))->putJson('/webadmin/system/settings', ['section' => 'logging', 'log_format' => 'compact'])->assertForbidden();
    $this->actingAs($this->actor())->putJson('/webadmin/system/settings', ['section' => 'logging', 'log_format' => 'invalid'])->assertUnprocessable();
    SystemSetting::create(['key' => SystemSettings::PROJECT_NAME, 'value' => 'Preserved project']);
    foreach (['compact', 'full'] as $mode) {
      $this->put('/webadmin/system/settings', ['section' => 'logging', 'log_format' => $mode, 'project_name' => 'Ignored'])
        ->assertRedirect(route('admin.system.settings.edit', ['tab' => 'logging']))->assertSessionHasNoErrors();
      $this->assertDatabaseHas('wbcms_system_settings', ['key' => SystemSettings::LOG_FORMAT, 'value' => $mode]);
      $this->assertSame($mode, app(LogFormatSettings::class)->mode());
    }
    $this->assertDatabaseHas('wbcms_system_settings', ['key' => SystemSettings::PROJECT_NAME, 'value' => 'Preserved project']);
    $this->get('/webadmin/system/settings?tab=logging')->assertOk()->assertSee('id="settings-log-format"', false)
      ->assertSee('value="full" selected', false);
  }

  private function innerFailure(string $secret): RuntimeException
  {
    return new RuntimeException('Root failure');
  }

  private function middleFailure(): RuntimeException
  {
    return $this->innerFailure('never-log-call-arguments');
  }

  private function exception(): RuntimeException
  {
    return new RuntimeException('Outer wrapper', previous: $this->middleFailure());
  }

  public function test_compact_and_full_modes_render_real_exceptions_without_arguments_and_keep_context(): void
  {
    $exception = $this->exception();
    $record = new LogRecord(new \DateTimeImmutable, 'production', Level::Error, $exception->getMessage(), ['exception' => $exception, 'request_id' => 'fixture-123']);
    $formatter = new SelectableLogFormatter(new LineFormatter('ORIGINAL %message%'));
    $this->assertSame('ORIGINAL Outer wrapper', $formatter->format($record));
    foreach (['compact', 'full', 'compact'] as $mode) {
      app(SystemSettings::class)->save([SystemSettings::LOG_FORMAT => $mode]);
      $output = $formatter->format($record);
      $this->assertStringContainsString('Root failure', $output);
      $this->assertStringContainsString(__FILE__.':', $output);
      $this->assertStringContainsString('fixture-123', $output);
      $this->assertStringNotContainsString('never-log-call-arguments', $output);
      if ($mode === 'compact') {
        $this->assertStringContainsString('#1 ', $output);
        $this->assertStringNotContainsString('#2 ', $output);
        $this->assertStringNotContainsString('Outer wrapper', $output);
      } else {
        $this->assertStringContainsString('#2 ', $output);
        $this->assertStringContainsString('Outer wrapper', $output);
        $this->assertStringContainsString('[previous exception]', $output);
      }
    }
  }

  public function test_full_choice_overrides_an_existing_host_compact_formatter_and_batch_uses_the_same_mode(): void
  {
    $exception = $this->exception();
    $record = new LogRecord(new \DateTimeImmutable, 'production', Level::Error, 'Custom message', ['exception' => $exception]);
    $formatter = new SelectableLogFormatter(new ExceptionLogFormatter);
    app(SystemSettings::class)->save([SystemSettings::LOG_FORMAT => 'full']);
    $this->assertStringContainsString('#2 ', $formatter->formatBatch([$record]));
    $this->assertStringContainsString('Custom message', $formatter->format($record));
  }

  public function test_missing_schema_preserves_the_original_formatter_without_breaking_logging(): void
  {
    Schema::drop('wbcms_system_settings');
    $record = new LogRecord(new \DateTimeImmutable, 'production', Level::Error, 'Database unavailable');
    $formatter = new SelectableLogFormatter(new LineFormatter('FALLBACK %message%'));
    $this->assertSame('FALLBACK Database unavailable', $formatter->format($record));
  }

  public function test_package_boot_wraps_file_handlers_and_preserves_channel_configuration(): void
  {
    $logger = Log::channel('fixture-file');
    $this->assertInstanceOf(SelectableLogFormatter::class, $logger->getHandlers()[0]->getFormatter());
    $this->assertSame('warning', config('logging.channels.fixture-file.level'));
    $this->assertSame(Level::Warning, $logger->getHandlers()[0]->getLevel());
    $this->assertNotContains(CmsLogTap::class, config('logging.channels.fixture-remote.tap', []));
  }

  public function test_a_resolved_real_file_logger_switches_modes_without_rebuilding_or_changing_levels(): void
  {
    $path = sys_get_temp_dir().'/cms-log-format-'.bin2hex(random_bytes(6)).'.log';
    config(['logging.channels.fixture-file.path' => $path]);
    $logger = Log::channel('fixture-file');
    try {
      app(SystemSettings::class)->save([SystemSettings::LOG_FORMAT => 'compact']);
      $logger->error('Compact record', ['exception' => $this->exception()]);
      $compact = file_get_contents($path);
      $this->assertStringContainsString('#1 ', $compact);
      $this->assertStringNotContainsString('#2 ', $compact);
      app(SystemSettings::class)->save([SystemSettings::LOG_FORMAT => 'full']);
      $logger->info('Below configured threshold');
      $logger->error('Full record', ['exception' => $this->exception()]);
      $output = file_get_contents($path);
      $this->assertStringContainsString('#2 ', $output);
      $this->assertStringNotContainsString('Below configured threshold', $output);
      $this->assertStringNotContainsString('never-log-call-arguments', $output);
    } finally {
      $logger->getHandlers()[0]->close();
      if (is_file($path)) {
        unlink($path);
      }
    }
  }

  public function test_logging_during_mode_lookup_cannot_recursively_query_the_setting(): void
  {
    $formatter = new SelectableLogFormatter(new LineFormatter('ORIGINAL %message%'));
    $record = new LogRecord(new \DateTimeImmutable, 'production', Level::Error, 'Nested record');
    $nested = null;
    DB::listen(function ($query) use ($formatter, $record, &$nested): void {
      if ($nested === null && str_contains($query->sql, 'wbcms_system_settings') && str_starts_with(strtolower($query->sql), 'select')) {
        $nested = $formatter->format($record);
      }
    });
    $this->assertSame('ORIGINAL Nested record', $formatter->format($record));
    $this->assertSame('ORIGINAL Nested record', $nested);
  }
}

class LogFormatUser extends User
{
  public function isSuperAdmin(): bool
  {
    return $this->role === 'super_admin';
  }

  public function normalizedRole(): string
  {
    return $this->role;
  }

  public function canAccessAdmin(): bool
  {
    return (bool) $this->is_active;
  }

  public function accessibleSiteIds(): Collection
  {
    return Site::pluck('id');
  }
}
