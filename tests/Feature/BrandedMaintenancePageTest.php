<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCommandRunner;
use WebBlocks\Cms\Support\Updates\CmsClientCommandRunner;
use WebBlocks\Cms\Support\Updates\CmsUpdateMaintenance;
use WebBlocks\Cms\Tests\TestCase;

class BrandedMaintenancePageTest extends TestCase
{
  protected function defineRoutes($router): void
  {
    $router->get('/maintenance-probe', fn () => 'available');
  }

  public static function locales(): array
  {
    return [
      ['en', 'We are carrying out a brief update', 'Try again'],
      ['tr', 'Kısa bir bakım yapıyoruz', 'Yeniden dene'],
      ['de', 'Wir führen eine kurze Wartung durch', 'Erneut versuchen'],
      ['fr', 'Une courte maintenance est en cours', 'Réessayer'],
      ['es', 'Estamos realizando un breve mantenimiento', 'Volver a intentar'],
      ['it', 'Stiamo effettuando una breve manutenzione', 'Riprova'],
    ];
  }

  #[Test]
  #[DataProvider('locales')]
  public function maintenance_uses_the_error_design_and_translations_without_database_queries(string $locale, string $title, string $cta): void
  {
    app()->setLocale($locale);
    DB::connection()->enableQueryLog();

    $html = view(CmsUpdateMaintenance::VIEW)->render();

    $this->assertSame([], DB::connection()->getQueryLog());
    $this->assertStringContainsString('lang="'.$locale.'"', $html);
    $this->assertStringContainsString($title, $html);
    $this->assertStringContainsString($cta, $html);
    $this->assertStringContainsString('wb-error-shell', $html);
    $this->assertStringContainsString('data-wb-public-theme="canvas"', $html);
    $this->assertStringContainsString('/cms/css/public.css', $html);
    $this->assertStringContainsString('<a href="" class="wb-btn wb-btn-primary">', $html);
    $this->assertStringNotContainsString('<script', $html);
    $this->assertStringNotContainsString('style=', $html);
  }

  #[Test]
  public function the_bound_client_runner_only_customizes_its_default_maintenance_command(): void
  {
    $runner = app(UpdateCommandRunner::class);
    $this->assertInstanceOf(CmsClientCommandRunner::class, $runner);
    $command = $runner->artisanCommand(['down', '--render=errors::503']);
    $this->assertSame(['artisan', ...CmsUpdateMaintenance::arguments()], array_slice($command, 1));
    $this->assertSame(['artisan', 'up'], array_slice($runner->artisanCommand(['up']), 1));
    $this->assertSame(['artisan', 'down', '--render=host-maintenance'], array_slice($runner->artisanCommand(['down', '--render=host-maintenance']), 1));
  }

  #[Test]
  public function maintenance_preserves_503_retry_after_and_preboot_rendering(): void
  {
    app()->setLocale('tr');
    try {
      $this->artisan('down', ['--render' => CmsUpdateMaintenance::VIEW, '--retry' => CmsUpdateMaintenance::RETRY_AFTER, '--status' => 503])->assertSuccessful();
      $this->get('/tr/example?from=visitor')->assertStatus(503)->assertHeader('Retry-After', '60')
        ->assertSee('Kısa bir bakım yapıyoruz')->assertSee('Yeniden dene');
      $jsonRequest = $this->getJson('/example')->assertStatus(503)->assertHeader('Retry-After', '60');
      // Laravel 12 returns its pre-rendered maintenance HTML even for JSON
      // requests. Laravel 13 negotiates JSON after the application boots.
      if (str_starts_with((string) $jsonRequest->headers->get('Content-Type'), 'application/json')) {
        $jsonRequest->assertJsonStructure(['message'])->assertDontSee('wb-error-shell');
      } else {
        $jsonRequest->assertSee('wb-error-shell')->assertSee('Kısa bir bakım yapıyoruz');
      }

      // Execute Laravel's generated entry point in a fresh process with no
      // Composer autoloader or application. This is the real update boundary.
      $script = '$_SERVER["REQUEST_URI"] = "/tr/example?from=visitor";'
        .'$_SERVER["HTTP_ACCEPT"] = "text/html";'
        .'register_shutdown_function(fn () => fwrite(STDERR, (string) http_response_code()));'
        .'require '.var_export(storage_path('framework/maintenance.php'), true).';';
      $process = new Process([PHP_BINARY, '-r', $script]);
      $process->mustRun();
      $this->assertSame('503', $process->getErrorOutput());
      $this->assertStringContainsString('Kısa bir bakım yapıyoruz', $process->getOutput());
      $this->assertStringContainsString('href=""', $process->getOutput());
    } finally {
      $this->artisan('up')->assertSuccessful();
    }
    $this->get('/maintenance-probe')->assertOk()->assertSee('available');
  }
}
