<?php

namespace WebBlocks\Cms\Actions\Mcp;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\McpPagePolicy;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Mcp\McpPages;
use WebBlocks\Cms\Support\Mcp\McpToolException;
use WebBlocks\Cms\Support\Pages\PageDraftInitializer;
use WebBlocks\Cms\Support\Pages\PageLayoutManager;
use WebBlocks\Cms\Support\Pages\PagePath;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;

final class CreatePageDraft
{
  public function __construct(
    private readonly McpPagePolicy $policy,
    private readonly LocaleResolver $locales,
    private readonly PageLayoutManager $layouts,
    private readonly PageDraftInitializer $drafts,
    private readonly PageRevisionManager $revisions,
    private readonly McpPages $pages,
  ) {}

  public function handle(User $user, int $siteId, array $input): array
  {
    if (! $this->policy->createDraft($user, $siteId)) {
      throw new McpToolException('forbidden');
    }

    try {
      $path = PagePath::canonicalize($input['path']);
    } catch (\InvalidArgumentException) {
      throw new McpToolException('invalid_page_path');
    }
    if (strlen($path) > 255 || PagePath::isReserved($path)) {
      throw new McpToolException('invalid_page_path');
    }

    $layout = $input['layout'] ?? 'default';
    $hash = SnapshotContent::hash(['title' => $input['title'], 'path' => $path, 'locale' => $input['locale'], 'layout' => $layout]);

    return DB::transaction(function () use ($user, $siteId, $input, $path, $layout, $hash): array {
      // Serialize MCP creation and receipt lookup within a site. The translation
      // unique constraints also cover concurrent panel and Content API writers.
      $site = Site::query()->lockForUpdate()->find($siteId) ?? throw new McpToolException('page_missing');
      $locale = $site->enabledLocales()->where((new Locale)->qualifyColumn('code'), $input['locale'])->first()
        ?? throw new McpToolException('locale_unavailable');

      if ($locale->id !== $this->locales->default()->id && ! $this->policy->editTranslations($user, $siteId)) {
        throw new McpToolException('forbidden');
      }

      $receipt = DB::table('wbcms_mcp_page_drafts')->where([
        'site_id' => $siteId, 'user_id' => $user->getAuthIdentifier(), 'request_hash' => $hash,
      ]);
      if ($previous = $receipt->first()) {
        $page = Page::query()->where('site_id', $siteId)->where('status', Page::STATUS_DRAFT)
          ->where('page_type', Page::TYPE_DEFAULT)->lockForUpdate()->find($previous->page_id);
        $translation = $page?->translationForLocale($locale);
        if (! $page || ! $translation || $page->isRevisionRestoreCandidate() || $page->setting('staged_update') !== null) {
          throw new McpToolException('created_draft_unavailable');
        }

        return $this->result($page, $locale, $translation->path);
      }

      if ($this->layouts->findByHandle($layout, includeInactive: false)?->handle !== $layout) {
        throw new McpToolException('layout_unavailable');
      }

      $slug = PagePath::slugFromPath($path);
      if (PageTranslation::query()->where('site_id', $siteId)->where('locale_id', $locale->id)
        ->where(fn ($query) => $query->where('path', $path)->orWhere('slug', $slug))->exists()) {
        throw new McpToolException('page_path_conflict');
      }

      try {
        $page = $this->drafts->create([
          'site_id' => $siteId,
          'settings' => ['public_shell' => $layout],
          'created_by_user_id' => $user->getAuthIdentifier(),
          'updated_by_user_id' => $user->getAuthIdentifier(),
        ], [
          'locale_id' => $locale->id,
          'name' => $input['title'],
          'slug' => $slug,
          'path' => $path,
        ], $layout);
      } catch (UniqueConstraintViolationException) {
        throw new McpToolException('page_path_conflict');
      }

      $this->revisions->capture($page, $user, event: 'page_created', source: 'mcp');
      DB::table('wbcms_mcp_page_drafts')->insert([
        'site_id' => $siteId, 'user_id' => $user->getAuthIdentifier(), 'request_hash' => $hash, 'page_id' => $page->id,
      ]);

      return $this->result($page, $locale, $path);
    }, attempts: 3);
  }

  private function result(Page $page, Locale $locale, string $path): array
  {
    return [
      'page_id' => $page->id,
      'draft_id' => $page->id,
      'locale' => $locale->code,
      'path' => $path,
      'preview_url' => $this->pages->draftPreviewUrl($page, $locale),
    ];
  }
}
