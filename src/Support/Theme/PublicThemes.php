<?php

namespace WebBlocks\Cms\Support\Theme;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;
use WebBlocks\Cms\Support\PublicRendering\SiteAssetResolver;

final class PublicThemes
{
  public const CORNERS = ['default', 'sharp', 'soft'];

  private const VERSIONS = 'wbcms_theme_versions';

  private const LAYOUTS = 'wbcms_theme_layouts';

  private const ASSETS = 'wbcms_theme_assets';

  private const CHANGES = 'wbcms_site_theme_changes';

  public function __construct(
    private readonly ThemePackage $packages,
    private readonly RequestMemo $memo,
    private readonly SiteAssetResolver $siteAssets,
  ) {}

  public function ready(): bool
  {
    return $this->memo->remember('public-themes.schema', [], fn () => Schema::hasTable(self::CHANGES));
  }

  public function versions(int $perPage = 25, bool $packageManagement = false): LengthAwarePaginator
  {
    $versions = DB::table(self::VERSIONS.' as versions')->select('versions.*')
      ->when($packageManagement, fn ($query) => $query->selectSub(
        DB::table(self::CHANGES)->selectRaw('count(*)')->whereColumn('theme_version_id', 'versions.id'), 'uses'
      ))
      ->orderBy('handle')->orderByDesc('id')->paginate($perPage, ['*'], $packageManagement ? 'page' : 'version_page');

    return $versions->through(fn (object $version) => $this->describe($version));
  }

  public function describe(object $version): object
  {
    $version->compatible = ThemePackage::compatible($version);
    $version->layouts = $this->layouts((int) $version->id);
    $version->button_corners = (bool) $version->button_corners;

    return $version;
  }

  public function install(string $file): object
  {
    $package = $this->packages->read($file);
    try {
      $id = DB::transaction(function () use ($package): int {
        $id = DB::table(self::VERSIONS)->insertGetId($package['version'] + ['created_at' => now()]);
        DB::table(self::LAYOUTS)->insert(array_map(fn (string $layout) => ['theme_version_id' => $id, 'layout' => $layout], $package['layouts']));
        foreach ($package['assets'] as $path => $asset) {
          DB::table(self::ASSETS)->insert($asset + ['theme_version_id' => $id, 'path' => $path]);
        }

        return $id;
      });
    } catch (QueryException $exception) {
      $existing = DB::table(self::VERSIONS)->where('handle', $package['version']['handle'])->where('version', $package['version']['version'])->first();
      if ($existing === null) {
        throw $exception;
      }
      if (! hash_equals($existing->checksum, $package['version']['checksum'])) {
        $this->invalid('package', 'duplicate_version');
      }

      return $this->describe($existing);
    }

    return $this->describe($this->version($id));
  }

  public function version(int $id, bool $lock = false): object
  {
    $version = DB::table(self::VERSIONS)->where('id', $id)->when($lock, fn ($query) => $query->lockForUpdate())->first();
    abort_if($version === null, 404);

    return $version;
  }

  public function delete(int $id): void
  {
    DB::transaction(function () use ($id): void {
      $this->version($id, lock: true);
      if (DB::table(self::CHANGES)->where('theme_version_id', $id)->exists()) {
        $this->invalid('package', 'in_use');
      }
      DB::table(self::VERSIONS)->where('id', $id)->delete();
    }, 3);
  }

  public function asset(int $id, string $checksum, string $path): ?object
  {
    if (! $this->ready()) {
      return null;
    }
    $asset = DB::table(self::ASSETS.' as assets')
      ->join(self::VERSIONS.' as versions', 'versions.id', '=', 'assets.theme_version_id')
      ->where('versions.id', $id)->where('versions.checksum', $checksum)->where('assets.path', $path)
      ->first(['assets.*']);

    return $asset && hash_equals($asset->checksum, hash('sha256', $asset->contents)) ? $asset : null;
  }

  public function history(Site $site): LengthAwarePaginator
  {
    return DB::table(self::CHANGES.' as changes')
      ->leftJoin(self::VERSIONS.' as versions', 'versions.id', '=', 'changes.theme_version_id')
      ->where('changes.site_id', $site->id)->latest('changes.id')
      ->paginate(25, ['changes.*', 'versions.name', 'versions.version'], 'history_page');
  }

  public function current(Site $site): ?object
  {
    return DB::table(self::CHANGES.' as changes')
      ->leftJoin(self::VERSIONS.' as versions', 'versions.id', '=', 'changes.theme_version_id')
      ->where('changes.site_id', $site->id)->latest('changes.id')
      ->first(['changes.*', 'versions.name', 'versions.version']);
  }

  public function render(?Site $site, ?Page $page): ?array
  {
    if (! $site || ! $this->ready()) {
      return null;
    }

    return $this->memo->remember('public-themes.render', [$site->id, $page?->publicShellPreset()], function () use ($site, $page): ?array {
      $change = $this->current($site);
      if (! $change?->theme_version_id) {
        return null;
      }
      $version = DB::table(self::VERSIONS)->find($change->theme_version_id);
      if (! $version || ! ThemePackage::compatible($version) || ($page && ! in_array($page->publicShellPreset(), $this->layouts((int) $version->id), true))
        || ! $this->asset((int) $version->id, $version->checksum, ThemePackage::STYLESHEET)) {
        return null;
      }

      return $this->appearance($version, $change->button_corners);
    });
  }

