<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;

final class StagedPageTranslations
{
  private const TABLE = 'wbcms_staged_page_translation_fields';

  public const ROUTE_FIELDS = ['path', 'slug'];

  public function __construct(
    private readonly PageTranslationPayload $payload,
    private readonly StagedPageBase $base,
  ) {}

  public function changes(Page $draft): array
  {
    if (! Schema::hasTable(self::TABLE)) {
      return [];
    }
    $translations = $draft->translations()->get()->keyBy('id');
    $fields = DB::table(self::TABLE)->whereIn('translation_id', $translations->keys())
      ->whereIn('field', [...PageTranslationPayload::IDENTITY_FIELDS, ...PageTranslationPayload::SEO_FIELDS])
      ->orderBy('translation_id')->orderBy('field')->get();
    $changes = [];
    foreach ($fields as $field) {
      $translation = $translations->get($field->translation_id);
      $changes[$translation->locale_id][$field->field] = in_array($field->field, self::ROUTE_FIELDS, true)
        ? $field->proposed_route_value
        : $translation->getAttribute($field->field);
    }

    return $changes;
  }

  public function save(Page $draft, Locale $locale, ?PageTranslation $translation, array $data): void
  {
    $previewData = Arr::except($data, self::ROUTE_FIELDS);
    if ($translation) {
      $translation->update($previewData);
    } else {
      $slug = 'staged-update-'.$draft->id.'-'.Str::uuid();
      $translation = $draft->translations()->create([
        'locale_id' => $locale->id,
        'slug' => $slug,
        'path' => '/staged-updates/'.$slug,
      ] + $previewData);
    }

    $rows = [];
    foreach ($data as $field => $value) {
      $rows[] = [
        'translation_id' => $translation->id,
        'field' => $field,
        'proposed_route_value' => in_array($field, self::ROUTE_FIELDS, true) ? $value : null,
      ];
    }
    DB::table(self::TABLE)->upsert($rows, ['translation_id', 'field'], ['proposed_route_value']);
  }

  public function assertPromotable(Page $source, Page $draft, array $changes): void
  {
    if ($changes === []) {
      return;
    }
    if ($draft->status !== Page::STATUS_DRAFT || $draft->setting('staged_update.state') !== 'draft'
      || (int) $draft->setting('staged_update.source_page_id') !== $source->id) {
      throw ValidationException::withMessages(['draft' => 'The staged translation draft is no longer active.']);
    }
    if (! $this->base->matches($draft, $this->base->fingerprint($source))) {
      throw ValidationException::withMessages(['source_page' => 'The published page changed after the translation draft was prepared.']);
    }
    $locales = $source->availableSiteLocales()->keyBy('id');
    foreach ($changes as $localeId => $fields) {
      $locale = $locales->get($localeId);
      if (! $locale) {
        throw ValidationException::withMessages(['locale' => 'A staged translation locale is no longer enabled for this site.']);
      }
      $this->payload->validate($fields, $source, $locale, $source->translationForLocale($locale));
    }
  }

  public function promote(Page $source, array $changes): void
  {
    foreach ($changes as $localeId => $fields) {
      $source->translations()->updateOrCreate(['locale_id' => $localeId], $fields);
    }
  }
}
