<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageAsset;
use WebBlocks\Cms\Support\Pages\PageAssetPathValidator;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;

class InternalPageAssetController extends Controller
{
  public function __construct(
    private readonly PageAssetPathValidator $pathValidator,
    private readonly PageRevisionManager $revisionManager,
  ) {}

  public function index(Page $page): JsonResponse
  {
    $assets = $page->pageAssets()
      ->orderBy('type')
      ->orderBy('sort_order')
      ->orderBy('id')
      ->get()
      ->map(fn (PageAsset $asset): array => $this->present($asset))
      ->values();

    return $this->ok(['page_assets' => $assets]);
  }

  public function store(Request $request, Page $page, string $type): JsonResponse
  {
    $normalizedType = $this->pathValidator->normalizeType($type);

    if (! in_array($normalizedType, PageAsset::allowedTypes(), true)) {
      return $this->validationError([
        ['path' => 'type', 'message' => 'Asset type must be css or js.'],
      ]);
    }

    $path = $request->input('path');
    $message = $this->pathValidator->validate($normalizedType, $path);

    if ($message !== null) {
      return $this->validationError([['path' => 'path', 'message' => $message]]);
    }

    $asset = DB::transaction(function () use ($page, $request, $normalizedType, $path): PageAsset {
      $asset = $page->pageAssets()->create($this->assetData($request, $normalizedType, $path));

      $this->touchAndCaptureRevision($page, 'Page asset added', 'A page asset was added through the Internal Content API.');

      return $asset;
    });

    return response()->json([
      'ok' => true,
      'page_asset' => $this->present($asset),
      'writes' => [['type' => 'page_asset', 'id' => $asset->id]],
      'warnings' => [],
      'errors' => [],
    ], 201);
  }

  public function update(Request $request, Page $page, PageAsset $pageAsset): JsonResponse
  {
    if ($pageAsset->page_id !== $page->id) {
      return $this->validationError([
        ['path' => 'page_asset', 'message' => 'The page asset does not belong to this page.'],
      ]);
    }

    // The stored type is immutable here: changing css <-> js would also change
    // the required extension and load position, so callers delete and re-add.
    $type = $pageAsset->type;
    $path = $request->has('path') ? $request->input('path') : $pageAsset->path;
    $message = $this->pathValidator->validate($type, $path);

    if ($message !== null) {
      return $this->validationError([['path' => 'path', 'message' => $message]]);
    }

    DB::transaction(function () use ($request, $page, $pageAsset, $type, $path): void {
      $pageAsset->update($this->assetData($request, $type, $path, $pageAsset));

      $this->touchAndCaptureRevision($page, 'Page asset updated', 'A page asset was updated through the Internal Content API.');
    });

    return $this->ok(['page_asset' => $this->present($pageAsset->fresh())]);
  }

  public function destroy(Page $page, PageAsset $pageAsset): JsonResponse
  {
    if ($pageAsset->page_id !== $page->id) {
      return $this->validationError([
        ['path' => 'page_asset', 'message' => 'The page asset does not belong to this page.'],
      ]);
    }

    DB::transaction(function () use ($page, $pageAsset): void {
      $pageAsset->delete();

      $this->touchAndCaptureRevision($page, 'Page asset deleted', 'A page asset was deleted through the Internal Content API.');
    });

    return $this->ok(['message' => 'Deleted']);
  }

  private function assetData(Request $request, string $type, mixed $path, ?PageAsset $existing = null): array
  {
    $isJs = $type === PageAsset::TYPE_JS;

    return [
      'type' => $type,
      'path' => $this->pathValidator->normalizeForStorage($type, $path),
      'load_position' => PageAsset::defaultLoadPositionFor($type),
      'sort_order' => max((int) $request->input('sort_order', $existing?->sort_order ?? 0), 0),
      'is_enabled' => $request->boolean('is_enabled', $existing?->is_enabled ?? true),
      'is_defer' => $isJs ? $request->boolean('is_defer', $existing?->is_defer ?? true) : false,
      'is_async' => $isJs ? $request->boolean('is_async', $existing?->is_async ?? false) : false,
      'is_module' => $isJs ? $request->boolean('is_module', $existing?->is_module ?? false) : false,
    ];
  }

  private function present(PageAsset $asset): array
  {
    return [
      'id' => $asset->id,
      'type' => $asset->type,
      'path' => $asset->path,
      'load_position' => $asset->load_position,
      'is_defer' => (bool) $asset->is_defer,
      'is_async' => (bool) $asset->is_async,
      'is_module' => (bool) $asset->is_module,
      'is_enabled' => (bool) $asset->is_enabled,
      'sort_order' => (int) $asset->sort_order,
    ];
  }

  private function touchAndCaptureRevision(Page $page, string $label, string $reason): void
  {
    $page->forceFill(['updated_at' => now()])->save();

    $this->revisionManager->capture(
      $page->fresh(),
      null,
      $label,
      $reason,
      event: 'page_updated',
      source: 'internal-content-api',
    );
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
      'code' => 'invalid_page_asset',
      'message' => 'The page asset request could not be validated.',
      'warnings' => [],
      'errors' => $errors,
    ], 422);
  }
}
