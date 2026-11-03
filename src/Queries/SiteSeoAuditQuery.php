<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\SeoAudit;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Seo\SeoFindingTarget;
use WebBlocks\Cms\Support\Seo\SeoSourceVersion;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

final class SiteSeoAuditQuery
{
  public function run(Site $site, array $filters, string $messageLocale = 'en'): array
  {
    $audit = SeoAudit::query()->where('site_id', $site->id)->when($filters['audit'] ?? null, fn ($q, $id) => $q->whereKey($id))->latest('id')->first();
    abort_if(isset($filters['audit']) && ! $audit, 404);
    $history = SeoAudit::query()->where('site_id', $site->id)->latest('id')->limit(10)->get(['id', 'state', 'created_at']);
    if (! $audit) {
      return ['audit' => null, 'history' => $history, 'pages' => null, 'findings' => null, 'links' => null];
    }
    $translator = app(CmsTranslator::class);
    $stale = ! hash_equals($audit->source_version, app(SeoSourceVersion::class)->current());
    $pages = DB::table('wbcms_seo_audit_pages')->where('audit_id', $audit->id);
    foreach (['locale' => 'locale', 'page_id' => 'page_id', 'status' => 'page_status'] as $filter => $column) {
      if (isset($filters[$filter]) && $filters[$filter] !== '') {
        $pages->where($column, $filters[$filter]);
      }
    }
    $findings = DB::table('wbcms_seo_audit_findings as f')->joinSub(clone $pages, 'p', 'p.id', '=', 'f.audit_page_id')
      ->select('f.*', 'p.page_id', 'p.locale', 'p.page_status')->where('f.audit_id', $audit->id);
    foreach (['code', 'category'] as $filter) {
      if (! empty($filters[$filter])) {
        $findings->where('f.'.$filter, $filters[$filter]);
      }
    }
    $links = DB::table('wbcms_seo_audit_links as l')->joinSub(clone $pages, 'p', 'p.id', '=', 'l.audit_page_id')
      ->select('l.*', 'p.page_id', 'p.locale', 'p.page_status')->where('l.audit_id', $audit->id);
    if (! empty($filters['link_state'])) {
      $links->where('l.state', $filters['link_state']);
    }
    if (! empty($filters['code']) || ! empty($filters['category'])) {
      $pages->whereIn('id', (clone $findings)->select('f.audit_page_id'));
    }
    $perPage = $filters['per_page'] ?? 25;
    $pageRows = $pages->orderBy('id')->paginate($perPage, ['*'], 'pages_page')->withQueryString();
    $findingRows = $findings->orderBy('f.id')->paginate($perPage, ['*'], 'findings_page')->withQueryString();
    $linkRows = $links->orderBy('l.id')->paginate($perPage, ['*'], 'links_page')->withQueryString();
    $pageModels = Page::query()->where('site_id', $site->id)->whereIn('id', $findingRows->getCollection()->pluck('page_id')->merge($linkRows->getCollection()->pluck('page_id')))->get()->keyBy('id');
    $findingRows->through(function ($row) use ($translator, $messageLocale, $pageModels) {
      $namespace = $row->category === 'not_checked' ? 'not_checked' : 'findings';
      $row->message = $translator->get('seo.'.$namespace.'.'.$row->code, $messageLocale, ['count' => $row->occurrences]);
      $page = $pageModels->get($row->page_id);
      $row->action = $page ? app(SeoFindingTarget::class)->for($page, $row->locale, $row->code, $row->block_id, $row->origin) : null;

      return $row;
    });
    $linkRows->through(function ($row) use ($translator, $messageLocale, $pageModels): object {
      $row->message = $translator->get('seo.link_states.'.$row->state, $messageLocale);
      $row->source_href = $row->navigation_item_id
        ? route('admin.navigation.edit', ['navigation' => $row->navigation_item_id, 'locale' => $row->locale])
        : ($row->block_id && $pageModels->has($row->page_id) ? app(SeoFindingTarget::class)->for($pageModels->get($row->page_id), $row->locale, 'link', $row->block_id)['href'] : ($row->page_id ? route('admin.pages.edit', ['page' => $row->page_id]) : null));

      return $row;
    });

    return ['audit' => [
      'id' => $audit->id, 'site_id' => $audit->site_id, 'state' => $audit->state, 'phase' => $audit->phase,
      'total' => $audit->total, 'processed' => $audit->processed, 'stale' => $stale,
      'source' => 'saved_preview', 'source_version' => $audit->source_version, 'cms_version' => $audit->cms_version,
      'created_at' => $audit->created_at?->toIso8601String(), 'completed_at' => $audit->completed_at?->toIso8601String(),
      'complete' => $audit->state === 'complete' && ! $stale,
      'locales' => DB::table('wbcms_seo_audit_locales')->where('audit_id', $audit->id)->pluck('locale'),
      'publication_blocked' => false,
      'scope' => $translator->get('seo.audit_scope', $messageLocale),
    ], 'history' => $history, 'pages' => $pageRows, 'findings' => $findingRows, 'links' => $linkRows];
  }
}
