<?php

namespace WebBlocks\Cms\Support\InternalContentApi;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockButtonTranslation;
use WebBlocks\Cms\Models\BlockContactFormTranslation;
use WebBlocks\Cms\Models\BlockImageTranslation;
use WebBlocks\Cms\Models\BlockMedia as BlockAsset;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageAsset;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockDeletionManager;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\Blocks\ManagedCtaSynchronizer;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeApiAuthoringPolicy;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotSyncer;
use WebBlocks\Cms\Support\Pages\PagePath;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;

class InternalContentPlanService
{
  public const MODE_CREATE_DRAFT_PAGE = 'create_draft_page';

  public const MODE_REPLACE_EXISTING_DRAFT_PAGE = 'replace_existing_draft_page';

  public const MODE_CREATE_STAGED_UPDATE = 'create_staged_update_for_published_page';

  public const MODE_REPLACE_STAGED_UPDATE = 'replace_staged_page_update';

  public const MODE_PROMOTE_STAGED_UPDATE = 'promote_staged_page_update';

  private const STAGED_UPDATE_TYPE = 'published_page_update';

  private const FORBIDDEN_KEYS = [
    'publish',
    'published',
    'published_at',
    'site_create',
    'create_site',
    'shared_slot',
    'media_import',
    'media_download',
    'remote_fetch',
    'fetch_url',
    'crawl',
    'crawler',
    'delete',
    'destroy',
    'replace',
    'overwrite',
  ];

  public const UNSUPPORTED_KEY_ERROR_CODE = 'unsupported_plan_fields';

  /**
   * Plan keys understood at the top level of every mode.
   *
   * A plan may be posted flat or wrapped in `plan`, and `create_restore_point`
   * rides alongside a flat plan for the controller to consume, so both stay
   * acceptable here even though this service never reads them itself.
   */
  private const SHARED_PLAN_KEYS = [
    'mode',
    'plan',
    'create_restore_point',
    'site',
    'site_handle',
    'site_id',
    'locale',
    'locale_id',
    'page',
    'source_sync',
  ];

  /**
   * Additional top-level plan keys accepted per mode.
   *
   * @var array<string, list<string>>
   */
  private const PLAN_KEYS_BY_MODE = [
    self::MODE_CREATE_DRAFT_PAGE => [
      'layout',
      'page_layout',
      'title',
      'path',
      'status',
      'slots',
      'navigation_menus',
      'shared_slots',
      'page_slot_shared_slots',
    ],
    self::MODE_REPLACE_EXISTING_DRAFT_PAGE => [
      'page_id',
      'expected_path',
      'expected_updated_at',
      'replace_slots',
    ],
    self::MODE_CREATE_STAGED_UPDATE => [
      'page_id',
      'source_page',
      'source_page_id',
      'expected_source_path',
      'expected_source_updated_at',
      'managed_slots',
    ],
    self::MODE_REPLACE_STAGED_UPDATE => [
      'page_id',
      'staged_page',
      'staged_page_id',
      'source_page',
      'expected_source_page_id',
      'expected_source_path',
      'expected_source_updated_at',
      'replace_slots',
    ],
    self::MODE_PROMOTE_STAGED_UPDATE => [
      'page_id',
      'staged_page',
      'staged_page_id',
      'source_page',
      'source_page_id',
      'expected_source_page_id',
      'expected_source_path',
      'expected_source_updated_at',
      'promote_slots',
      'managed_slots',
    ],
  ];

  /**
   * Keys accepted inside the `page` node, per mode.
   *
   * @var array<string, list<string>>
   */
  private const PAGE_NODE_KEYS_BY_MODE = [
    self::MODE_CREATE_DRAFT_PAGE => [
      'title',
      'path',
      'status',
      'layout',
      'settings',
      'source_sync',
    ],
    self::MODE_REPLACE_EXISTING_DRAFT_PAGE => [
      'id',
      'expected_path',
      'expected_updated_at',
      'settings',
      'source_sync',
    ],
    self::MODE_CREATE_STAGED_UPDATE => [
      'id',
      'expected_path',
      'settings',
      'source_sync',
    ],
    self::MODE_REPLACE_STAGED_UPDATE => [
      'id',
      'settings',
      'source_sync',
    ],
    self::MODE_PROMOTE_STAGED_UPDATE => [
      'id',
      'expected_path',
      'settings',
      'source_sync',
    ],
  ];

  /**
   * Keys accepted inside the staged-update reference nodes.
   *
   * @var array<string, list<string>>
   */
  private const REFERENCE_NODE_KEYS = [
    'source_page' => ['id', 'expected_path', 'expected_updated_at'],
    'staged_page' => ['id'],
  ];

  private const PLAN_MANAGED_RELATION_KEYS = [
    'id',
    'parent_id',
    'block_id',
    'slot_type_id',
    'block_type_id',
  ];

  private const CHILD_REQUIRED_BLOCK_TYPES = [
    'section',
    'container',
    'cluster',
    'grid',
    'slider',
    'card',
    'card_body',
    'card_footer',
    'sticky-navbar',
    'sidebar-navigation',
  ];

  private const TRANSLATABLE_FIELDS = [
    'title',
    'eyebrow',
    'subtitle',
    'content',
    'meta',
    'caption',
    'alt_text',
    'submit_label',
    'success_message',
  ];

  public function __construct(
    private readonly BlockPayloadWriter $blockPayloadWriter,
    private readonly BlockDeletionManager $blockDeletionManager,
    private readonly PageRevisionManager $pageRevisionManager,
    private readonly PageLayoutSlotSyncer $slotSyncer,
    private readonly InternalContentApiPresenter $presenter,
    private readonly InternalContentApiOperations $operations,
    private readonly BlockTypeApiAuthoringPolicy $apiAuthoringPolicy,
    private readonly ManagedCtaSynchronizer $managedCtaSynchronizer,
  ) {}

  public function validate(array $payload): InternalContentPlanResult
  {
    return $this->normalize($payload);
  }

