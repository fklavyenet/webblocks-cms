<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Support\Search\PublicSearchSchema;

class SearchRebuildCommand extends Command
{
  protected $signature = 'search:rebuild
    {--site= : Site id, handle, or domain}
    {--locale= : Locale code}
    {--page= : Page id}';

  protected $description = 'Rebuild the derived public search index';

  public function __construct(
    private readonly PublicSearchIndexer $indexer,
    private readonly PublicSearchSchema $schema,
  ) {
    parent::__construct();
  }

  public function handle(): int
  {
    if (! $this->schema->tableExists()) {
      $this->error('Public search index table is missing. Run `php artisan migrate` first.');

      return self::FAILURE;
    }

    $site = $this->resolveSite($this->option('site'));
    $locale = $this->resolveLocale($this->option('locale'));
    $page = $this->resolvePage($this->option('page'));

    $result = $this->indexer->rebuild($site, $locale, $page);

    $this->info('Public search index rebuilt.');
    $this->line('Indexed rows: '.$result->indexed);
    $this->line('Skipped pages/locales: '.$result->skipped);

    return self::SUCCESS;
  }

  private function resolveSite(?string $value): ?Site
  {
    $value = is_string($value) ? trim($value) : null;

    if (! $value) {
      return null;
    }

    $site = Site::query()
      ->where(function ($query) use ($value) {
        if (ctype_digit($value)) {
          $query->whereKey((int) $value);
        }

        $query->orWhere('handle', $value)
          ->orWhere('domain', $value);
      })
      ->first();

    if (! $site) {
      $this->fail('Site not found for ['.$value.'].');
    }

    return $site;
  }

  private function resolveLocale(?string $value): ?Locale
  {
    $value = Locale::normalizeCode(is_string($value) ? trim($value) : null);

    if (! $value) {
      return null;
    }

    $locale = Locale::query()->where('code', $value)->first();

    if (! $locale) {
      $this->fail('Locale not found for ['.$value.'].');
    }

    return $locale;
  }

  private function resolvePage(mixed $value): ?Page
  {
    $pageId = (int) $value;

    if ($pageId <= 0) {
      return null;
    }

    $page = Page::query()->find($pageId);

    if (! $page) {
      $this->fail('Page not found for ['.$pageId.'].');
    }

    return $page;
  }
}
