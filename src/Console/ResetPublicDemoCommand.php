<?php

namespace WebBlocks\Cms\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\PublicDemo\PublicDemoGuard;
use WebBlocks\Cms\Support\PublicDemo\PublicDemoResetPreflight;

class ResetPublicDemoCommand extends Command
{
  protected $signature = 'public-demo:reset {--dry-run : Validate every destructive-operation guard without resetting data}';

  protected $description = 'Reset an isolated public demo installation to its configured seed state';

  public function handle(PublicDemoResetPreflight $preflight, PublicDemoGuard $guard): int
  {
    try {
      $preflight->assertSafe();
    } catch (Throwable $exception) {
      $this->error($exception->getMessage());

      return self::FAILURE;
    }

    if ($this->option('dry-run')) {
      $this->info('Public demo reset preflight passed.');

      return self::SUCCESS;
    }

    $closed = $this->components->task('Closing the public demo', fn (): bool => Artisan::call('down', ['--retry' => 60]) === self::SUCCESS);

    if (! $closed) {
      $this->error('The public demo could not enter maintenance mode. No reset was attempted.');

      return self::FAILURE;
    }

    try {
      $exitCode = Artisan::call('migrate:fresh', [
        '--force' => true,
        '--seed' => true,
        '--seeder' => (string) config('webblocks-cms.public_demo.seeder'),
      ]);

      if ($exitCode !== self::SUCCESS) {
        throw new \RuntimeException('The demo database reset failed.');
      }

      $this->assertHealthy($guard);

      if (Artisan::call('up') !== self::SUCCESS) {
        throw new \RuntimeException('The demo could not leave maintenance mode.');
      }
    } catch (Throwable $exception) {
      $this->error($exception->getMessage());
      $this->error('The public demo remains in maintenance mode.');

      return self::FAILURE;
    }

    $this->info('Public demo reset completed and health checks passed.');

    return self::SUCCESS;
  }

  private function assertHealthy(PublicDemoGuard $guard): void
  {
    $user = User::query()->where('email', (string) config('webblocks-cms.public_demo.user_email'))->first();

    if (! $user || ! $guard->isDemoUser($user) || ! $user->canAccessAdmin()) {
      throw new \RuntimeException('The seeded demo user failed its health check.');
    }

    if (Site::query()->count() < max(1, (int) config('webblocks-cms.public_demo.minimum_sites', 1))) {
      throw new \RuntimeException('The seeded demo sites failed their health check.');
    }

    if (Page::query()->count() < max(1, (int) config('webblocks-cms.public_demo.minimum_pages', 1))) {
      throw new \RuntimeException('The seeded demo pages failed their health check.');
    }
  }
}
