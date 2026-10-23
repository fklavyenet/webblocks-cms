<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Actions\AutomaticPages\ActivateAutomaticPageRule;
use WebBlocks\Cms\Actions\AutomaticPages\PauseAutomaticPageRule;
use WebBlocks\Cms\Actions\AutomaticPages\SaveAutomaticPageRule;
use WebBlocks\Cms\Http\Requests\Admin\AutomaticPagePauseRequest;
use WebBlocks\Cms\Http\Requests\Admin\AutomaticPageRequest;
use WebBlocks\Cms\Http\Requests\Admin\AutomaticPageTransitionRequest;
use WebBlocks\Cms\Models\AutomaticPageAdoption;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Queries\AdminTableSort;
use WebBlocks\Cms\Support\ContentSources\AutomaticPageDesign;
use WebBlocks\Cms\Support\ContentSources\AutomaticPageInspector;
use WebBlocks\Cms\Support\ContentSources\AutomaticPagePreview;
use WebBlocks\Cms\Support\ContentSources\AutomaticPageTransition;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContract;
use WebBlocks\Cms\Support\ContentSources\PublicationSources;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

final class AutomaticPageController
{
  public function index(Request $request, AdminAuthorization $authorization): View
  {
    Gate::authorize('viewAny', AutomaticPageRule::class);
    $sites = $authorization->scopeSitesForUser(Site::query(), $request->user())->orderBy('name')->get();
    $siteId = $request->integer('site') ?: null;
    abort_if($siteId && ! $sites->contains('id', $siteId), 403);
    $ready = Schema::hasTable('wbcms_automatic_page_rules');
    $rules = $ready ? AutomaticPageRule::query()->with(['site', 'templatePage.translations.locale'])->whereIn('site_id', $siteId ? [$siteId] : $sites->modelKeys())->orderByDesc('id')->tap(fn ($query) => AdminTableSort::for('automatic-pages')->apply($query))->paginate(20)->withQueryString() : null;

    return view('webblocks-cms::admin.automatic-pages.index', compact('sites', 'siteId', 'rules', 'ready'));
  }

  public function create(Request $request, AdminAuthorization $authorization, PublicationSources $sources): View
  {
    Gate::authorize('viewAny', AutomaticPageRule::class);
    $sites = $authorization->scopeSitesForUser(Site::query(), $request->user())->orderBy('name')->get();
    $site = $request->filled('site') ? $sites->firstWhere('id', $request->integer('site')) : $sites->first();
    abort_unless($site, 403);
    Gate::authorize('create', [AutomaticPageRule::class, $site]);
    $rule = new AutomaticPageRule(['site_id' => $site->id, 'base_path' => '/items', 'source_handle' => $request->string('source')->toString()]);
    $rule->setRelation('site', $site);

    return $this->form($rule, $request, $sites, $sources);
  }

  public function edit(Request $request, AutomaticPageRule $automaticPage, PublicationSources $sources): View
  {
    Gate::authorize('view', $automaticPage);

    return $this->form($automaticPage, $request, collect([$automaticPage->site]), $sources);
  }

  private function form(AutomaticPageRule $rule, Request $request, Collection $sites, PublicationSources $sources): View
  {
    $choices = $sources->options($rule->site, $request->user());
    $pages = Page::query()->visibleInAdmin()->where('site_id', $rule->site_id)->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)->with('translations.locale')->orderBy('id')->get();
    $locales = $rule->site->enabledLocales()->get();

    $configuration = $choices[$rule->source_handle] ?? null;
    $design = app(AutomaticPageDesign::class)->page($rule);
    $connections = $configuration && $design ? app(AutomaticPageDesign::class)->connections($design, $configuration['entity']) : [];

    return view('webblocks-cms::admin.automatic-pages.form', compact('rule', 'sites', 'choices', 'pages', 'locales', 'connections', 'design'));
  }

  public function store(AutomaticPageRequest $request, SaveAutomaticPageRule $save): RedirectResponse
  {
    $rule = $save->handle($request->validated(), $request->user());

    return redirect()->route('admin.automatic-pages.edit', $rule)->with('status', __('webblocks-cms::automatic-pages.saved'));
  }

  public function update(AutomaticPageRequest $request, AutomaticPageRule $automaticPage, SaveAutomaticPageRule $save): RedirectResponse
  {
    $save->handle($request->validated(), $request->user(), $automaticPage);

    return redirect()->route('admin.automatic-pages.edit', $automaticPage)->with('status', __('webblocks-cms::automatic-pages.saved'));
  }

  public function records(Request $request, AutomaticPageRule $automaticPage, AutomaticPageInspector $inspector): View
  {
    Gate::authorize('view', $automaticPage);
    $input = $request->validate(['cursor' => ['nullable', 'string', 'max:512'], 'record' => ['nullable', 'string', 'max:190']]);
    $inspection = $inspector->entries($automaticPage, $request->user(), $input['cursor'] ?? null);
    $selected = isset($input['record']) ? $inspector->record($automaticPage, $input['record'], $request->user()) : null;
    $locales = $automaticPage->site->enabledLocales()->get();

    return view('webblocks-cms::admin.automatic-pages.records', compact('automaticPage', 'inspection', 'selected', 'locales', 'inspector'));
  }

  public function transition(Request $request, AutomaticPageRule $automaticPage): View
  {
    Gate::authorize('view', $automaticPage);
    $plan = app(AutomaticPageTransition::class)->inspect($automaticPage, $request->user());

    return view('webblocks-cms::admin.automatic-pages.transition', compact('automaticPage', 'plan'));
  }

  public function pause(AutomaticPagePauseRequest $request, AutomaticPageRule $automaticPage): RedirectResponse
  {
    app(PauseAutomaticPageRule::class)->handle($automaticPage, $request->user(), $request->integer('revision'));

    return redirect()->route('admin.automatic-pages.index', ['site' => $automaticPage->site_id]);
  }

  public function activate(AutomaticPageTransitionRequest $request, AutomaticPageRule $automaticPage): RedirectResponse
  {
    app(ActivateAutomaticPageRule::class)->handle($automaticPage, $request->user(), $request->validated('digest'));

    return redirect()->route('admin.automatic-pages.index', ['site' => $automaticPage->site_id])->with('status', __('webblocks-cms::automatic-pages.activated'));
  }

  public function preview(Request $request, AutomaticPageRule $automaticPage, AutomaticPagePreview $preview): Response
  {
    Gate::authorize('view', $automaticPage);
    $input = $request->validate(['record' => ['required', 'string', 'max:190'], 'locale' => ['required', Rule::in(ContentSourceContract::languages())]]);

    $inspection = app(AutomaticPageInspector::class)->record($automaticPage, $input['record'], $request->user());
    $adoption = AutomaticPageAdoption::query()->where('automatic_page_rule_id', $automaticPage->id)->where('record_key', $input['record'])->first();
    $design = $adoption ? Page::query()->visibleInAdmin()->where('site_id', $automaticPage->site_id)->find($adoption->page_id) : null;
    if (! $design && $inspection['status'] === 'available') {
      $design = Page::query()->visibleInAdmin()->where('site_id', $automaticPage->site_id)->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)
        ->whereHas('translations', fn ($q) => $q->where('path', $automaticPage->base_path.'/'.$inspection['slug']))->first();
    }

    return response($preview->render($automaticPage, $input['record'], $input['locale'], $request->user(), false, $design), 200,
      ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
  }
}
