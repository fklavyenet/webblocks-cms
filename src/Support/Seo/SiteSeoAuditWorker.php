<?php

namespace WebBlocks\Cms\Support\Seo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\SeoAudit;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Pages\PublicPageAccess;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;

/** Resumable bounded scheduler work, independent of the host queue driver. */
final class SiteSeoAuditWorker
{
  public function advance(int $id, int $limit = 5, int $seconds = 20): void
  {
    $token = (string) Str::uuid();
    $claimed = DB::transaction(function () use ($id, $token): bool {
      $audit = SeoAudit::query()->lockForUpdate()->find($id);
      if (! $audit || ! in_array($audit->state, ['queued', 'running'], true)
        || ($audit->worker_token && $audit->worker_expires_at?->isFuture())) {
        return false;
      }
      $audit->update(['state' => 'running', 'worker_token' => $token, 'worker_expires_at' => now()->addMinutes(3)]);

      return true;
    });
    if (! $claimed) {
      return;
    }
    $deadline = microtime(true) + max(1, min(40, $seconds));
    try {
      for ($i = 0; $i < max(1, min(100, $limit)) && microtime(true) < $deadline; $i++) {
        $audit = SeoAudit::query()->with('site')->find($id);
        if (! $audit || $audit->worker_token !== $token || $audit->state !== 'running') {
          return;
        }
        if (! hash_equals($audit->source_version, app(SeoSourceVersion::class)->current())) {
          $this->finish($audit, $token, 'stale');

          return;
        }
        if ($i === 0) {
          $this->pruneHistory($audit);
        }
        if ($audit->phase === 'inventory') {
          $this->inventory($audit, $token);
        } elseif ($audit->phase === 'inspect') {
          $row = DB::table('wbcms_seo_audit_pages')->where('audit_id', $id)->where('state', 'pending')->orderBy('id')->first();
          if (! $row) {
            $this->save($audit, $token, fn () => $audit->update(['phase' => 'finalize', 'cursor' => 0]));
          } else {
            $evidence = $row->page_id === 0 ? $this->navigation($audit, $row) : $this->page($audit, $row);
            $this->save($audit, $token, function () use ($audit, $row, $evidence): void {
              DB::table('wbcms_seo_audit_findings')->where('audit_page_id', $row->id)->delete();
              DB::table('wbcms_seo_audit_links')->where('audit_page_id', $row->id)->delete();
              DB::table('wbcms_seo_audit_pages')->where('id', $row->id)->update($evidence['page']);
              foreach (array_chunk($evidence['findings'], 100) as $chunk) {
                DB::table('wbcms_seo_audit_findings')->insert($chunk);
              }
              foreach (array_chunk($evidence['links'], 100) as $chunk) {
                DB::table('wbcms_seo_audit_links')->insert($chunk);
              }
              $audit->increment('processed');
            });
          }
        } else {
          $this->finalize($audit, $token);
        }
      }
    } catch (Throwable) {
      // Do not expose provider, SQL, template or host exception text.
      SeoAudit::query()->whereKey($id)->where('worker_token', $token)->update(['state' => 'failed']);
    } finally {
      SeoAudit::query()->whereKey($id)->where('worker_token', $token)->update(['worker_token' => null, 'worker_expires_at' => null]);
    }
  }

  private function save(SeoAudit $audit, string $token, callable $write): void
  {
    DB::transaction(function () use ($audit, $token, $write): void {
      $current = SeoAudit::query()->lockForUpdate()->find($audit->id);
      if (! $current || $current->worker_token !== $token || $current->state !== 'running') {
        return;
      }
      if (! hash_equals($current->source_version, app(SeoSourceVersion::class)->current())) {
        $current->update(['state' => 'stale', 'worker_token' => null, 'worker_expires_at' => null]);

        return;
      }
      $write();
    });
  }

