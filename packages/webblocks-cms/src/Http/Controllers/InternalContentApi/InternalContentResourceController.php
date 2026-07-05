<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\SharedSlotBlock;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Icons\IconCatalog;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Support\Media\MediaDeleter;
use WebBlocks\Cms\Support\Media\MediaInUseException;
use WebBlocks\Cms\Support\Media\MediaUploader;
use WebBlocks\Cms\Support\Media\RemoteMediaFetcher;
use WebBlocks\Cms\Support\Pages\PageDeleter;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;

class InternalContentResourceController extends Controller
{
  public function __construct(
    private readonly InternalContentApiPresenter $presenter,
    private readonly PageDeleter $pageDeleter,
    private readonly CmsApiTokenCapabilities $capabilities,
    private readonly MediaUploader $mediaUploader,
    private readonly RemoteMediaFetcher $remoteMediaFetcher,
    private readonly MediaDeleter $mediaDeleter,
    private readonly PluginBlockCatalog $pluginBlockCatalog,
  ) {}

  public function sites(): JsonResponse
  {
    $sites = Site::query()
      ->with([
        'faviconMedia',
        'locales' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name'),
        'socialImageMedia',
      ])
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

  public function storeLocale(Request $request): JsonResponse
  {
    $validator = Validator::make($request->all(), [
      'code' => ['required', 'string', 'regex:'.Locale::CODE_VALIDATION_PATTERN, 'unique:wbcms_locales,code'],
      'name' => ['required', 'string', 'max:255'],
      'is_default' => ['nullable', 'boolean'],
      'is_enabled' => ['nullable', 'boolean'],
    ]);

    if ($validator->fails()) {
      return $this->validationErrors('invalid_locale_payload', 'Locale payload is invalid.', $validator->errors()->toArray());
    }

    $data = $validator->validated();
    $isDefault = (bool) ($data['is_default'] ?? false);
    $isEnabled = array_key_exists('is_enabled', $data) ? (bool) $data['is_enabled'] : true;

    if ($isDefault && ! $isEnabled) {
      return $this->validationError('is_enabled', 'The default locale must stay enabled.', 'invalid_locale_payload');
    }

    $locale = DB::transaction(function () use ($data, $isDefault, $isEnabled): Locale {
      return Locale::query()->create([
        'code' => $data['code'],
        'name' => $data['name'],
        'is_default' => $isDefault,
        'is_enabled' => $isDefault || $isEnabled,
      ]);
    });

    return response()->json([
      'ok' => true,
      'locale' => $this->presenter->locale($locale->fresh()),
      'writes' => [['type' => 'locale', 'id' => $locale->id]],
      'warnings' => [],
      'errors' => [],
    ], 201);
  }

  public function updateLocale(Request $request, Locale $locale): JsonResponse
  {
    $validator = Validator::make($request->all(), [
      'code' => ['nullable', 'string', 'regex:'.Locale::CODE_VALIDATION_PATTERN, 'unique:wbcms_locales,code,'.$locale->id],
      'name' => ['nullable', 'string', 'max:255'],
      'is_default' => ['nullable', 'boolean'],
      'is_enabled' => ['nullable', 'boolean'],
    ]);

    if ($validator->fails()) {
      return $this->validationErrors('invalid_locale_payload', 'Locale payload is invalid.', $validator->errors()->toArray());
    }

    $data = $validator->validated();
    $willBeDefault = array_key_exists('is_default', $data)
      ? (bool) $data['is_default']
      : (bool) $locale->is_default;
    $willBeEnabled = array_key_exists('is_enabled', $data)
      ? (bool) $data['is_enabled']
      : (bool) $locale->is_enabled;

    if ($willBeDefault && ! $willBeEnabled) {
      return $this->validationError('is_enabled', 'The default locale must stay enabled.', 'invalid_locale_payload');
    }

    DB::transaction(function () use ($locale, $data, $willBeDefault, $willBeEnabled): void {
      $updates = [];

      foreach (['code', 'name'] as $field) {
        if (array_key_exists($field, $data)) {
          $updates[$field] = $data[$field];
        }
      }

      if (array_key_exists('is_default', $data)) {
        $updates['is_default'] = $willBeDefault;
      }

      if (array_key_exists('is_enabled', $data) || $willBeDefault) {
        $updates['is_enabled'] = $willBeDefault || $willBeEnabled;
      }

      if ($updates !== []) {
        $locale->fill($updates);
        $locale->save();
      }
    });

    return $this->ok([
      'locale' => $this->presenter->locale($locale->fresh()),
      'writes' => [['type' => 'locale', 'id' => $locale->id]],
    ]);
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
      ->pipe(fn ($blockTypes) => $this->pluginBlockCatalog->filterDiscoverableBlockTypes($blockTypes))
      ->map(fn (BlockType $blockType) => $this->apiBlockType($blockType))
      ->values();

    return $this->ok(['block_types' => $blockTypes]);
  }

  public function iconCatalog(Request $request, IconCatalog $iconCatalog): JsonResponse
  {
    $context = trim((string) $request->query('context', 'content'));
    $context = $context === '' ? 'content' : $context;

    if (! in_array($context, ['content', 'navigation'], true)) {
      return response()->json([
        'ok' => false,
        'code' => 'invalid_icon_catalog_context',
        'message' => 'Icon catalog context must be one of: content, navigation.',
        'warnings' => [],
        'errors' => [
          [
            'path' => 'context',
            'message' => 'Icon catalog context must be one of: content, navigation.',
          ],
        ],
      ], 422);
    }

    $icons = $iconCatalog->pickerOptions($context)
      ->map(fn (array $icon): array => [
        'slug' => $icon['slug'],
        'label' => $icon['label'],
        'context' => $context,
      ])
      ->values();

    return $this->ok([
      'context' => $context,
      'icons' => $icons,
      'count' => $icons->count(),
      '_links' => [
        'self' => '/webadmin/api/icon-catalog?context='.$context,
        'content' => '/webadmin/api/icon-catalog?context=content',
        'navigation' => '/webadmin/api/icon-catalog?context=navigation',
        'content_contract' => '/webadmin/api/content-contract',
      ],
    ]);
  }

  public function contentContract(): JsonResponse
  {
    $blockContracts = BlockType::query()
      ->where('status', 'published')
      ->orderBy('sort_order')
      ->orderBy('name')
      ->get()
      ->pipe(fn ($blockTypes) => $this->pluginBlockCatalog->filterDiscoverableBlockTypes($blockTypes))
      ->map(fn (BlockType $blockType) => $this->safeBlockContract($blockType))
      ->values();

    return $this->ok([
      'api' => [
        'prefix' => '/webadmin/api',
        'content_validate' => '/webadmin/api/content/validate',
        'content_apply' => '/webadmin/api/content/apply',
        'icon_catalog' => '/webadmin/api/icon-catalog?context=content',
        'media' => '/webadmin/api/media',
        'media_update' => '/webadmin/api/media/{media}',
        'block_update' => '/webadmin/api/blocks/{block}',
        'page_publish' => '/webadmin/api/pages/{page}/publish',
        'page_owned_blocks_publish' => '/webadmin/api/pages/{page}/publish-page-owned-blocks',
        'preview_url_template' => '/webadmin/pages/{page}/preview',
        'modes' => [
          'create_draft_page',
          'replace_existing_draft_page',
          'create_staged_update_for_published_page',
          'replace_staged_page_update',
          'promote_staged_page_update',
        ],
      ],
      'safety' => [
        'draft_only' => false,
        'apply_requires_explicit_user_approval' => true,
        'publishes' => false,
        'page_publish_default_includes_blocks' => false,
        'staged_updates_use_promote_not_page_publish' => true,
        'overwrites_existing_content' => false,
        'draft_slot_replacement' => true,
        'published_page_staged_updates' => true,
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
      'publishing' => [
        'publish_page_url_template' => '/webadmin/api/pages/{page}/publish',
        'publish_page_owned_blocks_url_template' => '/webadmin/api/pages/{page}/publish-page-owned-blocks',
        'requires_capability' => 'content.publish',
        'default_include_page_owned_blocks' => false,
        'include_page_owned_blocks_field' => 'include_page_owned_blocks',
        'shared_slot_cascade' => 'unsupported',
        'shared_slot_content' => 'excluded and must be reviewed separately',
        'staged_update_pages' => 'rejected; use content/apply mode promote_staged_page_update',
      ],
      'existing_block_updates' => [
        'update_url_template' => '/webadmin/api/blocks/{block}',
        'method' => 'PATCH',
        'requires_capability' => 'content.apply',
        'shared_slot_source_blocks_require_capability' => 'shared-slots.write',
        'purpose' => 'Update safe native fields on an existing structured block without changing the block tree.',
        'supported_native_fields' => [
          'media_id or asset_id for navbar-brand/sidebar-brand logo media',
          'media_id or asset_id for hero/section/card/cta/content_header/slide background media',
          'settings.url',
          'settings.target',
          'settings.aria_label',
          'settings.background_position',
          'settings.background_overlay',
          'translations.title',
          'translations.subtitle',
          'url',
          'variant',
        ],
        'rejected_fields' => [
          'parent_id',
          'slot_type_id',
          'block_type_id',
          'type',
          'sort_order',
          'children',
          'html',
          'remote_url',
          'source_url',
        ],
        'media_discovery' => 'Use GET /webadmin/api/media?kind=image before assigning logo media. Do not invent public file paths or use HTML fallback blocks for native logo fields.',
        'topology_changes' => 'Use content/validate and content/apply for creating, replacing, nesting, or reordering blocks.',
      ],
      'media_library' => [
        'index_url' => '/webadmin/api/media',
        'update_url_template' => '/webadmin/api/media/{media}',
        'read_requires_capability' => 'media.read',
        'read_transitional_capability' => 'content.read',
        'write_requires_capability' => 'media.write',
        'write_scope' => 'metadata-only',
        'supported_update_fields' => [
          'title',
          'alt_text',
          'caption',
          'description',
        ],
        'unsupported_operations' => [
          'upload files',
          'delete media',
          'replace binary files',
          'move folders',
          'change storage paths',
          'change mime type, kind, visibility, size, or dimensions',
          'fetch remote media',
        ],
      ],
      'locales' => [
        'index_url' => '/webadmin/api/locales',
        'create_url' => '/webadmin/api/locales',
        'update_url_template' => '/webadmin/api/locales/{locale}',
        'write_requires_capability' => 'site-settings.write',
        'supported_write_fields' => [
          'code',
          'name',
          'is_default',
          'is_enabled',
        ],
        'default_locale_behavior' => 'Default locales are forced enabled and demote the previous default through the normal CMS locale invariant.',
        'migration_note' => 'For language-only corrections on an existing install, PATCH the existing locale id so page, block, and site locale relations keep their ids.',
      ],
      'site_assets' => [
        'css_url_template' => '/webadmin/api/sites/{site}/assets/css',
        'js_url_template' => '/webadmin/api/sites/{site}/assets/js',
        'read_requires_capability' => 'site-assets.read',
        'write_requires_capability' => 'site-assets.write',
        'checksum_required' => true,
        'css_mode_policy' => [
          'Read asset.guidance and asset.analysis.mode_awareness before editing site.css.',
          'Keep site.css token-first and mode-aware so WebBlocks UI Light/Dark/Auto mode remains consistent.',
          'Prefer public theme custom properties, inherited wb-* component styling, and semantic site custom properties over raw light/dark colors.',
          'Use native block settings for content, media, background media, icon tones, and layout roles before adding CSS.',
          'If custom colors are unavoidable, define semantic variables with both light and dark values tied to active mode selectors or public theme tokens.',
          'Treat asset.analysis.mode_awareness.status = warning as work to review, report, or fix before considering a migration or new site setup complete.',
        ],
        'do_not_use' => [
          'hard-coded page-wide light backgrounds',
          'hard-coded dark text on surfaces that should follow tokens',
          'white card overrides that ignore dark mode',
          'one-off dark-mode palettes when public theme tokens can express the design',
        ],
      ],
      'published_page_staged_updates' => [
        'create_mode' => 'create_staged_update_for_published_page',
        'replace_mode' => 'replace_staged_page_update',
        'promote_mode' => 'promote_staged_page_update',
        'validate_url' => '/webadmin/api/content/validate',
        'apply_url' => '/webadmin/api/content/apply',
        'create_requires_capability' => 'content.apply',
        'replace_requires_capability' => 'content.apply',
        'promote_requires_capability' => 'content.apply + content.publish',
        'source_page_status' => Page::STATUS_PUBLISHED,
        'staged_page_status' => Page::STATUS_DRAFT,
        'source_public_route' => 'preserved until explicit promote',
        'staged_public_route' => 'not public because staged page remains draft',
        'preview_url_template' => '/webadmin/pages/{page}/preview',
        'requires_safety_guard' => 'expected_source_path or expected_source_updated_at',
        'shared_slot_backed_slots' => 'rejected for replace/promote',
        'promote_blocks_status' => 'promoted page-owned blocks are written as published',
        'shared_slot_cascade' => 'unsupported',
        'storage' => 'draft page with settings.staged_update metadata',
        'reuse_policy' => 'Only one active draft staged update is kept per published source page. Repeating create_staged_update_for_published_page for the same source returns the existing active staged draft with data.reused_staged_update=true; use replace_staged_page_update for later content revisions.',
        'wrong_endpoint_guard' => 'POST /webadmin/api/pages/{staged_page}/publish is rejected for staged updates. Use content/apply with mode promote_staged_page_update.',
        'promote_action_discovery' => 'GET /webadmin/api/pages/{staged_page} returns _actions.promote with the exact guarded payload.',
        'example' => [
          'create' => [
            'plan' => [
              'mode' => 'create_staged_update_for_published_page',
              'site' => 'default',
              'locale' => 'en',
              'page' => [
                'id' => 123,
              ],
              'expected_source_path' => '/docs',
              'managed_slots' => ['main'],
            ],
          ],
          'replace' => [
            'plan' => [
              'mode' => 'replace_staged_page_update',
              'staged_page_id' => 456,
              'expected_source_page_id' => 123,
              'expected_source_path' => '/docs',
              'replace_slots' => [
                'main' => [
                  [
                    'type' => 'plain_text',
                    'translations' => [
                      'content' => 'Replacement staged content.',
                    ],
                  ],
                ],
              ],
            ],
          ],
          'promote' => [
            'plan' => [
              'mode' => 'promote_staged_page_update',
              'staged_page_id' => 456,
              'expected_source_page_id' => 123,
              'expected_source_path' => '/docs',
              'promote_slots' => ['main'],
            ],
          ],
        ],
      ],
      'discovery' => [
        'sites' => '/webadmin/api/sites',
        'locales' => '/webadmin/api/locales',
        'page_layouts' => '/webadmin/api/page-layouts',
        'block_types' => '/webadmin/api/block-types',
        'media' => '/webadmin/api/media',
        'media_update' => '/webadmin/api/media/{media}',
        'block_update' => '/webadmin/api/blocks/{block}',
        'navigation_menus' => '/webadmin/api/navigation-menus',
        'shared_slots' => '/webadmin/api/shared-slots',
        'shared_slot_blocks_publish' => '/webadmin/api/shared-slots/{sharedSlot}/publish-blocks',
        'page_layout_slots_sync' => '/webadmin/api/pages/{page}/sync-layout-slots',
        'site_public_theme' => '/webadmin/api/sites/{site}/public-theme',
        'site_asset_css' => '/webadmin/api/sites/{site}/assets/css',
        'site_asset_js' => '/webadmin/api/sites/{site}/assets/js',
        'page_publish' => '/webadmin/api/pages/{page}/publish',
        'page_owned_blocks_publish' => '/webadmin/api/pages/{page}/publish-page-owned-blocks',
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

  public function page(Request $request, Page $page): JsonResponse
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

    $payload = $this->presenter->page($page, true);
    $actions = $this->stagedUpdateActions($request, $page);

    if ($actions !== []) {
      $payload['_actions'] = $actions;
    }

    return $this->ok(['page' => $payload]);
  }

  private function stagedUpdateActions(Request $request, Page $page): array
  {
    $metadata = $page->settings['staged_update'] ?? null;

    if (! is_array($metadata) || ($metadata['type'] ?? null) !== 'published_page_update') {
      return [];
    }

    $token = $request->attributes->get('cms_api_token');
    $canPromote = $this->capabilities->has($token, CmsApiTokenCapabilities::CONTENT_APPLY)
      && $this->capabilities->has($token, CmsApiTokenCapabilities::CONTENT_PUBLISH);
    $isActiveDraft = $page->status === Page::STATUS_DRAFT && ($metadata['state'] ?? null) === 'draft';
    $managedSlots = is_array($metadata['managed_slots'] ?? null)
      ? array_values(array_filter($metadata['managed_slots'], 'is_string'))
      : [];

    return [
      'promote' => [
        'method' => 'POST',
        'url' => '/webadmin/api/content/apply',
        'available' => $canPromote && $isActiveDraft,
        'required_capabilities' => [
          CmsApiTokenCapabilities::CONTENT_APPLY,
          CmsApiTokenCapabilities::CONTENT_PUBLISH,
        ],
        'required_state' => [
          'page_status' => Page::STATUS_DRAFT,
          'staged_update_state' => 'draft',
        ],
        'body' => [
          'plan' => [
            'mode' => 'promote_staged_page_update',
            'staged_page_id' => $page->id,
            'expected_source_page_id' => $metadata['source_page_id'] ?? null,
            'expected_source_path' => $metadata['source_path'] ?? null,
            'promote_slots' => $managedSlots,
          ],
        ],
      ],
      'page_publish' => [
        'available' => false,
        'method' => 'POST',
        'url' => '/webadmin/api/pages/'.$page->id.'/publish',
        'reason' => 'This page is a staged update. Use _actions.promote instead of page publish.',
      ],
    ];
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
      ->with(['blockType', 'slotType', 'media', 'textTranslations', 'buttonTranslations', 'imageTranslations'])
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
      'media',
      'textTranslations',
      'buttonTranslations',
      'imageTranslations',
      'children.blockType',
      'children.slotType',
      'children.media',
      'children.textTranslations',
      'children.buttonTranslations',
      'children.imageTranslations',
    ]);

    return $this->ok(['block' => $this->presenter->block($block)]);
  }

  public function media(Request $request): JsonResponse
  {
    if (! $this->hasAnyCapability($request, [CmsApiTokenCapabilities::MEDIA_READ, CmsApiTokenCapabilities::CONTENT_READ])) {
      return $this->capabilityError(CmsApiTokenCapabilities::MEDIA_READ, 'Reading Media Library records requires media.read.');
    }

    $media = Media::query()
      ->when($request->filled('kind'), fn ($query) => $query->where('kind', (string) $request->query('kind')))
      ->when($request->boolean('image_only'), fn ($query) => $query->where('kind', Media::KIND_IMAGE))
      ->when($request->filled('search'), function ($query) use ($request): void {
        $search = trim((string) $request->query('search'));

        $query->where(function ($searchQuery) use ($search): void {
          $searchQuery
            ->where('title', 'like', '%'.$search.'%')
            ->orWhere('filename', 'like', '%'.$search.'%')
            ->orWhere('original_name', 'like', '%'.$search.'%')
            ->orWhere('alt_text', 'like', '%'.$search.'%');
        });
      })
      ->orderByDesc('created_at')
      ->orderByDesc('id')
      ->limit(min(max((int) $request->query('limit', 50), 1), 100))
      ->get()
      ->map(fn (Media $media) => $this->presenter->media($media))
      ->values();

    return $this->ok(['media' => $media]);
  }

  public function updateMedia(Request $request, Media $media): JsonResponse
  {
    $blockedFields = array_values(array_intersect(array_keys($request->all()), [
      'id',
      'folder_id',
      'disk',
      'path',
      'filename',
      'original_name',
      'extension',
      'mime_type',
      'size',
      'kind',
      'visibility',
      'width',
      'height',
      'duration',
      'uploaded_by',
      'remote_url',
      'source_url',
      'file',
      'upload',
      'delete',
      'replace',
    ]));

    if ($blockedFields !== []) {
      return response()->json([
        'ok' => false,
        'code' => 'unsupported_media_update_fields',
        'message' => 'Media API updates may only change safe descriptive metadata.',
        'blocked_fields' => $blockedFields,
        'warnings' => [],
        'errors' => [
          [
            'path' => implode(',', $blockedFields),
            'message' => 'Use the browser admin Media Library for file replacement, upload, delete, storage, folder, or binary changes.',
          ],
        ],
      ], 422);
    }

    $allowedFields = ['title', 'alt_text', 'caption', 'description'];
    $payload = [];

    foreach ($allowedFields as $field) {
      if ($request->has($field)) {
        $payload[$field] = $this->normalizeNullableString($request->input($field), 2000);
      }
    }

    if ($payload === []) {
      return $this->validationError('media', 'Provide at least one safe media metadata field: title, alt_text, caption, or description.');
    }

    $media->fill($payload);
    $media->save();
    $media->refresh();

    return $this->ok(['media' => $this->presenter->media($media)]);
  }

  public function showMedia(Request $request, Media $media): JsonResponse
  {
    if (! $this->hasAnyCapability($request, [CmsApiTokenCapabilities::MEDIA_READ, CmsApiTokenCapabilities::CONTENT_READ])) {
      return $this->capabilityError(CmsApiTokenCapabilities::MEDIA_READ, 'Reading Media Library records requires media.read.');
    }

    return $this->ok([
      'media' => $this->presenter->media($media),
      'usages' => $media->usages()->values()->all(),
      'usage_count' => $media->usageCount(),
    ]);
  }

  public function storeMedia(Request $request): JsonResponse
  {
    $validator = Validator::make($request->all(), [
      'folder_id' => ['nullable', 'integer', 'exists:wbcms_media_folders,id'],
      'file' => $this->mediaFileRules(),
      'title' => ['nullable', 'string', 'max:255'],
      'alt_text' => ['nullable', 'string', 'max:255'],
      'caption' => ['nullable', 'string'],
      'description' => ['nullable', 'string'],
    ]);

    if ($validator->fails()) {
      return response()->json([
        'ok' => false,
        'code' => 'invalid_media_upload',
        'message' => 'Media upload failed validation.',
        'warnings' => [],
        'errors' => collect($validator->errors()->toArray())
          ->map(fn (array $messages, string $field) => [
            'path' => $field,
            'message' => $messages[0] ?? 'Invalid value.',
          ])
          ->values()
          ->all(),
      ], 422);
    }

    $media = $this->mediaUploader->upload($request->file('file'), $validator->validated());
    $media->refresh();

    return response()->json([
      'ok' => true,
      'media' => $this->presenter->media($media),
      'writes' => [['type' => 'media_upload', 'id' => $media->id]],
      'warnings' => [],
      'errors' => [],
    ], 201);
  }

  public function fetchRemoteMedia(Request $request): JsonResponse
  {
    $validator = Validator::make($request->all(), [
      'folder_id' => ['nullable', 'integer', 'exists:wbcms_media_folders,id'],
      'source_url' => ['required', 'url:http,https', 'max:2048'],
      'title' => ['nullable', 'string', 'max:255'],
      'alt_text' => ['nullable', 'string', 'max:255'],
      'caption' => ['nullable', 'string'],
      'description' => ['nullable', 'string'],
    ]);

    if ($validator->fails()) {
      return $this->validationErrors('invalid_remote_media_fetch', 'Remote media fetch failed validation.', $validator->errors()->toArray());
    }

    $data = $validator->validated();

    try {
      $media = $this->remoteMediaFetcher->fetch((string) $data['source_url'], $data);
    } catch (ConnectionException|\RuntimeException $exception) {
      return response()->json([
        'ok' => false,
        'code' => 'remote_media_fetch_failed',
        'message' => $exception->getMessage(),
        'warnings' => [],
        'errors' => [
          [
            'path' => 'source_url',
            'message' => $exception->getMessage(),
          ],
        ],
      ], 422);
    }

    $media->refresh();

    return response()->json([
      'ok' => true,
      'media' => $this->presenter->media($media),
      'writes' => [['type' => 'media_remote_fetch', 'id' => $media->id]],
      'warnings' => [],
      'errors' => [],
    ], 201);
  }

  public function replaceMedia(Request $request, Media $media): JsonResponse
  {
    $validator = Validator::make($request->all(), [
      'file' => $this->mediaFileRules(),
      'title' => ['nullable', 'string', 'max:255'],
      'alt_text' => ['nullable', 'string', 'max:255'],
      'caption' => ['nullable', 'string'],
      'description' => ['nullable', 'string'],
    ]);

    if ($validator->fails()) {
      return $this->validationErrors('invalid_media_replace', 'Media replace failed validation.', $validator->errors()->toArray());
    }

    $oldDisk = $media->disk;
    $oldPath = $media->path;
    $stored = $this->mediaUploader->storeFile($request->file('file'));

    if (($stored['kind'] ?? null) !== $media->kind) {
      Storage::disk((string) $stored['disk'])->delete((string) $stored['path']);

      return response()->json([
        'ok' => false,
        'code' => 'incompatible_media_replacement',
        'message' => 'Replacement media must keep the same media kind as the existing record.',
        'warnings' => [],
        'errors' => [
          [
            'path' => 'file',
            'message' => 'Expected '.$media->kind.' media, received '.($stored['kind'] ?? 'unknown').'.',
          ],
        ],
      ], 422);
    }

    $metadata = [];

    foreach (['title', 'alt_text', 'caption', 'description'] as $field) {
      if ($request->has($field)) {
        $metadata[$field] = $this->normalizeNullableString($request->input($field), $field === 'title' || $field === 'alt_text' ? 255 : 2000);
      }
    }

    $media->forceFill([
      ...$stored,
      ...$metadata,
    ])->save();

    Storage::disk($oldDisk)->delete($oldPath);
    $media->refresh();

    return $this->ok([
      'media' => $this->presenter->media($media),
      'usages' => $media->usages()->values()->all(),
      'usage_count' => $media->usageCount(),
      'writes' => [['type' => 'media_replace', 'id' => $media->id]],
    ]);
  }

  public function moveMedia(Request $request, Media $media): JsonResponse
  {
    $validator = Validator::make($request->all(), [
      'folder_id' => ['present', 'nullable', 'integer', 'exists:wbcms_media_folders,id'],
    ]);

    if ($validator->fails()) {
      return $this->validationErrors('invalid_media_move', 'Media move failed validation.', $validator->errors()->toArray());
    }

    $data = $validator->validated();

    $media->forceFill(['folder_id' => $data['folder_id'] ?? null])->save();
    $media->refresh()->load('folder');

    return $this->ok([
      'media' => $this->presenter->media($media),
      'writes' => [['type' => 'media_move', 'id' => $media->id]],
    ]);
  }

  public function deleteMedia(Media $media): JsonResponse
  {
    $payload = $this->presenter->media($media);

    try {
      $this->mediaDeleter->delete($media);
    } catch (MediaInUseException $exception) {
      return response()->json([
        'ok' => false,
        'code' => 'media_in_use',
        'message' => 'Media cannot be deleted because it is in use.',
        'usage_count' => $exception->usages()->count(),
        'usages' => $exception->usages()->values()->all(),
        'warnings' => [],
        'errors' => [
          [
            'path' => 'media',
            'message' => $exception->summary() ?: 'Media is in use.',
          ],
        ],
      ], 422);
    }

    return $this->ok([
      'deleted_media' => $payload,
      'writes' => [['type' => 'media_delete', 'id' => $payload['id']]],
    ]);
  }

  public function updateBlock(Request $request, Block $block): JsonResponse
  {
    $blockedFields = array_values(array_intersect(array_keys($request->all()), [
      'id',
      'page_id',
      'parent_id',
      'slot',
      'slot_type_id',
      'block_type_id',
      'type',
      'sort_order',
      'children',
      'html',
      'raw_html',
      'remote_url',
      'source_url',
    ]));

    if ($blockedFields !== []) {
      return response()->json([
        'ok' => false,
        'code' => 'unsupported_existing_block_update_fields',
        'message' => 'Existing block updates may only change safe content fields such as settings, translations, url, variant, and supported media_id.',
        'blocked_fields' => $blockedFields,
        'warnings' => [],
        'errors' => [
          [
            'path' => implode(',', $blockedFields),
            'message' => 'Use content/apply for block tree topology changes.',
          ],
        ],
      ], 422);
    }

    $sharedSlotBlock = SharedSlotBlock::query()
      ->with('sharedSlot')
      ->where('block_id', $block->id)
      ->first();

    if ($sharedSlotBlock && ! $this->hasCapability($request, CmsApiTokenCapabilities::SHARED_SLOTS_WRITE)) {
      return response()->json([
        'ok' => false,
        'code' => 'missing_internal_api_capability',
        'message' => 'Updating a Shared Slot source block requires shared-slots.write.',
        'required_capability' => CmsApiTokenCapabilities::SHARED_SLOTS_WRITE,
        'warnings' => [],
        'errors' => [
          [
            'path' => 'Authorization',
            'message' => 'The API token does not have the required capability for Shared Slot content.',
          ],
        ],
      ], 403);
    }

    $block->loadMissing(['blockType', 'textTranslations']);
    $type = (string) $block->typeSlug();
    $mediaId = $request->has('media_id') ? $request->input('media_id') : $request->input('asset_id');
    $mediaChanged = $request->has('media_id') || $request->has('asset_id');

    if ($mediaChanged) {
      $media = $mediaId ? Media::query()->find((int) $mediaId) : null;

      if ($mediaId && ! $media) {
        return $this->validationError('media_id', 'The selected media item does not exist.');
      }

      $allowedKinds = $this->directMediaKindsForBlockType($type);

      if ($allowedKinds === []) {
        return $this->validationError('media_id', 'This existing block update endpoint does not support direct media assignment for this block type.');
      }

      if ($media && ! in_array($media->kind, $allowedKinds, true)) {
        return $this->validationError('media_id', 'Selected media kind is not compatible with this block type.');
      }
    }

    $locale = $this->resolveLocale($request);
    $translations = $request->input('translations', []);

    if ($translations !== [] && ! is_array($translations)) {
      return $this->validationError('translations', 'Translations must be an object.');
    }

    $textTranslations = $this->normalizeTextTranslations($translations);
    $settings = $this->mergeSettings($block, $request);

    DB::transaction(function () use ($block, $request, $mediaChanged, $mediaId, $settings, $textTranslations, $locale): void {
      $updates = [];

      if ($mediaChanged) {
        $updates['media_id'] = $mediaId ? (int) $mediaId : null;
      }

      if ($request->has('url')) {
        $updates['url'] = $this->safeUrl($request->input('url'));
      }

      if ($request->has('variant')) {
        $updates['variant'] = trim((string) $request->input('variant')) ?: null;
      }

      if ($request->has('settings')) {
        $updates['settings'] = $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES);
      }

      if ($updates !== []) {
        $block->fill($updates);
        $block->save();
      }

      if ($textTranslations !== []) {
        BlockTextTranslation::query()->updateOrCreate(
          ['block_id' => $block->id, 'locale_id' => $locale->id],
          $textTranslations,
        );
      }
    });

    $block->refresh()->load([
      'blockType',
      'slotType',
      'media',
      'textTranslations',
      'buttonTranslations',
      'imageTranslations',
      'children.blockType',
      'children.slotType',
      'children.media',
      'children.textTranslations',
      'children.buttonTranslations',
      'children.imageTranslations',
    ]);

    return $this->ok([
      'block' => $this->presenter->block($block),
      'shared_slot' => $sharedSlotBlock?->sharedSlot ? [
        'id' => $sharedSlotBlock->sharedSlot->id,
        'handle' => $sharedSlotBlock->sharedSlot->handle,
      ] : null,
    ]);
  }

  private function hasCapability(Request $request, string $capability): bool
  {
    $token = $request->attributes->get('cms_api_token');

    return $token instanceof CmsApiToken && $this->capabilities->has($token, $capability);
  }

  private function hasAnyCapability(Request $request, array $capabilities): bool
  {
    foreach ($capabilities as $capability) {
      if ($this->hasCapability($request, $capability)) {
        return true;
      }
    }

    return false;
  }

  private function capabilityError(string $capability, string $message): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => 'missing_internal_api_capability',
      'message' => $message,
      'required_capability' => $capability,
      'warnings' => [],
      'errors' => [
        [
          'path' => 'Authorization',
          'message' => $message,
        ],
      ],
      'api_discovery_url' => '/webadmin/api',
      'openapi_url' => '/webadmin/api/openapi.json',
      'documentation_url' => '/docs/internal-content-api',
      'example_url' => '/webadmin/api/examples',
    ], 403);
  }

  private function normalizeNullableString(mixed $value, int $maxLength): ?string
  {
    if ($value === null) {
      return null;
    }

    $value = trim((string) $value);

    if ($value === '') {
      return null;
    }

    return mb_substr($value, 0, $maxLength);
  }

  private function validationErrors(string $code, string $message, array $errors): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => $code,
      'message' => $message,
      'warnings' => [],
      'errors' => collect($errors)
        ->map(fn (array $messages, string $field) => [
          'path' => $field,
          'message' => $messages[0] ?? 'Invalid value.',
        ])
        ->values()
        ->all(),
    ], 422);
  }

  private function mediaFileRules(): array
  {
    return [
      'required',
      'file',
      'max:51200',
      'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/svg+xml,video/mp4,video/webm,video/quicktime,application/pdf,text/plain,text/csv,application/msword,application/vnd.ms-excel,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/rtf,application/zip',
    ];
  }

  private function directMediaKindsForBlockType(string $type): array
  {
    return match ($type) {
      'image', 'navbar-brand', 'sidebar-brand' => [Media::KIND_IMAGE],
      'hero', 'section', 'card', 'cta', 'content_header', 'slide' => [Media::KIND_IMAGE],
      'download', 'file' => [Media::KIND_DOCUMENT, Media::KIND_OTHER],
      'video' => [Media::KIND_VIDEO],
      default => [],
    };
  }

  private function resolveLocale(Request $request): Locale
  {
    $locale = (string) $request->input('locale', $request->query('locale', ''));

    return Locale::query()
      ->when($locale !== '', fn ($query) => is_numeric($locale)
        ? $query->whereKey((int) $locale)
        : $query->where('code', $locale))
      ->first()
      ?? Locale::query()->where('is_default', true)->firstOrFail();
  }

  private function normalizeTextTranslations(array $translations): array
  {
    $text = is_array($translations['text'] ?? null) ? $translations['text'] : $translations;
    $allowed = ['title', 'eyebrow', 'subtitle', 'content', 'meta'];
    $payload = [];

    foreach ($allowed as $field) {
      if (array_key_exists($field, $text)) {
        $payload[$field] = is_array($text[$field])
          ? json_encode($text[$field], JSON_UNESCAPED_SLASHES)
          : trim((string) $text[$field]);
      }
    }

    return $payload;
  }

  private function mergeSettings(Block $block, Request $request): array
  {
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];

    if (! $request->has('settings')) {
      return $settings;
    }

    $incoming = $request->input('settings');

    if (! is_array($incoming)) {
      abort(response()->json([
        'ok' => false,
        'code' => 'invalid_settings',
        'message' => 'Settings must be an object.',
        'warnings' => [],
        'errors' => [
          [
            'path' => 'settings',
            'message' => 'Settings must be an object.',
          ],
        ],
      ], 422));
    }

    $type = (string) $block->typeSlug();
    $allowedSettings = ['url', 'target', 'aria_label'];

    if ($this->supportsBackgroundMediaBlockType($type)) {
      $allowedSettings[] = 'background_position';
      $allowedSettings[] = 'background_overlay';
    }

    if ($type === 'slider') {
      $allowedSettings = [
        ...$allowedSettings,
        'height',
        'min_height',
        'aspect_ratio',
        'transition',
        'interval_ms',
        'autoplay',
        'pause_on_hover',
        'show_arrows',
        'show_dots',
        'loop',
        'swipe',
        'keyboard',
        'overlay',
        'content_position',
        'content_width',
        'text_color',
        'background_fit',
      ];
    }

    if ($type === 'slide') {
      $allowedSettings = [
        ...$allowedSettings,
        'content_position',
        'content_width',
        'text_color',
        'background_fit',
      ];
    }

    if ($type === 'header-actions') {
      $allowedSettings = [
        ...$allowedSettings,
        'show_mode_toggle',
        'show_accent_toggle',
        'show_search',
      ];
    }

    $unsupported = array_values(array_diff(array_keys($incoming), $allowedSettings));

    if ($unsupported !== []) {
      abort(response()->json([
        'ok' => false,
        'code' => 'unsupported_block_settings_fields',
        'message' => 'Existing block updates may only change supported settings fields.',
        'blocked_fields' => array_map(fn (string $field) => 'settings.'.$field, $unsupported),
        'warnings' => [],
        'errors' => [
          [
            'path' => implode(',', array_map(fn (string $field) => 'settings.'.$field, $unsupported)),
            'message' => 'Use discovered block contract fields such as media_id for media-backed block visuals instead of unsupported settings keys.',
          ],
        ],
      ], 422));
    }

    $safeIncoming = [];

    if (array_key_exists('url', $incoming)) {
      $safeIncoming['url'] = $this->safeUrl($incoming['url']);
    }

    if (array_key_exists('target', $incoming)) {
      $safeIncoming['target'] = $incoming['target'] === '_blank' ? '_blank' : '_self';
    }

    if (array_key_exists('aria_label', $incoming)) {
      $safeIncoming['aria_label'] = trim((string) $incoming['aria_label']) ?: null;
    }

    if (array_key_exists('background_position', $incoming)) {
      $position = trim((string) $incoming['background_position']);
      $safeIncoming['background_position'] = in_array($position, ['top', 'bottom', 'left', 'right'], true)
        ? $position
        : null;
    }

    if (array_key_exists('background_overlay', $incoming)) {
      $overlay = trim((string) $incoming['background_overlay']);
      $safeIncoming['background_overlay'] = in_array($overlay, ['none', 'medium', 'strong'], true)
        ? $overlay
        : null;
    }

    if ($type === 'slider') {
      if (array_key_exists('height', $incoming)) {
        $height = trim((string) $incoming['height']);
        $safeIncoming['height'] = in_array($height, ['auto', 'fill', 'viewport', 'large', 'medium', 'small', 'custom'], true) ? $height : 'fill';
      }

      if (array_key_exists('min_height', $incoming)) {
        $minHeight = trim((string) $incoming['min_height']);
        $safeIncoming['min_height'] = preg_match('/^\d{2,4}(px|vh|svh|dvh)$/', $minHeight) === 1 ? $minHeight : null;
      }

      if (array_key_exists('aspect_ratio', $incoming)) {
        $aspectRatio = trim((string) $incoming['aspect_ratio']);
        $safeIncoming['aspect_ratio'] = in_array($aspectRatio, ['16/9', '4/3', '1/1'], true) ? $aspectRatio : null;
      }

      if (array_key_exists('transition', $incoming)) {
        $safeIncoming['transition'] = 'slide';
      }

      if (array_key_exists('interval_ms', $incoming)) {
        $safeIncoming['interval_ms'] = min(max((int) $incoming['interval_ms'], 1000), 30000);
      }

      foreach (['autoplay', 'pause_on_hover', 'show_arrows', 'show_dots', 'loop', 'swipe', 'keyboard'] as $booleanSetting) {
        if (array_key_exists($booleanSetting, $incoming)) {
          $safeIncoming[$booleanSetting] = filter_var($incoming[$booleanSetting], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
        }
      }

      if (array_key_exists('overlay', $incoming)) {
        $overlay = trim((string) $incoming['overlay']);
        $safeIncoming['overlay'] = in_array($overlay, ['soft', 'medium', 'dark', 'strong'], true) ? $overlay : null;
      }
    }

    if (in_array($type, ['slider', 'slide'], true)) {
      if (array_key_exists('content_position', $incoming)) {
        $contentPosition = trim((string) $incoming['content_position']);
        $safeIncoming['content_position'] = in_array($contentPosition, ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right'], true)
          ? $contentPosition
          : null;
      }

      if (array_key_exists('content_width', $incoming)) {
        $contentWidth = trim((string) $incoming['content_width']);
        $safeIncoming['content_width'] = in_array($contentWidth, ['narrow', 'wide', 'full'], true) ? $contentWidth : null;
      }

      if (array_key_exists('text_color', $incoming)) {
        $textColor = trim((string) $incoming['text_color']);
        $safeIncoming['text_color'] = in_array($textColor, ['light', 'dark'], true) ? $textColor : null;
      }

      if (array_key_exists('background_fit', $incoming)) {
        $safeIncoming['background_fit'] = trim((string) $incoming['background_fit']) === 'contain' ? 'contain' : null;
      }
    }

    if ($type === 'header-actions') {
      foreach (['show_mode_toggle', 'show_accent_toggle', 'show_search'] as $booleanSetting) {
        if (array_key_exists($booleanSetting, $incoming)) {
          $safeIncoming[$booleanSetting] = filter_var($incoming[$booleanSetting], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
        }
      }
    }

    return array_filter([
      ...$settings,
      ...$safeIncoming,
    ], fn ($value) => $value !== null && $value !== '');
  }

  private function supportsBackgroundMediaBlockType(string $type): bool
  {
    return in_array($type, ['hero', 'section', 'card', 'cta', 'content_header', 'slide'], true);
  }

  private function safeUrl(mixed $url): ?string
  {
    $url = trim((string) $url);

    if ($url === '') {
      return null;
    }

    if (str_starts_with($url, '/') || str_starts_with($url, '#') || preg_match('/^https?:\/\//i', $url) === 1) {
      return $url;
    }

    abort(response()->json([
      'ok' => false,
      'code' => 'unsafe_url',
      'message' => 'URL must be a safe internal path, anchor, or http(s) URL.',
      'warnings' => [],
      'errors' => [
        [
          'path' => 'url',
          'message' => 'URL must be a safe internal path, anchor, or http(s) URL.',
        ],
      ],
    ], 422));
  }

  private function validationError(string $path, string $message, string $code = 'invalid_existing_block_update'): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => $code,
      'message' => $message,
      'warnings' => [],
      'errors' => [
        [
          'path' => $path,
          'message' => $message,
        ],
      ],
    ], 422);
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

  private function apiBlockType(BlockType $blockType): array
  {
    $payload = $this->presenter->blockType($blockType);

    if ($blockType->slug === 'webblocks-commerce-buy-button') {
      $payload['settings_schema'] = $this->commerceBuyButtonSettingsSchema();
      $payload['commerce_products_url'] = '/webadmin/api/commerce/products';
    }

    return $payload;
  }

  private function safeBlockContract(BlockType $blockType): array
  {
    $blockTypePayload = $this->presenter->blockType($blockType);
    $contract = $blockTypePayload['contract'] ?? [];

    $payload = [
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

    if ($blockType->slug === 'contact_form') {
      $payload['settings_schema'] = [
        'recipient_email' => 'nullable email string; overrides the site and environment recipient fallback chain when present',
        'send_email_notification' => 'boolean; default true',
        'store_submissions' => 'boolean; always true in the native CMS contract',
      ];
      $payload['public_submit_endpoint'] = [
        'method' => 'POST',
        'path' => '/contact-messages',
        'route_name' => 'contact-messages.store',
        'csrf' => 'required for browser submissions',
      ];
      $payload['validation_rules'] = [
        'block_id' => 'required integer existing block id',
        'page_id' => 'nullable integer existing page id',
        'name' => 'required string max 255',
        'email' => 'required RFC email max 255',
        'subject' => 'nullable string max 255',
        'message' => 'required string',
        '_form_check_name' => 'renderer-generated signed metadata; not normal visitor input',
        'form_check_{token}' => 'renderer-generated anti-spam check field; not normal visitor input',
        'submitted_at' => 'required integer timestamp',
      ];
      $payload['spam_behavior'] = [
        'check_field' => 'renderer-generated form_check_{token} field signed by _form_check_name',
        'check_response' => 'generic success redirect without storing or notifying when the generated check field is filled or invalid',
        'classification' => 'stored submissions may be classified as spam from conservative commercial/link/repeat-IP signals',
      ];
      $payload['storage_behavior'] = 'Legitimate submissions are stored in contact_messages before email notification is attempted; notification status fields do not change the editorial message status.';
      $payload['notification_behavior'] = [
        'recipient_order' => ['block recipient_email', 'site contact_recipient_email', 'CONTACT_RECIPIENT_EMAIL', 'MAIL_FROM_ADDRESS'],
        'failure_detail' => 'safe redacted delivery error stored on the message',
      ];
      $payload['admin_review_behavior'] = 'Stored messages appear under /webadmin/contact-messages with editorial status, spam score/reasons, notification status, and safe failure detail.';
    }

    if ($blockType->slug === 'webblocks-commerce-buy-button') {
      $payload['settings_schema'] = $this->commerceBuyButtonSettingsSchema();
      $payload['commerce_products_url'] = '/webadmin/api/commerce/products';
      $payload['public_behavior'] = 'Renders a plugin-owned buy button that links visitors to the public Commerce checkout page for the selected active product.';
      $payload['validation_rules'] = [
        'settings.commerce_product_id' => 'required integer id from GET /webadmin/api/commerce/products',
        'settings.label' => 'nullable string max 80',
        'settings.show_price' => 'nullable boolean; default true',
        'settings.alignment' => 'nullable one of start, center, end',
      ];
    }

    return $payload;
  }

  private function commerceBuyButtonSettingsSchema(): array
  {
    return [
      'commerce_product_id' => 'required integer; use an active product id from GET /webadmin/api/commerce/products',
      'label' => 'nullable string max 80; default Buy Now',
      'show_price' => 'boolean; default true',
      'alignment' => 'start|center|end; default start',
    ];
  }
}
