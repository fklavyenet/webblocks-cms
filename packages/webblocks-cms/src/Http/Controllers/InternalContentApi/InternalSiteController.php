<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotSyncer;

class InternalSiteController extends Controller
{
  public function __construct(
    private readonly InternalContentApiPresenter $presenter,
    private readonly PageLayoutSlotSyncer $slotSyncer,
  ) {}

  public function updatePublicTheme(Request $request, Site $site): JsonResponse
  {
    if (! Schema::hasColumn('sites', 'public_theme_preset')) {
      return $this->validationError([
        ['path' => 'site.public_theme_preset', 'message' => 'Site public theme presets are not available until the latest site schema has been applied.'],
      ]);
    }

    $preset = trim((string) $request->input('public_theme_preset', $request->input('theme')));

    if (! in_array($preset, Site::PUBLIC_THEME_PRESETS, true)) {
      return $this->validationError([
        ['path' => 'site.public_theme_preset', 'message' => 'Public theme preset must be one of: '.implode(', ', Site::PUBLIC_THEME_PRESETS).'.'],
      ]);
    }

    $site->forceFill(['public_theme_preset' => $preset])->save();

    return response()->json([
      'ok' => true,
      'site' => $this->presenter->site($site->fresh(['locales'])),
      'writes' => [['type' => 'site_public_theme_preset', 'id' => $site->id]],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function syncPageLayoutSlots(Page $page): JsonResponse
  {
    $before = $page->slots()->count();
    $added = $this->slotSyncer->seedInitialSlots($page, $page->publicShellPreset());
    $page = $page->fresh(['site.locales', 'translations.locale', 'slots.slotType', 'slots.sharedSlot']);

    return response()->json([
      'ok' => true,
      'page' => $this->presenter->page($page),
      'added_count' => $added,
      'before_count' => $before,
      'after_count' => $page->slots->count(),
      'writes' => $added > 0 ? [['type' => 'page_layout_slots_sync', 'id' => $page->id]] : [],
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