  private function inventory(SeoAudit $audit, string $token): void
  {
    $pages = Page::query()->where('site_id', $audit->site_id)->whereNotIn('page_type', [Page::TYPE_SHARED_SLOT_SOURCE, Page::TYPE_SYSTEM])
      ->where('id', '>', $audit->cursor)->where('id', '<=', $audit->upper_page_id)->orderBy('id')->limit(50)->get();
    $locales = DB::table('wbcms_seo_audit_locales')->where('audit_id', $audit->id)->pluck('locale');
    $rows = [];
    if ((int) $audit->cursor === 0) {
      foreach ($locales as $locale) {
        $rows[] = ['audit_id' => $audit->id, 'page_id' => 0, 'locale' => $locale, 'page_status' => 'navigation', 'state' => 'pending'];
      }
    }
    foreach ($pages as $page) {
      if ($page->isRevisionRestoreCandidate()) {
        continue;
      }
      foreach ($locales as $locale) {
        $rows[] = ['audit_id' => $audit->id, 'page_id' => $page->id, 'locale' => $locale, 'page_status' => $page->status, 'state' => 'pending'];
      }
    }
    $this->save($audit, $token, function () use ($audit, $rows, $pages): void {
      foreach (array_chunk($rows, 100) as $chunk) {
        DB::table('wbcms_seo_audit_pages')->insertOrIgnore($chunk);
      }
      $audit->update(['cursor' => $pages->last()?->id ?? $audit->cursor,
        'phase' => $pages->count() < 50 ? 'inspect' : 'inventory',
        'total' => DB::table('wbcms_seo_audit_pages')->where('audit_id', $audit->id)->count()]);
    });
  }

