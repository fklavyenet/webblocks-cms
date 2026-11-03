<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Jobs\TransitionJobRun;
use WebBlocks\Cms\Actions\Seo\ManageSeoAudit;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\JobRun;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\SeoAudit;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Jobs\JobAutomation;
use WebBlocks\Cms\Support\Jobs\JobDefinition;
use WebBlocks\Cms\Support\Jobs\JobRegistry;
use WebBlocks\Cms\Support\Jobs\JobRunner;
use WebBlocks\Cms\Support\Jobs\JobSchema;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

class JobExecutionTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('auth.providers.users.model', User::class);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    if (! class_exists(User::class)) {
      class_alias(MaintenanceTestUser::class, User::class);
    }
    parent::setUp();
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
  }

  private function actor(string $role = 'super_admin', array $sites = []): User
  {
    $actor = User::forceCreate(['name' => 'Synthetic operator', 'email' => uniqid().'@example.test', 'password' => 'unused', 'role' => $role, 'is_active' => true]);
    $this->cmsActor($actor, $sites);
    $this->actingAs($actor);

    return $actor;
  }

  private function site(): Site
  {
    $locale = Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site = Site::create(['name' => 'Synthetic studio', 'handle' => uniqid('studio'), 'domain' => uniqid().'.example.test']);
    $site->locales()->sync([$locale->id => ['is_enabled' => true]]);

    return $site;
  }

  private function registry(array $jobs): void
  {
    $this->app->instance(JobRegistry::class, new class($jobs) extends JobRegistry
    {
      public function __construct(private array $jobs) {}

      public function all(): array
      {
        return collect($this->jobs)->keyBy('key')->all();
      }
    });
  }

  #[Test]
  public function seo_completes_from_explicit_panel_steps_without_cron_or_get_side_effects(): void
  {
    $site = $this->site();
    Page::create(['site_id' => $site->id, 'title' => 'Synthetic', 'slug' => 'sample', 'status' => 'published']);
    $this->actor('editor', [$site->id]);
    $this->post('/webadmin/sites/'.$site->id.'/seo-audits')->assertSessionHas('seo_manual_run');
    $audit = SeoAudit::latest('id')->firstOrFail();
    $this->get('/webadmin/sites/'.$site->id.'/seo')->assertOk()->assertSee('Run in this screen')->assertDontSee('<dl', false);
    $this->assertDatabaseCount('wbcms_job_runs', 0);
    $this->assertDatabaseCount('wbcms_seo_audit_pages', 0);
    $url = '/webadmin/sites/'.$site->id.'/seo-audits/'.$audit->id.'/work';
    for ($i = 0; $i < 20 && in_array($audit->fresh()->state, ['queued', 'running'], true); $i++) {
      $this->postJson($url)->assertOk();
    }
    $this->assertContains($audit->fresh()->state, ['complete', 'partial']);
    $this->assertGreaterThan(0, DB::table('wbcms_seo_audit_findings')->count());
    $this->assertDatabaseCount('wbcms_job_runs', 1);
    $this->assertDatabaseCount('wbcms_job_ticks', 0);
    $this->assertNull(app(SchedulerHealth::class)->snapshot()['last_seen_at']);
    $this->assertFalse(app(JobAutomation::class)->status()['sources']['cli']['verified']);
  }

  #[Test]
  public function site_users_cannot_execute_other_sites_or_system_jobs_and_automation_requires_confirmation(): void
  {
    $site = $this->site();
    $other = $this->site();
    $this->actor('editor', [$site->id]);
    $run = app(JobRunner::class)->request('seo', $other->id, app(ManageSeoAudit::class)->start($other)->id);
    $this->postJson('/webadmin/jobs/'.$run->id.'/step')->assertForbidden();
    $this->postJson('/webadmin/jobs', ['job_key' => 'probe'])->assertForbidden();
    $this->postJson('/webadmin/jobs/automation/rotate', ['confirmed' => 1])->assertForbidden();
    $this->get('/webadmin/jobs')->assertOk()->assertDontSee('Hosting setup commands');
    $this->actor();
    $this->postJson('/webadmin/jobs/automation/rotate')->assertStatus(422);
    $this->postJson('/webadmin/jobs', ['job_key' => 'not-registered'])->assertStatus(422);
    $this->postJson('/webadmin/jobs', ['job_key' => 'notifications'])->assertStatus(422);
    $this->post('/webadmin/jobs', ['job_key' => 'probe'])->assertRedirect();
    $this->get('/webadmin/jobs?tab=automation')->assertOk()->assertSee('Jobs and automation')->assertSee('jobs-automation', false);
  }

  #[Test]
  public function automatic_ticks_do_not_take_over_manual_runs_and_changed_policy_pauses_before_work(): void
  {
    $calls = 0;
    $policy = 'first';
    // Capture mutable policy by reference to model an operator changing saved settings.
    $this->registry([new JobDefinition('guarded', 'test', function () use (&$calls) {
      $calls++;

      return ['state' => 'running', 'processed' => $calls];
    }, '* * * * *', web: true, automatic: false, guard: function () use (&$policy) {
    return hash('sha256', $policy);
    })]);
    $runner = app(JobRunner::class);
    $run = $runner->request('guarded');
    $runner->tick('cli');
    $this->assertSame(0, $calls);
    $this->assertSame('running', $runner->advance($run)->state);
    $policy = 'second';
    $this->assertSame('paused', $runner->advance($run)->state);
    $this->assertSame('policy_changed', $run->reason);
    $this->assertSame(1, $calls);
    $this->assertDatabaseCount('wbcms_job_attempts', 1);
  }

  #[Test]
  public function manual_installations_have_no_implicit_domain_automation_or_web_cron_installer(): void
  {
    $this->site();
    $registry = app(JobRegistry::class);
    foreach ($registry->all() as $job) {
      if ($job->key !== 'probe') {
      $this->assertFalse($registry->automatic($job), $job->key);
      }
    }
    $this->assertFalse(app(JobAutomation::class)->status()['has_automatic']);
    $this->actor();
    $this->get('/webadmin/jobs')->assertOk()->assertSee('Manual operation is available without cron or a queue worker.');
    $this->post('/webadmin/jobs/automation/install', ['confirmed' => 1])->assertNotFound();
    $this->post('/webadmin/jobs', ['job_key' => 'reports'])->assertSessionHasErrors('job_key');
    $this->assertDatabaseCount('wbcms_job_runs', 0);
    $this->post('/webadmin/jobs', ['job_key' => 'editor-recovery', 'confirmed' => 1])->assertSessionHas('job_manual_run');
    $this->get('/webadmin/jobs')->assertOk()->assertSee('data-autostart="true"', false);
    $this->get('/webadmin/jobs')->assertOk()->assertDontSee('data-autostart="true"', false);
    DB::table('wbcms_job_settings')->insert(['job_key' => 'seo', 'automatic' => true]);
    $this->assertTrue($registry->automatic($registry->get('seo')));
  }

  #[Test]
  public function admissions_are_idempotent_and_live_leases_exclude_other_workers(): void
  {
    $calls = 0;
    $this->registry([new JobDefinition('synthetic', 'test', function () use (&$calls) {
    $calls++;

    return ['state' => 'complete'];
    }, web: true)]);
    $runner = app(JobRunner::class);
    $run = $runner->request('synthetic');
    $this->assertSame($run->id, $runner->request('synthetic')->id);
    $run->update(['lease_token' => 'other', 'lease_until' => now()->addMinute()]);
    $runner->advance($run);
    $this->assertSame(0, $calls);
    $run->update(['lease_until' => now()->subSecond()]);
    $this->assertSame('complete', $runner->advance($run)->state);
    $this->assertSame(1, $calls);
    $this->assertDatabaseCount('wbcms_job_attempts', 1);
    $this->assertNotSame($run->id, $runner->request('synthetic')->id);
  }

  #[Test]
  public function old_worker_cannot_commit_after_lease_replacement(): void
  {
    $this->registry([new JobDefinition('synthetic', 'test', function ($run) {
      JobRun::query()->whereKey($run->id)->update(['lease_token' => 'replacement', 'processed' => 77]);

      return ['state' => 'complete', 'processed' => 1];
    }, web: true)]);
    $runner = app(JobRunner::class);
    $run = $runner->advance($runner->request('synthetic'));
    $this->assertSame(77, $run->processed);
    $this->assertSame('replacement', $run->lease_token);
    $this->assertNull($run->completed_at);
  }

  #[Test]
  public function http_is_separate_authenticated_automatic_evidence_and_rotation_revokes_previous_key(): void
  {
    $this->registry([new JobDefinition('probe', 'test', fn () => ['state' => 'complete'], '* * * * *', web: true, automatic: true)]);
    $service = app(JobAutomation::class);
    $first = $service->rotate();
    $this->assertStringNotContainsString($first, (string) DB::table('wbcms_job_automation')->value('token_hash'));
    $this->postJson('https://localhost/webblocks-jobs/tick')->assertUnauthorized();
    $this->withToken($first)->postJson('http://localhost/webblocks-jobs/tick')->assertNotFound();
    $this->postJson('https://localhost/webblocks-jobs/tick')->assertOk()->assertExactJson(['ok' => true]);
    $this->assertFalse($service->status()['sources']['http']['verified']);
    $this->postJson('https://localhost/webblocks-jobs/tick')->assertOk();
    $this->assertDatabaseCount('wbcms_job_runs', 1);
    $this->travel(1)->minutes();
    $this->postJson('https://localhost/webblocks-jobs/tick')->assertOk();
    $this->assertTrue($service->status()['sources']['http']['verified']);
    $this->assertFalse($service->status()['sources']['cli']['verified']);
    $this->assertNull(app(SchedulerHealth::class)->snapshot()['last_seen_at']);
    $next = $service->rotate();
    $this->withToken($first)->postJson('https://localhost/webblocks-jobs/tick')->assertUnauthorized();
    $this->withToken($next)->postJson('https://localhost/webblocks-jobs/tick')->assertOk();
    $service->disable();
    $this->postJson('https://localhost/webblocks-jobs/tick')->assertUnauthorized();
    $this->travelBack();
  }

  #[Test]
  public function cli_only_tasks_are_not_executed_in_web_and_new_automatic_tasks_require_opt_in(): void
  {
    $calls = 0;
    $this->registry([new JobDefinition('synthetic', 'test', function () use (&$calls) {
    $calls++;

    return ['state' => 'complete'];
    }, '* * * * *', automatic: false)]);
    $runner = app(JobRunner::class);
    $runner->tick('cli');
    $this->assertSame(0, $calls);
    DB::table('wbcms_job_settings')->insert(['job_key' => 'synthetic', 'automatic' => true]);
    $runner->tick('http');
    $this->assertSame(0, $calls);
    $run = $runner->request('synthetic');
    $runner->advance($run);
    $this->assertSame(0, $calls);
    $runner->advance($run, 'cli');
    $this->assertSame(1, $calls);
  }

  #[Test]
  public function failure_records_only_safe_codes_and_keeps_sensitive_exception_text_out_of_history(): void
  {
    $this->registry([new JobDefinition('synthetic', 'test', function () {
    throw new \RuntimeException('private-provider-secret');
    }, web: true)]);
    $runner = app(JobRunner::class);
    $run = $runner->advance($runner->request('synthetic'));
    $this->assertSame('failed', $run->state);
    $this->assertSame('execution_failed', $run->reason);
    $this->assertStringNotContainsString('private-provider-secret', json_encode(DB::table('wbcms_job_attempts')->get()));
  }

  #[Test]
  public function migrations_are_idempotent_and_all_stored_fields_are_relational(): void
  {
    JobSchema::up();
    app(JobRunner::class)->request('probe');
    JobSchema::up();
    $this->assertDatabaseCount('wbcms_job_runs', 1);
    foreach (['runs', 'attempts', 'automation', 'ticks', 'settings'] as $table) {
      foreach (Schema::getColumns('wbcms_job_'.$table) as $column) {
        $this->assertNotSame('json', $column['type_name']);
      }
    }
  }

  #[Test]
  public function manual_cli_does_not_establish_automatic_health_and_failed_probe_does_not_verify_a_tick(): void
  {
    $this->registry([new JobDefinition('probe', 'test', fn () => ['state' => 'failed'], '* * * * *', web: true, automatic: true)]);
    $this->artisan('webblocks:jobs:work')->assertSuccessful();
    $this->assertDatabaseCount('wbcms_job_ticks', 0);
    app(JobRunner::class)->tick('cli');
    $this->travel(1)->minutes();
    app(JobRunner::class)->tick('cli');
    $this->assertDatabaseCount('wbcms_job_ticks', 2);
    $this->assertFalse(app(JobAutomation::class)->status()['sources']['cli']['verified']);
    $this->travelBack();
  }

  #[Test]
  public function pause_resume_and_cancel_preserve_seo_domain_state_and_defer_leased_transitions(): void
  {
    $site = $this->site();
    $this->actor('editor', [$site->id]);
    $audit = app(ManageSeoAudit::class)->start($site);
    $run = app(JobRunner::class)->request('seo', $site->id, $audit->id);
    $url = '/webadmin/jobs/'.$run->id.'/transition';
    $this->post($url, ['action' => 'pause'])->assertRedirect();
    $this->assertSame('paused', $audit->fresh()->state);
    $this->assertSame('paused', $run->fresh()->state);
    $this->post($url, ['action' => 'resume'])->assertRedirect();
    $this->assertSame('queued', $audit->fresh()->state);
    $run->update(['lease_token' => 'other', 'lease_until' => now()->addMinute()]);
    $this->post($url, ['action' => 'cancel'])->assertRedirect();
    $this->assertSame('cancelled', $run->fresh()->requested_state);
    $run->update(['lease_token' => null, 'lease_until' => null, 'requested_state' => null]);
    $this->post($url, ['action' => 'cancel'])->assertRedirect();
    $this->assertSame('cancelled', $run->fresh()->state);
    $this->assertSame('paused', $audit->fresh()->state);
  }

  #[Test]
  public function six_locales_render_the_jobs_workspace_without_expanded_metadata_stacks(): void
  {
    $actor = $this->actor();
    $english = require dirname(__DIR__, 2).'/resources/lang/en/jobs.php';
    foreach (['en', 'tr', 'de', 'es', 'fr', 'it'] as $locale) {
      $catalog = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/jobs.php';
      $this->assertSame(array_keys($english), array_keys($catalog));
      $actor->forceFill(['admin_locale' => $locale])->save();
      $this->get('/webadmin/jobs?tab=automation')->assertOk()->assertSee($catalog['title'])->assertDontSee('jobs.title')->assertDontSee('<dl', false);
    }
    $this->assertDatabaseCount('wbcms_job_ticks', 0);
    $this->assertDatabaseCount('wbcms_job_runs', 0);
  }

  #[Test]
  public function stop_requests_take_effect_after_the_current_step_without_replaying_its_effect(): void
  {
    $calls = 0;
    $this->registry([new JobDefinition('synthetic', 'test', function ($run) use (&$calls) {
      $calls++;
      app(TransitionJobRun::class)->execute($run, 'pause');

      return ['state' => 'running', 'processed' => 1];
    }, web: true)]);
    $runner = app(JobRunner::class);
    $run = $runner->advance($runner->request('synthetic'));
    $this->assertSame('paused', $run->state);
    $this->assertSame(1, $run->processed);
    $this->assertNull($run->requested_state);
    $runner->advance($run);
    $this->assertSame(1, $calls);
  }

  #[Test]
  public function leased_or_disabled_work_cannot_starve_an_available_job(): void
  {
    $this->registry([new JobDefinition('synthetic', 'test', fn () => ['state' => 'complete'], web: true, automatic: true)]);
    $runner = app(JobRunner::class);
    for ($i = 1; $i <= 25; $i++) {
      $run = $runner->request('synthetic', subject: $i);
      $run->update(['lease_token' => 'busy', 'lease_until' => now()->addMinutes(5)]);
    }
    $available = $runner->request('synthetic', subject: 100);
    $runner->tick('http');
    $this->assertSame('complete', $available->fresh()->state);
    $this->assertSame(25, JobRun::query()->whereNotNull('lease_token')->count());
  }
}
