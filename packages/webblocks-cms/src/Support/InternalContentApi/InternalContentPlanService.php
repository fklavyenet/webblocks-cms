<?php

namespace WebBlocks\Cms\Support\InternalContentApi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotSyncer;

class InternalContentPlanService
{
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

  public function __construct(
    private readonly BlockPayloadWriter $blockPayloadWriter,
    private readonly PageLayoutSlotSyncer $slotSyncer,
    private readonly InternalContentApiPresenter $presenter,
    private readonly InternalContentApiOperations $operations,
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

      if ($plan['page'] !== null) {
        $page = Page::query()->create([
          'site_id' => $plan['site']['id'],
          'page_type' => Page::TYPE_DEFAULT,
          'status' => Page::STATUS_DRAFT,
          'settings' => Page::supportsSettingsColumn()
            ? ['public_shell' => $plan['layout']['handle']]
            : null,
        ]);

        PageTranslation::query()->create([
          'page_id' => $page->id,
          'site_id' => $page->site_id,
          'locale_id' => $plan['locale']['id'],
          'name' => $plan['page']['title'],
          'slug' => $plan['page']['slug'],
          'path' => PageTranslation::pathFromSlug($plan['page']['slug']),
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
      );
    }

    return new InternalContentPlanResult(
      ok: true,
      normalizedPlan: $plan,
      warnings: $validated->warnings,
      writes: $applied['writes'],
      data: $applied['data'],
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
      'title' => $translations['title'] ?? null,
      'subtitle' => $translations['subtitle'] ?? null,
      'content' => $translations['content'] ?? null,
      'meta' => $translations['meta'] ?? null,
    ];

    $block = $this->blockPayloadWriter->save(new Block, $page, $data, $localeCode);

    foreach (array_values($payload['children']) as $index => $childPayload) {
      $this->createBlock($page, $slotType, $childPayload, $localeCode, $block, $index);
    }

    return $block;
  }

  private function normalize(array $payload): InternalContentPlanResult
  {
    $input = is_array($payload['plan'] ?? null) ? $payload['plan'] : $payload;
    $errors = [];
    $warnings = [];

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
    $path = $hasPagePlan ? trim((string) data_get($input, 'page.path', data_get($input, 'path', ''))) : '';
    $slug = $hasPagePlan ? $this->slugFromPath($path) : '';

    if ($hasPagePlan && $title === '') {
      $errors[] = $this->error('plan.page.title', 'Page title is required.');
    }

    if ($hasPagePlan && $slug === '') {
      $errors[] = $this->error('plan.page.path', 'Page path is required.');
    }

    if ($hasPagePlan && $site && $locale && $slug !== '' && PageTranslation::query()
      ->where('site_id', $site->id)
      ->where('locale_id', $locale->id)
      ->where('slug', $slug)
      ->exists()) {
      $errors[] = $this->error('plan.page.path', 'A page already exists at this path for the selected site and locale.');
    }

    $slots = $hasPagePlan ? $this->normalizeSlots($input, $layout, $errors, $warnings) : [];
    $navigationMenus = $this->normalizeNavigationMenus($input, $site, $errors);
    $sharedSlots = $this->normalizeSharedSlots($input, $site, $errors, $warnings);
    $pageSlotSharedSlots = $this->normalizePageSlotSharedSlots($input, $site, $hasPagePlan, $errors);

    $normalized = [
      'site' => $site ? ['id' => $site->id, 'handle' => $site->handle] : null,
      'locale' => $locale ? ['id' => $locale->id, 'code' => $locale->code] : null,
      'layout' => $layout ? ['id' => $layout->id, 'handle' => $layout->handle] : null,
      'page' => $hasPagePlan ? [
        'title' => $title,
        'path' => $slug !== '' ? PageTranslation::pathFromSlug($slug) : '',
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
    );
  }

  private function resolveSite(array $input, array &$errors): ?Site
  {
    $value = data_get($input, 'site', data_get($input, 'site_handle', data_get($input, 'site_id')));

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
    $handle = trim((string) data_get($input, 'layout', data_get($input, 'page.layout', data_get($input, 'page_layout', 'default'))));
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

    $typeSlug = trim((string) ($block['type'] ?? $block['block_type'] ?? ''));
    $blockType = BlockType::query()->where('slug', $typeSlug)->where('status', 'published')->first();

    if (! $blockType) {
      $errors[] = $this->error($path.'.type', 'Block type must be published and usable.');

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

    foreach (['media_id', 'asset_id', 'gallery_media_ids', 'gallery_items', 'remote_url', 'source_url'] as $mediaKey) {
      if (array_key_exists($mediaKey, $block) || array_key_exists($mediaKey, $settings)) {
        $errors[] = $this->error($path.'.'.$mediaKey, 'Media import, media assignment, and remote fetch are outside Phase 1.');
      }
    }

    $translations = $block['translations'] ?? [];
    if (! is_array($translations)) {
      $errors[] = $this->error($path.'.translations', 'Translations must be an object.');
      $translations = [];
    }

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

  private function rejectForbiddenKeys(array $data, string $path, array &$errors): void
  {
    foreach ($data as $key => $value) {
      $keyString = strtolower((string) $key);

      if (in_array($keyString, self::FORBIDDEN_KEYS, true)) {
        $errors[] = $this->error($path.'.'.$key, 'This operation is outside Internal Content API Phase 1.');
      }

      if (is_array($value)) {
        $this->rejectForbiddenKeys($value, $path.'.'.$key, $errors);
      }
    }
  }

  private function slugFromPath(string $path): string
  {
    $path = trim($path);
    $path = trim($path, '/');

    if ($path === '') {
      return 'home';
    }

    return Str::slug($path);
  }

  private function error(string $path, string $message): array
  {
    return [
      'path' => $path,
      'message' => $message,
    ];
  }
}