  public function apply(array $payload): InternalContentPlanResult
  {
    $validated = $this->normalize($payload);

    if (! $validated->ok) {
      return $validated;
    }

    $plan = $validated->normalizedPlan;

    try {
      $applied = DB::transaction(function () use ($plan): array {
      $writes = [];
      $data = [];
      $page = null;
      $sharedSlotsByHandle = [];

      if ($plan['mode'] === self::MODE_CREATE_STAGED_UPDATE) {
        $sourcePage = Page::query()
          ->with([
            'site.locales',
            'translations.locale',
            'slots.slotType',
            'slots.sharedSlot',
            'pageAssets',
          ])
          ->find($plan['staged_update']['source_page']['id']);

        if (! $sourcePage) {
          throw new \InvalidArgumentException('Source page no longer resolves.');
        }

        $stagedPage = $this->findReusableStagedPage($sourcePage);
        $reusedStagedUpdate = $stagedPage !== null;
        $revision = null;

        if ($stagedPage) {
          $this->refreshReusableStagedMetadata($stagedPage, $sourcePage, $plan);
        } else {
          $stagedPage = $this->createStagedPage($sourcePage, $plan);
          $revision = $this->pageRevisionManager->capture(
            $stagedPage,
            label: 'Internal Content API staged update created',
            reason: 'A staged content update was created from published page #'.$sourcePage->id.'.',
            event: 'internal_content_api_staged_update_created',
            source: 'internal-content-api',
          );
        }

        $stagedPage = $stagedPage->fresh([
          'site.locales',
          'translations.locale',
          'slots.slotType',
          'slots.sharedSlot',
          'blocks.blockType',
          'blocks.slotType',
          'blocks.textTranslations',
          'blocks.buttonTranslations',
          'blocks.imageTranslations',
          'blocks.contactFormTranslations',
        ]);

        $writes[] = ['type' => 'staged_page_update', 'id' => $stagedPage->id];
        if ($revision) {
          $writes[] = ['type' => 'page_revision', 'id' => $revision->id];
        }
        $writes = [
          ...$writes,
          ...$stagedPage->blocks->map(fn (Block $block) => ['type' => 'block', 'id' => $block->id])->all(),
        ];
        $data['source_page'] = $this->presenter->page($sourcePage->fresh(['site.locales', 'translations.locale', 'slots.slotType', 'slots.sharedSlot']), false);
        $data['staged_page'] = $this->presenter->page($stagedPage, true);
        $data['preview_url'] = route('admin.pages.preview', $stagedPage, absolute: false);
        $data['reused_staged_update'] = $reusedStagedUpdate;

        return ['writes' => $writes, 'data' => $data];
      }

      if ($plan['mode'] === self::MODE_REPLACE_EXISTING_DRAFT_PAGE || $plan['mode'] === self::MODE_REPLACE_STAGED_UPDATE) {
        $page = Page::query()->with(['site.locales'])->find($plan['replace_page']['id']);

        if (! $page) {
          throw new \InvalidArgumentException('Replacement page no longer resolves.');
        }

        $this->pageRevisionManager->capture(
          $page->fresh(),
          label: $plan['mode'] === self::MODE_REPLACE_STAGED_UPDATE
            ? 'Pre Internal Content API staged update replacement'
            : 'Pre Internal Content API slot replacement',
          reason: $plan['mode'] === self::MODE_REPLACE_STAGED_UPDATE
            ? 'Staged page slot content was saved before API replacement.'
            : 'Existing draft page slot content was saved before API replacement.',
          event: $plan['mode'] === self::MODE_REPLACE_STAGED_UPDATE
            ? 'internal_content_api_staged_update_replace'
            : 'internal_content_api_replace',
          source: 'internal-content-api',
        );

        $slotTypes = SlotType::query()->whereIn('slug', array_keys($plan['replace_slots']))->get()->keyBy('slug');
        $deletedCount = 0;

        foreach ($plan['replace_slots'] as $slotSlug => $blocks) {
          $slotType = $slotTypes->get($slotSlug);

          if (! $slotType) {
            continue;
          }

          $topLevelBlocks = Block::query()
            ->where('page_id', $page->id)
            ->where('slot_type_id', $slotType->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

          foreach ($topLevelBlocks as $block) {
            foreach ($this->blockDeletionManager->recursiveDeleteOrder($block) as $deleteBlock) {
              $deleteBlock->delete();
              $deletedCount++;
            }
          }

          foreach (array_values($blocks) as $index => $blockPayload) {
            $this->createBlock($page, $slotType, $blockPayload, $plan['locale']['code'], null, $index);
          }
        }

        $this->persistPageSourceSync($page, $plan['page_settings']);
        $page->touch();

        $page = $page->fresh([
          'site.locales',
          'translations.locale',
          'slots.slotType',
          'slots.sharedSlot',
          'blocks.blockType',
          'blocks.slotType',
          'blocks.textTranslations',
          'blocks.buttonTranslations',
          'blocks.imageTranslations',
          'blocks.contactFormTranslations',
        ]);

        $revision = $this->pageRevisionManager->capture(
          $page,
          label: $plan['mode'] === self::MODE_REPLACE_STAGED_UPDATE
            ? 'Internal Content API staged update replacement'
            : 'Internal Content API slot replacement',
          reason: $plan['mode'] === self::MODE_REPLACE_STAGED_UPDATE
            ? 'Staged page-owned slot content was replaced through the Internal Content API.'
            : 'Existing draft page-owned slot content was replaced through the Internal Content API.',
          event: $plan['mode'] === self::MODE_REPLACE_STAGED_UPDATE
            ? 'internal_content_api_staged_update_replace'
            : 'internal_content_api_replace',
          source: 'internal-content-api',
        );

        $writes[] = ['type' => $plan['mode'] === self::MODE_REPLACE_STAGED_UPDATE ? 'staged_page_slot_replacement' : 'page_slot_replacement', 'id' => $page->id];
        $writes[] = ['type' => 'page_revision', 'id' => $revision->id];
        $writes[] = ['type' => 'deleted_block', 'count' => $deletedCount];
        $writes = [
          ...$writes,
          ...$page->blocks
            ->whereIn('slot', array_keys($plan['replace_slots']))
            ->map(fn (Block $block) => ['type' => 'block', 'id' => $block->id])
            ->values()
            ->all(),
        ];
        $data[$plan['mode'] === self::MODE_REPLACE_STAGED_UPDATE ? 'staged_page' : 'page'] = $this->presenter->page($page, true);

        return ['writes' => $writes, 'data' => $data];
      }

      if ($plan['mode'] === self::MODE_PROMOTE_STAGED_UPDATE) {
        $sourcePage = Page::query()
          ->with(['site.locales', 'translations.locale', 'slots.slotType', 'slots.sharedSlot'])
          ->lockForUpdate()
          ->find($plan['staged_update']['source_page']['id']);
        $stagedPage = Page::query()
          ->with(['site.locales', 'translations.locale', 'slots.slotType', 'slots.sharedSlot'])
          ->lockForUpdate()
          ->find($plan['staged_update']['staged_page']['id']);

        if (! $sourcePage || ! $stagedPage) {
          throw new \InvalidArgumentException('Source or staged page no longer resolves.');
        }

        $this->pageRevisionManager->capture(
          $sourcePage->fresh(),
          label: 'Pre Internal Content API staged update promote',
          reason: 'Published page state was saved before staged content promotion.',
          event: 'internal_content_api_staged_update_promote',
          source: 'internal-content-api',
        );

        $deletedCount = $this->promoteStagedSlots($sourcePage, $stagedPage, $plan['staged_update']['promote_slots']);
        $this->persistPageSourceSync($sourcePage, $plan['page_settings']);
        $sourcePage->forceFill([
          'status' => Page::STATUS_PUBLISHED,
          'published_at' => $sourcePage->published_at ?? now(),
        ])->save();

        $this->markStagedUpdatePromoted($stagedPage, $sourcePage);

        $sourcePage = $sourcePage->fresh([
          'site.locales',
          'translations.locale',
          'slots.slotType',
          'slots.sharedSlot',
          'blocks.blockType',
          'blocks.slotType',
          'blocks.textTranslations',
          'blocks.buttonTranslations',
          'blocks.imageTranslations',
          'blocks.contactFormTranslations',
        ]);

        $revision = $this->pageRevisionManager->capture(
          $sourcePage,
          label: 'Internal Content API staged update promoted',
          reason: 'Staged page-owned content was promoted onto the published page.',
          event: 'internal_content_api_staged_update_promote',
          source: 'internal-content-api',
        );

        $writes[] = ['type' => 'staged_page_update_promote', 'id' => $stagedPage->id];
        $writes[] = ['type' => 'page', 'id' => $sourcePage->id];
        $writes[] = ['type' => 'page_revision', 'id' => $revision->id];
        $writes[] = ['type' => 'deleted_block', 'count' => $deletedCount];
        $writes = [
          ...$writes,
          ...$sourcePage->blocks
            ->whereIn('slot', $plan['staged_update']['promote_slots'])
            ->map(fn (Block $block) => ['type' => 'block', 'id' => $block->id])
            ->values()
            ->all(),
        ];
        $data['page'] = $this->presenter->page($sourcePage, true);
        $data['staged_page'] = $this->presenter->page($stagedPage->fresh(['site.locales', 'translations.locale', 'slots.slotType', 'slots.sharedSlot']), false);

        return ['writes' => $writes, 'data' => $data];
      }

      if ($plan['page'] !== null) {
        $page = Page::query()->create([
          'site_id' => $plan['site']['id'],
          'page_type' => Page::TYPE_DEFAULT,
          'status' => Page::STATUS_DRAFT,
          'settings' => Page::supportsSettingsColumn()
            ? array_filter([
              'public_shell' => $plan['layout']['handle'],
              'source_sync' => $plan['page_settings']['source_sync'] ?? null,
            ], fn ($value) => $value !== null)
            : null,
        ]);

        PageTranslation::query()->create([
          'page_id' => $page->id,
          'site_id' => $page->site_id,
          'locale_id' => $plan['locale']['id'],
          'name' => $plan['page']['title'],
          'slug' => $plan['page']['slug'],
          'path' => $plan['page']['path'],
        ]);

        $this->slotSyncer->seedInitialSlots($page, $plan['layout']['handle']);

        $slotTypes = SlotType::query()->whereIn('slug', array_keys($plan['slots']))->get()->keyBy('slug');

        foreach ($plan['slots'] as $slotSlug => $blocks) {
          $slotType = $slotTypes->get($slotSlug);

          if (! $slotType) {
            continue;
          }

          foreach (array_values($blocks) as $index => $blockPayload) {
            $this->createBlock($page, $slotType, $blockPayload, $plan['locale']['code'], null, $index);
          }
        }

        $page = $page->fresh([
          'site.locales',
          'translations.locale',
          'slots.slotType',
          'blocks.blockType',
          'blocks.slotType',
          'blocks.textTranslations',
          'blocks.buttonTranslations',
          'blocks.imageTranslations',
          'blocks.contactFormTranslations',
        ]);

        $writes[] = ['type' => 'page', 'id' => $page->id];
        $writes = [
          ...$writes,
          ...$page->blocks->map(fn (Block $block) => ['type' => 'block', 'id' => $block->id])->all(),
        ];
        $data['page'] = $this->presenter->page($page, true);
      }

      foreach ($plan['navigation_menus'] as $menu) {
        $created = $this->operations->createNavigationMenu($menu);
        foreach ($created['items'] as $item) {
          $writes[] = ['type' => 'navigation_item', 'id' => $item->id];
        }
      }

      foreach ($plan['shared_slots'] as $sharedSlotPlan) {
        $sharedSlot = $this->operations->createSharedSlot($sharedSlotPlan, $plan['locale']['code']);
        $sharedSlotsByHandle[$sharedSlot->handle] = $sharedSlot;
        $writes[] = ['type' => 'shared_slot', 'id' => $sharedSlot->id];
      }

      foreach ($plan['page_slot_shared_slots'] as $assignment) {
        $targetPage = $assignment['page_id'] === '__created_page__' ? $page : Page::query()->find($assignment['page_id']);
        $sharedSlot = $sharedSlotsByHandle[$assignment['shared_slot']]
          ?? SharedSlot::query()->where('site_id', $targetPage?->site_id)->where('handle', $assignment['shared_slot'])->first();
        $errors = [];

        if (! $targetPage || ! $sharedSlot) {
          throw new \InvalidArgumentException('Page slot Shared Slot assignment references an unresolved page or Shared Slot.');
        }

        $pageSlot = $this->operations->assignSharedSlot($targetPage, $assignment['slot'], $sharedSlot, 'plan.page_slot_shared_slots', $errors);

        if ($errors !== [] || ! $pageSlot) {
          throw new \InvalidArgumentException($errors[0]['message'] ?? 'Page slot Shared Slot assignment failed.');
        }

        $writes[] = ['type' => 'page_slot_shared_slot', 'id' => $pageSlot->id];
      }

        return ['writes' => $writes, 'data' => $data];
      });
    } catch (\InvalidArgumentException $exception) {
      return new InternalContentPlanResult(
        ok: false,
        normalizedPlan: $plan,
        warnings: $validated->warnings,
        errors: [$this->error('plan', $exception->getMessage())],
        renderability: $validated->renderability,
      );
    } catch (\Throwable $exception) {
      report($exception);

      return new InternalContentPlanResult(
        ok: false,
        normalizedPlan: $plan,
        warnings: $validated->warnings,
        errors: [$this->error('plan.apply', 'Content apply failed while writing the normalized plan. Check application logs for the exception details.')],
        renderability: $validated->renderability,
      );
    }

    return new InternalContentPlanResult(
      ok: true,
      normalizedPlan: $plan,
      warnings: $validated->warnings,
      writes: $applied['writes'],
      data: $applied['data'],
      renderability: $validated->renderability,
    );
  }

  private function createBlock(Page $page, SlotType $slotType, array $payload, string $localeCode, ?Block $parent, int $sortOrder): Block
  {
    $blockType = BlockType::query()->where('slug', $payload['type'])->where('status', 'published')->firstOrFail();
    $settings = $payload['settings'] === [] ? null : json_encode($payload['settings'], JSON_UNESCAPED_SLASHES);
    $translations = $payload['translations'];
    $data = [
      'page_id' => $page->id,
      'parent_id' => $parent?->id,
      'block_type_id' => $blockType->id,
      'type' => $blockType->slug,
      'source_type' => $blockType->source_type ?: 'static',
      'slot_type_id' => $slotType->id,
      'slot' => $slotType->slug,
      'sort_order' => $sortOrder,
      'status' => 'draft',
      'is_system' => (bool) $blockType->is_system,
      'settings' => $settings,
      'variant' => $payload['variant'] ?? ($payload['settings']['variant'] ?? null),
      'url' => $payload['settings']['url'] ?? null,
      'media_id' => $payload['media_id'] ?? null,
      '_block_media' => $payload['_block_media'] ?? [],
      '_gallery_items' => $payload['_gallery_items'] ?? [],
      'title' => $translations['title'] ?? null,
      'eyebrow' => $translations['eyebrow'] ?? null,
      'subtitle' => $translations['subtitle'] ?? null,
      'content' => $translations['content'] ?? null,
      'meta' => $translations['meta'] ?? null,
      'submit_label' => $translations['submit_label'] ?? null,
      'success_message' => $translations['success_message'] ?? null,
    ];

    $block = $this->blockPayloadWriter->save(new Block, $page, $data, $localeCode);

    $this->managedCtaSynchronizer->sync($block, $payload['_managed_ctas'] ?? [], $localeCode);

    foreach (array_values($payload['children']) as $index => $childPayload) {
      $this->createBlock($page, $slotType, $childPayload, $localeCode, $block, $index);
    }

    return $block;
  }

  private function createStagedPage(Page $sourcePage, array $plan): Page
  {
    $sourcePage = Page::query()
      ->with([
        'site',
        'translations.locale',
        'slots.slotType',
        'slots.sharedSlot',
        'pageAssets',
        'blocks.blockAssets',
        'blocks.textTranslations',
        'blocks.buttonTranslations',
        'blocks.imageTranslations',
        'blocks.contactFormTranslations',
      ])
      ->lockForUpdate()
      ->findOrFail($sourcePage->id);

    $sourcePath = $this->pagePath($sourcePage);
    $defaultTitle = $sourcePage->defaultTranslation()?->name ?? $sourcePage->name ?? 'Staged update';
    $settings = is_array($sourcePage->settings) ? $sourcePage->settings : [];
    $settings['staged_update'] = [
      'type' => self::STAGED_UPDATE_TYPE,
      'source_page_id' => $sourcePage->id,
      'source_path' => $sourcePath,
      'source_updated_at' => $sourcePage->updated_at?->toIso8601String(),
      'state' => 'draft',
      'managed_slots' => $plan['staged_update']['managed_slots'],
      'created_at' => now()->toIso8601String(),
    ];

    if (isset($plan['page_settings']['source_sync'])) {
      $settings['source_sync'] = $plan['page_settings']['source_sync'];
    }

    $stagedPage = Page::query()->create([
      'site_id' => $sourcePage->site_id,
      'title' => $defaultTitle.' staged update',
      'page_type' => $sourcePage->page_type,
      'page_type_id' => $sourcePage->page_type_id,
      'layout_id' => $sourcePage->layout_id,
      'settings' => $settings,
      'status' => Page::STATUS_DRAFT,
      'published_at' => null,
      'review_requested_at' => null,
    ]);

    $stagedPage->translations()->delete();

    $basePath = '/staged-updates/page-'.$sourcePage->id.'/update-'.$stagedPage->id;
    foreach ($sourcePage->translations as $translation) {
      $path = $this->uniqueStagedPath($sourcePage, $translation->locale_id, $basePath);

      PageTranslation::query()->create([
        'page_id' => $stagedPage->id,
        'site_id' => $sourcePage->site_id,
        'locale_id' => $translation->locale_id,
        'name' => $translation->name.' staged update',
        'slug' => PagePath::slugFromPath($path),
        'path' => $path,
        'seo_title' => $translation->seo_title,
        'seo_description' => $translation->seo_description,
        'seo_keywords' => $translation->seo_keywords,
        'og_title' => $translation->og_title,
        'og_description' => $translation->og_description,
        'og_image_media_id' => $translation->og_image_media_id,
      ]);
    }

    foreach ($sourcePage->slots as $slot) {
      PageSlot::query()->create([
        'page_id' => $stagedPage->id,
        'slot_type_id' => $slot->slot_type_id,
        'source_type' => $slot->runtimeSourceType(),
        'shared_slot_id' => $slot->shared_slot_id,
        'sort_order' => $slot->sort_order,
        'settings' => PageSlot::sanitizeSettings($slot->settings),
      ]);
    }

    foreach ($sourcePage->pageAssets as $pageAsset) {
      PageAsset::query()->create([
        'page_id' => $stagedPage->id,
        'type' => $pageAsset->type,
        'path' => $pageAsset->path,
        'load_position' => $pageAsset->load_position,
        'is_defer' => $pageAsset->is_defer,
        'is_async' => $pageAsset->is_async,
        'is_module' => $pageAsset->is_module,
        'is_enabled' => $pageAsset->is_enabled,
        'sort_order' => $pageAsset->sort_order,
      ]);
    }

    $this->cloneBlocks($sourcePage, $stagedPage);

    return $stagedPage;
  }

  private function findReusableStagedPage(Page $sourcePage): ?Page
  {
    return Page::query()
      ->where('site_id', $sourcePage->site_id)
      ->where('status', Page::STATUS_DRAFT)
      ->where('settings->staged_update->type', self::STAGED_UPDATE_TYPE)
      ->where('settings->staged_update->source_page_id', $sourcePage->id)
      ->where('settings->staged_update->state', 'draft')
      ->orderByDesc('id')
      ->lockForUpdate()
      ->first();
  }

  private function refreshReusableStagedMetadata(Page $stagedPage, Page $sourcePage, array $plan): void
  {
    $settings = is_array($stagedPage->settings) ? $stagedPage->settings : [];
    $metadata = is_array($settings['staged_update'] ?? null) ? $settings['staged_update'] : [];

    $settings['staged_update'] = array_merge($metadata, [
      'type' => self::STAGED_UPDATE_TYPE,
      'source_page_id' => $sourcePage->id,
      'source_path' => $this->pagePath($sourcePage),
      'source_updated_at' => $sourcePage->updated_at?->toIso8601String(),
      'state' => 'draft',
      'managed_slots' => $plan['staged_update']['managed_slots'],
      'reused_at' => now()->toIso8601String(),
    ]);

    if (isset($plan['page_settings']['source_sync'])) {
      $settings['source_sync'] = $plan['page_settings']['source_sync'];
    }

    $stagedPage->forceFill(['settings' => $settings])->save();
  }

  private function promoteStagedSlots(Page $sourcePage, Page $stagedPage, array $slotSlugs): int
  {
    $slotTypes = SlotType::query()->whereIn('slug', $slotSlugs)->get()->keyBy('slug');
    $deletedCount = 0;

    foreach ($slotSlugs as $slotSlug) {
      $slotType = $slotTypes->get($slotSlug);

      if (! $slotType) {
        continue;
      }

      $topLevelBlocks = Block::query()
        ->where('page_id', $sourcePage->id)
        ->where('slot_type_id', $slotType->id)
        ->whereNull('parent_id')
        ->orderBy('sort_order')
        ->orderBy('id')
        ->lockForUpdate()
        ->get();

      foreach ($topLevelBlocks as $block) {
        foreach ($this->blockDeletionManager->recursiveDeleteOrder($block) as $deleteBlock) {
          $deleteBlock->delete();
          $deletedCount++;
        }
      }

      $this->cloneBlocks($stagedPage, $sourcePage, [$slotType->id], 'published');
    }

    return $deletedCount;
  }

  private function cloneBlocks(Page $sourcePage, Page $targetPage, ?array $slotTypeIds = null, ?string $forcedStatus = null): void
  {
    $blocks = Block::query()
      ->where('page_id', $sourcePage->id)
      ->when($slotTypeIds !== null, fn ($query) => $query->whereIn('slot_type_id', $slotTypeIds))
      ->with(['blockAssets', 'textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])
      ->orderBy('id')
      ->lockForUpdate()
      ->get();
    $blockMap = [];

    foreach ($blocks as $block) {
      $attributes = Arr::except($block->getAttributes(), ['id', 'parent_id', 'page_id', 'created_at', 'updated_at']);
      $attributes['page_id'] = $targetPage->id;
      $attributes['parent_id'] = null;

      if ($forcedStatus !== null) {
        $attributes['status'] = $forcedStatus;
      }

      $newBlock = Block::query()->create($attributes);
      $blockMap[$block->id] = $newBlock->id;

      foreach ($block->blockAssets as $blockAsset) {
        BlockAsset::query()->create([
          'block_id' => $newBlock->id,
          'media_id' => $blockAsset->media_id,
          'role' => $blockAsset->role,
          'position' => $blockAsset->position,
        ]);
      }

      $this->cloneBlockTranslations($block, $newBlock);
    }

    foreach ($blocks as $block) {
      if (! $block->parent_id || ! isset($blockMap[$block->id], $blockMap[$block->parent_id])) {
        continue;
      }

      Block::query()->whereKey($blockMap[$block->id])->update([
        'parent_id' => $blockMap[$block->parent_id],
      ]);
    }
  }

  private function cloneBlockTranslations(Block $source, Block $target): void
  {
    foreach ($source->textTranslations as $translation) {
      BlockTextTranslation::query()->create([
        'block_id' => $target->id,
        'locale_id' => $translation->locale_id,
        'title' => $translation->title,
        'eyebrow' => $translation->eyebrow,
        'subtitle' => $translation->subtitle,
        'content' => $translation->content,
        'meta' => $translation->meta,
      ]);
    }

    foreach ($source->buttonTranslations as $translation) {
      BlockButtonTranslation::query()->create([
        'block_id' => $target->id,
        'locale_id' => $translation->locale_id,
        'title' => $translation->title,
      ]);
    }

    foreach ($source->imageTranslations as $translation) {
      BlockImageTranslation::query()->create([
        'block_id' => $target->id,
        'locale_id' => $translation->locale_id,
        'caption' => $translation->caption,
        'alt_text' => $translation->alt_text,
      ]);
    }

    foreach ($source->contactFormTranslations as $translation) {
      BlockContactFormTranslation::query()->create([
        'block_id' => $target->id,
        'locale_id' => $translation->locale_id,
        'title' => $translation->title,
        'content' => $translation->content,
        'submit_label' => $translation->submit_label,
        'success_message' => $translation->success_message,
      ]);
    }
  }

  private function markStagedUpdatePromoted(Page $stagedPage, Page $sourcePage): void
  {
    $settings = is_array($stagedPage->settings) ? $stagedPage->settings : [];
    $metadata = is_array($settings['staged_update'] ?? null) ? $settings['staged_update'] : [];
    $metadata['state'] = 'promoted';
    $metadata['promoted_at'] = now()->toIso8601String();
    $metadata['promoted_to_page_id'] = $sourcePage->id;
    $settings['staged_update'] = $metadata;

    $stagedPage->forceFill([
      'settings' => $settings,
      'status' => Page::STATUS_ARCHIVED,
    ])->save();
  }

  private function normalize(array $payload): InternalContentPlanResult
  {
    $input = is_array($payload['plan'] ?? null) ? $payload['plan'] : $payload;
    $errors = [];
    $warnings = [];
    $mode = trim((string) data_get($input, 'mode', self::MODE_CREATE_DRAFT_PAGE));

    // An unrecognised mode still falls through to draft-page creation below, so
    // the key check is made against the mode that actually runs.
    $this->rejectUnsupportedKeys(
      $input,
      array_key_exists($mode, self::PLAN_KEYS_BY_MODE) ? $mode : self::MODE_CREATE_DRAFT_PAGE,
      $errors,
    );

    if ($mode === self::MODE_REPLACE_EXISTING_DRAFT_PAGE) {
      return $this->normalizeDraftPageReplacement($input, $errors, $warnings);
    }

    if ($mode === self::MODE_CREATE_STAGED_UPDATE) {
      return $this->normalizeCreateStagedUpdate($input, $errors, $warnings);
    }

    if ($mode === self::MODE_REPLACE_STAGED_UPDATE) {
      return $this->normalizeReplaceStagedUpdate($input, $errors, $warnings);
    }

    if ($mode === self::MODE_PROMOTE_STAGED_UPDATE) {
      return $this->normalizePromoteStagedUpdate($input, $errors, $warnings);
    }

    $this->rejectForbiddenKeys($input, 'plan', $errors);

    $hasPagePlan = is_array(data_get($input, 'page')) || is_array(data_get($input, 'slots')) || data_get($input, 'path') || data_get($input, 'title');
    $status = strtolower(trim((string) data_get($input, 'page.status', data_get($input, 'status', 'draft'))));
    if ($status !== '' && $status !== Page::STATUS_DRAFT) {
      $errors[] = $this->error('plan.page.status', 'Phase 1 can only create draft pages.');
    }

    $site = $this->resolveSite($input, $errors);
    $locale = $this->resolveLocale($input, $site, $errors);
    $layout = $hasPagePlan ? $this->resolveLayout($input, $errors) : null;
    $title = $hasPagePlan ? trim((string) data_get($input, 'page.title', data_get($input, 'title', ''))) : '';
    $rawPath = $hasPagePlan ? trim((string) data_get($input, 'page.path', data_get($input, 'path', ''))) : '';
    $path = '';
    $slug = '';

    if ($hasPagePlan && $rawPath !== '') {
      try {
        $path = PagePath::canonicalize($rawPath);
        $slug = PagePath::slugFromPath($path);

        if (PagePath::isReserved($path)) {
          $errors[] = $this->error('plan.page.path', 'Page path is reserved by CMS or host routes.');
        }
      } catch (\InvalidArgumentException $exception) {
        $errors[] = $this->error('plan.page.path', $exception->getMessage());
      }
    }

    if ($hasPagePlan && $title === '') {
      $errors[] = $this->error('plan.page.title', 'Page title is required.');
    }

    if ($hasPagePlan && $slug === '') {
      $errors[] = $this->error('plan.page.path', 'Page path is required.');
    }

    if ($hasPagePlan && $site && $locale && $path !== '' && PageTranslation::query()
      ->where('site_id', $site->id)
      ->where('locale_id', $locale->id)
      ->where('path', $path)
      ->exists()) {
      $errors[] = $this->error('plan.page.path', 'A page already exists at this path for the selected site and locale.');
    }

    $slots = $hasPagePlan ? $this->normalizeSlots($input, $layout, $errors, $warnings) : [];
    $navigationMenus = $this->normalizeNavigationMenus($input, $site, $errors);
    $sharedSlots = $this->normalizeSharedSlots($input, $site, $errors, $warnings);
    $pageSlotSharedSlots = $this->normalizePageSlotSharedSlots($input, $site, $hasPagePlan, $errors);
    $pageSettings = $hasPagePlan ? $this->normalizePageSettings($input, $errors) : [];

    $normalized = [
      'mode' => self::MODE_CREATE_DRAFT_PAGE,
      'site' => $site ? ['id' => $site->id, 'handle' => $site->handle] : null,
      'locale' => $locale ? ['id' => $locale->id, 'code' => $locale->code] : null,
      'layout' => $layout ? ['id' => $layout->id, 'handle' => $layout->handle] : null,
      'replace_page' => null,
      'replace_slots' => [],
      'staged_update' => null,
      'page_settings' => $pageSettings,
      'page' => $hasPagePlan ? [
        'title' => $title,
        'path' => $path,
        'slug' => $slug,
        'status' => Page::STATUS_DRAFT,
      ] : null,
      'slots' => $slots,
      'navigation_menus' => $navigationMenus,
      'shared_slots' => $sharedSlots,
      'page_slot_shared_slots' => $pageSlotSharedSlots,
    ];

    return new InternalContentPlanResult(
      ok: $errors === [],
      normalizedPlan: $normalized,
      warnings: $warnings,
      errors: $errors,
      renderability: $this->summarizeRenderability($normalized),
    );
  }

  /**
   * Resolves the page referenced by a staged-update plan so the human-only
   * block policy can inspect the scope before any write happens.
   */
  private function stagedSourcePageFrom(array $input): ?Page
  {
    $pageId = data_get($input, 'page.id', data_get($input, 'page_id'));

    return is_numeric($pageId) ? Page::query()->find((int) $pageId) : null;
  }

  /**
   * True when the page owns a block the API may not author, such as Trusted
   * HTML. Staged copies of the page are included because promoting or
   * replacing them would move that block through the API.
   */
  private function pageHasHumanOnlyBlock(Page $page): bool
  {
    $pageIds = Page::query()
      ->where('settings->staged_update->source_page_id', $page->id)
      ->pluck('id')
      ->map(fn ($id): int => (int) $id)
      ->push($page->id)
      ->unique()
      ->values()
      ->all();

    return $this->apiAuthoringPolicy->scopeHasHumanOnlyBlock(
      Block::query()->whereIn('page_id', $pageIds)->get(['id', 'type', 'block_type_id'])
    );
  }

  private function normalizeDraftPageReplacement(array $input, array &$errors, array &$warnings): InternalContentPlanResult
  {
    $this->rejectForbiddenKeys($input, 'plan', $errors, [
      'mode',
      'replace_slots',
    ]);

    $pageId = data_get($input, 'page.id', data_get($input, 'page_id'));
    $page = is_numeric($pageId)
      ? Page::query()->with(['site.locales', 'translations.locale', 'slots.slotType', 'slots.sharedSlot'])->find((int) $pageId)
      : null;

    if (! $page) {
      $errors[] = $this->error('plan.page.id', 'Existing draft page ID must resolve.');
    }

    if ($page && $page->status !== Page::STATUS_DRAFT) {
      $errors[] = $this->error('plan.page.status', 'Existing page replacement is draft-only. Published pages are not supported.');
    }

    if ($page && $this->pageHasHumanOnlyBlock($page)) {
      $errors[] = $this->apiAuthoringPolicy->error('plan.page.blocks');
    }

    $site = null;
    $siteValue = data_get($input, 'site', data_get($input, 'site_handle', data_get($input, 'site_id')));
    if ($siteValue !== null && $siteValue !== '') {
      $site = $this->resolveSite($input, $errors);

      if ($page && $site && (int) $page->site_id !== (int) $site->id) {
        $errors[] = $this->error('plan.site', 'Site must match the existing page site.');
      }
    } elseif ($page) {
      $site = $page->site;
    }

    $locale = $this->resolveReplacementLocale($input, $page, $site, $errors);

    $rawExpectedPath = trim((string) data_get($input, 'page.expected_path', data_get($input, 'expected_path', '')));
    $expectedPath = '';
    if ($rawExpectedPath !== '') {
      try {
        $expectedPath = PagePath::canonicalize($rawExpectedPath);
      } catch (\InvalidArgumentException $exception) {
        $errors[] = $this->error('plan.page.expected_path', $exception->getMessage());
      }
    }
    if ($page && $locale && $expectedPath !== '') {
      $translation = $page->translations->first(fn (PageTranslation $translation) => (int) $translation->locale_id === (int) $locale->id);
      $actualPath = $translation?->path ?: PageTranslation::pathFromSlug($translation?->slug ?? $page->slug ?? '');

      if ($actualPath !== $expectedPath) {
        $errors[] = $this->error('plan.page.expected_path', 'Expected path does not match the existing page translation.');
      }
    }

    $expectedUpdatedAt = trim((string) data_get($input, 'page.expected_updated_at', data_get($input, 'expected_updated_at', '')));
    if ($page && $expectedUpdatedAt !== '') {
      try {
        if (! $page->updated_at || ! $page->updated_at->equalTo(Carbon::parse($expectedUpdatedAt))) {
          $errors[] = $this->error('plan.page.expected_updated_at', 'Expected updated_at does not match the existing page.');
        }
      } catch (\Throwable) {
        $errors[] = $this->error('plan.page.expected_updated_at', 'Expected updated_at must be a valid date-time string.');
      }
    }

    if ($expectedPath === '' && $expectedUpdatedAt === '') {
      $errors[] = $this->error('plan.page', 'Existing page replacement requires expected_path or expected_updated_at.');
    }

    $replaceSlots = $this->normalizeReplacementSlots($input, $page, $errors, $warnings);
    $pageSettings = $this->normalizePageSettings($input, $errors);

    $normalized = [
      'mode' => self::MODE_REPLACE_EXISTING_DRAFT_PAGE,
      'site' => $site ? ['id' => $site->id, 'handle' => $site->handle] : null,
      'locale' => $locale ? ['id' => $locale->id, 'code' => $locale->code] : null,
      'layout' => null,
      'page_settings' => $pageSettings,
      'replace_page' => $page ? [
        'id' => $page->id,
        'status' => $page->status,
        'expected_path' => $expectedPath,
        'expected_updated_at' => $expectedUpdatedAt,
      ] : null,
      'replace_slots' => $replaceSlots,
      'staged_update' => null,
      'page' => null,
      'slots' => [],
      'navigation_menus' => [],
      'shared_slots' => [],
      'page_slot_shared_slots' => [],
    ];

    return new InternalContentPlanResult(
      ok: $errors === [],
      normalizedPlan: $normalized,
      warnings: $warnings,
      errors: $errors,
      renderability: $this->summarizeRenderability($normalized),
    );
  }

  private function normalizeCreateStagedUpdate(array $input, array &$errors, array &$warnings): InternalContentPlanResult
  {
    $stagedSourcePage = $this->stagedSourcePageFrom($input);

    if ($stagedSourcePage && $this->pageHasHumanOnlyBlock($stagedSourcePage)) {
      $errors[] = $this->apiAuthoringPolicy->error('plan.page.blocks');
    }

    $this->rejectForbiddenKeys($input, 'plan', $errors, [
      'mode',
    ]);

    $sourcePage = $this->resolveSourcePublishedPage($input, $errors);
    $site = $sourcePage?->site;
    $locale = $this->resolveReplacementLocale($input, $sourcePage, $site, $errors);
    $pageSettings = $this->normalizePageSettings($input, $errors);

    $managedSlots = $this->managedSlotsForStagedUpdate($input, $sourcePage, $errors);

    $normalized = [
      'mode' => self::MODE_CREATE_STAGED_UPDATE,
      'site' => $site ? ['id' => $site->id, 'handle' => $site->handle] : null,
      'locale' => $locale ? ['id' => $locale->id, 'code' => $locale->code] : null,
      'layout' => null,
      'page_settings' => $pageSettings,
      'replace_page' => null,
      'replace_slots' => [],
      'staged_update' => [
        'source_page' => $sourcePage ? [
          'id' => $sourcePage->id,
          'status' => $sourcePage->status,
          'expected_path' => $this->safeExpectedSourcePath($input),
          'expected_updated_at' => $this->expectedSourceUpdatedAt($input),
        ] : null,
        'staged_page' => null,
        'managed_slots' => $managedSlots,
        'promote_slots' => [],
      ],
      'page' => null,
      'slots' => [],
      'navigation_menus' => [],
      'shared_slots' => [],
      'page_slot_shared_slots' => [],
    ];

    return new InternalContentPlanResult($errors === [], $normalized, $warnings, $errors, renderability: $this->summarizeRenderability($normalized));
  }

  private function normalizeReplaceStagedUpdate(array $input, array &$errors, array &$warnings): InternalContentPlanResult
  {
    $this->rejectForbiddenKeys($input, 'plan', $errors, [
      'mode',
      'replace_slots',
    ]);

    $stagedPage = $this->resolveStagedPage($input, $errors);
    $sourcePage = $this->resolveStagedSourcePage($stagedPage, $input, $errors);
    $site = $stagedPage?->site;
    $locale = $this->resolveReplacementLocale($input, $stagedPage, $site, $errors);
    $replaceSlots = $this->normalizeReplacementSlots($input, $stagedPage, $errors, $warnings);
    $pageSettings = $this->normalizePageSettings($input, $errors);

    $normalized = [
      'mode' => self::MODE_REPLACE_STAGED_UPDATE,
      'site' => $site ? ['id' => $site->id, 'handle' => $site->handle] : null,
      'locale' => $locale ? ['id' => $locale->id, 'code' => $locale->code] : null,
      'layout' => null,
      'page_settings' => $pageSettings,
      'replace_page' => $stagedPage ? [
        'id' => $stagedPage->id,
        'status' => $stagedPage->status,
        'expected_path' => $this->stagedPagePath($stagedPage),
        'expected_updated_at' => '',
      ] : null,
      'replace_slots' => $replaceSlots,
      'staged_update' => [
        'source_page' => $sourcePage ? ['id' => $sourcePage->id, 'status' => $sourcePage->status] : null,
        'staged_page' => $stagedPage ? ['id' => $stagedPage->id, 'status' => $stagedPage->status] : null,
        'managed_slots' => $this->stagedUpdateMetadata($stagedPage)['managed_slots'] ?? [],
        'promote_slots' => [],
      ],
      'page' => null,
      'slots' => [],
      'navigation_menus' => [],
      'shared_slots' => [],
      'page_slot_shared_slots' => [],
    ];

    return new InternalContentPlanResult($errors === [], $normalized, $warnings, $errors, renderability: $this->summarizeRenderability($normalized));
  }

  private function normalizePromoteStagedUpdate(array $input, array &$errors, array &$warnings): InternalContentPlanResult
  {
    $promoteSourcePage = $this->stagedSourcePageFrom($input);

    if ($promoteSourcePage && $this->pageHasHumanOnlyBlock($promoteSourcePage)) {
      $errors[] = $this->apiAuthoringPolicy->error('plan.page.blocks');
    }

    $this->rejectForbiddenKeys($input, 'plan', $errors, [
      'mode',
    ]);

    $stagedPage = $this->resolveStagedPage($input, $errors);
    $sourcePage = $this->resolveStagedSourcePage($stagedPage, $input, $errors);
    $site = $sourcePage?->site;
    $locale = $this->resolveReplacementLocale($input, $sourcePage, $site, $errors);
    $pageSettings = $this->normalizePageSettings($input, $errors);
    if ($pageSettings === [] && $this->sourceSync($stagedPage) !== []) {
      $pageSettings = ['source_sync' => $this->sourceSync($stagedPage)];
    }
    $promoteSlots = $this->normalizePromoteSlots($input, $sourcePage, $stagedPage, $errors);

    $normalized = [
      'mode' => self::MODE_PROMOTE_STAGED_UPDATE,
      'site' => $site ? ['id' => $site->id, 'handle' => $site->handle] : null,
      'locale' => $locale ? ['id' => $locale->id, 'code' => $locale->code] : null,
      'layout' => null,
      'page_settings' => $pageSettings,
      'replace_page' => null,
      'replace_slots' => [],
      'staged_update' => [
        'source_page' => $sourcePage ? [
          'id' => $sourcePage->id,
          'status' => $sourcePage->status,
          'expected_path' => $this->safeExpectedSourcePath($input),
          'expected_updated_at' => $this->expectedSourceUpdatedAt($input),
        ] : null,
        'staged_page' => $stagedPage ? ['id' => $stagedPage->id, 'status' => $stagedPage->status] : null,
        'managed_slots' => $this->stagedUpdateMetadata($stagedPage)['managed_slots'] ?? [],
        'promote_slots' => $promoteSlots,
      ],
      'page' => null,
      'slots' => [],
      'navigation_menus' => [],
      'shared_slots' => [],
      'page_slot_shared_slots' => [],
    ];

    return new InternalContentPlanResult($errors === [], $normalized, $warnings, $errors, renderability: $this->summarizeRenderability($normalized));
  }

  private function resolveReplacementLocale(array $input, ?Page $page, ?Site $site, array &$errors): ?Locale
  {
    $value = data_get($input, 'locale', data_get($input, 'locale_id'));

    if ($value !== null && $value !== '') {
      return $this->resolveLocale($input, $site, $errors);
    }

    if (! $page) {
      $errors[] = $this->error('plan.locale', 'Locale must resolve.');

      return null;
    }

    $translation = $page->translations->first();
    $locale = $translation?->locale ?: Locale::query()->where('is_default', true)->first();

    if (! $locale) {
      $errors[] = $this->error('plan.locale', 'Locale must resolve.');

      return null;
    }

    if (! $site || ! $site->locales()->whereKey($locale->id)->wherePivot('is_enabled', true)->exists()) {
      $errors[] = $this->error('plan.locale', 'Locale must be enabled for the target site.');
    }

    return $locale;
  }

  private function normalizeReplacementSlots(array $input, ?Page $page, array &$errors, array &$warnings): array
  {
    $replaceSlots = data_get($input, 'replace_slots', []);

    if (! is_array($replaceSlots) || $replaceSlots === []) {
      $errors[] = $this->error('plan.replace_slots', 'At least one page-owned slot replacement is required.');

      return [];
    }

    $pageSlots = $page
      ? $page->slots->keyBy(fn (PageSlot $slot) => $slot->slotSlug())
      : collect();
    $normalized = [];

    foreach ($replaceSlots as $slotName => $blocks) {
      $slotSlug = trim((string) $slotName);
      $pageSlot = $pageSlots->get($slotSlug);

      if ($slotSlug === '' || ! SlotType::query()->where('slug', $slotSlug)->where('status', 'published')->exists()) {
        $errors[] = $this->error('plan.replace_slots.'.$slotName, 'Slot name must resolve to a published slot type.');

        continue;
      }

      if (! $pageSlot) {
        $errors[] = $this->error('plan.replace_slots.'.$slotName, 'Slot must exist on the selected page.');

        continue;
      }

      if (! $pageSlot->usesPageOwnedBlocks()) {
        $errors[] = $this->error('plan.replace_slots.'.$slotName, 'Shared-slot-backed slots cannot be replaced by this operation.');

        continue;
      }

      if (! is_array($blocks)) {
        $errors[] = $this->error('plan.replace_slots.'.$slotName, 'Replacement slot blocks must be an array.');

        continue;
      }

      $normalized[$slotSlug] = [];

      foreach (array_values($blocks) as $index => $block) {
        $normalizedBlock = $this->normalizeBlock($block, 'plan.replace_slots.'.$slotSlug.'.'.$index, null, $errors, $warnings);

        if ($normalizedBlock !== null) {
          $normalized[$slotSlug][] = $normalizedBlock;
        }
      }
    }

    return $normalized;
  }

  private function resolveSourcePublishedPage(array $input, array &$errors): ?Page
  {
    $pageId = data_get($input, 'page.id', data_get($input, 'source_page_id', data_get($input, 'page_id')));
    $page = is_numeric($pageId)
      ? Page::query()->with(['site.locales', 'translations.locale', 'slots.slotType', 'slots.sharedSlot'])->find((int) $pageId)
      : null;

    if (! $page) {
      $errors[] = $this->error('plan.page.id', 'Published source page ID must resolve.');

      return null;
    }

    if ($page->status !== Page::STATUS_PUBLISHED) {
      $errors[] = $this->error('plan.page.status', 'Staged updates can only be created for published source pages.');
    }

    $this->validateSourcePageGuards($page, $input, $errors);

    return $page;
  }

  private function resolveStagedPage(array $input, array &$errors): ?Page
  {
    $pageId = data_get($input, 'staged_page.id', data_get($input, 'staged_page_id', data_get($input, 'page.id')));
    $page = is_numeric($pageId)
      ? Page::query()->with(['site.locales', 'translations.locale', 'slots.slotType', 'slots.sharedSlot'])->find((int) $pageId)
      : null;

    if (! $page) {
      $errors[] = $this->error('plan.staged_page.id', 'Staged page ID must resolve.');

      return null;
    }

    $metadata = $this->stagedUpdateMetadata($page);
    if ($metadata === []) {
      $errors[] = $this->error('plan.staged_page.id', 'Page is not a staged update.');
    }

    if ($page->status !== Page::STATUS_DRAFT) {
      $errors[] = $this->error('plan.staged_page.status', 'Only draft staged updates can be changed or promoted.');
    }

    if (($metadata['state'] ?? null) !== 'draft') {
      $errors[] = $this->error('plan.staged_page.state', 'Only active draft staged updates can be changed or promoted.');
    }

    return $page;
  }

  private function resolveStagedSourcePage(?Page $stagedPage, array $input, array &$errors): ?Page
  {
    if (! $stagedPage) {
      return null;
    }

    $metadata = $this->stagedUpdateMetadata($stagedPage);
    $sourcePageId = $metadata['source_page_id'] ?? null;
    $sourcePage = is_numeric($sourcePageId)
      ? Page::query()->with(['site.locales', 'translations.locale', 'slots.slotType', 'slots.sharedSlot'])->find((int) $sourcePageId)
      : null;

    if (! $sourcePage) {
      $errors[] = $this->error('plan.source_page.id', 'Staged update source page must resolve.');

      return null;
    }

    if ($sourcePage->status !== Page::STATUS_PUBLISHED) {
      $errors[] = $this->error('plan.source_page.status', 'Staged update source page must still be published.');
    }

    $expectedSourcePageId = data_get($input, 'source_page.id', data_get($input, 'expected_source_page_id'));
    if ($expectedSourcePageId !== null && (int) $expectedSourcePageId !== (int) $sourcePage->id) {
      $errors[] = $this->error('plan.expected_source_page_id', 'Expected source page ID does not match the staged update.');
    }

    $this->validateSourcePageGuards($sourcePage, $input, $errors);

    return $sourcePage;
  }

  private function validateSourcePageGuards(Page $page, array $input, array &$errors): void
  {
    try {
      $expectedPath = $this->expectedSourcePath($input);
    } catch (\InvalidArgumentException $exception) {
      $errors[] = $this->error('plan.expected_source_path', $exception->getMessage());
      $expectedPath = '';
    }
    if ($expectedPath !== '') {
      $actualPath = $this->pagePath($page);

      if ($actualPath !== $expectedPath) {
        $errors[] = $this->error('plan.expected_source_path', 'Expected source path does not match the page translation.');
      }
    }

    $expectedUpdatedAt = $this->expectedSourceUpdatedAt($input);
    if ($expectedUpdatedAt !== '') {
      try {
        if (! $page->updated_at || ! $page->updated_at->equalTo(Carbon::parse($expectedUpdatedAt))) {
          $errors[] = $this->error('plan.expected_source_updated_at', 'Expected source updated_at does not match the page.');
        }
      } catch (\Throwable) {
        $errors[] = $this->error('plan.expected_source_updated_at', 'Expected source updated_at must be a valid date-time string.');
      }
    }

    if ($expectedPath === '' && $expectedUpdatedAt === '') {
      $errors[] = $this->error('plan.source_page', 'Published page staged updates require expected_source_path or expected_source_updated_at.');
    }
  }

  private function normalizePromoteSlots(array $input, ?Page $sourcePage, ?Page $stagedPage, array &$errors): array
  {
    $metadata = $this->stagedUpdateMetadata($stagedPage);
    $rawSlots = data_get($input, 'promote_slots', data_get($input, 'managed_slots', $metadata['managed_slots'] ?? []));

    if (! is_array($rawSlots) || $rawSlots === []) {
      $rawSlots = $this->sourceSync($stagedPage)['managed_slots'] ?? [];
    }

    if (! is_array($rawSlots) || $rawSlots === []) {
      $rawSlots = $sourcePage
        ? $sourcePage->slots->filter(fn (PageSlot $slot) => $slot->usesPageOwnedBlocks())->map(fn (PageSlot $slot) => $slot->slotSlug())->values()->all()
        : [];
    }

    $slots = [];
    foreach (array_values($rawSlots) as $slot) {
      $slot = is_string($slot) ? trim($slot) : '';

      if ($slot === '') {
        continue;
      }

      $slots[] = $slot;
    }

    $slots = array_values(array_unique($slots));

    if ($slots === []) {
      $errors[] = $this->error('plan.promote_slots', 'At least one page-owned slot must be selected for promote.');

      return [];
    }

    $this->validatePromoteSlotSet($sourcePage, $stagedPage, $slots, $errors);

    return $slots;
  }

  private function managedSlotsForStagedUpdate(array $input, ?Page $sourcePage, array &$errors): array
  {
    $raw = data_get($input, 'managed_slots', $this->sourceSync($sourcePage)['managed_slots'] ?? []);

    if (! is_array($raw) || $raw === []) {
      $raw = $sourcePage
        ? $sourcePage->slots->filter(fn (PageSlot $slot) => $slot->usesPageOwnedBlocks())->map(fn (PageSlot $slot) => $slot->slotSlug())->values()->all()
        : [];
    }

    $slots = array_values(array_unique(array_filter(array_map(fn ($slot) => is_string($slot) ? trim($slot) : '', $raw))));

    if ($slots === []) {
      $errors[] = $this->error('plan.managed_slots', 'At least one page-owned managed slot is required.');
    }

    $this->validatePromoteSlotSet($sourcePage, null, $slots, $errors);

    return $slots;
  }

  private function validatePromoteSlotSet(?Page $sourcePage, ?Page $stagedPage, array $slots, array &$errors): void
  {
    foreach ($slots as $slotSlug) {
      if (! SlotType::query()->where('slug', $slotSlug)->where('status', 'published')->exists()) {
        $errors[] = $this->error('plan.promote_slots', 'Promote slot ['.$slotSlug.'] must resolve to a published slot type.');

        continue;
      }

      foreach ([['page' => $sourcePage, 'path' => 'source_page'], ['page' => $stagedPage, 'path' => 'staged_page']] as $target) {
        $page = $target['page'];

        if (! $page) {
          continue;
        }

        $pageSlot = $page->slots->first(fn (PageSlot $slot) => $slot->slotSlug() === $slotSlug);

        if (! $pageSlot) {
          $errors[] = $this->error('plan.'.$target['path'].'.slots', 'Slot ['.$slotSlug.'] must exist on the '.$target['path'].'.');

          continue;
        }

        if (! $pageSlot->usesPageOwnedBlocks()) {
          $errors[] = $this->error('plan.'.$target['path'].'.slots', 'Shared-slot-backed slot ['.$slotSlug.'] cannot be promoted or replaced.');
        }
      }
    }
  }

  private function expectedSourcePath(array $input): string
  {
    $raw = trim((string) data_get($input, 'source_page.expected_path', data_get($input, 'expected_source_path', data_get($input, 'page.expected_path', ''))));

    if ($raw === '') {
      return '';
    }

    return PagePath::canonicalize($raw);
  }

  private function safeExpectedSourcePath(array $input): string
  {
    try {
      return $this->expectedSourcePath($input);
    } catch (\InvalidArgumentException) {
      return '';
    }
  }

  private function expectedSourceUpdatedAt(array $input): string
  {
    return trim((string) data_get($input, 'source_page.expected_updated_at', data_get($input, 'expected_source_updated_at', '')));
  }

  private function pagePath(Page $page): string
  {
    $translation = $page->translations->first();

    return $translation?->path ?: PageTranslation::pathFromSlug($translation?->slug ?? $page->slug ?? '');
  }

  private function stagedPagePath(Page $page): string
  {
    return $this->pagePath($page);
  }

  private function uniqueStagedPath(Page $sourcePage, int $localeId, string $basePath): string
  {
    $path = PagePath::canonicalize($basePath);
    $candidate = $path;
    $index = 2;

    while (PageTranslation::query()
      ->where('site_id', $sourcePage->site_id)
      ->where('locale_id', $localeId)
      ->where('path', $candidate)
      ->exists()) {
      $candidate = $path.'-'.$index;
      $index++;
    }

    return $candidate;
  }

  private function stagedUpdateMetadata(?Page $page): array
  {
    if (! $page || ! is_array($page->settings)) {
      return [];
    }

    $metadata = $page->settings['staged_update'] ?? null;

    if (! is_array($metadata) || ($metadata['type'] ?? null) !== self::STAGED_UPDATE_TYPE) {
      return [];
    }

    return $metadata;
  }

  private function sourceSync(?Page $page): array
  {
    if (! $page || ! is_array($page->settings) || ! is_array($page->settings['source_sync'] ?? null)) {
      return [];
    }

    return $page->settings['source_sync'];
  }

  private function resolveSite(array $input, array &$errors): ?Site
  {
    $value = data_get($input, 'site', data_get($input, 'site_handle', data_get($input, 'site_id')));

    if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_null($value)) {
      $errors[] = $this->error('plan.site', 'Site must be a handle string or numeric ID.');

      return null;
    }

    $site = Site::query()
      ->when(is_numeric($value), fn ($query) => $query->whereKey((int) $value), fn ($query) => $query->where('handle', trim((string) $value)))
      ->first();

    if (! $site) {
      $errors[] = $this->error('plan.site', 'Site handle or ID must resolve.');
    }

    return $site;
  }

  private function resolveLocale(array $input, ?Site $site, array &$errors): ?Locale
  {
    $value = data_get($input, 'locale', data_get($input, 'locale_id'));

    if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_null($value)) {
      $errors[] = $this->error('plan.locale', 'Locale must be a code string or numeric ID.');

      return null;
    }

    $locale = Locale::query()
      ->when(is_numeric($value), fn ($query) => $query->whereKey((int) $value), fn ($query) => $query->where('code', Locale::normalizeCode((string) $value)))
      ->first();

    if (! $locale) {
      $errors[] = $this->error('plan.locale', 'Locale must exist.');

      return null;
    }

    if (! $site || ! $site->locales()->whereKey($locale->id)->wherePivot('is_enabled', true)->exists()) {
      $errors[] = $this->error('plan.locale', 'Locale must be enabled for the target site.');
    }

    return $locale;
  }

  private function resolveLayout(array $input, array &$errors): ?PageLayout
  {
    $value = data_get($input, 'layout', data_get($input, 'page.layout', data_get($input, 'page_layout', 'default')));

    if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_null($value)) {
      $errors[] = $this->error('plan.layout', 'Page layout must be a handle string.');

      return null;
    }

    $handle = trim((string) $value);
    $layout = PageLayout::query()->with(['layoutSlots.slotType'])->where('handle', $handle)->where('is_active', true)->first();

    if (! $layout) {
      $errors[] = $this->error('plan.layout', 'Page layout must exist and be active.');
    }

    return $layout;
  }

