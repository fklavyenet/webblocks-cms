<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockMedia;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageRevisionCandidate;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Support\Blocks\BlockTranslationRegistry;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;

final class PageLocaleRestorePlanner
{
  public const SCOPES = ['full_page', 'page_fields', 'page_locale'];

  public const PAGE_FIELDS = ['name', 'slug', 'path', 'seo_title', 'seo_description', 'seo_keywords', 'list_excerpt', 'og_title', 'og_description', 'og_image_media_id'];

  public const FAMILIES = [
    'text_translations' => ['textTranslations', ['title', 'eyebrow', 'subtitle', 'content', 'meta']],
    'button_translations' => ['buttonTranslations', ['title']],
    'image_translations' => ['imageTranslations', ['caption', 'alt_text']],
    'contact_form_translations' => ['contactFormTranslations', ['title', 'content', ...BlockTranslationRegistry::CONTACT_FORM_COPY_FIELDS]],
  ];

  public const GALLERY_FIELDS = ['alt_text', 'caption', 'overlay_title', 'overlay_text'];

  public function __construct(private readonly PageRevisionManager $revisions, private readonly PageRevisionIdentityMatcher $matcher, private readonly BlockTranslationRegistry $registry) {}

  public function plan(Page $page, PageRevision $revision, string $scope, ?int $localeId): array
  {
    app(RequestMemo::class)->clear();
    $result = ['ok' => false, 'scope' => $scope, 'locale_id' => $localeId, 'locale_code' => null, 'changes' => [], 'issues' => [], 'excluded' => ['shared_slots', 'plugins', 'external_sources', 'raw_html'], 'projected_snapshot' => null];
    if (! in_array($scope, ['page_fields', 'page_locale'], true)) {
      return $this->issue($result, 'invalid_scope');
    }
    if ((int) $revision->page_id !== (int) $page->id || (int) $revision->site_id !== (int) $page->site_id) {
      return $this->issue($result, 'invalid_scope');
    }
    $locale = $page->site->enabledLocales()->whereKey($localeId)->where('wbcms_locales.is_enabled', true)->first();
    if (! $locale) {
      return $this->issue($result, 'locale_disabled');
    }
    $result['locale_code'] = $locale->code;
    $historical = $revision->snapshot;
    if (! is_array($historical) || ! in_array($historical['schema_version'] ?? null, [1, 2], true)) {
      return $this->issue($result, 'incomplete_snapshot');
    }
    $old = $this->selectedRow($historical['translations'] ?? null, $locale->id, $result);
    if ($result['issues'] !== []) {
      return $result;
    }
    if (! is_array($old)) {
      return $this->issue($result, 'historical_locale_missing');
    }
    if (array_diff(self::PAGE_FIELDS, array_keys($old)) !== []) {
      return $this->issue($result, 'incomplete_snapshot');
    }
    $current = $this->revisions->snapshotForInspection($page->fresh());
    $live = collect($current['translations'])->firstWhere('locale_id', $locale->id);
    $fields = Arr::only($old, self::PAGE_FIELDS);
    if (! $this->validPageFields($page, $locale, $fields)) {
      return $this->issue($result, 'invalid_page_fields');
    }
    if ($fields['og_image_media_id'] !== null && ! Media::whereKey($fields['og_image_media_id'])->exists()) {
      return $this->issue($result, 'references_missing');
    }
    $result['proposed_path'] = $fields['path'];
    $proposedPage = clone $page;
    $proposedPage->setRelation('currentTranslation', $page->translations()->make(['locale_id' => $locale->id, ...$fields])->setRelation('locale', $locale));
    $result['proposed_url'] = app(PageRouteResolver::class)->canonicalUrlFor($proposedPage, $locale, $page->site);
    $projected = $current;
    $this->change($result, 'page', $page->id, 'translations', $live, $old, self::PAGE_FIELDS, $locale->id);
    $this->overlayRows($projected['translations'], $old, $locale->id);
    if ($scope === 'page_locale') {
      $matches = $this->matcher->match($page, $revision);
      if (! $matches['compatible']) {
        return $this->issue($result, 'structure_incompatible', ['details' => $matches['issues']]);
      }
      $liveBlocks = $page->blocks()->with(['blockType', 'blockAssets.galleryItemTranslations', 'textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])->get()->keyBy('id');
      foreach ($historical['blocks'] as $entry) {
        $block = $liveBlocks[$matches['blocks'][$entry['snapshot_id']]];
        $family = $this->registry->familyFor($block);
        if (($family === null && ! $this->registry->supportsImageTranslations($block)) || $family === 'plugin' || $block->pluginTranslations()->exists() || ! in_array($block->source_type, ['static', null], true) || in_array($block->typeSlug(), ['html', 'external-content'], true)) {
          continue;
        }
        $index = array_search($block->id, array_column($projected['blocks'], 'snapshot_id'), true);
        foreach (self::FAMILIES as $key => [$relation, $fieldNames]) {
          $before = $block->{$relation}->firstWhere('locale_id', $locale->id)?->only($fieldNames);
          $selected = $this->selectedRow($entry[$key] ?? [], $locale->id, $result);
          if ($selected === null) {
            if ($before !== null) {
              $result = $this->issue($result, 'missing_translation', ['block_id' => $block->id, 'family' => $key]);
            }

            continue;
          }
          if (! $this->completeFields($selected, $fieldNames)) {
            $result = $this->issue($result, 'incomplete_snapshot', ['block_id' => $block->id, 'family' => $key]);

            continue;
          }
          $this->change($result, 'block', $block->id, $relation, $before, $selected, $fieldNames, $locale->id);
          $this->overlayRows($projected['blocks'][$index][$key], $selected, $locale->id);
        }
        foreach ($entry['block_media'] ?? [] as $itemIndex => $item) {
          $mediaId = $matches['media'][$entry['snapshot_id']][$itemIndex];
          $media = $block->blockAssets->firstWhere('id', $mediaId);
          $before = $media->galleryItemTranslations->firstWhere('locale_id', $locale->id)?->only(self::GALLERY_FIELDS);
          $selected = $this->selectedRow($item['gallery_item_translations'] ?? [], $locale->id, $result);
          if ($selected === null) {
            if ($before !== null) {
              $result = $this->issue($result, 'missing_translation', ['block_media_id' => $mediaId]);
            }

            continue;
          }
          if (! $this->completeFields($selected, self::GALLERY_FIELDS)) {
            $result = $this->issue($result, 'incomplete_snapshot', ['block_media_id' => $mediaId]);

            continue;
          }
          $this->change($result, 'gallery', $mediaId, 'galleryItemTranslations', $before, $selected, self::GALLERY_FIELDS, $locale->id);
          $mediaIndex = array_search($mediaId, array_column($projected['blocks'][$index]['block_media'], 'snapshot_id'), true);
          $this->overlayRows($projected['blocks'][$index]['block_media'][$mediaIndex]['gallery_item_translations'], $selected, $locale->id);
        }
      }
      $this->fallbackImpacts($page, $locale, $result);
    }
    $result['ok'] = $result['issues'] === [];
    $result['projected_snapshot'] = $this->enabledPreviewSnapshot($page, $projected);
    $result['source_fingerprint'] = $this->fingerprint($page);
    $result['revision_fingerprint'] = SnapshotContent::hash($historical);

    return $result;
  }

  public function fingerprint(Page $page): string
  {
    $shared = [];
    foreach ($page->slots()->with('sharedSlot')->whereNotNull('shared_slot_id')->get() as $slot) {
      $source = $slot->sharedSlot ? app(SharedSlotSourcePageManager::class)->findFor($slot->sharedSlot) : null;
      $shared[$slot->shared_slot_id] = [$slot->sharedSlot?->getAttributes(), $source ? $this->revisions->snapshotForInspection($source) : null];
      if (isset($shared[$slot->shared_slot_id][1]['captured_at'])) {
        unset($shared[$slot->shared_slot_id][1]['captured_at']);
      }
    }

    return SnapshotContent::hash([
      'page' => Arr::except($this->revisions->snapshotForInspection($page->fresh()), ['captured_at']),
      'locales' => $page->site->enabledLocales()->orderBy('wbcms_locales.id')->get(['wbcms_locales.id', 'wbcms_locales.code', 'wbcms_locales.is_default', 'wbcms_locales.is_enabled'])->toArray(),
      'default_locale' => app(LocaleResolver::class)->default()->id,
      'shared' => $shared,
    ]);
  }

  public function publicPlan(array $plan): array
  {
    return Arr::except($plan, ['projected_snapshot', 'source_fingerprint', 'revision_fingerprint']);
  }

  private function validPageFields(Page $page, Locale $locale, array $fields): bool
  {
    $rules = ['name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'], 'path' => ['required', 'string', 'max:255'], 'seo_title' => ['nullable', 'string', 'max:255'], 'seo_description' => ['nullable', 'string', 'max:1000'], 'seo_keywords' => ['nullable', 'string', 'max:500'], 'list_excerpt' => ['nullable', 'string', 'max:300'], 'og_title' => ['nullable', 'string', 'max:255'], 'og_description' => ['nullable', 'string', 'max:1000'], 'og_image_media_id' => ['nullable', 'integer']];
    if (Validator::make($fields, $rules)->fails()) {
      return false;
    }
    try {
      if (PagePath::canonicalize($fields['path']) !== $fields['path'] || PagePath::isReserved($fields['path'])) {
        return false;
      }
    } catch (\InvalidArgumentException) {
      return false;
    }

    return ! PageTranslation::where('site_id', $page->site_id)->where('locale_id', $locale->id)->where('page_id', '!=', $page->id)
      ->where(fn ($query) => $query->where('path', $fields['path'])->orWhere('slug', $fields['slug']))->exists();
  }

  private function change(array &$result, string $entity, int $id, string $relation, ?array $before, array $after, array $fields, int $localeId): void
  {
    $after = Arr::only($after, $fields);
    $before = $before === null ? null : Arr::only($before, $fields);
    if ($before !== $after) {
      $result['changes'][] = ['entity' => $entity, 'owner_id' => $id, 'relation' => $relation, 'locale_id' => $localeId, 'before' => $before, 'after' => $after];
    }
  }

  private function overlayRows(array &$rows, array $selected, int $localeId): void
  {
    foreach ($rows as &$row) {
      if ((int) $row['locale_id'] === $localeId) {
        $row = $selected;

        return;
      }
    }
    unset($row);
    $rows[] = $selected;
  }

  private function fallbackImpacts(Page $page, Locale $locale, array &$result): void
  {
    $defaultId = app(LocaleResolver::class)->default()->id;
    $others = $page->site->enabledLocales()->where('wbcms_locales.is_enabled', true)->whereKeyNot($locale->id)->pluck('wbcms_locales.id')->all();
    foreach ($result['changes'] as $change) {
      if ($change['entity'] === 'page') {
        // A language without a page translation has no independent page address.
        continue;
      }
      $owner = $change['entity'] === 'gallery' ? BlockMedia::findOrFail($change['owner_id']) : Block::findOrFail($change['owner_id']);
      $rows = $owner->{$change['relation']}()->get();
      $translated = $rows->pluck('locale_id')->all();
      $fields = array_keys(array_filter($change['after'], fn ($value, $field) => ($change['before'][$field] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));
      $isDefault = (int) $locale->id === (int) $defaultId;
      $hasDefaultRow = in_array($defaultId, $translated);
      $legacyFields = $change['relation'] === 'textTranslations' && in_array($owner->typeSlug(), ['link-list', 'link-list-item', 'navbar-brand', 'navbar-navigation', 'sidebar-brand', 'sidebar-footer', 'sidebar-navigation'], true);
      // Gallery rows and legacy field helpers may fall through to the first row
      // when no default exists. Never assume that row belongs to the default locale.
      $affected = $isDefault || ($change['entity'] === 'gallery' && ! $hasDefaultRow)
        ? array_values(array_diff($others, $translated)) : [];
      if ($legacyFields && ($isDefault || ! $hasDefaultRow)) {
        foreach ($others as $otherId) {
          $row = $rows->firstWhere('locale_id', $otherId);
          foreach (array_intersect($fields, ['title', 'subtitle', 'content']) as $field) {
            if (trim((string) $row?->{$field}) === '') {
              $affected[] = $otherId;
            }
          }
        }
      }
      $affected = array_values(array_unique($affected));
      if ($affected !== []) {
        $result = $this->issue($result, 'fallback_impact', ['locale_ids' => $affected, 'owner_id' => $owner->id, 'entity' => $change['entity'], 'fields' => $fields]);
      }
    }
  }

  public function reviewCandidate(PageRevisionCandidate $candidate): array
  {
    $plan = $this->plan($candidate->page, $candidate->revision, $candidate->restore_scope, $candidate->restore_locale_id);
    if ($plan['ok'] && (! hash_equals((string) $candidate->source_fingerprint, $plan['source_fingerprint']) || ! hash_equals((string) $candidate->revision_fingerprint, $plan['revision_fingerprint']))) {
      $plan = $this->issue($plan, 'stale');
    }

    if ($plan['ok'] && (! $candidate->candidatePage || ! hash_equals((string) $candidate->preview_fingerprint, SnapshotContent::hash($this->revisions->snapshotForInspection($candidate->candidatePage->fresh()))))) {
      $plan = $this->issue($plan, 'preview_changed');
    }

    return $this->publicPlan($plan);
  }

  private function enabledPreviewSnapshot(Page $page, array $snapshot): array
  {
    // Dormant locale records belong in the source and safety snapshots. A private
    // render draft only needs languages the site currently permits creating.
    $enabledIds = $page->site->enabledLocales()->pluck('wbcms_locales.id')->all();
    $filter = fn (array $rows) => array_values(array_filter($rows, fn (array $row) => in_array((int) $row['locale_id'], $enabledIds, true)));
    $snapshot['translations'] = $filter($snapshot['translations']);
    foreach ($snapshot['blocks'] as &$block) {
      foreach ([...array_keys(self::FAMILIES), 'plugin_translations'] as $key) {
        $block[$key] = $filter($block[$key] ?? []);
      }
      foreach ($block['block_media'] ?? [] as $index => $item) {
        $block['block_media'][$index]['gallery_item_translations'] = $filter($item['gallery_item_translations'] ?? []);
      }
    }
    unset($block);

    return $snapshot;
  }

  private function selectedRow(mixed $rows, int $localeId, array &$result): ?array
  {
    if (! is_array($rows) || ! array_is_list($rows)) {
      $result = $this->issue($result, 'incomplete_snapshot');

      return null;
    }
    $found = null;
    foreach ($rows as $row) {
      if (! is_array($row) || ! isset($row['locale_id']) || (! is_int($row['locale_id']) && ! is_string($row['locale_id'])) || (int) $row['locale_id'] < 1 || (string) (int) $row['locale_id'] !== (string) $row['locale_id']) {
        $result = $this->issue($result, 'incomplete_snapshot');

        return null;
      }
      if ((int) $row['locale_id'] === $localeId) {
        if ($found !== null) {
          $result = $this->issue($result, 'incomplete_snapshot');

          return null;
        }
        $found = $row;
      }
    }

    return $found;
  }

  private function completeFields(array $row, array $fields): bool
  {
    foreach ($fields as $field) {
      if (! array_key_exists($field, $row) || ($row[$field] !== null && ($field === 'meta' ? ! is_array($row[$field]) : ! is_string($row[$field])))) {
        return false;
      }
    }

    return true;
  }

  private function issue(array $result, string $code, array $context = []): array
  {
    $result['ok'] = false;
    $result['issues'][] = ['code' => $code] + $context;

    return $result;
  }
}
