<?php

namespace WebBlocks\Cms\Support\Locales;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;

final class SiteLocaleAssignments
{
  public function withDefault(iterable $ids): Collection
  {
    $ids = collect($ids)->map(fn ($id): int => (int) $id);
    $default = (int) Locale::query()->where('is_default', true)->value('id');

    if ($default > 0) {
      $ids->push($default);
    }

    return $ids->unique()->values();
  }

  public function sync(Site $site, iterable $ids): void
  {
    $site->locales()->sync(collect($ids)->mapWithKeys(fn (int $id) => [$id => ['is_enabled' => true]])->all());
  }
}