  private function normalizeSlots(array $input, ?PageLayout $layout, array &$errors, array &$warnings): array
  {
    $slots = data_get($input, 'slots', []);

    if (! is_array($slots) || $slots === []) {
      $errors[] = $this->error('plan.slots', 'At least one page slot with blocks is required.');

      return [];
    }

    $layoutSlotNames = $layout
      ? $layout->layoutSlots->pluck('slotType.slug')->filter()->values()->all()
      : [];
    $normalized = [];

    foreach ($slots as $slotName => $blocks) {
      $slotSlug = trim((string) $slotName);

      if ($slotSlug === '' || ! SlotType::query()->where('slug', $slotSlug)->where('status', 'published')->exists()) {
        $errors[] = $this->error('plan.slots.'.$slotName, 'Slot name must resolve to a published slot type.');

        continue;
      }

      if ($layoutSlotNames !== [] && ! in_array($slotSlug, $layoutSlotNames, true)) {
        $errors[] = $this->error('plan.slots.'.$slotName, 'Slot must belong to the selected page layout.');

        continue;
      }

      if (! is_array($blocks)) {
        $errors[] = $this->error('plan.slots.'.$slotName, 'Slot blocks must be an array.');

        continue;
      }

      $normalized[$slotSlug] = [];

      foreach (array_values($blocks) as $index => $block) {
        $normalizedBlock = $this->normalizeBlock($block, 'plan.slots.'.$slotSlug.'.'.$index, null, $errors, $warnings);

        if ($normalizedBlock !== null) {
          $normalized[$slotSlug][] = $normalizedBlock;
        }
      }
    }

    return $normalized;
  }