  private function page(SeoAudit $audit, object $row): array
  {
    $page = Page::query()->with(['translations.locale', 'site'])->where('site_id', $audit->site_id)->find($row->page_id);
    $findings = [];
    $links = [];
    if (! $page || ! $page->translations->contains(fn ($translation) => $translation->locale?->code === $row->locale)) {
      $findings[] = $this->finding($row, 'editorial', 'translation_missing');

      return ['page' => ['state' => 'missing_translation', 'checked_at' => now()], 'findings' => $findings, 'links' => []];
    }
    $url = app(PageRouteResolver::class)->urlFor($page, $row->locale, $page->site);
    $rules = AutomaticPageRule::query()->where('site_id', $audit->site_id)->where('status', 'active')->get();
    if ($rules->contains('template_page_id', $page->id) || $rules->contains(fn ($rule) => str_starts_with((string) parse_url($url, PHP_URL_PATH), rtrim((string) parse_url(app(PageRouteResolver::class)->homeUrl($row->locale, $page->site), PHP_URL_PATH), '/').$rule->base_path.'/'))) {
      return ['page' => ['state' => 'partial', 'public_url' => $url, 'checked_at' => now()],
        'findings' => [$this->finding($row, 'not_checked', 'automatic_pages')], 'links' => []];
    }
    $report = app(PageSeoReport::class)->run($page, $row->locale, 'en', true);
    foreach (['technical', 'editorial', 'not_checked'] as $category) {
      foreach ($report[$category] as $finding) {
        if ($category === 'not_checked' && in_array($finding['code'], ['links', 'cross_page'], true)) {
          continue; // The site run performs these within its declared native scope.
        }
        $examples = $finding['examples'] ?? [];
        if ($examples === []) {
          $examples = [[]];
        }
        foreach ($examples as $example) {
          $findings[] = $this->finding($row, $category, $finding['code'], [
            'target' => $example['location'] ?? $finding['target'] ?? null,
            'occurrences' => max(0, $finding['count'] ?? 1), 'example' => $example['example'] ?? null,
            'origin' => $example['origin'] ?? null,
            'block_id' => $example['block_id'] ?? null, 'owner_page_id' => $example['owner_page_id'] ?? null,
          ]);
        }
      }
    }
    if ($report['metadata']['description']['source'] === 'site_fallback') {
      $findings[] = $this->finding($row, 'editorial', 'metadata_fallback');
    }
    foreach ($report['links'] as $link) {
      $links[] = $this->link($audit, $row, $link, $report['public_url'] ?? $url);
    }
    $blockIds = $report['rendered_block_ids'];
    $localeId = Locale::query()->where('code', $row->locale)->value('id');
    foreach (array_chunk($blockIds, 100) as $ids) {
      $blocks = Block::query()->with(['textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])->whereIn('id', $ids)->whereHas('page', fn ($q) => $q->where('site_id', $audit->site_id))->get();
      foreach ($blocks as $block) {
        foreach (['textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'] as $relation) {
          if ($block->$relation->isNotEmpty() && ! $block->$relation->contains('locale_id', $localeId)) {
            $findings[] = $this->finding($row, 'editorial', 'block_translation_fallback', ['block_id' => $block->id, 'owner_page_id' => $block->page_id]);
            break;
          }
        }
      }
    }
    $title = $report['metadata']['title']['value'];
    $description = $report['metadata']['description']['value'];

    return ['page' => ['state' => $report['inspection_status'], 'public_url' => $report['public_url'], 'title' => $title, 'description' => $description,
      'title_hash' => $this->hash($title), 'description_hash' => $this->hash($description), 'checked_at' => now()], 'findings' => $findings, 'links' => $links];
  }

  private function navigation(SeoAudit $audit, object $row): array
  {
    $items = NavigationItem::query()->with(['page.translations.locale', 'translations'])->where('site_id', $audit->site_id)->where('visibility', NavigationItem::VISIBILITY_VISIBLE)->orderBy('id')->limit(2001)->get();
    $locale = Locale::query()->where('code', $row->locale)->first();
    $links = [];
    foreach ($items->take(2000) as $item) {
      if (! in_array($item->link_type, [NavigationItem::LINK_PAGE, NavigationItem::LINK_CUSTOM_URL], true)) {
        continue;
      }
      $href = $item->link_type === NavigationItem::LINK_PAGE && $item->page
        ? app(PageRouteResolver::class)->pathFor($item->page, $row->locale, $audit->site) : $item->url;
      $link = $this->link($audit, $row, ['href' => SeoHtmlEvidence::safeHref((string) $href), 'text' => $item->resolvedTitle($locale), 'navigation_item_id' => $item->id, 'source' => 'navigation_definition'], app(PageRouteResolver::class)->homeUrl($row->locale, $audit->site));
      if ($item->link_type === NavigationItem::LINK_PAGE) {
        $link['target_page_id'] = $item->page_id;
        $link['target_locale'] = $row->locale;
        if (! $item->page || $item->page->status !== Page::STATUS_PUBLISHED) {
          $link['state'] = 'unpublished_target';
        } elseif (! $href) {
          $link['state'] = 'missing_target_translation';
        }
      }
      $links[] = $link;
    }

    return ['page' => ['state' => $items->count() > 2000 ? 'partial' : 'complete', 'checked_at' => now()],
      'findings' => $items->count() > 2000 ? [$this->finding($row, 'not_checked', 'navigation_truncated')] : [], 'links' => $links];
  }

  private function link(SeoAudit $audit, object $row, array $link, string $url): array
  {
    return ['audit_id' => $audit->id, 'audit_page_id' => $row->id,
      'block_id' => $link['block_id'] ?? null, 'owner_page_id' => $link['owner_page_id'] ?? null,
      'navigation_item_id' => $link['navigation_item_id'] ?? null, 'source' => $link['source'] ?? 'rendered',
      'is_public_source' => ($link['source'] ?? 'rendered') === 'rendered' && $this->publicSource($audit, $row, $link['block_id'] ?? null),
      'location' => mb_substr($link['location'] ?? '', 0, 255), 'text' => mb_substr($link['text'] ?? '', 0, 255), 'href' => $link['href'],
      ...app(SeoLinkResolver::class)->resolve($audit->site, $url, $row->locale, $link['href'])];
  }

  private function finalize(SeoAudit $audit, string $token): void
  {
    $rows = DB::table('wbcms_seo_audit_pages')->where('audit_id', $audit->id)->where('id', '>', $audit->cursor)->orderBy('id')->limit(50)->get();
    $findings = [];
    foreach ($rows as $row) {
      if ($row->page_id === 0 || ! in_array($row->state, ['complete', 'partial'], true)) {
        continue;
      }
      foreach (['title', 'description'] as $field) {
        if ($row->{$field.'_hash'} && DB::table('wbcms_seo_audit_pages')->where('audit_id', $audit->id)->where('locale', $row->locale)
          ->where($field.'_hash', $row->{$field.'_hash'})->where('page_id', '!=', $row->page_id)->exists()) {
          $findings[] = $this->finding($row, 'editorial', 'duplicate_'.$field, ['example' => mb_substr($row->$field, 0, 160)]);
        }
      }
      if ($row->state === 'complete' && $row->page_status === 'published' && ! DB::table('wbcms_seo_audit_links as l')
        ->join('wbcms_seo_audit_pages as p', 'p.id', '=', 'l.audit_page_id')->where('l.audit_id', $audit->id)
        ->where('l.target_page_id', $row->page_id)->where('l.target_locale', $row->locale)->where('l.source', 'rendered')
        ->where('l.is_public_source', true)
        ->whereIn('l.state', ['ok', 'old_url', 'wrong_locale'])->where('p.page_status', 'published')->where('p.page_id', '!=', $row->page_id)->exists()) {
        $page = Page::query()->find($row->page_id);
        if ($page && ! app(PublicPageAccess::class)->protected($page)) {
          $findings[] = $this->finding($row, 'editorial', 'no_incoming_links');
        }
      }
    }
    $this->save($audit, $token, function () use ($audit, $rows, $findings): void {
      foreach (array_chunk($findings, 100) as $chunk) {
        DB::table('wbcms_seo_audit_findings')->insert($chunk);
      }
      $audit->update(['cursor' => $rows->last()?->id ?? $audit->cursor]);
      if ($rows->count() < 50) {
        $incomplete = DB::table('wbcms_seo_audit_pages')->where('audit_id', $audit->id)->whereIn('state', ['pending', 'partial', 'unavailable'])->exists();
        $audit->update(['state' => $incomplete ? 'partial' : 'complete', 'phase' => 'finished', 'completed_at' => now()]);
      }
    });
  }

  private function finish(SeoAudit $audit, string $token, string $state): void
  {
    SeoAudit::query()->whereKey($audit->id)->where('worker_token', $token)->update(['state' => $state, 'worker_token' => null, 'worker_expires_at' => null]);
  }

  private function pruneHistory(SeoAudit $audit): void
  {
    // Cascading evidence deletion can be large; never do it in an enqueue request.
    $recent = SeoAudit::query()->where('site_id', $audit->site_id)->orderByDesc('id')->limit(10)->pluck('id');
    $old = SeoAudit::query()->where('site_id', $audit->site_id)->whereNotIn('id', $recent)
      ->whereNotIn('state', ['queued', 'running', 'paused'])->orderBy('id')->limit(100)->pluck('id');
    SeoAudit::query()->whereIn('id', $old)->whereNotIn('state', ['queued', 'running', 'paused'])->delete();
  }

  private function hash(?string $value): ?string
  {
    $value = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value ?? '')));

