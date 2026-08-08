<?php

namespace WebBlocks\Cms\Support\InternalContentApi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\Blocks\ManagedCtaSynchronizer;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeApiAuthoringPolicy;
use WebBlocks\Cms\Support\Icons\IconCatalog;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;
use WebBlocks\Cms\Support\PublicRendering\PublicIconPresenter;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;

class InternalContentApiOperations
{
  private const FORBIDDEN_KEYS = [
    'publish',
    'published',
    'published_at',
    'site_create',
    'create_site',
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

  /**
   * Block types whose public renderer draws an icon. Single source of truth for
   * both the full content plan and the incremental block endpoints.
   *
   * @var list<string>
   */
  public const PUBLIC_ICON_BLOCK_TYPES = [
    'content_header',
    'card_header',
    'column_item',
    'feature-item',
    'link-list-item',
  ];

  /**
   * Block types that accept a direct Media Library assignment through media_id,
   * mapped to the media kinds each one allows. Single source of truth for both
   * the full content plan and the incremental block endpoints.
   *
   * @var array<string, list<string>>
   */
  public const DIRECT_MEDIA_KIND_RULES = [
    'image' => [Media::KIND_IMAGE],
    'navbar-brand' => [Media::KIND_IMAGE],
    'sidebar-brand' => [Media::KIND_IMAGE],
    'hero' => [Media::KIND_IMAGE],
    'section' => [Media::KIND_IMAGE],
    'slide' => [Media::KIND_IMAGE],
    'card' => [Media::KIND_IMAGE],
    'cta' => [Media::KIND_IMAGE],
    'content_header' => [Media::KIND_IMAGE],
    'link-list-item' => [Media::KIND_IMAGE],
    'download' => [Media::KIND_DOCUMENT, Media::KIND_OTHER],
    'file' => [Media::KIND_DOCUMENT, Media::KIND_OTHER],
    'video' => [Media::KIND_VIDEO],
  ];

  public function __construct(
    private readonly BlockPayloadWriter $blockPayloadWriter,
    private readonly SharedSlotSourcePageManager $sharedSlotSourcePages,
    private readonly BlockTypeApiAuthoringPolicy $apiAuthoringPolicy,
    private readonly ManagedCtaSynchronizer $managedCtaSynchronizer,
  ) {}

  public function resolveSite(mixed $value, string $path, array &$errors): ?Site
  {
    if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_null($value)) {
      $errors[] = $this->error($path, 'Site must be a handle string or numeric ID.');

      return null;
    }

    $site = Site::query()
      ->when(is_numeric($value), fn ($query) => $query->whereKey((int) $value), fn ($query) => $query->where('handle', trim((string) $value)))
      ->first();

    if (! $site) {
      $errors[] = $this->error($path, 'Site handle or ID must resolve.');
    }

    return $site;
  }

  public function resolveLocale(mixed $value, ?Site $site, string $path, array &$errors): ?Locale
  {
    if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_null($value)) {
      $errors[] = $this->error($path, 'Locale must be a code string or numeric ID.');

      return null;
    }

    $locale = Locale::query()
      ->when(is_numeric($value), fn ($query) => $query->whereKey((int) $value), fn ($query) => $query->where('code', Locale::normalizeCode((string) $value)))
      ->first();

    if (! $locale) {
      $errors[] = $this->error($path, 'Locale must exist.');

      return null;
    }

    if (! $site || ! $site->locales()->whereKey($locale->id)->wherePivot('is_enabled', true)->exists()) {
      $errors[] = $this->error($path, 'Locale must be enabled for the target site.');
    }