  private function normalizeNavigationMenus(array $input, ?Site $site, array &$errors): array
  {
    $menus = data_get($input, 'navigation_menus', []);

    if ($menus === null) {
      return [];
    }

    if (! is_array($menus)) {
      $errors[] = $this->error('plan.navigation_menus', 'Navigation menus must be an array.');

      return [];
    }

    $normalized = [];
    $seen = [];

    foreach (array_values($menus) as $index => $menu) {
      if (! is_array($menu)) {
        $errors[] = $this->error('plan.navigation_menus.'.$index, 'Navigation menu must be an object.');

        continue;
      }

      $normalizedMenu = $this->operations->normalizeNavigationMenu($menu, $site, 'plan.navigation_menus.'.$index, $errors);

      if (! $normalizedMenu) {
        continue;
      }

      $signature = $normalizedMenu['site']['id'].'|'.$normalizedMenu['handle'];

      if (in_array($signature, $seen, true)) {
        $errors[] = $this->error('plan.navigation_menus.'.$index.'.handle', 'Navigation menu handles must be unique per site within a plan.');

        continue;
      }

      $seen[] = $signature;
      $normalized[] = $normalizedMenu;
    }

    return $normalized;
  }

  private function normalizeSharedSlots(array $input, ?Site $site, array &$errors, array &$warnings): array
  {
    $sharedSlots = data_get($input, 'shared_slots', []);

    if ($sharedSlots === null) {
      return [];
    }

    if (! is_array($sharedSlots)) {
      $errors[] = $this->error('plan.shared_slots', 'Shared Slots must be an array.');

      return [];
    }

    $normalized = [];
    $seen = [];

    foreach (array_values($sharedSlots) as $index => $sharedSlot) {
      if (! is_array($sharedSlot)) {
        $errors[] = $this->error('plan.shared_slots.'.$index, 'Shared Slot must be an object.');

        continue;
      }

      $normalizedSharedSlot = $this->operations->normalizeSharedSlot($sharedSlot, $site, 'plan.shared_slots.'.$index, $errors, $warnings);

      if (! $normalizedSharedSlot) {
        continue;
      }

      $signature = $normalizedSharedSlot['site']['id'].'|'.$normalizedSharedSlot['handle'];

      if (in_array($signature, $seen, true)) {
        $errors[] = $this->error('plan.shared_slots.'.$index.'.handle', 'Shared Slot handles must be unique per site within a plan.');

        continue;
      }

      $seen[] = $signature;
      $normalized[] = $normalizedSharedSlot;
    }

    return $normalized;
  }

