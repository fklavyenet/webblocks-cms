<?php

namespace WebBlocks\Cms\Support\Locales;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;
use WebBlocks\Cms\Support\System\SystemSettings;

class LocaleResolver
{
  public function __construct(
    private readonly SystemSettings $systemSettings,
  ) {}

  public function current(?Request $request = null, ?Site $site = null): Locale
  {
    $request ??= request();
    $code = Locale::normalizeCode((string) $request->route('locale'));

    if ($code === null) {
      return $this->default();
    }

    return $this->enabled($code, $site) ?? $this->default();
  }

  public function default(): Locale
  {
    return app(RequestMemo::class)->remember('locale.default', [], fn () => $this->loadDefault());
  }

  private function loadDefault(): Locale
  {
    $configuredCode = $this->systemSettings->defaultLocaleCode();

    return Locale::query()
      ->where('is_enabled', true)
      ->where(function ($query) use ($configuredCode) {
        $query->where('code', $configuredCode)
          ->orWhere('is_default', true);
      })
      ->orderByRaw('case when code = ? then 0 else 1 end', [$configuredCode])
      ->orderByDesc('is_default')
      ->orderBy('id')
      ->firstOrFail();
  }

  public function enabled(string $code, ?Site $site = null): ?Locale
  {
    $code = Locale::normalizeCode($code);

    if ($code === null) {
      return null;
    }

    if ($site) {
      return $this->enabledForSite($site)->firstWhere('code', $code);
    }

    return app(RequestMemo::class)->remember('locale.enabled', [$code], fn () => Locale::query()
      ->where('code', $code)
      ->where('is_enabled', true)->first());
  }

  public function enabledForSite(Site $site): Collection
  {
    return app(RequestMemo::class)->remember('locale.site', [$site->getConnectionName(), $site->id], fn () => $site->enabledLocales()->get());
  }

  public function resolve(string $code): ?Locale
  {
    $code = Locale::normalizeCode($code);

    if ($code === null) {
      return null;
    }

    return app(RequestMemo::class)->remember('locale.resolve', [$code], fn () => Locale::query()->where('code', $code)->first());
  }
}
