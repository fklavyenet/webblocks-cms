<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Icons\IconCatalog;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Navigation\NavigationTree;

class InternalNavigationController extends Controller
{
  public function __construct(
    private readonly InternalContentApiOperations $operations,
    private readonly InternalContentApiPresenter $presenter,
    private readonly NavigationTree $tree,
    private readonly IconCatalog $iconCatalog,
  ) {}

  public function index(Request $request): JsonResponse
  {
    $site = $this->siteFromRequest($request);
    $items = NavigationItem::query()
      ->when($request->attributes->has('cms_api_allowed_site_ids'), fn ($query) => $query->whereIn('site_id', $request->attributes->get('cms_api_allowed_site_ids')))
      ->with(['page', 'children.page', 'translations.locale', 'children.translations.locale'])
      ->when($site, fn ($query) => $query->where('site_id', $site->id))
      ->ordered()
      ->get()
      ->groupBy(fn (NavigationItem $item) => $item->site_id.'|'.$item->menu_key);

    $menus = collect(NavigationItem::menuKeys())
      ->flatMap(function (string $handle) use ($site, $items) {
        $sites = $site ? collect([$site]) : Site::query()
          ->when(request()->attributes->has('cms_api_allowed_site_ids'), fn ($query) => $query->whereIn('id', request()->attributes->get('cms_api_allowed_site_ids')))
          ->primaryFirst()->orderBy('name')->get();

        return $sites->map(fn (Site $menuSite) => $this->presenter->navigationMenu(
          $menuSite,
          $handle,
          $items->get($menuSite->id.'|'.$handle, collect()),
        ));
      })
      ->values();

    return $this->ok(['navigation_menus' => $menus]);
  }

  public function show(Request $request, string $navigationMenu): JsonResponse
  {
    $site = $this->siteFromRequest($request) ?? Site::primary();

    if (! $site || ! in_array($navigationMenu, NavigationItem::menuKeys(), true)) {
      return $this->validationError([
        ['path' => 'navigation_menu', 'message' => 'Navigation menu must resolve for a site.'],
      ]);
    }

    $items = NavigationItem::query()
      ->with(['page', 'children.page', 'translations.locale', 'children.translations.locale'])
      ->where('site_id', $site->id)
      ->where('menu_key', $navigationMenu)
      ->ordered()
      ->get();

    return $this->ok(['navigation_menu' => $this->presenter->navigationMenu($site, $navigationMenu, $items)]);
  }

  public function store(Request $request): JsonResponse
  {
    $errors = [];
    $warnings = [];
    $site = $this->siteFromRequest($request);
    $normalized = $this->operations->normalizeNavigationMenu($request->json()->all(), $site, 'navigation_menu', $errors);

    if ($errors !== [] || ! $normalized) {
      return $this->validationError($errors);
    }

    try {
      $created = DB::transaction(fn () => $this->operations->createNavigationMenu($normalized));
    } catch (\InvalidArgumentException $exception) {
      return $this->validationError([
        ['path' => 'navigation_menu.handle', 'message' => $exception->getMessage()],
      ]);
    }

    $site = Site::query()->findOrFail($normalized['site']['id']);

    return response()->json([
      'ok' => true,
      'navigation_menu' => $this->presenter->navigationMenu($site, $normalized['handle'], collect($created['items'])),
      'writes' => collect($created['items'])->map(fn (NavigationItem $item) => ['type' => 'navigation_item', 'id' => $item->id])->values()->all(),
      'warnings' => $warnings,
      'errors' => [],
    ], 201);
  }

  public function storeItem(Request $request, string $navigationMenu): JsonResponse
  {
    $errors = [];
    $site = $this->siteFromRequest($request) ?? Site::primary();

    if (! $site || ! in_array($navigationMenu, NavigationItem::menuKeys(), true)) {
      return $this->validationError([
        ['path' => 'navigation_menu', 'message' => 'Navigation menu must resolve for a site.'],
      ]);
    }

    $normalized = $this->operations->normalizeNavigationItem($request->json()->all(), $site, $navigationMenu, 'navigation_item', $errors);

    if ($errors !== [] || ! $normalized) {
      return $this->validationError($errors);
    }

    if (! $normalized['position']) {
      $normalized['position'] = ((int) NavigationItem::query()
        ->where('site_id', $normalized['site_id'])
        ->where('menu_key', $normalized['menu_key'])
        ->max('position')) + 1;
    }

    $item = DB::transaction(fn () => NavigationItem::query()->create($normalized));

    return response()->json([
      'ok' => true,
      'navigation_item' => $this->presenter->navigationItem($item->fresh(['page', 'children', 'translations.locale'])),
      'writes' => [['type' => 'navigation_item', 'id' => $item->id]],
      'warnings' => [],
      'errors' => [],
    ], 201);
  }