  private function normalizePageSlotSharedSlots(array $input, ?Site $site, bool $hasPagePlan, array &$errors): array
  {
    $assignments = data_get($input, 'page_slot_shared_slots', []);

    if ($assignments === null) {
      return [];
    }

    if (! is_array($assignments)) {
      $errors[] = $this->error('plan.page_slot_shared_slots', 'Page slot Shared Slot assignments must be an array.');

      return [];
    }

    $normalized = [];

    foreach (array_values($assignments) as $index => $assignment) {
      if (! is_array($assignment)) {
        $errors[] = $this->error('plan.page_slot_shared_slots.'.$index, 'Page slot Shared Slot assignment must be an object.');

        continue;
      }

      $pageValue = trim((string) ($assignment['page'] ?? $assignment['page_id'] ?? ''));
      $slot = Str::slug(trim((string) ($assignment['slot'] ?? '')));
      $sharedSlot = Str::slug(trim((string) ($assignment['shared_slot'] ?? $assignment['shared_slot_id'] ?? '')));
      $pageId = null;

      if ($slot === '') {
        $errors[] = $this->error('plan.page_slot_shared_slots.'.$index.'.slot', 'Page slot name is required.');
      }

      if ($sharedSlot === '') {
        $errors[] = $this->error('plan.page_slot_shared_slots.'.$index.'.shared_slot', 'Shared Slot handle or ID is required.');
      }

      if ($pageValue === '' && $hasPagePlan) {
        $pageId = '__created_page__';
      } elseif ($pageValue === 'created' || ($hasPagePlan && ! is_numeric($pageValue))) {
        $pageId = '__created_page__';
      } elseif (is_numeric($pageValue)) {
        $page = Page::query()->find((int) $pageValue);

        if (! $page || ($site && (int) $page->site_id !== (int) $site->id)) {
          $errors[] = $this->error('plan.page_slot_shared_slots.'.$index.'.page', 'Page must resolve within the plan site.');
        } else {
          $pageId = $page->id;
        }
      } else {
        $errors[] = $this->error('plan.page_slot_shared_slots.'.$index.'.page', 'Page must be an existing page ID or the page created by this plan.');
      }

      if ($pageId !== null && $slot !== '' && $sharedSlot !== '') {
        $normalized[] = [
          'page_id' => $pageId,
          'slot' => $slot,
          'shared_slot' => $sharedSlot,
        ];
      }
    }

    return $normalized;
  }

