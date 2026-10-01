<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Pages\PublicMount;

// Run in a fresh PHP process against a real installed Laravel consumer.
// Arguments: consumer root, seed|uncached|cached.
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$console = $app->make(Kernel::class);
$console->bootstrap();

$assert = static function (bool $condition, string $message): void {
  if (! $condition) {
    throw new RuntimeException($message);
  }
};

if ($argv[2] === 'seed') {
  $site = Site::query()->where('is_primary', true)->firstOrFail();
  $english = Locale::query()->where('is_default', true)->firstOrFail();
  $german = Locale::query()->firstOrCreate(['code' => 'de'], ['name' => 'German', 'is_enabled' => true, 'is_default' => false]);
  $site->locales()->syncWithoutDetaching([$german->id => ['is_enabled' => true]]);
  $home = $site->pages()->whereHas('translations', fn ($query) => $query->where('path', '/'))->firstOrFail();
  $home->update(['status' => 'published']);
  $home->translations()->updateOrCreate(['locale_id' => $german->id], ['name' => 'German homepage', 'slug' => 'home', 'path' => '/']);
  foreach (['/about', '/blog/article', '/host-missing'] as $path) {
    $page = Page::query()->create(['site_id' => $site->id, 'status' => 'published']);
    foreach ([$english, $german] as $locale) {
      $page->translations()->create(['locale_id' => $locale->id, 'name' => 'Consumer page', 'slug' => basename($path), 'path' => $path]);
    }
  }
  echo "Consumer routing fixtures seeded.\n";
  exit(0);
}

$assert($app->routesAreCached() === ($argv[2] === 'cached'), 'Unexpected route cache state.');
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$mounted = app(PublicMount::class)->prefix() !== '';
$base = $mounted ? '/wb' : '';
$hostHome = $app->make('router')->getRoutes()->getByName('home');
$assert($hostHome->uri() === '/', 'The host home route name was replaced.');
if ($mounted) {
  $assert($app->make('router')->getRoutes()->getByName('webblocks.public.home')?->uri() === 'wb', 'Mounted CMS home name was not disambiguated.');
}
$checks = [
  '/' => [200, 'host'],
  '/up' => [200, 'health'],
  '/settings' => [200, 'host'],
  '/de' => [200, 'host'],
  '/en' => [200, 'host'],
  '/products' => [200, 'host'],
  '/products/42' => [200, 'host'],
  '/account/settings' => [200, 'host'],
  '/orders/42/items/7' => [200, 'host'],
  '/host-missing' => [404, 'missing'],
  $base.'/about' => [200, 'cms'],
  $base.'/de/about' => [200, 'cms'],
  $base.'/blog/article' => [200, 'cms'],
  $base.'/en/about' => [404, 'cms'],
  $base.'/sitemap.xml' => [200, 'sitemap'],
  $base.'/search.json' => [200, 'search'],
  $base.'/p/about' => [301, 'legacy'],
  $base.'/de/p/about' => [301, 'legacy'],
];
if ($mounted) {
  $checks['/wb'] = [200, 'cms'];
  $checks['/wb/de'] = [200, 'cms'];
  $checks['/about'] = [404, 'outside'];
}
foreach ($checks as $path => [$status, $owner]) {
  $request = Request::create('http://localhost'.$path);
  $response = $kernel->handle($request);
  $assert($response->getStatusCode() === $status, $path.' expected '.$status.', received '.$response->getStatusCode());
  if ($owner === 'host') {
    $assert($response->getContent() === 'host:'.$request->path(), $path.' was not served by the host controller.');
  } elseif ($owner === 'health') {
    $assert(! $request->route()->isFallback && $request->route()->uri() === 'up', '/up did not select Laravel health.');
  } elseif ($owner === 'cms') {
    $assert($request->route()->isFallback, $path.' was not a CMS content fallback.');
    $assert(str_contains($request->route()->getActionName(), 'PageController'), $path.' did not select CMS pages.');
  } elseif ($owner === 'legacy') {
    $assert($response->headers->get('Location') === 'http://localhost'.$base.(str_contains($path, '/de/') ? '/de' : '').'/about', 'Incorrect legacy redirect for '.$path);
  }
  $kernel->terminate($request, $response);
}
echo 'Consumer routing passed: '.($mounted ? 'mounted' : 'integrated').' / '.$argv[2].' ('.count($checks)." requests).\n";
