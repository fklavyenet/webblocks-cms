<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;
use WebBlocks\Cms\Http\Requests\Admin\IconCatalogItemUpdateRequest;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\Icons\WebBlocksIconManifestSyncer;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class IconCatalogController extends Controller
{
  public function __construct(
    private readonly AdminAuthorization $authorization,
    private readonly WebBlocksIconManifestSyncer $iconManifestSyncer,
  ) {}

  public function index(Request $request): View
  {
    $this->authorization->abortUnlessSystem($request->user());

    $filters = [
      'search' => trim((string) $request->string('search')),
      'source' => trim((string) $request->string('source')),
      'tag' => IconCatalogItem::normalizeTag($request->string('tag')->toString()) ?? '',
      'status' => in_array($request->string('status')->toString(), ['active', 'inactive'], true)
              ? $request->string('status')->toString()
              : '',
    ];

    $totalCount = IconCatalogItem::query()->count();

    $icons = IconCatalogItem::query()
      ->search($filters['search'])
      ->when($filters['source'] !== '', fn (Builder $query) => $query->forSource($filters['source']))
      ->when($filters['tag'] !== '', fn (Builder $query) => $query->tagged($filters['tag']))
      ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
        $query->where('is_active', $filters['status'] === 'active');
      })
      ->orderByDesc('is_active')
      ->orderBy('source')
      ->orderBy('sort_order')
      ->orderBy('label')
      ->paginate(AdminPagination::perPage())
      ->withQueryString();

    $baseQuery = array_filter([
      'search' => $filters['search'],
      'source' => $filters['source'],
      'tag' => $filters['tag'],
      'status' => $filters['status'],
      'page' => $request->integer('page') > 1 ? $request->integer('page') : null,
    ], fn ($value) => $value !== null && $value !== '');

    $requestedModal = old('_icon_modal', $request->string('modal')->toString());
    $requestedIconId = (int) old('_icon_id', $request->integer('icon'));
    $editIcon = $requestedIconId > 0
          ? ($icons->getCollection()->firstWhere('id', $requestedIconId) ?? IconCatalogItem::query()->find($requestedIconId))
          : null;

    return view('webblocks-cms::admin.system.icons.index', [
      'icons' => $icons,
      'filters' => $filters,
      'sources' => IconCatalogItem::query()->orderBy('source')->distinct()->pluck('source')->all(),
      'tags' => $this->tagOptions(),
      'requestedModal' => $requestedModal,
      'editIcon' => $editIcon,
      'closeUrl' => route('admin.system.icons.index', $baseQuery),
      'defaultManifest' => WebBlocksIconManifestSyncer::DEFAULT_MANIFEST,
      'totalCount' => $totalCount,
      'filteredCount' => $icons->total(),
    ]);
  }

  public function update(IconCatalogItemUpdateRequest $request, IconCatalogItem $iconCatalogItem): RedirectResponse
  {
    $this->authorization->abortUnlessSystem($request->user());

    $iconCatalogItem->update($request->catalogData());

    return redirect()
      ->to($request->input('_icon_index_url', route('admin.system.icons.index')))
      ->with('status', 'Icon updated successfully.');
  }

  public function sync(Request $request): RedirectResponse
  {
    $this->authorization->abortUnlessSystem($request->user());

    try {
      $summary = $this->iconManifestSyncer->sync();
    } catch (Throwable $exception) {
      return redirect()
        ->route('admin.system.icons.index')
        ->withErrors(['icons' => 'Icon manifest sync failed: '.$exception->getMessage()]);
    }

    return redirect()
      ->route('admin.system.icons.index')
      ->with('status', sprintf(
        'Icon manifest synchronized. Created: %d. Updated: %d. Unchanged: %d. Deactivated: %d.',
        $summary['created'],
        $summary['updated'],
        $summary['unchanged'],
        $summary['deactivated'],
      ));
  }

  private function tagOptions(): array
  {
    return IconCatalogItem::query()
      ->orderBy('label')
      ->get(['categories', 'contexts'])
      ->flatMap(function (IconCatalogItem $icon): array {
        return array_merge($icon->categories ?? [], $icon->contexts ?? []);
      })
      ->unique()
      ->sort()
      ->values()
      ->mapWithKeys(fn (string $tag) => [$tag => str($tag)->replace('-', ' ')->title()->toString()])
      ->all();
  }
}