  private function normalizeBlock(mixed $block, string $path, ?BlockType $parentType, array &$errors, array &$warnings): ?array
  {
    if (! is_array($block)) {
      $errors[] = $this->error($path, 'Block must be an object.');

      return null;
    }

    $this->rejectForbiddenKeys($block, $path, $errors);
    $this->rejectPlanManagedRelationKeys($block, $path, $errors);

    $typeSlug = trim((string) ($block['type'] ?? $block['block_type'] ?? ''));
    $blockType = BlockType::query()->where('slug', $typeSlug)->where('status', 'published')->first();

    if (! $blockType || $this->pluginBlockUnavailable($blockType)) {
      $errors[] = $this->error($path.'.type', 'Block type must be published and usable.');

      return null;
    }

    if (! $this->apiAuthoringPolicy->isApiWritable($blockType->slug)) {
      $errors[] = $this->apiAuthoringPolicy->error($path.'.type', $blockType->slug);

      return null;
    }

    if ($parentType && ! $this->parentAcceptsChild($parentType, $blockType)) {
      $errors[] = $this->error($path.'.type', 'Child block type is not allowed by the parent block contract.');
    }

    $settings = $block['settings'] ?? [];

    if (! is_array($settings)) {
      $errors[] = $this->error($path.'.settings', 'Block settings must be an object.');
      $settings = [];
    }

    $settings = $this->normalizeCommerceBuyButtonSettings($settings, $blockType, $path, $errors);
    $settings = $this->operations->normalizePublicIconSlugSettings($settings, $blockType, $path, $errors);
    $settings = $this->operations->normalizePublicIconToneSettings($settings, $blockType, $path, $errors);

    foreach (['remote_url', 'source_url'] as $mediaKey) {
      if (array_key_exists($mediaKey, $block) || array_key_exists($mediaKey, $settings)) {
        $errors[] = $this->error($path.'.'.$mediaKey, 'Remote media fetch is not supported. Upload media through the Media API first, then assign the returned media_id.');
      }
    }

    $mediaAssignment = $this->normalizeMediaAssignment($block, $settings, $blockType, $path, $errors);

    $translations = $block['translations'] ?? [];
    if (! is_array($translations)) {
      $errors[] = $this->error($path.'.translations', 'Translations must be an object.');
      $translations = [];
    }

    $this->validateTranslationShape($translations, $path.'.translations', $errors);

    foreach (['title', 'eyebrow', 'subtitle', 'content', 'meta'] as $field) {
      if (array_key_exists($field, $block) && ! array_key_exists($field, $translations)) {
        $translations[$field] = $block[$field];
      }
    }

    $children = $block['children'] ?? [];
    if (! is_array($children)) {
      $errors[] = $this->error($path.'.children', 'Children must be an array.');
      $children = [];
    }

    if ($children !== [] && ! (new Block(['type' => $blockType->slug]))->setRelation('blockType', $blockType)->canAcceptChildren()) {
      $errors[] = $this->error($path.'.children', 'This block type does not accept children.');
    }

    if ($children === [] && $this->requiresChildren($blockType)) {
      $errors[] = $this->error($path.'.children', 'This wrapper block type must contain renderable child blocks. Use nested children arrays; flat id/parent_id references are not part of the content plan contract.');
    }

    $normalizedChildren = [];
    foreach (array_values($children) as $index => $child) {
      $normalizedChild = $this->normalizeBlock($child, $path.'.children.'.$index, $blockType, $errors, $warnings);

      if ($normalizedChild !== null) {
        $normalizedChildren[] = $normalizedChild;
      }
    }

    return [
      'type' => $blockType->slug,
      'translations' => $translations,
      'settings' => $settings,
      'media_id' => $mediaAssignment['media_id'],
      '_block_media' => $mediaAssignment['_block_media'],
      '_gallery_items' => $mediaAssignment['_gallery_items'],
      '_managed_ctas' => $this->managedCtaSynchronizer->normalizeApiPayload($block, $blockType->slug, $path, $errors),
      'children' => $normalizedChildren,
    ];
  }

