<?php

namespace WebBlocks\Cms\Support\Translations;

use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;

final class NativeLanguagePacks
{
  private const PACKS = 'wbcms_native_language_packs';

  private const LINES = 'wbcms_native_language_lines';

  public function __construct(
    private readonly RequestMemo $memo,
    private readonly NativeLanguageSource $source,
  ) {}

  public function ready(): bool
  {
    return $this->memo->remember('native.languages.ready', [], function (): bool {
      try {
        return Schema::hasTable(self::PACKS) && Schema::hasTable(self::LINES);
      } catch (QueryException) {
        return false;
      }
    });
  }

  public function installed(): Collection
  {
    return $this->memo->remember('native.languages.installed', [], function (): Collection {
      try {
        return $this->ready() ? DB::table(self::PACKS)->orderBy('code')->get()->keyBy('code') : collect();
      } catch (QueryException) {
        return collect();
      }
    });
  }

  public function lines(string $locale, string $group): array
  {
    if (in_array($locale, AdminLocaleResolver::SUPPORTED_LOCALES, true) || ! $this->installed()->has($locale)) {
      return [];
    }

    return $this->memo->remember('native.languages.lines', [$locale, $group], function () use ($locale, $group): array {
      $source = $this->source->group($group);
      if ($source === []) {
        return [];
      }

      $translations = [];
      foreach (DB::table(self::LINES)->where('language_code', $locale)->where('catalog_group', $group)->get() as $line) {
        $key = $line->translation_key;
        if (isset($source[$key]) && hash_equals($line->source_hash, hash('sha256', $source[$key]))) {
          $translations[$key] = $line->translation;
        }
      }

      return Arr::undot($translations);
    });
  }

  public function install(array $metadata, array $lines): void
  {
    DB::transaction(function () use ($metadata, $lines): void {
      $code = $metadata['code'];
      $created = DB::table(self::PACKS)->insertOrIgnore($metadata);
      $installed = DB::table(self::PACKS)->where('code', $code)->lockForUpdate()->first();
      if (! $created) {
        $order = version_compare($metadata['release_version'], $installed->release_version);
        if ($order === 0 && hash_equals($installed->checksum, $metadata['checksum'])) {
          return;
        }

        if ($order <= 0) {
          throw ValidationException::withMessages(['catalog' => __('webblocks-cms::panel-languages.extensions_release_conflict')]);
        }

        DB::table(self::PACKS)->where('code', $code)->update($metadata);
        DB::table(self::LINES)->where('language_code', $code)->delete();
      }

      foreach (array_chunk($lines, 150) as $chunk) {
        DB::table(self::LINES)->insert($chunk);
      }
    });
    $this->memo->clear();

    $translator = app('translator');
    foreach (array_unique(array_column($lines, 'catalog_group')) as $name) {
      [$namespace, $group] = explode('::', $name);
      $loaded = $translator->getLoader()->load($metadata['code'], $group, $namespace);
      $translator->addLines(Arr::dot([$group => $loaded]), $metadata['code'], $namespace);
    }
  }
}
