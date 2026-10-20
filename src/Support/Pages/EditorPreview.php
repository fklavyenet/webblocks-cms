<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\InputBag;
use WebBlocks\Cms\Http\Requests\Admin\BlockRequest;
use WebBlocks\Cms\Http\Requests\Admin\EditorPreviewRequest;
use WebBlocks\Cms\Http\Requests\Admin\PageRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Policies\CmsResourcePolicy;
use WebBlocks\Cms\Policies\EditorRecoveryPolicy;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\EditorRecovery\RecoveryFields;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;

/** A detached model graph for one render. Never saves, dispatches writes or creates revisions. */
final class EditorPreview
{
  public function form($user, Page $page, ?Block $block = null, ?string $locale = null): ?array
  {
    if (! $page->exists || ! app(EditorRecoveryPolicy::class)->edit($user, $page)
      || ($block && (! $block->exists || (int) $block->page_id !== (int) $page->id))) {
      return null;
    }
    $locales = $page->availableSiteLocales();
    $locale ??= $locales->firstWhere('is_default', true)?->code;
    if (! $locales->contains('code', $locale) || ($block && $locale !== $locales->firstWhere('is_default', true)?->code
      && ! app(CmsAuthorization::class)->allows($user, 'cms.translations.update', $page->site_id))) {
      return null;
    }

    return [
      'endpoint' => route('admin.pages.editor.preview', $page),
      'separate' => route('admin.pages.preview', $page),
      'locale' => $locale, 'locales' => $locales, 'block' => $block?->id,
      'supported' => ! $block || $this->supports($block),
      'roots' => $block ? RecoveryFields::BLOCK_FIELDS : RecoveryFields::PAGE_FIELDS,
    ];
  }

  public function supports(Block $block): bool
  {
    // These native forms also mutate child rows, gallery translations or executable configuration.
    return ! in_array($block->typeSlug(), ['columns', 'feature-grid', 'link-list', 'gallery', 'application'], true)
      && ! app(PluginBlockCatalog::class)->isPluginCatalogSlug($block->typeSlug());
  }

  public function page(EditorPreviewRequest $request, Page $saved): Page
  {
    $block = app(EditorRecoveryPolicy::class)->authorizeContext($request->user(), $saved, $request->integer('block_id') ?: null, $request->string('form_locale')->toString());
    if ($block && ! $this->supports($block)) {
      $this->unsupported();
    }
    $fields = app(RecoveryFields::class);
    $input = $fields->input($fields->decode($request->validated('entries'), $block !== null));
    // Encoded controls bypass ConvertEmptyStringsToNull; native forms do not.
    array_walk_recursive($input, static function (&$value): void {
      if ($value === '') {
        $value = null;
      }
    });
    app(CmsResourcePolicy::class)->authorizeFields($input, $request->user(), $saved->site_id);
    $page = clone $saved;
    $page->load(['site', 'translations.locale', 'slots.slotType', 'slots.sharedSlot', 'pageAssets', 'blocks.blockType', 'blocks.slotType', 'blocks.asset', 'blocks.blockAssets.asset']);

    if ($block) {
      // Moving/retyping blocks belongs to saved structure, not to this form overlay.
      foreach (['block_type_id', 'slot_type_id', 'parent_id', 'sort_order', 'status'] as $key) {
        if (array_key_exists($key, $input) && (string) $input[$key] !== (string) $block->{$key}) {
          $this->unsupported();
        }
      }
      $input += Arr::only($block->getAttributes(), ['block_type_id', 'slot_type_id', 'parent_id', 'sort_order', 'status']);
      $input['page_id'] = $page->id;
      $input['locale'] = $request->validated('form_locale');
      $data = $this->validateForm(BlockRequest::class, $request, $input, $block)->validatedData();
      $replacement = app(BlockPayloadWriter::class)->preview($block, $page, $data, $input['locale']);
      $page->setRelation('blocks', $page->blocks->map(fn ($item) => $item->id === $block->id ? $replacement : $item));
    } else {
      $data = $this->validateForm(PageRequest::class, $request, $input)->validatedData();
      $page->fill(Arr::only($data, ['title', 'slug']));
      // Retain staged/workflow metadata while replacing only the native shell field.
      $page->settings = array_replace((array) $page->settings, Arr::only((array) ($data['settings'] ?? []), ['public_shell']));
      $default = $page->availableSiteLocales()->firstWhere('is_default', true);
      $page->setRelation('translations', $page->translations->map(function ($translation) use ($default, $data) {
        $copy = clone $translation;
        if ($copy->locale_id === $default->id) {
          $copy->fill($data['translation']);
        }

        return $copy;
      }));
    }
    $nodes = $page->blocks->keyBy('id');
    foreach ($nodes as $node) {
      $node->setRelation('page', $page);
      $node->setRelation('parent', $nodes->get($node->parent_id));
      $node->setRelation('children', $page->blocks->where('parent_id', $node->id)->sortBy('sort_order')->values());
    }
    foreach ($page->slots as $slot) {
      $slot->setRelation('page', $page);
    }
    $translation = $page->translations->first(fn ($item) => $item->locale?->code === $request->validated('locale'));
    // No fallback to another language while the selector says otherwise.
    if (! $translation) {
      throw ValidationException::withMessages(['locale' => __('webblocks-cms::preview.translation_missing')]);
    }
    $page->setRelation('currentTranslation', $translation);

    return $page;
  }

  private function validateForm(string $class, EditorPreviewRequest $request, array $input, ?Block $block = null): FormRequest
  {
    $native = $class::createFrom($request, new $class);
    $native->setJson(new InputBag($input));
    $native->replace($input);
    $route = clone $request->route();
    if ($block) {
      $route->setParameter('block', $block);
    }
    $native->setRouteResolver(fn () => $route);
    $native->setContainer(app())->setRedirector(app('redirect'));
    $native->validateResolved();

    return $native;
  }

  private function unsupported(): never
  {
    throw ValidationException::withMessages(['entries' => __('webblocks-cms::preview.unsupported')]);
  }
}
