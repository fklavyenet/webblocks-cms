<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;

class InternalContentResourceController extends Controller
{
  public function __construct(
    private readonly InternalContentApiPresenter $presenter,
  ) {}

  public function sites(): JsonResponse
  {
    $sites = Site::query()
      ->with(['locales' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name')])
      ->primaryFirst()
      ->orderBy('name')
      ->get()
      ->map(fn (Site $site) => $this->presenter->site($site))
      ->values();

    return $this->ok(['sites' => $sites]);
  }

  public function locales(): JsonResponse
  {
    $locales = Locale::query()
      ->orderByDesc('is_default')
      ->orderBy('name')
      ->get()
      ->map(fn (Locale $locale) => $this->presenter->locale($locale))
      ->values();

    return $this->ok(['locales' => $locales]);
  }

  public function pageLayouts(): JsonResponse
  {
    $layouts = PageLayout::query()
      ->with('layoutSlots.slotType')
      ->orderBy('sort_order')
      ->orderBy('name')
      ->get()
      ->map(fn (PageLayout $layout) => $this->presenter->pageLayout($layout))
      ->values();

    return $this->ok(['page_layouts' => $layouts]);
  }

  public function blockTypes(): JsonResponse
  {
    $blockTypes = BlockType::query()
      ->where('status', 'published')
      ->orderBy('sort_order')
      ->orderBy('name')
      ->get()
      ->map(fn (BlockType $blockType) => $this->presenter->blockType($blockType))
      ->values();

    return $this->ok(['block_types' => $blockTypes]);
  }

  public function pages(Request $request): JsonResponse
  {
    $pages = Page::query()
      ->with(['site.locales', 'translations.locale', 'slots.slotType'])
      ->withCount(['slots', 'blocks'])
      ->when($request->filled('site'), function ($query) use ($request) {
        $site = (string) $request->query('site');

        $query->whereHas('site', fn ($siteQuery) => is_numeric($site)
          ? $siteQuery->whereKey((int) $site)
          : $siteQuery->where('handle', $site));
      })
      ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->query('status')))
      ->orderByDesc('created_at')
      ->limit(min(max((int) $request->query('limit', 50), 1), 100))
      ->get()
      ->map(fn (Page $page) => $this->presenter->page($page))
      ->values();

    return $this->ok(['pages' => $pages]);
  }

  public function page(Page $page): JsonResponse
  {
    $page->load([
      'site.locales',
      'translations.locale',
      'slots.slotType',
      'blocks.blockType',
      'blocks.slotType',
      'blocks.textTranslations',
      'blocks.buttonTranslations',
      'blocks.imageTranslations',
    ]);

    return $this->ok(['page' => $this->presenter->page($page, true)]);
  }

  public function blocks(Request $request): JsonResponse
  {
    $blocks = Block::query()
      ->with(['blockType', 'slotType', 'textTranslations', 'buttonTranslations', 'imageTranslations'])
      ->when($request->filled('page'), fn ($query) => $query->where('page_id', (int) $request->query('page')))
      ->whereNull('parent_id')
      ->orderBy('sort_order')
      ->orderBy('id')
      ->limit(min(max((int) $request->query('limit', 100), 1), 200))
      ->get()
      ->map(fn (Block $block) => $this->presenter->block($block, false))
      ->values();

    return $this->ok(['blocks' => $blocks]);
  }

  public function block(Block $block): JsonResponse
  {
    $block->load([
      'blockType',
      'slotType',
      'textTranslations',
      'buttonTranslations',
      'imageTranslations',
      'children.blockType',
      'children.slotType',
      'children.textTranslations',
      'children.buttonTranslations',
      'children.imageTranslations',
    ]);

    return $this->ok(['block' => $this->presenter->block($block)]);
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
}
