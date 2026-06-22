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
    'navigation',
    'navigation_items',
    'shared_slots',
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

    $page = DB::transaction(function () use ($plan): Page {
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

      return $page->fresh([
        'site.locales',
        'translations.locale',
        'slots.slotType',
        'blocks.blockType',
        'blocks.slotType',
        'blocks.textTranslations',
        'blocks.buttonTranslations',
        'blocks.imageTranslations',
      ]);
    });

    return new InternalContentPlanResult(
      ok: true,
      normalizedPlan: $plan,
      warnings: $validated->warnings,
      writes: [
        ['type' => 'page', 'id' => $page->id],
        ...$page->blocks->map(fn (Block $block) => ['type' => 'block', 'id' => $block->id])->all(),
      ],
      data: [
        'page' => $this->presenter->page($page, true),
      ],
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

    $status = strtolower(trim((string) data_get($input, 'page.status', data_get($input, 'status', 'draft'))));
    if ($status !== '' && $status !== Page::STATUS_DRAFT) {
      $errors[] = $this->error('plan.page.status', 'Phase 1 can only create draft pages.');
    }

    $site = $this->resolveSite($input, $errors);
    $locale = $this->resolveLocale($input, $site, $errors);
    $layout = $this->resolveLayout($input, $errors);
    $title = trim((string) data_get($input, 'page.title', data_get($input, 'title', '')));
    $path = trim((string) data_get($input, 'page.path', data_get($input, 'path', '')));
    $slug = $this->slugFromPath($path);

    if ($title === '') {
      $errors[] = $this->error('plan.page.title', 'Page title is required.');
    }

    if ($slug === '') {
      $errors[] = $this->error('plan.page.path', 'Page path is required.');
    }

    if ($site && $locale && $slug !== '' && PageTranslation::query()
      ->where('site_id', $site->id)
      ->where('locale_id', $locale->id)
      ->where('slug', $slug)
      ->exists()) {
      $errors[] = $this->error('plan.page.path', 'A page already exists at this path for the selected site and locale.');
    }

    $slots = $this->normalizeSlots($input, $layout, $errors, $warnings);

    $normalized = [
      'site' => $site ? ['id' => $site->id, 'handle' => $site->handle] : null,
      'locale' => $locale ? ['id' => $locale->id, 'code' => $locale->code] : null,
      'layout' => $layout ? ['id' => $layout->id, 'handle' => $layout->handle] : null,
      'page' => [
        'title' => $title,
        'path' => $slug !== '' ? PageTranslation::pathFromSlug($slug) : '',
        'slug' => $slug,
        'status' => Page::STATUS_DRAFT,
      ],
      'slots' => $slots,
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
