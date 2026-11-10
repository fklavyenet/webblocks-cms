<?php

namespace WebBlocks\Cms\Actions\Mcp;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Policies\McpPagePolicy;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Mcp\McpDraftAccess;
use WebBlocks\Cms\Support\Mcp\McpMedia;
use WebBlocks\Cms\Support\Mcp\McpPages;
use WebBlocks\Cms\Support\Mcp\McpToolException;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Pages\PageTranslationPayload;
use WebBlocks\Cms\Support\Pages\StagedPageTranslations;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;

final class EditDraftTranslation
{
  public function __construct(
    private readonly McpPages $pages,
    private readonly McpDraftAccess $access,
    private readonly McpPagePolicy $policy,
    private readonly InternalContentPlanService $plans,
    private readonly PageTranslationPayload $payload,
    private readonly StagedPageTranslations $staged,
    private readonly McpMedia $media,
    private readonly PageRevisionManager $revisions,
  ) {}

  public function read(int $siteId, array $input, ?array $grant = null): array
  {
    [$source, $draft] = $this->pages->draftTarget($siteId, $input['page_id']);
    $this->access->assertAllowed($grant, $draft);
    $locale = $this->locale($source, $input['locale']);

    return $this->result($source, $draft, $locale);
  }

  public function handle(User $user, int $siteId, array $input, ?array $grant = null): array
  {
    $fields = Arr::except($input, ['locale', 'page_id', 'version']);
    if ($fields === []) {
      throw new McpToolException('invalid_request');
    }
    $identity = Arr::only($fields, PageTranslationPayload::IDENTITY_FIELDS);
    $seo = Arr::only($fields, PageTranslationPayload::SEO_FIELDS);
    if (! $this->policy->write($user, $siteId)
      || ($identity !== [] && ! $this->policy->editTranslations($user, $siteId))
      || ($seo !== [] && ! $this->policy->editSeo($user, $siteId))) {
      throw new McpToolException('forbidden');
    }

    return DB::transaction(function () use ($user, $siteId, $input, $fields, $grant): array {
      [$source, $draft] = $this->pages->draftTarget($siteId, $input['page_id'], lock: true);
      $this->access->assertAllowed($grant, $draft);
      $this->pages->lockBlocks($source, $draft);
      PageTranslation::query()->whereIn('page_id', array_filter([$source->id, $draft?->id]))
        ->orderBy('id')->lockForUpdate()->pluck('id');
      $locale = $this->locale($source, $input['locale']);
      $current = $this->result($source, $draft, $locale);
      if (($draft && $draft->status !== Page::STATUS_DRAFT) || ! hash_equals($current['version'], $input['version'])) {
        throw new McpToolException('conflict');
      }

      if ($source->isPublished() && $draft && ! $this->pages->hasVerifiedBase($draft, $this->pages->fingerprint($source))) {
        throw new McpToolException('unverified_base');
      }

      $previous = $current['changes'];
      if (isset($fields['path']) && ! isset($fields['slug'])) {
        unset($previous['slug']);
      }
      if (isset($fields['slug']) && ! isset($fields['path'])) {
        unset($previous['path']);
      }
      $translation = $source->translationForLocale($locale);
      try {
        $validated = $this->payload->validate($fields + $previous, $source, $locale, $translation);
      } catch (ValidationException) {
        throw new McpToolException('invalid_page_translation');
      }
      if ($validated === []) {
        throw new McpToolException('invalid_request');
      }
      if (isset($fields['og_image_media_id'])) {
        $this->media->selectedImage($user, $siteId, $fields['og_image_media_id']);
      }

      if (! $draft) {
        $draft = $this->plans->prepareAuthoringDraft($source, $user, [], 'mcp_draft_prepared');
        $this->access->recordCreated($grant, $draft);
      }
      if (! $this->policy->editDraft($user, $draft, $locale->id !== app(LocaleResolver::class)->default()->id)) {
        throw new McpToolException('forbidden');
      }
      if ($source->isPublished()) {
        $this->staged->save($draft, $locale, $draft->translationForLocale($locale), $validated);
      } else {
        try {
          $draft->translations()->updateOrCreate(['locale_id' => $locale->id], $validated);
        } catch (UniqueConstraintViolationException) {
          throw new McpToolException('invalid_page_translation');
        }
      }
      $this->revisions->recordTranslationChange($draft, $user, source: 'mcp', touch: true);

      return $this->result($source, $draft, $locale);
    }, attempts: 3);
  }

  private function locale(Page $page, string $code): Locale
  {
    return $page->availableSiteLocales()->firstWhere('code', $code) ?? throw new McpToolException('locale_unavailable');
  }

  private function result(Page $source, ?Page $draft, Locale $locale): array
  {
    $published = $source->isPublished();
    $fingerprint = $this->pages->fingerprint($source);
    $version = $this->pages->version($source, $fingerprint, $published ? $draft : null, $locale);
    $changes = $published && $draft ? ($this->staged->changes($draft)[$locale->id] ?? []) : [];
    $translation = $source->translationForLocale($locale);
    $values = $translation?->only([...PageTranslationPayload::IDENTITY_FIELDS, ...PageTranslationPayload::SEO_FIELDS]) ?? [];

    return [
      'page_id' => $source->id,
      'draft_id' => $draft?->id,
      'locale' => $locale->code,
      'version' => SnapshotContent::hash(['page_version' => $version, 'changes' => $changes]),
      'translation' => $changes + $values,
      'changes' => $changes,
      'preview_url' => $draft?->translationForLocale($locale) ? $this->pages->draftPreviewUrl($draft, $locale) : null,
    ];
  }
}
