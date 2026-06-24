<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Support\Pages\PageDeleter;

class InternalContentResourceController extends Controller
{
  public function __construct(
    private readonly InternalContentApiPresenter $presenter,
    private readonly PageDeleter $pageDeleter,
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

  public function contentContract(): JsonResponse
  {
    $blockContracts = BlockType::query()
      ->where('status', 'published')
      ->orderBy('sort_order')
      ->orderBy('name')
      ->get()
      ->map(fn (BlockType $blockType) => $this->safeBlockContract($blockType))
      ->values();

    return $this->ok([
      'api' => [
        'prefix' => '/webadmin/api',
        'content_validate' => '/webadmin/api/content/validate',
        'content_apply' => '/webadmin/api/content/apply',
        'preview_url_template' => '/webadmin/pages/{page}/preview',
        'modes' => [
          'create_draft_page',
          'replace_existing_draft_page',
        ],
      ],
      'safety' => [
        'draft_only' => true,
        'apply_requires_explicit_user_approval' => true,
        'publishes' => false,
        'overwrites_existing_content' => false,
        'draft_slot_replacement' => true,
        'remote_fetch' => false,
        'media_import' => false,
      ],
      'draft_slot_replacement' => [
        'mode' => 'replace_existing_draft_page',
        'validate_url' => '/webadmin/api/content/validate',
        'apply_url' => '/webadmin/api/content/apply',
        'requires_capability' => 'content.apply',
        'requires_page_status' => Page::STATUS_DRAFT,
        'requires_safety_guard' => 'expected_path or expected_updated_at',
        'shared_slot_backed_slots' => 'rejected',
        'publishes' => false,
        'example' => [
          'plan' => [
            'mode' => 'replace_existing_draft_page',
            'site' => 'default',
            'locale' => 'en',
            'page' => [
              'id' => 123,
              'expected_path' => '/contact',
              'status' => 'draft',
            ],
            'replace_slots' => [
              'main' => [
                [
                  'type' => 'plain_text',
                  'translations' => [
                    'content' => 'Replacement draft content.',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'discovery' => [
        'sites' => '/webadmin/api/sites',
        'locales' => '/webadmin/api/locales',
        'page_layouts' => '/webadmin/api/page-layouts',
        'block_types' => '/webadmin/api/block-types',
        'navigation_menus' => '/webadmin/api/navigation-menus',
        'shared_slots' => '/webadmin/api/shared-slots',
      ],
      'recommended_patterns' => [
        'marketing_homepage' => [
          'section -> container -> hero',
          'section -> container -> grid -> card -> card_body',
          'section -> container -> cta',
        ],
        'avoid' => [
          'single rich-text blob for a full page',
          'trusted html fallback when structured blocks can represent the content',
          'full-width hero/cta without a container unless intentionally edge-to-edge',
        ],
      ],
      'block_contracts' => $blockContracts,
    ]);
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

  public function deletePage(Page $page): JsonResponse
  {
    $pageId = $page->id;

    $this->pageDeleter->delete($page);

    Log::info('Internal Content API page deleted.', [
      'page_id' => $pageId,
      'type' => 'page',
    ]);

    return response()->json([
      'ok' => true,
      'deleted' => [
        'type' => 'page',
        'id' => $pageId,
      ],
      'warnings' => [],
      'errors' => [],
    ]);
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

  private function safeBlockContract(BlockType $blockType): array
  {
    $blockTypePayload = $this->presenter->blockType($blockType);
    $contract = $blockTypePayload['contract'] ?? [];

    return [
      'handle' => $blockType->slug,
      'slug' => $blockType->slug,
      'label' => $blockType->name,
      'category' => $blockType->category,
      'status' => $blockType->status,
      'is_active' => $blockType->status === 'published',
      'source_type' => $blockType->source_type,
      'is_system' => (bool) $blockType->is_system,
      'is_container' => (bool) $blockType->is_container,
      'supports_children' => (bool) ($contract['supports_children'] ?? false),
      'allowed_child_handles' => $contract['allowed_child_type_slugs'] ?? null,
      'translatable_fields' => $contract['translatable_fields'] ?? [],
      'translation_family' => $contract['translation_family'] ?? null,
      'translation_family_fields' => $contract['translation_family_fields'] ?? [],
      'shared_settings_fields' => $contract['shared_settings_fields'] ?? [],
      'renderer_root_contract' => $contract['renderer_root_contract'] ?? null,
      'owns_public_root' => (bool) ($contract['owns_public_root_helper'] ?? false),
      'documented_contract' => (bool) ($contract['documented'] ?? false),
      'contract_status' => $contract['current_contract_status'] ?? null,
    ];
  }
}