  private function parentAcceptsChild(BlockType $parentType, BlockType $childType): bool
  {
    $parent = new Block(['type' => $parentType->slug]);
    $parent->setRelation('blockType', $parentType);
    $allowed = $parent->allowedChildTypeSlugs();

    if ($allowed === null) {
      return $parent->canAcceptChildren();
    }

    return in_array($childType->slug, $allowed, true);
  }

  private function rejectPlanManagedRelationKeys(array $block, string $path, array &$errors): void
  {
    foreach (self::PLAN_MANAGED_RELATION_KEYS as $key) {
      if (! array_key_exists($key, $block)) {
        continue;
      }

      $errors[] = $this->error(
        $path.'.'.$key,
        'Content plans do not accept flat block relationship fields. Nest child blocks inside the parent block children array instead.',
      );
    }
  }

  private function validateTranslationShape(array $translations, string $path, array &$errors): void
  {
    foreach ($translations as $key => $value) {
      if (! is_array($value)) {
        continue;
      }

      $key = (string) $key;
      if (in_array($key, self::TRANSLATABLE_FIELDS, true)) {
        continue;
      }

      $looksLikeLocale = Locale::query()->where('code', Locale::normalizeCode($key))->exists()
        || preg_match('/^[a-z]{2}(?:[-_][A-Za-z]{2})?$/', $key) === 1;

      if ($looksLikeLocale) {
        $errors[] = $this->error(
          $path.'.'.$key,
          'Locale-keyed translations are not accepted inside block content plans. Put translated fields directly under translations, such as translations.title or translations.content, for the selected plan locale.',
        );
      }
    }
  }

  private function requiresChildren(BlockType $blockType): bool
  {
    return in_array($blockType->slug, self::CHILD_REQUIRED_BLOCK_TYPES, true);
  }

  private function summarizeRenderability(array $normalized): array
  {
    $summary = [
      'root_blocks' => 0,
      'total_blocks' => 0,
      'html_blocks' => 0,
      'wrapper_blocks_without_children' => 0,
      'text_blocks_without_visible_content' => 0,
      'button_blocks_without_label_or_url' => 0,
    ];

    foreach ($this->renderabilityRoots($normalized) as $block) {
      $this->accumulateRenderability($block, $summary, true);
    }

    return $summary;
  }

  private function renderabilityRoots(array $normalized): array
  {
    $roots = [];

    foreach (($normalized['slots'] ?? []) as $blocks) {
      if (is_array($blocks)) {
        $roots = [...$roots, ...array_values($blocks)];
      }
    }

    foreach (($normalized['replace_slots'] ?? []) as $blocks) {
      if (is_array($blocks)) {
        $roots = [...$roots, ...array_values($blocks)];
      }
    }

    foreach (($normalized['shared_slots'] ?? []) as $sharedSlot) {
      foreach (($sharedSlot['blocks'] ?? []) as $block) {
        if (is_array($block)) {
          $roots[] = $block;
        }
      }
    }

    return $roots;
  }

  private function accumulateRenderability(array $block, array &$summary, bool $isRoot = false): void
  {
    $summary['total_blocks']++;

    if ($isRoot) {
      $summary['root_blocks']++;
    }

    $type = (string) ($block['type'] ?? '');
    $children = is_array($block['children'] ?? null) ? $block['children'] : [];
    $translations = is_array($block['translations'] ?? null) ? $block['translations'] : [];
    $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];

    if ($type === 'html') {
      $summary['html_blocks']++;
    }

    if (in_array($type, self::CHILD_REQUIRED_BLOCK_TYPES, true) && $children === []) {
      $summary['wrapper_blocks_without_children']++;
    }

    if (in_array($type, ['content_header', 'header', 'plain_text', 'rich-text', 'column_item', 'feature-item', 'stat-card', 'alert', 'cta'], true)
      && ! $this->hasVisibleTranslationContent($translations)) {
      $summary['text_blocks_without_visible_content']++;
    }

    if ($type === 'button_link' && (trim((string) ($translations['title'] ?? '')) === '' || trim((string) ($settings['url'] ?? '')) === '')) {
      $summary['button_blocks_without_label_or_url']++;
    }