    return $value === '' ? null : hash('sha256', $value);
  }

  private function publicSource(SeoAudit $audit, object $row, ?int $blockId): bool
  {
    if ($row->page_status !== 'published') {
      return false;
    }
    $memo = app(RequestMemo::class);
    $page = $memo->remember('seo.source-page', [$row->page_id], fn () => Page::query()->where('site_id', $audit->site_id)->find($row->page_id));
    if (! $page || app(PublicPageAccess::class)->protected($page)) {
      return false;
    }
    $seen = [];
    while ($blockId) {
      if (isset($seen[$blockId]) || count($seen) >= 100) {
        return false;
      }
      $seen[$blockId] = true;
      $block = $memo->remember('seo.source-block', [$blockId], fn () => Block::query()->whereKey($blockId)->whereHas('page', fn ($q) => $q->where('site_id', $audit->site_id))->first());
      if (! $block || $block->status !== 'published') {
        return false;
      }
      $blockId = $block->parent_id;
    }

    return true;
  }

  private function finding(object $row, string $category, string $code, array $extra = []): array
  {
    return ['audit_id' => $row->audit_id, 'audit_page_id' => $row->id, 'category' => $category, 'code' => $code,
      'target' => null, 'occurrences' => 1, 'example' => null, 'origin' => null, 'block_id' => null, 'owner_page_id' => null, 'navigation_item_id' => null, ...$extra];
  }
}
