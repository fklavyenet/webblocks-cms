<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WebBlocks\Cms\Actions\DynamicContent\CreateDynamicContentStarter;
use WebBlocks\Cms\Actions\DynamicContent\SaveDynamicContent;
use WebBlocks\Cms\Http\Requests\Admin\DynamicContentAuthoringRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\ContentSources\ContentCollectionLoader;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContract;
use WebBlocks\Cms\Support\ContentSources\ContentSourceDefinition;
use WebBlocks\Cms\Support\ContentSources\ContentSourceEditor;
use WebBlocks\Cms\Support\ContentSources\ContentSourceRegistry;
use WebBlocks\Cms\Support\ContentSources\ContentSourceRuntime;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceResolver;
use WebBlocks\Cms\Support\ContentSources\DynamicContentDesign;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

final class DynamicContentAuthoringController
{
  public function edit(DynamicContentAuthoringRequest $request, DynamicContentDesign $design, ContentSourceEditor $editor): View
  {
    $sites = app(AdminAuthorization::class)->scopeSitesForUser(Site::query(), $request->user())->orderBy('name')->get();
    $definition = app(ContentSourceRegistry::class)->find($request->input('source'));
    abort_unless($definition && $definition->contract(), 404);
    $accessiblePages = app(AdminAuthorization::class)->scopePagesForUser(Page::query(), $request->user())->with('site')->get();
    $runtime = app(ContentSourceRuntime::class);
    $sites = $sites->filter(fn ($site) => $runtime->allows($definition, new ContentSourceContext($site, null, 'en', true, $request->user()))
      || $accessiblePages->where('site_id', $site->id)->contains(fn ($candidate) => $runtime->allows($definition, new ContentSourceContext($site, $candidate, 'en', true, $request->user()))));
    $siteId = $request->integer('site') ?: $sites->first()?->id;
    abort_unless($sites->contains('id', $siteId), 403);
    $pages = app(AdminAuthorization::class)->scopePagesForUser(Page::query(), $request->user())->visibleInAdmin()->whereRuntimeSettingNull('revision_restore_candidate')->where('site_id', $siteId)->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)->with('translations.locale')->orderBy('id')->get();
    $page = $request->filled('page_id') ? $this->page($request) : null;
    abort_if($page && $page->site_id !== $siteId, 403);
    $source = $page ? $this->source($request, $page) : $definition;
    $blocks = $page ? $design->blocks($page) : collect();
    $destinations = $blocks->filter(fn ($block) => $source->isCollection() ? $editor->supportsCollection($block) : $editor->targets($block) !== []);
    $block = $request->filled('block_id') ? $destinations->get($request->integer('block_id')) : null;
    abort_if($request->filled('block_id') && ! $block, 404);
    $templateId = $request->integer('template_id') ?: (int) $block?->setting('content_collection.template_block_id');
    abort_if($block && $templateId && ! $block->children->contains(fn ($child) => $child->id === $templateId && $editor->collectionTemplateIsAllowed($block, $child->typeSlug())), 422);
    if ($block && $source->isCollection() && $templateId) {
      $settings = $block->decodedSettings();
      $settings['content_collection'] = array_replace((array) ($settings['content_collection'] ?? []), ['source' => $source->handle(), 'template_block_id' => $templateId]);
      $block->settings = $settings;
    }
    $mappingBlocks = $block ? $design->mappingBlocks($block, $source->isCollection() ? $templateId : null) : collect();
    $locale = $request->input('locale', app()->getLocale());
    $slots = $page ? $page->slots()->with('slotType')->where('source_type', 'page')->get() : collect();

