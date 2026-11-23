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
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\WebBlocks;

final class PanelLanguages
{
  private const LANGUAGES = 'wbcms_panel_languages';

  private const CATALOGS = 'wbcms_panel_language_catalogs';

  private const LINES = 'wbcms_panel_language_lines';

  private const CATALOG_FIELDS = [
    'catalogs.id', 'catalogs.language_code', 'catalogs.name', 'catalogs.source_version',
    'catalogs.created_at', 'languages.active_catalog_id',
  ];

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

  public function catalogs(int $perPage = 25): LengthAwarePaginator
  {
    return DB::table(self::CATALOGS.' as catalogs')
      ->join(self::LANGUAGES.' as languages', 'languages.code', '=', 'catalogs.language_code')
      ->select(self::CATALOG_FIELDS)
      ->orderByDesc('catalogs.id')->paginate($perPage);
  }

  public function import(string $json, ?string $catalogCode = null, ?ExtensionOperation $operation = null): int
  {
    $operation?->during('manifest');
    try {
      $catalog = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      $this->invalid('invalid_file');
    }

    $code = $catalog['code'] ?? null;
    $name = $catalog['name'] ?? null;
    $translations = $catalog['translations'] ?? null;
    $operation?->identify(is_string($code) ? $code : null, label: is_string($name) ? $name : null);
    if ($catalogCode === null) {
      $sourceVersion = $catalog['source_version'] ?? null;
      $operation?->targetCms(is_string($sourceVersion) ? $sourceVersion : null);
    }
    if (($catalog['format_version'] ?? null) !== 1 || ($catalog['source_version'] ?? null) !== WebBlocks::VERSION
      || ! is_string($code) || ! $this->source->validCode($code) || in_array($code, AdminLocaleResolver::SUPPORTED_LOCALES, true)
      || ($catalogCode !== null && $code !== $catalogCode)
      || ! is_string($name) || trim($name) === '' || mb_strlen($name) > 100 || ! $this->source->plainText($name)
      || ! is_array($translations) || ! array_is_list($translations) || $translations === []) {
      $this->invalid('invalid_catalog');
    }

    $operation?->during('translation');
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

    $checksum = $catalogCode === null ? null : hash('sha256', $json);

    $operation?->during('database');

    return DB::transaction(function () use ($code, $name, $lines, $checksum): int {
      DB::table(self::LANGUAGES)->insertOrIgnore(['code' => $code]);
      $metadata = [
        'language_code' => $code,
        'name' => trim($name),
        'source_version' => WebBlocks::VERSION,
        'created_at' => now(),
      ];

      if ($checksum !== null) {
        DB::table(self::LANGUAGES)->where('code', $code)->lockForUpdate()->first(['code']);
        $existing = DB::table(self::CATALOGS)->where('language_code', $code)->where('artifact_checksum', $checksum)->first(['id']);
        if ($existing !== null) {
          return $existing->id;
        }
        $metadata['artifact_checksum'] = $checksum;
      }

      $id = DB::table(self::CATALOGS)->insertGetId($metadata);

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

  public function activate(int $id, ?ExtensionOperation $operation = null): void
  {
    DB::transaction(function () use ($id, $operation): void {
      $language = $this->lockCatalogLanguage($id);
      if ($operation !== null) {
        $catalog = $this->catalog($id);
        $operation->identify($language->code, label: $catalog->name);
        $operation->targetCms($catalog->source_version);
      }
      if ($this->compatibleLines($id, $this->source->messages()) === []) {
        $operation?->during('translation');
        $this->invalid('stale_catalog');
      }

      DB::table(self::LANGUAGES)->where('code', $language->code)->update(['active_catalog_id' => $id]);
    });

    $this->memo->clear();
  }

  public function delete(int $id): void
  {
    DB::transaction(function () use ($id): void {
      $language = $this->lockCatalogLanguage($id);
      if ((int) $language->active_catalog_id === $id) {
        $this->invalid('delete_active');
      }

      DB::table(self::CATALOGS)->where('id', $id)->delete();
    });
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

  private function lockCatalogLanguage(int $id): object
  {
    $code = DB::table(self::CATALOGS)->where('id', $id)->value('language_code');
    abort_if($code === null, 404);

    // Serialize activation and deletion on the same language row. A locking read
    // also detects a catalog deleted while this transaction waited for that row.
    $language = DB::table(self::LANGUAGES)->where('code', $code)->lockForUpdate()->first();
    $catalog = DB::table(self::CATALOGS)->where('id', $id)->lockForUpdate()->first(['id']);
    abort_if($language === null || $catalog === null, 404);

    return $language;
  }

  private function catalog(int $id): object
  {
    $catalog = DB::table(self::CATALOGS.' as catalogs')
      ->join(self::LANGUAGES.' as languages', 'languages.code', '=', 'catalogs.language_code')
      ->where('catalogs.id', $id)->first(self::CATALOG_FIELDS);
    abort_if($catalog === null, 404);

    return $catalog;
  }

  private function invalid(string $key, array $replace = []): never
  {
    throw ValidationException::withMessages(['catalog' => __('webblocks-cms::panel-languages.'.$key, $replace)]);
  }
}