    foreach ($children as $child) {
      if (is_array($child)) {
        $this->accumulateRenderability($child, $summary);
      }
    }
  }

  private function hasVisibleTranslationContent(array $translations): bool
  {
    foreach (self::TRANSLATABLE_FIELDS as $field) {
      $value = $translations[$field] ?? null;

      if (is_string($value) && trim(strip_tags($value)) !== '') {
        return true;
      }
    }

    return false;
  }

  private function normalizeMediaAssignment(array $block, array &$settings, BlockType $blockType, string $path, array &$errors): array
  {
    $mediaId = $block['media_id'] ?? $block['asset_id'] ?? $settings['media_id'] ?? $settings['asset_id'] ?? null;
    unset($settings['media_id'], $settings['asset_id']);

    $galleryItems = $block['gallery_items'] ?? $settings['gallery_items'] ?? null;
    $galleryMediaIds = $block['gallery_media_ids'] ?? $block['gallery_asset_ids'] ?? $settings['gallery_media_ids'] ?? $settings['gallery_asset_ids'] ?? null;
    unset($settings['gallery_items'], $settings['gallery_media_ids'], $settings['gallery_asset_ids']);

    $payload = [
      'media_id' => null,
      '_block_media' => [],
      '_gallery_items' => [],
    ];

    if ($mediaId !== null && $mediaId !== '') {
      if (! array_key_exists($blockType->slug, InternalContentApiOperations::DIRECT_MEDIA_KIND_RULES)) {
        $errors[] = $this->error($path.'.media_id', 'This block type does not support direct Media Library assignment through media_id.');
      } else {
        $media = Media::query()->find((int) $mediaId);

        if (! $media) {
          $errors[] = $this->error($path.'.media_id', 'Media Library record must exist.');
        } elseif (! in_array($media->kind, InternalContentApiOperations::DIRECT_MEDIA_KIND_RULES[$blockType->slug], true)) {
          $errors[] = $this->error($path.'.media_id', 'Media Library record kind is not compatible with this block type.');
        } else {
          $payload['media_id'] = (int) $media->id;
        }
      }
    }

    if ($galleryItems === null && $galleryMediaIds === null) {
      return $payload;
    }

    if ($blockType->slug !== 'gallery') {
      $errors[] = $this->error($path.'.gallery_media_ids', 'Gallery media assignment is only supported by Gallery blocks.');

      return $payload;
    }

    $items = collect(is_array($galleryItems) ? $galleryItems : [])
      ->map(function (mixed $item, int $index): array {
        $item = is_array($item) ? $item : [];

        return [
          'media_id' => (int) ($item['media_id'] ?? $item['asset_id'] ?? 0),
          'sort_order' => (int) ($item['sort_order'] ?? $index),
          'alt_text' => trim((string) ($item['alt_text'] ?? '')) ?: null,
          'caption' => trim((string) ($item['caption'] ?? '')) ?: null,
          'overlay_title' => trim((string) ($item['overlay_title'] ?? '')) ?: null,
          'overlay_text' => trim((string) ($item['overlay_text'] ?? '')) ?: null,
        ];
      });

    if ($items->isEmpty() && is_array($galleryMediaIds)) {
      $items = collect($galleryMediaIds)
        ->map(fn (mixed $id, int $index): array => [
          'media_id' => (int) $id,
          'sort_order' => $index,
          'alt_text' => null,
          'caption' => null,
          'overlay_title' => null,
          'overlay_text' => null,
        ]);
    }

    $items = $items
      ->filter(fn (array $item): bool => (int) $item['media_id'] > 0)
      ->sortBy('sort_order')
      ->values();

    $mediaIds = $items->pluck('media_id')->unique()->values()->all();

    if ($mediaIds === []) {
      return $payload;
    }

    $validMediaIdSet = Media::query()
      ->whereIn('id', $mediaIds)
      ->where('kind', Media::KIND_IMAGE)
      ->pluck('id')
      ->mapWithKeys(fn ($id): array => [(int) $id => true])
      ->all();
    $validMediaIds = array_values(array_filter($mediaIds, fn (int $id): bool => isset($validMediaIdSet[$id])));

    $invalidMediaIds = array_values(array_diff($mediaIds, $validMediaIds));

    if ($invalidMediaIds !== []) {
      $errors[] = $this->error($path.'.gallery_media_ids', 'Gallery media items must be existing image Media Library records.');
    }

    $payload['_block_media'] = ['gallery_item' => $validMediaIds];
    $payload['_gallery_items'] = $items
      ->filter(fn (array $item): bool => in_array((int) $item['media_id'], $validMediaIds, true))
      ->values()
      ->all();

    return $payload;
  }

  /**
   * Rejects plan fields the API does not understand.
   *
   * Normalization reads the keys it knows and ignores everything else, so a
   * plan carrying an unsupported field such as `page.seo_title` used to apply
   * cleanly and write none of it. Failing here is the only way a caller can
   * discover the boundary, because fields the API cannot write are absent from
   * its read payloads too.
   */
  private function rejectUnsupportedKeys(array $input, string $mode, array &$errors): void
  {
    $this->rejectUnknownNodeKeys(
      $input,
      'plan',
      [...self::SHARED_PLAN_KEYS, ...(self::PLAN_KEYS_BY_MODE[$mode] ?? [])],
      $errors,
    );

    if (is_array($input['page'] ?? null)) {
      $this->rejectUnknownNodeKeys($input['page'], 'plan.page', self::PAGE_NODE_KEYS_BY_MODE[$mode] ?? [], $errors);
    }

    foreach (self::REFERENCE_NODE_KEYS as $node => $allowedKeys) {
      if (is_array($input[$node] ?? null)) {
        $this->rejectUnknownNodeKeys($input[$node], 'plan.'.$node, $allowedKeys, $errors);
      }
    }
  }

  /**
   * @param  list<string>  $allowedKeys
   */
  private function rejectUnknownNodeKeys(array $node, string $path, array $allowedKeys, array &$errors): void
  {
    foreach (array_diff(array_keys($node), $allowedKeys) as $key) {
      $errors[] = [
        'path' => $path.'.'.$key,
        'message' => 'This field is not supported by the Internal Content API and would be ignored. Remove it, or set the value in the browser admin.',
        'code' => self::UNSUPPORTED_KEY_ERROR_CODE,
      ];
    }
  }

  private function rejectForbiddenKeys(array $data, string $path, array &$errors, array $allowedKeys = []): void
  {
    foreach ($data as $key => $value) {
      $keyString = strtolower((string) $key);

      if (! in_array($keyString, $allowedKeys, true) && in_array($keyString, self::FORBIDDEN_KEYS, true)) {
        $errors[] = $this->error($path.'.'.$key, 'This operation is outside Internal Content API Phase 1.');
      }

      if (is_array($value)) {
        $this->rejectForbiddenKeys($value, $path.'.'.$key, $errors, $allowedKeys);
      }
    }
  }

  private function normalizePageSettings(array $input, array &$errors): array
  {
    $settings = data_get($input, 'page.settings', []);
    $sourceSync = data_get($input, 'page.source_sync', data_get($input, 'source_sync'));

    if ($settings !== [] && $settings !== null) {
      if (! is_array($settings)) {
        $errors[] = $this->error('plan.page.settings', 'Page settings must be an object.');

        return [];
      }

      $extraKeys = array_diff(array_keys($settings), ['source_sync']);
      if ($extraKeys !== []) {
        $errors[] = $this->error('plan.page.settings', 'Only source_sync page settings are supported.');
      }

      if (array_key_exists('source_sync', $settings)) {
        $sourceSync = $settings['source_sync'];
      }
    }

    if ($sourceSync === null || $sourceSync === '') {
      return [];
    }

    if (! is_array($sourceSync)) {
      $errors[] = $this->error('plan.page.settings.source_sync', 'source_sync must be an object.');

      return [];
    }

    $allowedKeys = ['type', 'source_id', 'source_path', 'source_sha256', 'managed_slots', 'last_synced_at'];
    $extraKeys = array_diff(array_keys($sourceSync), $allowedKeys);
    if ($extraKeys !== []) {
      $errors[] = $this->error('plan.page.settings.source_sync', 'source_sync contains unsupported fields.');
    }

    $normalized = [
      'type' => $this->safeSourceSyncString($sourceSync['type'] ?? null, 'type', $errors, 80),
      'source_id' => $this->safeSourceSyncString($sourceSync['source_id'] ?? null, 'source_id', $errors, 180),
      'source_path' => $this->safeSourceSyncPath($sourceSync['source_path'] ?? null, $errors),
      'source_sha256' => $this->safeSourceSyncSha((string) ($sourceSync['source_sha256'] ?? ''), $errors),
      'managed_slots' => $this->safeSourceSyncSlots($sourceSync['managed_slots'] ?? null, $errors),
      'last_synced_at' => $this->safeSourceSyncTimestamp($sourceSync['last_synced_at'] ?? null, $errors),
    ];

    return in_array(null, $normalized, true) ? [] : ['source_sync' => $normalized];
  }

  private function persistPageSourceSync(Page $page, array $pageSettings): void
  {
    if (! Page::supportsSettingsColumn() || ! array_key_exists('source_sync', $pageSettings)) {
      return;
    }

    $settings = is_array($page->settings) ? $page->settings : [];
    $settings['source_sync'] = $pageSettings['source_sync'];
    $page->settings = $settings;
  }

  private function safeSourceSyncString(mixed $value, string $field, array &$errors, int $max): ?string
  {
    $value = is_string($value) ? trim($value) : '';

    if ($value === '' || mb_strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value)) {
      $errors[] = $this->error('plan.page.settings.source_sync.'.$field, 'source_sync '.$field.' is invalid.');

      return null;
    }

    if (preg_match('/(token|secret|password|\\.env)/i', $value)) {
      $errors[] = $this->error('plan.page.settings.source_sync.'.$field, 'source_sync '.$field.' must not contain secret-like values.');

      return null;
    }

    return $value;
  }

  private function safeSourceSyncPath(mixed $value, array &$errors): ?string
  {
    $value = $this->safeSourceSyncString($value, 'source_path', $errors, 240);

    if ($value === null) {
      return null;
    }

    if (str_starts_with($value, '/') || str_contains($value, '\\') || str_contains($value, '..')) {
      $errors[] = $this->error('plan.page.settings.source_sync.source_path', 'source_sync source_path must be a relative documentation path.');

      return null;
    }

    return $value;
  }

  private function safeSourceSyncSha(string $value, array &$errors): ?string
  {
    $value = trim($value);

    if (! preg_match('/^[a-f0-9]{64}$/', $value)) {
      $errors[] = $this->error('plan.page.settings.source_sync.source_sha256', 'source_sync source_sha256 must be a lowercase SHA-256 hex value.');

      return null;
    }

    return $value;
  }

  private function safeSourceSyncSlots(mixed $value, array &$errors): ?array
  {
    if (! is_array($value) || $value === []) {
      $errors[] = $this->error('plan.page.settings.source_sync.managed_slots', 'source_sync managed_slots must be a non-empty array.');

      return null;
    }

    $slots = [];
    foreach (array_values($value) as $slot) {
      $slot = is_string($slot) ? trim($slot) : '';

      if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slot)) {
        $errors[] = $this->error('plan.page.settings.source_sync.managed_slots', 'source_sync managed_slots contains an invalid slot.');

        return null;
      }

      $slots[] = $slot;
    }

    return array_values(array_unique($slots));
  }

  private function safeSourceSyncTimestamp(mixed $value, array &$errors): ?string
  {
    $value = is_string($value) ? trim($value) : '';

    try {
      return Carbon::parse($value)->utc()->toIso8601String();
    } catch (\Throwable) {
      $errors[] = $this->error('plan.page.settings.source_sync.last_synced_at', 'source_sync last_synced_at must be a valid date-time string.');

      return null;
    }
  }

  private function error(string $path, string $message): array
  {
    return [
      'path' => $path,
      'message' => $message,
    ];
  }

  private function pluginBlockUnavailable(BlockType $blockType): bool
  {
    $catalog = app(PluginBlockCatalog::class);

    return $catalog->isPluginCatalogSlug($blockType->slug) && ! $catalog->isEnabledCatalogSlug($blockType->slug);
  }

  private function normalizeCommerceBuyButtonSettings(array $settings, BlockType $blockType, string $path, array &$errors): array
  {
    if ($blockType->slug !== 'webblocks-commerce-buy-button') {
      return $settings;
    }

    $productId = (int) ($settings['commerce_product_id'] ?? $settings['product_id'] ?? 0);

    if ($productId <= 0) {
      $errors[] = $this->error($path.'.settings.commerce_product_id', 'Commerce Buy Button requires a commerce_product_id from GET /webadmin/api/commerce/products.');

      return $settings;
    }

    if (! Schema::hasTable('webblocks_commerce_products')) {
      $errors[] = $this->error($path.'.settings.commerce_product_id', 'WebBlocks Commerce setup is required before using Commerce Buy Button blocks.');

      return $settings;
    }

    $exists = DB::table('webblocks_commerce_products')
      ->where('id', $productId)
      ->where('status', 'active')
      ->exists();

    if (! $exists) {
      $errors[] = $this->error($path.'.settings.commerce_product_id', 'Commerce Buy Button product must be an existing active Commerce product.');
    }

    $settings['commerce_product_id'] = $productId;
    unset($settings['product_id']);

    return $settings;
  }
}