  public function updateItem(Request $request, string $navigationMenu, NavigationItem $item): JsonResponse
  {
    $errors = [];
    $site = $this->siteFromRequest($request) ?? $item->site;

    if (! $this->itemBelongsToMenu($item, $navigationMenu, $site)) {
      return $this->validationError([
        ['path' => 'navigation_item', 'message' => 'Navigation item must belong to the selected site and menu.'],
      ]);
    }

    $payload = $request->json()->all();
    $translationLocale = $this->translationLocaleFromPayload($payload, $site, $errors);
    $hasTitleKey = array_key_exists('label', $payload) || array_key_exists('title', $payload);

    $updates = $this->normalizeNavigationItemUpdate($payload, $item, $site, $navigationMenu, $errors);

    if ($errors !== []) {
      return $this->validationError($errors);
    }

    if ($translationLocale && $hasTitleKey) {
      $translatedTitle = trim((string) ($updates['title'] ?? ''));
      $updates['title'] = $item->title;

      DB::transaction(function () use ($item, $updates, $translationLocale, $translatedTitle): void {
        $item->update($updates);
        $item->translations()->updateOrCreate(
          ['locale_id' => $translationLocale->id],
          ['title' => $translatedTitle !== '' ? $translatedTitle : null],
        );
      });
    } else {
      DB::transaction(fn () => $item->update($updates));
    }

    return $this->ok([
      'navigation_item' => $this->presenter->navigationItem($item->fresh(['page', 'children', 'translations.locale'])),
      'writes' => [['type' => 'navigation_item', 'id' => $item->id]],
    ]);
  }

  public function reorderItems(Request $request, string $navigationMenu): JsonResponse
  {
    $site = $this->siteFromRequest($request) ?? Site::primary();

    if (! $site || ! in_array($navigationMenu, NavigationItem::menuKeys(), true)) {
      return $this->validationError([
        ['path' => 'navigation_menu', 'message' => 'Navigation menu must resolve for a site.'],
      ]);
    }

    $items = $request->input('items');
    if (! is_array($items) || $items === []) {
      return $this->validationError([
        ['path' => 'items', 'message' => 'Reorder payload must include an items array.'],
      ]);
    }

    try {
      $normalized = $this->tree->validateAndNormalizeTreePayload($navigationMenu, $site, $items);
    } catch (ValidationException $exception) {
      return $this->validationError($this->validationExceptionErrors($exception));
    }

    DB::transaction(function () use ($normalized): void {
      foreach ($normalized as $item) {
        NavigationItem::query()
          ->whereKey($item['id'])
          ->update([
            'parent_id' => $item['parent_id'],
            'position' => $item['position'],
          ]);
      }
    });

    $items = NavigationItem::query()
      ->with(['page', 'children.page', 'translations.locale', 'children.translations.locale'])
      ->where('site_id', $site->id)
      ->where('menu_key', $navigationMenu)
      ->ordered()
      ->get();

    return $this->ok([
      'navigation_menu' => $this->presenter->navigationMenu($site, $navigationMenu, $items),
      'writes' => collect($normalized)->map(fn (array $item) => ['type' => 'navigation_item', 'id' => $item['id']])->values()->all(),
    ]);
  }

  public function destroyItem(Request $request, string $navigationMenu, NavigationItem $item): JsonResponse
  {
    $site = $this->siteFromRequest($request) ?? $item->site;

    if (! $this->itemBelongsToMenu($item, $navigationMenu, $site)) {
      return $this->validationError([
        ['path' => 'navigation_item', 'message' => 'Navigation item must belong to the selected site and menu.'],
      ]);
    }

    if ($item->children()->exists()) {
      return $this->validationError([
        ['path' => 'navigation_item.children', 'message' => 'Navigation items with child items cannot be deleted through the API. Reorder or delete child items first.'],
      ]);
    }

    DB::transaction(fn () => $item->delete());

    return $this->ok([
      'deleted' => ['type' => 'navigation_item', 'id' => $item->id],
      'writes' => [['type' => 'navigation_item_delete', 'id' => $item->id]],
    ]);
  }

