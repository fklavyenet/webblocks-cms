<?php

namespace WebBlocks\Cms\Support\PublicDemo;

use Illuminate\Support\Str;
use RuntimeException;

class PublicDemoResetPreflight
{
  public function assertSafe(): void
  {
    $environment = trim((string) config('webblocks-cms.public_demo.environment', 'demo'));
    $host = Str::lower(trim((string) config('webblocks-cms.public_demo.host')));
    $expectedDatabase = trim((string) config('webblocks-cms.public_demo.expected_database'));
    $database = trim((string) config('database.connections.'.config('database.default').'.database'));
    $appUrlHost = Str::lower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    $seeder = trim((string) config('webblocks-cms.public_demo.seeder'));

    $this->require((bool) config('webblocks-cms.public_demo.enabled'), 'Public demo mode is disabled.');
    $this->require((bool) config('webblocks-cms.public_demo.reset_enabled'), 'Public demo reset is disabled.');
    $this->require($environment !== '' && app()->environment($environment), 'The application environment is not the configured demo environment.');
    $this->require($host !== '' && hash_equals($host, $appUrlHost), 'APP_URL does not match the configured demo host.');
    $this->require($expectedDatabase !== '' && hash_equals($expectedDatabase, $database), 'The active database is not the configured demo database.');
    $this->require(config('session.driver') === 'database', 'Public demo reset requires database-backed sessions.');
    $this->require(config('cache.default') === 'database', 'Public demo reset requires a database-backed cache.');
    $this->require(config('queue.default') === 'database', 'Public demo reset requires a database-backed queue.');
    $this->require(config('app.debug') === false, 'Public demo reset requires APP_DEBUG=false.');
    $this->require(in_array(config('mail.default'), ['array', 'log'], true), 'Public demo mail must use the array or log transport.');
    $this->require($seeder !== '' && class_exists($seeder), 'The configured public demo seeder is unavailable.');

    $markerPath = trim((string) config('webblocks-cms.public_demo.marker_path'));
    $markerPath = $markerPath !== '' ? $markerPath : storage_path('app/public-demo.marker');
    $marker = is_file($markerPath) ? trim((string) file_get_contents($markerPath)) : '';
    $this->require($marker !== '' && hash_equals($expectedDatabase, $marker), 'The public demo storage marker is missing or invalid.');
  }

  private function require(bool $condition, string $message): void
  {
    if (! $condition) {
      throw new RuntimeException($message);
    }
  }
}