  public function intact(object $version): bool
  {
    $assets = DB::table(self::ASSETS)->where('theme_version_id', $version->id)->cursor();
    $stylesheet = false;
    foreach ($assets as $asset) {
      if (! hash_equals($asset->checksum, hash('sha256', $asset->contents))) {
        return false;
      }
      $stylesheet = $stylesheet || $asset->path === ThemePackage::STYLESHEET;
    }

    return $stylesheet;
  }

  public function appearance(object $version, string $corners): array
  {
    if (! in_array($corners, self::CORNERS, true) || (! $version->button_corners && $corners !== 'default')) {
      $this->invalid('button_corners', 'invalid_setting');
    }

    return [
      'handle' => $version->handle,
      'corners' => $corners,
      'url' => route('public-theme-assets.show', [
        'version' => $version->id, 'checksum' => $version->checksum, 'path' => ThemePackage::STYLESHEET,
      ], false),
    ];
  }

  public function report(Site $site, object $version): array
  {
    $layouts = $this->layouts((int) $version->id);
    $affected = [];
    foreach ($this->pages($site)->with('translations.locale')->lazyById() as $page) {
      $layout = $page->publicShellPreset();
      if (! in_array($layout, $layouts, true)) {
        $affected[] = ['id' => $page->id, 'title' => $page->title, 'layout' => $layout];
      }
    }

    return [
      'compatible' => ThemePackage::compatible($version),
      'intact' => $this->intact($version),
      'affected_pages' => $affected,
    ];
  }

  public function pages(Site $site): Builder
  {
    return Page::query()->where('site_id', $site->id)
      ->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)
      ->whereIn('status', [Page::STATUS_DRAFT, Page::STATUS_IN_REVIEW, Page::STATUS_PUBLISHED])
      ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('wbcms_page_revision_candidates')
        ->whereColumn('candidate_page_id', 'wbcms_pages.id'));
  }

  public function confirmation(Site $site, object $version, string $corners, int $expected): string
  {
    $this->appearance($version, $corners);

    return Crypt::encrypt([
      'site' => (int) $site->id,
      'version' => (int) $version->id,
      'corners' => $corners,
      'state' => $this->state($site, $expected),
      'expires' => now()->addMinutes(30)->timestamp,
    ]);
  }

  public function activate(Site $site, string $confirmation): int
  {
    try {
      $preview = Crypt::decrypt($confirmation);
    } catch (DecryptException) {
      $this->invalid('confirmation', 'stale_preview');
    }
    if (! is_array($preview) || ($preview['site'] ?? null) !== (int) $site->id || ($preview['expires'] ?? 0) < now()->timestamp) {
      $this->invalid('confirmation', 'stale_preview');
    }

    return $this->change($site, function (Site $locked, int $current) use ($preview): array {
      if ($this->state($locked, $current) !== $preview['state']) {
        $this->invalid('confirmation', 'stale_preview');
      }

      return [$preview['version'], $preview['corners']];
    });
  }

  public function restore(Site $site, int $expected, ?int $revision): int
  {
    return $this->change($site, function (Site $locked, int $current) use ($expected, $revision): array {
      if ($expected !== $current) {
        $this->invalid('expected', 'stale_preview');
      }
      if ($revision === null) {
        return [null, 'default'];
      }
      $change = DB::table(self::CHANGES)->where('site_id', $locked->id)->where('id', $revision)->first();
      abort_if($change === null, 404);

      return [$change->theme_version_id === null ? null : (int) $change->theme_version_id, $change->button_corners];
    });
  }

  private function change(Site $site, callable $select): int
  {
    $id = DB::transaction(function () use ($site, $select): int {
      $locked = Site::query()->lockForUpdate()->findOrFail($site->id);
      $current = $this->current($locked);
      [$versionId, $corners] = $select($locked, (int) ($current?->id ?? 0));
      if ($versionId !== null) {
        $version = $this->version($versionId, lock: true);
        $this->appearance($version, $corners);
        $report = $this->report($locked, $version);
        if (! $report['compatible'] || ! $report['intact'] || $report['affected_pages'] !== []) {
          $this->invalid('version', 'incompatible_site');
        }
      }
      if ((int) ($current?->theme_version_id ?? 0) === (int) $versionId && ($current?->button_corners ?? 'default') === $corners) {
        return (int) ($current?->id ?? 0);
      }

      return DB::table(self::CHANGES)->insertGetId([
        'site_id' => $locked->id, 'theme_version_id' => $versionId, 'button_corners' => $corners, 'created_at' => now(),
      ]);
    }, 3);
    $this->memo->clear();

    return $id;
  }

  private function layouts(int $id): array
  {
    return $this->memo->remember('public-themes.layouts', [$id], fn () => DB::table(self::LAYOUTS)
      ->where('theme_version_id', $id)->orderBy('layout')->pluck('layout')->all());
  }

  private function state(Site $site, int $expected): string
  {
    return hash('sha256', serialize([
      $expected, $site->resolvedPublicThemePreset(), $site->brandPalette(),
      $this->siteAssets->cssPathFor($site),
    ]));
  }

  private function invalid(string $field, string $key): never
  {
    throw ValidationException::withMessages([$field => __('webblocks-cms::themes.'.$key)]);
  }
}