  private function siteFromRequest(Request $request): ?Site
  {
    $value = $request->query('site', $request->query('site_id', $request->input('site', $request->input('site_id'))));

    if ($value === null || $value === '') {
      return null;
    }

    return Site::query()
      ->when(is_numeric($value), fn ($query) => $query->whereKey((int) $value), fn ($query) => $query->where('handle', trim((string) $value)))
      ->first();
  }

  private function itemBelongsToMenu(NavigationItem $item, string $navigationMenu, ?Site $site): bool
  {
    return $site
      && in_array($navigationMenu, NavigationItem::menuKeys(), true)
      && (int) $item->site_id === (int) $site->id
      && $item->menu_key === $navigationMenu;
  }

  private function translationLocaleFromPayload(array &$payload, Site $site, array &$errors): ?Locale
  {
    if (! array_key_exists('locale', $payload)) {
      return null;
    }

    $value = $payload['locale'];
    unset($payload['locale']);

    if ($value === null || trim((string) $value) === '') {
      return null;
    }

    $locale = $this->operations->resolveLocale($value, $site, 'navigation_item.locale', $errors);

    if (! $locale || $locale->id === app(LocaleResolver::class)->default()->id) {
      return null;
    }

    return $locale;
  }

  private function normalizeNavigationItemUpdate(array $payload, NavigationItem $item, Site $site, string $navigationMenu, array &$errors): array
  {
    $this->operations->rejectForbiddenKeys($payload, 'navigation_item', $errors);

    $updates = [];
    $linkType = (string) ($payload['link_type'] ?? $item->link_type);
    $title = array_key_exists('label', $payload)
      ? trim((string) $payload['label'])
      : (array_key_exists('title', $payload) ? trim((string) $payload['title']) : (string) $item->title);
    $url = array_key_exists('url', $payload) ? trim((string) $payload['url']) : (string) $item->url;
    $target = array_key_exists('target', $payload) ? trim((string) $payload['target']) : ($item->target ?: '_self');
    $pageId = array_key_exists('page_id', $payload) ? $payload['page_id'] : $item->page_id;
    $parentId = array_key_exists('parent_id', $payload) ? $payload['parent_id'] : $item->parent_id;
    $visibility = (string) ($payload['visibility'] ?? $item->visibility);
    $hasPosition = array_key_exists('sort_order', $payload) || array_key_exists('position', $payload);
    $position = $hasPosition ? ($payload['sort_order'] ?? $payload['position']) : $item->position;

    if (! in_array($linkType, NavigationItem::linkTypes(), true)) {
      $errors[] = $this->operations->error('navigation_item.link_type', 'Navigation item link type must be supported.');
    }

    if (! in_array($target, ['_self', '_blank'], true)) {
      $errors[] = $this->operations->error('navigation_item.target', 'Navigation item target must be _self or _blank.');
    }

    if (! in_array($visibility, NavigationItem::visibilities(), true)) {
      $errors[] = $this->operations->error('navigation_item.visibility', 'Navigation item visibility must be visible or hidden.');
    }

    // Pre-tree-editor rows may carry position 0; only reject positions the caller sent.
    if ($hasPosition && (! is_numeric($position) || (int) $position < 1)) {
      $errors[] = $this->operations->error('navigation_item.sort_order', 'Navigation item sort order must be one or greater.');
    }

    if ($parentId === '' || $parentId === 0 || $parentId === '0') {
      $parentId = null;
    }

    // Legacy rows may sit under a non-group parent; only vet parents the caller reassigns.
    $parentChanged = array_key_exists('parent_id', $payload)
      && (int) ($parentId ?? 0) !== (int) ($item->parent_id ?? 0);

    if ($parentId !== null && $parentChanged) {
      $parent = NavigationItem::query()->with('parent')->find((int) $parentId);

      if (! $parent || $parent->menu_key !== $navigationMenu || (int) $parent->site_id !== (int) $site->id) {
        $errors[] = $this->operations->error('navigation_item.parent_id', 'Parent item must belong to the same menu.');
      } elseif ((int) $parent->id === (int) $item->id) {
        $errors[] = $this->operations->error('navigation_item.parent_id', 'A navigation item cannot be moved under itself.');
      } elseif ($parent->link_type !== NavigationItem::LINK_GROUP) {
        $errors[] = $this->operations->error('navigation_item.parent_id', 'Only navigation groups can contain child items.');
      } elseif ($this->wouldExceedDepth($item, $parent)) {
        $errors[] = $this->operations->error('navigation_item.parent_id', 'Navigation depth cannot exceed 3 levels.');
      }
    }

    if ($linkType === NavigationItem::LINK_PAGE) {
      if (! $pageId) {
        $errors[] = $this->operations->error('navigation_item.page_id', 'Select a page for page links.');
      } else {
        $page = Page::query()->with('translations')->find((int) $pageId);

        if (! $page || (int) $page->site_id !== (int) $site->id) {
          $errors[] = $this->operations->error('navigation_item.page_id', 'Selected page must belong to the selected site.');
        } else {
          $updates['page_id'] = $page->id;
          $title = $title !== '' ? $title : $page->name;
        }
      }

      $updates['url'] = null;
    }

    if ($linkType === NavigationItem::LINK_CUSTOM_URL) {
      if ($title === '') {
        $errors[] = $this->operations->error('navigation_item.label', 'Navigation item label is required.');
      }

      if (! $this->operations->isSafeNavigationUrl($url)) {
        $errors[] = $this->operations->error('navigation_item.url', 'Navigation item URL must be a safe internal path or http(s) URL.');
      }

      $updates['page_id'] = null;
      $updates['url'] = $url;
    }

    if ($linkType === NavigationItem::LINK_GROUP) {
      if ($title === '') {
        $errors[] = $this->operations->error('navigation_item.label', 'Navigation group label is required.');
      }

      $updates['page_id'] = null;
      $updates['url'] = null;
      $updates['target'] = null;
    } else {
      $updates['target'] = $target;
    }

    if (array_key_exists('icon', $payload)) {
      $icon = $this->iconCatalog->normalizeSlug($payload['icon']);

      if (! $this->iconCatalog->isValidNavigationSelection($icon, $this->iconCatalog->normalizeSlug($item->icon))) {
        $errors[] = $this->operations->error('navigation_item.icon', 'Select an active navigation icon from the catalog.');
      }

      $updates['icon'] = $icon;
    }

    return [
      ...$updates,
      'site_id' => $site->id,
      'menu_key' => $navigationMenu,
      'parent_id' => $parentId,
      'title' => $title !== '' ? $title : null,
      'link_type' => $linkType,
      'position' => (int) $position,
      'visibility' => $visibility,
      'is_system' => (bool) $item->is_system,
    ];
  }