    return $locale;
  }

  public function normalizeNavigationMenu(array $payload, ?Site $fallbackSite, string $path, array &$errors): ?array
  {
    $this->rejectForbiddenKeys($payload, $path, $errors);

    $site = $this->resolveSite($payload['site'] ?? $payload['site_id'] ?? $fallbackSite?->handle, $path.'.site', $errors);
    $handle = Str::slug(trim((string) ($payload['handle'] ?? $payload['menu_key'] ?? '')));
    $label = trim((string) ($payload['label'] ?? NavigationItem::menuOptions()[$handle] ?? Str::headline($handle)));

    if (! in_array($handle, NavigationItem::menuKeys(), true)) {
      $errors[] = $this->error($path.'.handle', 'Navigation menu handle must be one of the supported CMS menu handles.');
    }

    if ($label === '') {
      $errors[] = $this->error($path.'.label', 'Navigation menu label is required.');
    }

    $items = $payload['items'] ?? [];
    if (! is_array($items)) {
      $errors[] = $this->error($path.'.items', 'Navigation menu items must be an array.');
      $items = [];
    }

    $normalizedItems = [];
    foreach (array_values($items) as $index => $item) {
      $normalized = $this->normalizeNavigationItem($item, $site, $handle, $path.'.items.'.$index, $errors);

      if ($normalized !== null) {
        $normalizedItems[] = $normalized;
      }
    }

    return $site && $handle !== '' ? [
      'site' => ['id' => $site->id, 'handle' => $site->handle],
      'handle' => $handle,
      'label' => $label,
      'items' => $normalizedItems,
    ] : null;
  }

  public function normalizeNavigationItem(mixed $payload, ?Site $site, ?string $menuHandle, string $path, array &$errors): ?array
  {
    if (! is_array($payload)) {
      $errors[] = $this->error($path, 'Navigation item must be an object.');

      return null;
    }

    $this->rejectForbiddenKeys($payload, $path, $errors);

    $label = trim((string) ($payload['label'] ?? $payload['title'] ?? ''));
    $target = trim((string) ($payload['target'] ?? '_self'));
    $sortOrder = (int) ($payload['sort_order'] ?? $payload['position'] ?? 0);
    $url = trim((string) ($payload['url'] ?? ''));
    $linkType = trim((string) ($payload['link_type'] ?? NavigationItem::LINK_CUSTOM_URL));
    $pageId = $payload['page_id'] ?? null;

    if (! in_array($linkType, NavigationItem::linkTypes(), true)) {
      $errors[] = $this->error($path.'.link_type', 'Navigation item link type must be one of: '.implode(', ', NavigationItem::linkTypes()).'.');
      $linkType = NavigationItem::LINK_CUSTOM_URL;
    }

    if ($label === '') {
      $errors[] = $this->error($path.'.label', 'Navigation item label is required.');
    }

    if (! in_array($target, ['_self', '_blank'], true)) {
      $errors[] = $this->error($path.'.target', 'Navigation item target must be _self or _blank.');
    }

    if ($sortOrder < 0) {
      $errors[] = $this->error($path.'.sort_order', 'Navigation item sort order must be zero or greater.');
    }

    // Each link type owns a different destination field. Validating the wrong
    // one is how this used to report a URL error for an item whose URL the
    // caller never sent.
    $resolvedPageId = null;

    if ($linkType === NavigationItem::LINK_PAGE) {
      if (! $pageId) {
        $errors[] = $this->error($path.'.page_id', 'Page navigation items require page_id.');
      } else {
        $page = Page::query()->find((int) $pageId);

        if (! $page || ($site && (int) $page->site_id !== (int) $site->id)) {
          $errors[] = $this->error($path.'.page_id', 'Selected page must belong to the selected site.');
        } else {
          $resolvedPageId = $page->id;
          $label = $label !== '' ? $label : (string) $page->name;
        }
      }

      $url = '';
    } elseif ($linkType === NavigationItem::LINK_GROUP) {
      if ($url !== '') {
        $errors[] = $this->error($path.'.url', 'Navigation groups are labels that open their children and cannot carry a URL.');
      }

      $url = '';
    } elseif (! $this->isSafeNavigationUrl($url)) {
      $errors[] = $this->error($path.'.url', 'Navigation item URL must be a safe internal path or http(s) URL.');
    }

    $children = [];

    if (array_key_exists('children', $payload)) {
      if (! is_array($payload['children'])) {
        $errors[] = $this->error($path.'.children', 'Navigation item children must be an array.');
      } elseif ($payload['children'] !== [] && $linkType !== NavigationItem::LINK_GROUP) {
        $errors[] = $this->error($path.'.children', 'Only navigation groups can contain child items; set link_type to group.');
      } else {
        foreach ($payload['children'] as $index => $child) {
          $normalizedChild = $this->normalizeNavigationItem($child, $site, $menuHandle, $path.'.children.'.$index, $errors);

          if ($normalizedChild !== null) {
            if ($normalizedChild['children'] !== []) {
              $errors[] = $this->error($path.'.children.'.$index.'.children', 'Navigation depth cannot exceed 2 levels in a content plan; use POST /navigation-menus/{menu}/items for deeper trees.');
            }

            $normalizedChild['position'] = $normalizedChild['position'] > 0 ? $normalizedChild['position'] : $index + 1;
            $children[] = $normalizedChild;
          }
        }
      }
    }

    return [
      'site_id' => $site?->id,
      'menu_key' => $menuHandle ?: NavigationItem::MENU_PRIMARY,
      'title' => $label,
      'link_type' => $linkType,
      'page_id' => $resolvedPageId,
      'url' => $url,
      'target' => $linkType === NavigationItem::LINK_GROUP ? null : $target,
      'position' => $sortOrder > 0 ? $sortOrder : 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
      'is_system' => false,
      'children' => $children,
    ];
  }

  public function createNavigationMenu(array $normalized): array
  {
    if (NavigationItem::query()->where('site_id', $normalized['site']['id'])->where('menu_key', $normalized['handle'])->exists()) {
      throw new \InvalidArgumentException('Navigation menu already has items and will not be overwritten.');
    }

    $items = [];
    foreach ($normalized['items'] as $index => $item) {
      $children = $item['children'] ?? [];
      unset($item['children']);

      $item['position'] = $item['position'] ?: ($index + 1);
      $parent = NavigationItem::query()->create($item);
      $items[] = $parent;

      // Children are created after their parent so parent_id is a real row id;
      // a plan expressing a dropdown used to need one POST per item plus a
      // PATCH per item to re-link them.
      foreach ($children as $childIndex => $child) {
        unset($child['children']);

        $child['parent_id'] = $parent->id;
        $child['position'] = $child['position'] ?: ($childIndex + 1);
        $items[] = NavigationItem::query()->create($child);
      }
    }

    return [
      'menu' => $normalized,
      'items' => $items,
    ];
  }

  public function addNavigationItem(NavigationItem|string $menu, array $payload, ?Site $site, string $path, array &$errors): ?NavigationItem
  {
    $handle = $menu instanceof NavigationItem ? $menu->menu_key : Str::slug((string) $menu);
    $normalized = $this->normalizeNavigationItem($payload, $site, $handle, $path, $errors);

    if ($errors !== [] || $normalized === null) {
      return null;
    }

    if (! $normalized['position']) {
      $normalized['position'] = ((int) NavigationItem::query()
        ->where('site_id', $normalized['site_id'])
        ->where('menu_key', $normalized['menu_key'])
        ->max('position')) + 1;
    }

    return NavigationItem::query()->create($normalized);
  }

  public function normalizeSharedSlot(array $payload, ?Site $fallbackSite, string $path, array &$errors, array &$warnings): ?array
  {
    $this->rejectForbiddenKeys($payload, $path, $errors);

    $site = $this->resolveSite($payload['site'] ?? $payload['site_id'] ?? $fallbackSite?->handle, $path.'.site', $errors);
    $handle = Str::slug(trim((string) ($payload['handle'] ?? '')));
    $label = trim((string) ($payload['label'] ?? $payload['name'] ?? ''));
    $slot = Str::slug(trim((string) ($payload['slot'] ?? $payload['slot_name'] ?? '')));
    $publicShell = trim((string) ($payload['layout'] ?? $payload['public_shell'] ?? ''));

    if ($handle === '') {
      $errors[] = $this->error($path.'.handle', 'Shared Slot handle is required.');
    }

    if ($label === '') {
      $errors[] = $this->error($path.'.label', 'Shared Slot label is required.');
    }

    if ($slot === '') {
      $errors[] = $this->error($path.'.slot', 'Shared Slot slot name is required.');
    } elseif (! SlotType::query()->where('slug', $slot)->where('status', 'published')->exists()) {
      $errors[] = $this->error($path.'.slot', 'Shared Slot slot name must resolve to a published slot type.');
    }

    if ($site && $handle !== '' && SharedSlot::query()->where('site_id', $site->id)->where('handle', $handle)->exists()) {
      $errors[] = $this->error($path.'.handle', 'A Shared Slot with this handle already exists for the selected site.');
    }

    $blocks = $payload['blocks'] ?? [];
    if (! is_array($blocks)) {
      $errors[] = $this->error($path.'.blocks', 'Shared Slot blocks must be an array.');
      $blocks = [];
    }

    $normalizedBlocks = [];
    foreach (array_values($blocks) as $index => $block) {
      $normalizedBlock = $this->normalizeBlock($block, $path.'.blocks.'.$index, null, $errors, $warnings);

      if ($normalizedBlock !== null) {
        $normalizedBlocks[] = $normalizedBlock;
      }
    }

    return $site && $handle !== '' ? [
      'site' => ['id' => $site->id, 'handle' => $site->handle],
      'handle' => $handle,
      'label' => $label,
      'slot' => $slot,
      'public_shell' => $publicShell !== '' ? Page::normalizePublicShellHandle($publicShell) : null,
      'blocks' => $normalizedBlocks,
    ] : null;
  }

  public function createSharedSlot(array $normalized, string $localeCode): SharedSlot
  {
    return DB::transaction(function () use ($normalized, $localeCode): SharedSlot {
      $sharedSlot = SharedSlot::query()->create([
        'site_id' => $normalized['site']['id'],
        'name' => $normalized['label'],
        'handle' => $normalized['handle'],
        'slot_name' => $normalized['slot'],
        'public_shell' => $normalized['public_shell'],
        'is_active' => true,
      ]);

      foreach (array_values($normalized['blocks']) as $index => $blockPayload) {
        $this->createSharedSlotBlock($sharedSlot, $blockPayload, $localeCode, null, $index);
      }

      $this->sharedSlotSourcePages->rebuildAssignments($sharedSlot);

      return $sharedSlot->fresh(['site', 'slotBlocks.block.blockType', 'slotBlocks.block.slotType']);
    });
  }

  public function createSharedSlotBlock(SharedSlot $sharedSlot, array $payload, string $localeCode, ?Block $parent, int $sortOrder): Block
  {
    $page = $this->sharedSlotSourcePages->ensureFor($sharedSlot);
    $slotType = $this->sharedSlotSourcePages->editorSlotTypeFor($sharedSlot);
    $blockType = BlockType::query()->where('slug', $payload['type'])->where('status', 'published')->firstOrFail();
    $translations = $payload['translations'];
    $settings = $payload['settings'] === [] ? null : json_encode($payload['settings'], JSON_UNESCAPED_SLASHES);

    $block = $this->blockPayloadWriter->save(new Block, $page, [
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
      'subtitle' => $translations['subtitle'] ?? null,
      'content' => $translations['content'] ?? null,
      'meta' => $translations['meta'] ?? null,
    ], $localeCode);

    $this->managedCtaSynchronizer->sync($block, $payload['_managed_ctas'] ?? [], $localeCode);

    foreach (array_values($payload['children']) as $index => $childPayload) {
      $this->createSharedSlotBlock($sharedSlot, $childPayload, $localeCode, $block, $index);
    }

    $this->sharedSlotSourcePages->rebuildAssignments($sharedSlot);

    return $block;
  }

  /**
   * Create a single draft page-owned block (with any normalized children) in a
   * page slot. Mirrors createSharedSlotBlock for page-owned slot content and
   * reuses the shared BlockPayloadWriter so resource writes stay consistent
   * with content apply and the admin editor.
   */
  public function createPageSlotBlock(Page $page, SlotType $slotType, array $payload, string $localeCode, ?Block $parent, int $sortOrder): Block
  {
    $blockType = BlockType::query()->where('slug', $payload['type'])->where('status', 'published')->firstOrFail();
    $translations = $payload['translations'];
    $settings = $payload['settings'] === [] ? null : json_encode($payload['settings'], JSON_UNESCAPED_SLASHES);

    $block = $this->blockPayloadWriter->save(new Block, $page, [
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
    ], $localeCode);

    $this->managedCtaSynchronizer->sync($block, $payload['_managed_ctas'] ?? [], $localeCode);

    foreach (array_values($payload['children']) as $index => $childPayload) {
      $this->createPageSlotBlock($page, $slotType, $childPayload, $localeCode, $block, $index);
    }

    return $block;
  }

  public function normalizeBlock(mixed $block, string $path, ?BlockType $parentType, array &$errors, array &$warnings): ?array
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
    $settings = $this->normalizePublicIconSlugSettings($settings, $blockType, $path, $errors);
    $settings = $this->normalizePublicIconToneSettings($settings, $blockType, $path, $errors);

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

  public function assignSharedSlot(Page $page, string $slotName, SharedSlot $sharedSlot, string $path, array &$errors): ?PageSlot
  {
    if ((int) $sharedSlot->site_id !== (int) $page->site_id) {
      $errors[] = $this->error($path.'.shared_slot', 'Shared Slot must belong to the same site as the page.');

      return null;
    }

    $slot = $page->slots()->with('slotType')->get()->first(fn (PageSlot $slot) => $slot->slotSlug() === $slotName);

    if (! $slot) {
      $errors[] = $this->error($path.'.slot', 'Page slot must exist before assigning a Shared Slot.');

      return null;
    }

    if ($page->blocks()->where('slot_type_id', $slot->slot_type_id)->whereNull('parent_id')->exists()) {
      $errors[] = $this->error($path.'.slot', 'Page slot contains page-owned blocks and must be cleared manually before Shared Slot assignment.');

      return null;
    }

    $issues = $sharedSlot->compatibilityIssuesFor($page, $slot->slotSlug());

    foreach ($issues as $issue) {
      $errors[] = $this->error($path.'.shared_slot', match ($issue) {
        'inactive' => 'Inactive Shared Slots cannot be assigned to page slots.',
        'public_shell' => 'Shared Slot Page Layout must match the page Page Layout.',
        'slot_name' => 'Shared Slot slot name must match the page slot name.',
        default => 'Shared Slot is not compatible with the selected page slot.',
      });
    }

    if ($errors !== []) {
      return null;
    }

    $slot->update([
      'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
      'shared_slot_id' => $sharedSlot->id,
    ]);

    return $slot->fresh(['slotType', 'sharedSlot']);
  }

  public function rejectForbiddenKeys(array $data, string $path, array &$errors): void
  {
    foreach ($data as $key => $value) {
      $keyString = strtolower((string) $key);

      if (in_array($keyString, self::FORBIDDEN_KEYS, true)) {
        $errors[] = $this->error($path.'.'.$key, 'This operation is outside the supported Internal Content API phase.');
      }

      if (is_array($value)) {
        $this->rejectForbiddenKeys($value, $path.'.'.$key, $errors);
      }
    }
  }

  public function error(string $path, string $message): array
  {
    return [
      'path' => $path,
      'message' => $message,
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

  public function normalizePublicIconToneSettings(array $settings, BlockType $blockType, string $path, array &$errors): array
  {
    if (! array_key_exists('icon_tone', $settings)) {
      return $settings;
    }

    if (! in_array($blockType->slug, self::PUBLIC_ICON_BLOCK_TYPES, true)) {
      $errors[] = $this->error($path.'.settings.icon_tone', 'icon_tone is only supported by public icon-enabled block types.');
      unset($settings['icon_tone']);

      return $settings;
    }

    $tone = app(PublicIconPresenter::class)->visualTone($settings['icon_tone']);

    if ($tone === null) {
      $errors[] = $this->error($path.'.settings.icon_tone', 'icon_tone must be one of: default, soft, brand, accent, highlight, bold, quiet.');
      unset($settings['icon_tone']);

      return $settings;
    }

    if ($tone === 'default') {
      unset($settings['icon_tone']);
    } else {
      $settings['icon_tone'] = $tone;
    }

    return $settings;
  }

  public function normalizePublicIconSlugSettings(array $settings, BlockType $blockType, string $path, array &$errors): array
  {
    if (! array_key_exists('icon_slug', $settings)) {
      return $settings;
    }

    if (! in_array($blockType->slug, self::PUBLIC_ICON_BLOCK_TYPES, true)) {
      $errors[] = $this->error($path.'.settings.icon_slug', 'icon_slug is only supported by public icon-enabled block types.');
      unset($settings['icon_slug']);

      return $settings;
    }

    $slug = app(IconCatalog::class)->normalizeSlug(is_string($settings['icon_slug']) ? $settings['icon_slug'] : null);

    if ($slug === null) {
      unset($settings['icon_slug']);

      return $settings;
    }

    if (! app(IconCatalog::class)->isActiveSelection($slug)) {
      $errors[] = $this->error($path.'.settings.icon_slug', 'icon_slug must be an active icon catalog slug.');
      unset($settings['icon_slug']);

      return $settings;
    }

    $settings['icon_slug'] = $slug;

    return $settings;
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
      if (! array_key_exists($blockType->slug, self::DIRECT_MEDIA_KIND_RULES)) {
        $errors[] = $this->error($path.'.media_id', 'This block type does not support direct Media Library assignment through media_id.');
      } else {
        $media = Media::query()->find((int) $mediaId);

        if (! $media) {
          $errors[] = $this->error($path.'.media_id', 'Media Library record must exist.');
        } elseif (! in_array($media->kind, self::DIRECT_MEDIA_KIND_RULES[$blockType->slug], true)) {
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

  public function isSafeNavigationUrl(string $url): bool
  {
    if ($url === '') {
      return false;
    }

    $lower = strtolower($url);

    if (str_contains($lower, "\0") || str_contains($lower, '..') || preg_match('/^\s*(javascript|data|vbscript):/i', $url)) {
      return false;
    }

    if (str_starts_with($url, '/')) {
      return ! str_starts_with($url, '//');
    }

    return (bool) filter_var($url, FILTER_VALIDATE_URL)
      && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
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
