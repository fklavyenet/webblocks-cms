<?php

namespace WebBlocks\Cms\Support\Seo;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Throwable;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\WebBlocks;

/** One report contract for the panel and the Internal Content API. No stored results. */
final class PageSeoReport
{
  public function __construct(
    private readonly PublicPagePresenter $presenter,
    private readonly PageSeoHtmlCheck $check,
    private readonly PageRouteResolver $routes,
    private readonly CmsTranslator $translator,
  ) {}

  public function run(Page $page, string $locale, string $messageLocale = 'en', bool $includeLinks = false): array
  {
    $sourceVersion = app(SeoSourceVersion::class)->current();
    abort_if($page->isSharedSlotSourcePage() || $page->isRevisionRestoreCandidate(), 404);
    $graph = $page->fresh(['site', 'translations.locale', 'slots.slotType', 'slots.sharedSlot', 'pageAssets',
      'blocks.blockType', 'blocks.slotType', 'blocks.asset', 'blocks.blockAssets.asset',
      'blocks.textTranslations', 'blocks.buttonTranslations', 'blocks.imageTranslations', 'blocks.contactFormTranslations']);
    $translation = $graph->translations->first(fn ($row) => $row->locale?->code === $locale);
    abort_unless($translation && $graph->availableSiteLocales()->contains('code', $locale), 422);
    $graph->setRelation('currentTranslation', $translation);
    $children = $graph->blocks->groupBy('parent_id');
    foreach ($graph->blocks as $block) {
      $block->setRelation('page', $graph);
      $block->setRelation('children', $children->get($block->id, collect())->values());
    }
    $url = $this->routes->canonicalUrlFor($graph, $locale, $graph->site);
    $originalRequest = request();
    $originalLocale = app()->getLocale();
    $runtime = Request::create($url ?: config('app.url'), 'GET');
    $runtime->setUserResolver($originalRequest->getUserResolver());
    if ($originalRequest->hasSession()) {
      $runtime->setLaravelSession($originalRequest->session());
    }
    $runtime->attributes->set('cms_editor_preview', true);
    $marker = 'seo-'.Str::random(24);
    $runtime->attributes->set('cms_seo_marker', $marker);
    $runtime->setRouteResolver(fn () => tap(new Route('GET', '/', fn () => null), fn ($route) => $route->bind($runtime)->setParameter('locale', $locale)));
    $result = ['technical' => [], 'editorial' => [], 'not_checked' => []];
    $evidence = ['title' => null, 'description' => null, 'links' => [], 'locations' => []];
    try {
      app()->instance('request', $runtime);
      app()->setLocale($locale);
      // Include saved draft blocks, but omit the preview banner and its forced
      // robots meta: those transport protections are not page SEO findings.
      $html = view('webblocks-cms::public.pages.show', [
        ...$this->presenter->present($graph, preview: true, locale: $locale),
        'previewMode' => false,
      ])->render();
      $result = $this->check->inspect($html, $locale);
      $evidence = app(SeoHtmlEvidence::class)->inspect($html, $marker);
      if ($includeLinks && ($evidence['links_truncated'] ?? false)) {
        $result['not_checked'][] = 'links_truncated';
      }
      if ($runtime->attributes->get('cms_editor_preview_issues', []) !== []) {
        $result['not_checked'][] = 'dynamic_content_incomplete';
      }
    } catch (Throwable) {
      // Plugin/provider/template exceptions may contain credentials or host data.
      // A failed render is unavailable evidence, never a clean SEO result.
      $result['not_checked'][] = 'render_failed';
    } finally {
      app()->instance('request', $originalRequest);
      app()->setLocale($originalLocale);
    }
    foreach (['technical', 'editorial'] as $category) {
      foreach ($result[$category] as &$finding) {
        $finding['message'] = $this->translator->get('seo.findings.'.$finding['code'], $messageLocale, ['count' => $finding['count']]);
        $finding['examples'] = app(SeoHtmlEvidence::class)->examples($finding['code'], $evidence);
        foreach ($finding['examples'] as &$example) {
          $example['action'] = app(SeoFindingTarget::class)->for($graph, $locale, $finding['code'], $example['block_id'] ?? null, $example['origin'] ?? null);
        }
        unset($example);
        $finding['action'] = $finding['examples'][0]['action'] ?? app(SeoFindingTarget::class)->for($graph, $locale, $finding['code']);
      }
      unset($finding);
    }
    $notChecked = array_map(fn ($code) => ['code' => $code, 'message' => $this->translator->get('seo.not_checked.'.$code, $messageLocale)],
      array_values(array_unique([...$result['not_checked'], 'live_delivery', 'links', 'performance', 'cross_page', 'scripts'])));

    return [
      'page_id' => (int) $graph->id,
      'site_id' => (int) $graph->site_id,
      'locale' => $locale,
      'status' => $graph->status,
      'source' => 'saved_preview',
      'inspection_status' => array_intersect($result['not_checked'], ['render_failed', 'html_unavailable']) !== []
        ? 'unavailable' : ($result['not_checked'] === [] ? 'complete' : 'partial'),
      'public_url' => $url,
      'checked_at' => now()->toIso8601String(),
      'cms_version' => WebBlocks::version(),
      'source_version' => $sourceVersion,
      'stale' => ! hash_equals($sourceVersion, app(SeoSourceVersion::class)->current()),
      'metadata' => [
        'title' => ['value' => $evidence['title'], 'source' => trim((string) $translation->seo_title) !== '' ? 'translation_seo_title' : 'translation_name', 'site_label' => true],
        'description' => ['value' => $evidence['description'], 'source' => trim((string) $translation->seo_description) !== '' ? 'translation_seo_description' : (trim((string) $graph->site?->seo_description) !== '' ? 'site_fallback' : 'empty')],
      ],
      ...($includeLinks ? ['links' => $evidence['links']] : []),
      ...($includeLinks ? ['rendered_block_ids' => array_values(array_unique(array_filter(array_map(fn ($row) => $row['evidence']['block_id'] ?? null, $evidence['locations']))))] : []),
      'blocks_publication' => 'page_owned_including_drafts_shared_published',
      'blocks_publication_draft_count' => $graph->blocks->where('status', '!=', 'published')->count(),
      'blocks_publication_note' => $this->translator->get('seo.saved_scope', $messageLocale),
      'publication_blocked' => false,
      'technical' => $result['technical'],
      'editorial' => $result['editorial'],
      'not_checked' => $notChecked,
    ];
  }
}
