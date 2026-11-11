<?php

namespace WebBlocks\Cms\Support\Translations;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use JsonException;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\WebBlocks;

final class PanelLanguages
{
  private const LANGUAGES = 'wbcms_panel_languages';

  private const CATALOGS = 'wbcms_panel_language_catalogs';

  private const LINES = 'wbcms_panel_language_lines';

  public const MAX_KILOBYTES = 2048;

  public function __construct(
    private readonly PanelLanguageSource $source,
    private readonly RequestMemo $memo,
    private readonly SystemSettings $settings,
  ) {}

  public function options(): array
  {
    return $this->memo->remember('panel.languages.options', [], function (): array {
      $options = $this->source->packagedOptions();
      try {
        if (Schema::hasTable(self::LANGUAGES)) {
          foreach ($this->active()->orderBy('languages.code')->get(['languages.code', 'catalogs.name']) as $language) {
            $options[$language->code] = strtoupper($language->code).' - '.$language->name;
          }
        }
      } catch (QueryException) {
        // The bundled options still work before installation or during recovery.
      }

      return $options;
    });
  }

  public function catalogs(): LengthAwarePaginator
  {
    return DB::table(self::CATALOGS.' as catalogs')
      ->join(self::LANGUAGES.' as languages', 'languages.code', '=', 'catalogs.language_code')
      ->select('catalogs.*', 'languages.active_catalog_id')
      ->orderByDesc('catalogs.id')->paginate(25);
  }

  public function import(string $json): int
  {
    try {
      $catalog = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      $this->invalid('invalid_file');
    }

    $code = $catalog['code'] ?? null;
    $name = $catalog['name'] ?? null;
    $translations = $catalog['translations'] ?? null;
    if (($catalog['format_version'] ?? null) !== 1 || ($catalog['source_version'] ?? null) !== WebBlocks::VERSION
      || ! is_string($code) || ! $this->source->validCode($code) || in_array($code, AdminLocaleResolver::SUPPORTED_LOCALES, true)
      || ! is_string($name) || trim($name) === '' || mb_strlen($name) > 100 || ! $this->source->plainText($name)
      || ! is_array($translations) || ! array_is_list($translations) || $translations === []) {
      $this->invalid('invalid_catalog');
    }

    $source = $this->source->messages();
    $lines = [];
    foreach ($translations as $row) {
      $key = $row['key'] ?? null;
      $value = $row['text'] ?? null;
      if (! is_string($key) || ! isset($source[$key]) || isset($lines[$key])
        || ! is_string($value) || trim($value) === '' || mb_strlen($value) > 4000 || ! $this->source->plainText($value)
        || $this->source->placeholders($value) !== $this->source->placeholders($source[$key])
        || (str_contains($value, '|') !== str_contains($source[$key], '|'))) {
        $this->invalid('invalid_line', ['key' => is_string($key) && isset($source[$key]) ? $key : '?']);
      }
      $lines[$key] = ['translation_key' => $key, 'translation' => $value, 'source_hash' => hash('sha256', $source[$key])];
    }

    return DB::transaction(function () use ($code, $name, $lines): int {
      DB::table(self::LANGUAGES)->insertOrIgnore(['code' => $code]);
      $id = DB::table(self::CATALOGS)->insertGetId([
        'language_code' => $code,
        'name' => trim($name),
        'source_version' => WebBlocks::VERSION,
        'created_at' => now(),
      ]);

      foreach (array_chunk($lines, 150) as $chunk) {
        DB::table(self::LINES)->insert(array_map(fn (array $line) => $line + ['catalog_id' => $id], $chunk));
      }

      return $id;
    });
  }

  public function preview(int $id): array
  {
    $catalog = $this->catalog($id);
    $source = $this->source->messages();
    $lines = $this->compatibleLines($id, $source);

    return [
      'catalog' => $catalog,
      'translated' => count($lines),
      'total' => count($source),
      'missing' => array_keys(array_diff_key($source, $lines)),
      'sample' => array_slice($lines, 0, 12, true),
    ];
  }

  public function activate(int $id): void
  {
    $catalog = $this->catalog($id);
    if ($this->compatibleLines($id, $this->source->messages()) === []) {
      $this->invalid('stale_catalog');
    }

    DB::table(self::LANGUAGES)->where('code', $catalog->language_code)->update(['active_catalog_id' => $id]);
    $this->memo->clear();
  }

  public function deactivate(string $code): void
  {
    DB::transaction(function () use ($code): void {
      $language = DB::table(self::LANGUAGES)->where('code', $code)->lockForUpdate()->first();
      abort_if($language === null, 404);
      $default = SystemSetting::query()->where('key', SystemSettings::ADMIN_LOCALE)->value('value');
      if ($default === $code && ! isset($this->source->packagedOptions()[$code])) {
        $this->invalid('change_default');
      }
      DB::table(self::LANGUAGES)->where('code', $code)->update(['active_catalog_id' => null]);
    });

    $this->memo->clear();
  }

  public function saveDefault(string $code): void
  {
    DB::transaction(function () use ($code): void {
      if (! isset($this->source->packagedOptions()[$code])) {
        $language = DB::table(self::LANGUAGES)->where('code', $code)->lockForUpdate()->first();
        if ($language?->active_catalog_id === null) {
          $this->invalid('unavailable');
        }
      }
      $this->settings->save([SystemSettings::ADMIN_LOCALE => $code]);
    });

    $this->memo->clear();
  }

  public function translations(string $code): array
  {
    if (in_array($code, AdminLocaleResolver::SUPPORTED_LOCALES, true) || ! isset($this->options()[$code])) {
      return [];
    }

    return $this->memo->remember('panel.languages.translations', [$code], function () use ($code): array {
      if (! Schema::hasTable(self::LANGUAGES)) {
        return [];
      }

      $id = $this->active()->where('languages.code', $code)->value('catalogs.id');

      return $id === null ? [] : $this->compatibleLines((int) $id, $this->source->messages());
    });
  }

  private function compatibleLines(int $id, array $source): array
  {
    $translations = [];
    foreach (DB::table(self::LINES)->where('catalog_id', $id)->orderBy('translation_key')->get() as $line) {
      $key = $line->translation_key;
      if (isset($source[$key]) && hash_equals($line->source_hash, hash('sha256', $source[$key]))) {
        $translations[$key] = $line->translation;
      }
    }

    return $translations;
  }

  private function active(): Builder
  {
    return DB::table(self::LANGUAGES.' as languages')->join(self::CATALOGS.' as catalogs', function ($join): void {
      $join->on('catalogs.id', '=', 'languages.active_catalog_id')->on('catalogs.language_code', '=', 'languages.code');
    });
  }

  private function catalog(int $id): object
  {
    $catalog = DB::table(self::CATALOGS.' as catalogs')
      ->join(self::LANGUAGES.' as languages', 'languages.code', '=', 'catalogs.language_code')
      ->where('catalogs.id', $id)->first(['catalogs.*', 'languages.active_catalog_id']);
    abort_if($catalog === null, 404);

    return $catalog;
  }

  private function invalid(string $key, array $replace = []): never
  {
    throw ValidationException::withMessages(['catalog' => __('webblocks-cms::panel-languages.'.$key, $replace)]);
  }
}