  private function wouldExceedDepth(NavigationItem $item, NavigationItem $parent): bool
  {
    $parentDepth = 1;
    $cursor = $parent;

    while ($cursor) {
      if ((int) $cursor->id === (int) $item->id) {
        return true;
      }

      $cursor = $cursor->parent;

      if ($cursor) {
        $parentDepth++;
      }
    }

    return ($parentDepth + $this->descendantDepth($item)) > NavigationTree::MAX_DEPTH;
  }

  private function descendantDepth(NavigationItem $item): int
  {
    $item->loadMissing('children.children');

    if ($item->children->isEmpty()) {
      return 1;
    }

    return 1 + (int) $item->children->map(fn (NavigationItem $child) => $this->descendantDepth($child))->max();
  }

  private function validationExceptionErrors(ValidationException $exception): array
  {
    return collect($exception->errors())
      ->flatMap(fn (array $messages, string $field) => collect($messages)->map(fn (string $message) => [
        'path' => $field,
        'message' => $message,
      ]))
      ->values()
      ->all();
  }

  private function ok(array $data): JsonResponse
  {
    return response()->json([
      'ok' => true,
      ...$data,
      'warnings' => [],
      'errors' => [],
    ]);
  }

  private function validationError(array $errors): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'warnings' => [],
      'errors' => $errors,
    ], 422);
  }
}
