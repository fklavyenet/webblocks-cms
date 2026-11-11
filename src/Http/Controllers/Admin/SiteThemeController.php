<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\SiteThemeRequest;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\PublicThemePolicy;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\Theme\PublicThemes;

final class SiteThemeController
{
  public function __construct(
    private readonly PublicThemes $themes,
    private readonly PublicThemePolicy $policy,
  ) {}

  public function index(SiteThemeRequest $request, Site $site): View|JsonResponse
  {
    $this->authorize($request, $site);
    $history = $this->themes->history($site);
    $versions = $this->themes->versions();
    $current = $this->themes->current($site);
    if ($request->routeIs('internal-content-api.*')) {
      return response()->json(compact('history', 'versions', 'current') + ['preset' => $site->resolvedPublicThemePreset()]);
    }

    $canPreview = $this->policy->manageSite($request, $site, preview: true);
    $candidate = $canPreview && $request->filled('version') ? $this->candidate($request) : null;
    $page = $candidate ? $this->page($request, $site) : null;
    $report = $candidate ? $this->themes->report($site, $candidate) : null;
    $options = $candidate ? $this->options($request, $page, $candidate, $current) : [];
    $confirmation = $page && $this->canActivate($report)
      ? $this->themes->confirmation($site, $candidate, $options['button_corners'], (int) ($current?->id ?? 0))
      : null;
    $pages = $candidate ? $this->themes->pages($site)->with('translations.locale')->orderBy('id')->simplePaginate(50, ['*'], 'page_list')->withQueryString() : null;

    return view('webblocks-cms::admin.sites.themes.index', compact('site', 'history', 'versions', 'current', 'candidate', 'page', 'pages', 'report', 'options', 'confirmation', 'canPreview'));
  }

  public function preview(SiteThemeRequest $request, Site $site, PublicPagePresenter $presenter): Response
  {
    $this->authorize($request, $site, preview: true);
    $page = $this->page($request, $site);
    abort_if($page === null, 404);
    $candidate = $this->candidate($request);
    $side = $request->validated('side') ?? 'candidate';
    $current = $this->themes->current($site);
    $options = $this->options($request, $page, $candidate, $current);

    $theme = $this->themes->render($site, $page);
    $confirmation = null;
    if ($side === 'candidate') {
      $report = $this->themes->report($site, $candidate);
      if (! $report['compatible'] || ! $report['intact'] || ! in_array($page->publicShellPreset(), $candidate->layouts, true)) {
        throw ValidationException::withMessages(['version' => __('webblocks-cms::themes.incompatible_page')]);
      }
      $theme = $this->themes->appearance($candidate, $options['button_corners']);
      if ($request->routeIs('internal-content-api.*') && $this->canActivate($report)) {
        $confirmation = $this->themes->confirmation($site, $candidate, $options['button_corners'], (int) ($current?->id ?? 0));
      }
    }

    $presenter->loadPreviewRelations($page);
    $translation = $options['locale'] ? $page->translationForLocale($options['locale']) : $page->defaultTranslation();
    if ($options['locale'] && ! $translation) {
      throw ValidationException::withMessages(['locale' => __('webblocks-cms::preview.translation_missing')]);
    }
    $page->setRelation('currentTranslation', $translation);

    $response = response()->view('webblocks-cms::public.pages.show', [
      ...$presenter->present($page, preview: true, locale: $translation?->locale),
      'themePreview' => ['theme' => $theme, 'mode' => $options['mode'], 'omit_site_css' => $options['omit_site_css']],
    ])->withHeaders([
      'Cache-Control' => 'private, no-store',
      'X-Robots-Tag' => 'noindex, nofollow',
      'Content-Security-Policy' => "sandbox allow-same-origin; script-src 'none'; object-src 'none'; frame-src 'none'; form-action 'none'; base-uri 'none'; frame-ancestors 'self'",
    ]);
    if ($confirmation !== null) {
      $response->header('X-WebBlocks-Theme-Confirmation', $confirmation);
    }

    return $response;
  }

  public function activate(SiteThemeRequest $request, Site $site): RedirectResponse|JsonResponse
  {
    $this->authorize($request, $site);
    $id = $this->themes->activate($site, $request->validated('confirmation'));

    return $this->result($request, $site, $id, 'activated');
  }

  public function restore(SiteThemeRequest $request, Site $site): RedirectResponse|JsonResponse
  {
    $this->authorize($request, $site);
    $id = $this->themes->restore($site, $request->integer('expected'), $request->integer('revision') ?: null);

    return $this->result($request, $site, $id, 'restored');
  }

  private function authorize(Request $request, Site $site, bool $preview = false): void
  {
    abort_unless($this->policy->manageSite($request, $site, $preview), 403);
    abort_unless($this->themes->ready(), 503);
  }

  private function candidate(SiteThemeRequest $request): object
  {
    return $this->themes->describe($this->themes->version($request->integer('version')));
  }

  private function page(SiteThemeRequest $request, Site $site): ?Page
  {
    $pages = $this->themes->pages($site);
    if ($request->filled('page')) {
      return $pages->findOrFail($request->integer('page'));
    }

    return $pages->orderByRaw('case when status = ? then 0 else 1 end', [Page::STATUS_PUBLISHED])->orderBy('id')->first();
  }

  private function options(SiteThemeRequest $request, ?Page $page, object $candidate, ?object $current): array
  {
    return [
      'version' => $candidate->id,
      'page' => $page?->id,
      'button_corners' => $request->validated('button_corners') ?? ((int) $current?->theme_version_id === (int) $candidate->id ? $current->button_corners : 'default'),
      'mode' => $request->validated('mode') ?? 'auto',
      'width' => $request->validated('width') ?? 'desktop',
      'locale' => $request->validated('locale'),
      'omit_site_css' => $request->boolean('omit_site_css'),
    ];
  }

  private function canActivate(array $report): bool
  {
    return $report['compatible'] && $report['intact'] && $report['affected_pages'] === [];
  }

  private function result(Request $request, Site $site, int $id, string $status): RedirectResponse|JsonResponse
  {
    return $request->routeIs('internal-content-api.*')
      ? response()->json(['ok' => true, 'revision' => $id])
      : redirect()->route('admin.sites.themes.index', $site)->with('theme_status', $status);
  }
}