    return view('webblocks-cms::admin.dynamic-content.author', compact('sites', 'siteId', 'pages', 'page', 'source', 'blocks', 'destinations', 'block', 'templateId', 'mappingBlocks', 'editor', 'design', 'locale', 'slots'));
  }

  public function save(DynamicContentAuthoringRequest $request, SaveDynamicContent $save): RedirectResponse
  {
    $page = $this->page($request, true);
    $this->source($request, $page);
    $save->handle($page, $request->validated(), $request->user());

    return redirect()->route('admin.dynamic-content.author', $request->only('source', 'site', 'page_id', 'block_id', 'template_id', 'locale'))->with('status', app(CmsTranslator::class)->get('dynamic-authoring.saved', app(AdminLocaleResolver::class)->locale()));
  }

  public function stage(DynamicContentAuthoringRequest $request, InternalContentPlanService $plans): RedirectResponse
  {
    $page = $this->page($request);
    abort_unless(app(PageWorkflowManager::class)->canEditContent($request->user(), $page), 403);
    $this->source($request, $page);
    abort_unless($page->status === Page::STATUS_PUBLISHED, 409);
    $draft = $plans->prepareAuthoringDraft($page, $request->user());

    return redirect()->route('admin.dynamic-content.author', ['source' => $request->input('source'), 'site' => $draft->site_id, 'page_id' => $draft->id]);
  }

  public function starter(DynamicContentAuthoringRequest $request, CreateDynamicContentStarter $create): RedirectResponse
  {
    $page = $this->page($request, true);
    $source = $this->source($request, $page);
    abort_unless($source->isCollection(), 422);
    $block = $create->handle($page, $request->integer('slot_id'), $request->user());

    return redirect()->route('admin.dynamic-content.author', ['source' => $source->handle(), 'site' => $page->site_id, 'page_id' => $page->id, 'block_id' => $block->id, 'template_id' => $block->children()->first()->id]);
  }

  public function preview(DynamicContentAuthoringRequest $request, SaveDynamicContent $save, BlockTranslationResolver $translations): Response
  {
    $page = $this->page($request);
    $source = $this->source($request, $page);
    [$block] = $save->configure($page, $request->validated());
    $locale = $request->input('locale', 'en');
    abort_unless($page->site->hasEnabledLocale($locale), 422);
    $block = $translations->resolve($block, $locale, $page->site);
    $walk = function ($node, $parent = null) use (&$walk, $page): void {
      $node->setRelation('page', $page);
      $node->setRelation('parent', $parent);
      $node->setAttribute('render_preview', true);
      foreach ($node->children as $child) {
        $walk($child, $node);
      }
    };
    $walk($block, $block->parent);
    $status = 'available';
    $total = null;
    if ($source->isCollection()) {
      try {
        $result = app(ContentCollectionLoader::class)->load($block, (array) $block->setting('content_collection'), true);
        $status = $result->records === [] ? 'empty' : 'available';
        $total = $result->total;
      } catch (AuthorizationException) {
        $status = 'denied';
      } catch (\InvalidArgumentException|\UnexpectedValueException) {
        $status = 'invalid';
      } catch (Throwable $exception) {
        report($exception);
        $status = 'unavailable';
      }
    }
    if (in_array($status, ['available', 'empty'], true)) {
      $bindingStatus = $this->checkFixedBindings($block, $locale);
      if ($bindingStatus !== 'available') {
      $status = $bindingStatus;
      }
    }

    $dynamicAuthoringPreviewFields = collect(Arr::dot($request->validated()))->mapWithKeys(function ($value, $key) {
      $parts = explode('.', $key);
      $name = array_shift($parts).implode('', array_map(fn ($part) => '['.$part.']', $parts));

      return [$name => is_scalar($value) ? (string) $value : ''];
    });
    $previousLocale = app()->getLocale();
    app()->setLocale($locale);
    try {
      return response()->view('webblocks-cms::admin.dynamic-content.preview', compact('source', 'block', 'locale', 'status', 'total', 'dynamicAuthoringPreviewFields') + ['site' => $page->site])->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'private, no-store');
    } finally {
      app()->setLocale($previousLocale);
    }
  }

  private function checkFixedBindings(Block $root, string $locale): string
  {
    $resolved = [];
    foreach (app(DynamicContentDesign::class)->branch($root) as $block) {
      foreach ((array) $block->setting('content_bindings', []) as $binding) {
        if (($binding['record'] ?? '') === '@item') {
        continue;
        }
        $source = app(ContentSourceRegistry::class)->find($binding['source'] ?? '');
        if (! $source || ! $source->resolverClass()) {
        return 'unavailable';
        }
        $context = new ContentSourceContext($block->renderSite(), $block->renderPage(), $locale, true, auth()->user());
        if (! app(ContentSourceRuntime::class)->allows($source, $context)) {
        return 'denied';
        }
        $key = $source->handle().'|'.($binding['record'] ?? '');
        try {
          if (! array_key_exists($key, $resolved)) {
            $resolver = app($source->resolverClass());
            if (! $resolver instanceof ContentSourceResolver) {
            return 'invalid';
            }
            $record = $resolver->resolve($binding['record'], $context);
            $resolved[$key] = $record === null ? null : ContentSourceContract::record($source, $record, $context);
          }
          if ($resolved[$key] === null) {
          return 'empty';
          }
        } catch (\InvalidArgumentException|\UnexpectedValueException) {
          return 'invalid';
        } catch (Throwable $exception) {
          report($exception);

          return 'unavailable';
        }
      }
    }

    return 'available';
  }

  private function page(DynamicContentAuthoringRequest $request, bool $draft = false): Page
  {
    Gate::authorize('access-admin');
    $page = app(AdminAuthorization::class)->scopePagesForUser(Page::query(), $request->user())->with('site.locales')->findOrFail($request->integer('page_id'));
    abort_if($page->isSharedSlotSourcePage() || $page->isRevisionRestoreCandidate(), 404);
    if ($draft) {
      abort_unless($page->status === Page::STATUS_DRAFT && app(PageWorkflowManager::class)->canEditContent($request->user(), $page), 403);
    }

    return $page;
  }

  private function source(DynamicContentAuthoringRequest $request, ?Page $page, ?Site $site = null): ContentSourceDefinition
  {
    $source = app(ContentSourceRegistry::class)->find($request->input('source'));
    abort_unless($source && $source->contract(), 404);
    abort_unless(app(ContentSourceRuntime::class)->allows($source, new ContentSourceContext($page?->site ?? $site, $page, $request->input('locale', 'en'), true, $request->user())), 403);

    return $source;
  }
}
