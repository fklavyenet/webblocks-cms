<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;

class InternalNavigationController extends Controller
{
  public function __construct(
    private readonly InternalContentApiOperations $operations,
    private readonly InternalContentApiPresenter $presenter,
  ) {}

  public function index(Request $request): JsonResponse
  {
    $site = $this->siteFromRequest($request);
    $items = NavigationItem::query()
      ->with(['page', 'children.page'])
      ->when($site, fn ($query) => $query->where('site_id', $site->id))
      ->ordered()
      ->get()
      ->groupBy(fn (NavigationItem $item) => $item->site_id.'|'.$item->menu_key);

    $menus = collect(NavigationItem::menuKeys())
      ->flatMap(function (string $handle) use ($site, $items) {
        $sites = $site ? collect([$site]) : Site::query()->primaryFirst()->orderBy('name')->get();

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
      ->with(['page', 'children.page'])
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
      'navigation_item' => $this->presenter->navigationItem($item->fresh(['page', 'children'])),
      'writes' => [['type' => 'navigation_item', 'id' => $item->id]],
      'warnings' => [],
      'errors' => [],
    ], 201);
  }

  private function siteFromRequest(Request $request): ?Site
  {
    $value = $request->query('site', $request->input('site', $request->input('site_id')));

    if ($value === null || $value === '') {
      return null;
    }

    return Site::query()
      ->when(is_numeric($value), fn ($query) => $query->whereKey((int) $value), fn ($query) => $query->where('handle', trim((string) $value)))
      ->first();
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
