<?php

namespace WebBlocks\Cms\Support\PageConverter;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Audit\CurrentActorResolver;
use WebBlocks\Cms\Support\Blocks\BlockPayloadWriter;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotSyncer;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;

class PageConversionDraftCreator
{
  private const SUPPORTED_BLOCK_SLUGS = [
    'header',
    'plain_text',
    'rich-text',
    'code',
    'table',
    'quote',
    'html',
    'button_link',
    'alert',
    'list',
    'callout',
    'section',
    'content_header',
    'hero',
    'cta',
    'card',
    'card_header',
    'card_body',
    'card_footer',
    'button',
    'accordion',
    'accordion_item',
  ];

  private const SKIPPED_BLOCK_SLUGS = [
    'image',
    'gallery',
  ];

  private const CHILD_ONLY_BLOCK_SLUGS = [
    'card_header',
    'card_body',
    'card_footer',
    'button',
    'accordion_item',
  ];

  public function __construct(
    private readonly CurrentActorResolver $currentActorResolver,
    private readonly PageConversionPlanValidator $planValidator,
    private readonly PageLayoutSlotSyncer $pageLayoutSlotSyncer,
    private readonly PageRevisionManager $revisionManager,
    private readonly BlockPayloadWriter $blockPayloadWriter,
  ) {}

  public function create(array $plan, User $user): PageConversionDraftResult
  {
    $this->guardPlan($plan, $user);

    return DB::transaction(function () use ($plan, $user): PageConversionDraftResult {
      $this->guardPlan($plan, $user);

      $target = $plan['target'];
      $site = Site::query()->findOrFail((int) $target['site_id']);
      $locale = Locale::query()->findOrFail((int) $target['locale_id']);
      $layoutHandle = Page::normalizePublicShellHandle((string) $target['page_layout']);
      $slug = $this->normalizedSlug((string) $target['page_path']);
      $actor = $this->currentActorResolver->resolve($user, 'admin/page_converter');

      $page = Page::query()->create([
        'site_id' => $site->id,
        'title' => (string) $target['page_title'],
        'slug' => $slug,
        'page_type' => Page::TYPE_DEFAULT,
        'settings' => Page::sanitizeSettings(['public_shell' => $layoutHandle], $layoutHandle),
        'status' => Page::STATUS_DRAFT,
        'published_at' => null,
        'review_requested_at' => null,
        'created_by_user_id' => $actor['user_id'],
        'updated_by_user_id' => $actor['user_id'],
      ]);

      $this->ensureTargetTranslation($page, $site, $locale, (string) $target['page_title'], $slug);
      $this->pageLayoutSlotSyncer->seedInitialSlots($page, $layoutHandle);
      $mainSlot = $this->ensureMainSlot($page);

      $createdBlockCount = 0;
      $skippedSuggestionCount = 0;
      $warnings = [];
      $createdByKey = [];
      $siblingSortOrders = ['root' => 0];
      $blocks = $this->orderedBlocks($plan);
      $childrenByParentKey = $this->childrenByParentKey($blocks);

      foreach ($blocks as $block) {
        $warnings = array_merge($warnings, $this->stringList($block['warnings'] ?? []));
        $converterSlug = $this->blockSlug($block);
        $blockKey = (string) ($block['key'] ?? '');
        $parentKey = isset($block['parent_key']) ? trim((string) $block['parent_key']) : '';
        $parentBlock = $parentKey !== '' ? ($createdByKey[$parentKey] ?? null) : null;

        if (! in_array($converterSlug, self::SUPPORTED_BLOCK_SLUGS, true)) {
          $skippedSuggestionCount++;
          $warnings[] = 'Skipped unsupported Page Converter suggestion ['.$converterSlug.'].';

          continue;
        }

        if ($parentKey === '' && in_array($converterSlug, self::CHILD_ONLY_BLOCK_SLUGS, true)) {
          $skippedSuggestionCount++;
          $warnings[] = 'Skipped child-only Page Converter suggestion ['.$converterSlug.'] because it had no created parent.';

          continue;
        }

        if ($parentKey !== '') {
          if (! $parentBlock) {
            $skippedSuggestionCount++;
            $warnings[] = 'Skipped Page Converter suggestion ['.$converterSlug.'] because its parent suggestion was not created.';

            continue;
          }

          if (! $this->parentAcceptsChild($parentBlock, $converterSlug)) {
            $skippedSuggestionCount++;
            $warnings[] = 'Skipped Page Converter suggestion ['.$converterSlug.'] because parent ['.$parentBlock->typeSlug().'] cannot accept it.';

            continue;
          }
        }

        if ($converterSlug === 'card' && ! $this->hasCreatableCardChildren($blockKey, $childrenByParentKey)) {
          $skippedSuggestionCount++;
          $warnings[] = 'Skipped Page Converter suggestion [card] because no explicit usable card child region was present.';

          continue;
        }

        if ($converterSlug === 'accordion' && ! $this->hasCreatableAccordionChildren($blockKey, $childrenByParentKey)) {
          $skippedSuggestionCount++;
          $warnings[] = 'Skipped Page Converter suggestion [accordion] because no explicit usable accordion item children were present.';

          continue;
        }

        if ($converterSlug === 'section' && ! isset($childrenByParentKey[$blockKey])) {
          $warnings[] = 'Created Page Converter section shell without child blocks because the signed plan did not include explicit section children.';
        }

        if (in_array($converterSlug, ['hero', 'cta'], true) && $this->hasActionLikeFields($block) && ! isset($childrenByParentKey[$blockKey])) {
          $warnings[] = 'Created Page Converter suggestion ['.$converterSlug.'] without CTA child buttons because the signed plan did not include explicit button children.';
        }

        $siblingKey = $parentBlock ? 'parent:'.$parentBlock->id : 'root';
        $sortOrder = $siblingSortOrders[$siblingKey] ?? 0;
        $payload = $this->blockPayload($block, $page, $mainSlot, $sortOrder, $parentBlock);

        if ($payload === null) {
          $skippedSuggestionCount++;
          $warnings[] = 'Skipped Page Converter suggestion ['.$converterSlug.'] because it could not be converted safely.';

          continue;
        }

        $createdBlock = $this->blockPayloadWriter->save(new Block, $page, $payload, $locale->code);
        $createdBlock->setRelation('blockType', $createdBlock->blockType()->first());
        $createdByKey[$blockKey] = $createdBlock;
        $siblingSortOrders[$siblingKey] = $sortOrder + 1;
        $createdBlockCount++;
      }

      $page->forceFill(['updated_by_user_id' => $actor['user_id']])->save();

      $this->revisionManager->capture(
        $page->fresh(),
        $user,
        'Page Converter draft created',
        'Draft page was created from a signed Page Converter plan.',
        event: 'page_created',
        source: 'admin/page_converter',
      );

      return new PageConversionDraftResult(
        page: $page->fresh(['translations.locale', 'slots.slotType', 'blocks.textTranslations']),
        createdBlockCount: $createdBlockCount,
        skippedSuggestionCount: $skippedSuggestionCount,
        warningCount: count($warnings),
        warnings: $warnings,
      );
    });
  }

  private function guardPlan(array $plan, User $user): void
  {
    $errors = $this->planValidator->validate($plan, $user);

    if ($errors !== []) {
      throw ValidationException::withMessages($errors);
    }
  }

  private function ensureTargetTranslation(Page $page, Site $site, Locale $locale, string $title, string $slug): void
  {
    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $locale->id],
      [
        'site_id' => $site->id,
        'name' => $title,
        'slug' => $slug,
        'path' => PageTranslation::pathFromSlug($slug),
      ],
    );
  }

  private function ensureMainSlot(Page $page): PageSlot
  {
    $mainSlotType = SlotType::query()->where('slug', 'main')->first();

    if (! $mainSlotType) {
      throw ValidationException::withMessages([
        'plan_payload' => 'The CMS main slot type is not available.',
      ]);
    }

    return $page->slots()->where('slot_type_id', $mainSlotType->id)->first()
      ?? $page->slots()->create([
        'slot_type_id' => $mainSlotType->id,
        'source_type' => PageSlot::SOURCE_TYPE_PAGE,
        'sort_order' => (int) $page->slots()->max('sort_order') + 1,
      ]);
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function orderedBlocks(array $plan): array
  {
    return collect($plan['blocks'] ?? [])
      ->filter(fn ($block): bool => is_array($block))
      ->sortBy(fn (array $block): int => (int) ($block['order'] ?? 0))
      ->values()
      ->all();
  }

  private function blockPayload(array $block, Page $page, PageSlot $slot, int $sortOrder, ?Block $parent = null): ?array
  {
    $converterSlug = $this->blockSlug($block);
    $blockTypeSlug = $this->createdBlockSlug($converterSlug);
    $blockType = BlockType::query()
      ->where('slug', $blockTypeSlug)
      ->where('status', 'published')
      ->first();

    if (! $blockType) {
      return null;
    }

    $translated = is_array($block['translated_fields'] ?? null) ? $block['translated_fields'] : [];
    $shared = is_array($block['shared_fields'] ?? null) ? $block['shared_fields'] : [];
    $sourceFragment = is_array($block['source_fragment'] ?? null) ? $block['source_fragment'] : [];
    $sourceHtml = trim((string) ($sourceFragment['html'] ?? ''));
    $sourceText = trim((string) ($sourceFragment['preview_text'] ?? ''));
    $data = [
      'page_id' => $page->id,
      'parent_id' => $parent?->id,
      'type' => $blockType->slug,
      'block_type_id' => $blockType->id,
      'source_type' => $blockType->source_type ?: 'static',
      'slot' => 'main',
      'slot_type_id' => $slot->slot_type_id,
      'sort_order' => $sortOrder,
      'title' => null,
      'subtitle' => null,
      'content' => null,
      'url' => null,
      'media_id' => null,
      'variant' => null,
      'meta' => null,
      'settings' => null,
      'status' => Page::STATUS_DRAFT,
      'is_system' => (bool) $blockType->is_system,
    ];

    return match ($converterSlug) {
      'header' => array_merge($data, [
        'title' => $this->stringField($translated, 'title') ?? $sourceText,
        'variant' => 'h2',
      ]),
      'plain_text' => array_merge($data, [
        'content' => $this->stringField($translated, 'content') ?? $sourceText,
      ]),
      'rich-text' => array_merge($data, [
        'content' => $this->stringField($translated, 'content') ?? ($sourceHtml ?: $sourceText),
      ]),
      'list' => array_merge($data, [
        'content' => $sourceHtml ?: $this->stringField($translated, 'content') ?? $sourceText,
      ]),
      'code' => array_merge($data, [
        'content' => $this->stringField($translated, 'code') ?? $this->stringField($translated, 'content') ?? $sourceText,
      ]),
      'table' => array_merge($data, [
        'content' => $this->tableContent($sourceHtml) ?: $sourceText,
        'variant' => 'header-row',
      ]),
      'quote' => array_merge($data, [
        'content' => $this->stringField($translated, 'content') ?? $sourceText,
        'variant' => 'default',
      ]),
      'html' => array_merge($data, [
        'content' => $this->stringField($translated, 'html') ?? $sourceHtml,
      ]),
      'button_link' => array_merge($data, [
        'title' => $this->stringField($translated, 'label') ?? $sourceText ?: 'Open link',
        'settings' => $this->settingsJson([
          'url' => $this->safeUrl($shared['url'] ?? null),
          'target' => ($shared['target'] ?? '_self') === '_blank' ? '_blank' : '_self',
        ]),
      ]),
      'callout' => array_merge($data, [
        'title' => null,
        'content' => $this->stringField($translated, 'content') ?? $sourceText,
        'variant' => 'info',
      ]),
      'alert' => array_merge($data, [
        'title' => $this->stringField($translated, 'title'),
        'content' => $this->stringField($translated, 'content') ?? $sourceText,
        'variant' => 'info',
      ]),
      'section' => array_merge($data, [
        'settings' => $this->settingsJson([
          'layout_name' => $this->layoutName($block, 'Converted section'),
          'spacing' => $this->allowedValue($shared['spacing'] ?? null, ['sm', 'md', 'lg', 'xl']),
        ]),
      ]),
      'content_header' => array_merge($data, [
        'title' => $this->firstString($translated, ['title', 'headline', 'heading']) ?? $sourceText,
        'subtitle' => $this->firstString($translated, ['subtitle', 'intro_text', 'intro', 'body', 'content']),
        'meta' => $this->metaItemsJson($translated['meta'] ?? $shared['meta_items'] ?? $shared['meta'] ?? null),
        'settings' => $this->settingsJson([
          'alignment' => $this->allowedValue($shared['alignment'] ?? null, ['left', 'center']),
        ]),
      ]),
      'hero' => array_merge($data, [
        'title' => $this->firstString($translated, ['title', 'headline', 'heading']) ?? $sourceText,
        'subtitle' => $this->firstString($translated, ['eyebrow', 'label']),
        'content' => $this->firstString($translated, ['body', 'content', 'intro', 'subtitle']),
        'variant' => $this->allowedValue($shared['variant'] ?? null, ['default', 'muted', 'accent', 'soft']) ?? 'default',
        'settings' => $this->settingsJson([
          'layout' => $this->allowedValue($shared['layout'] ?? null, ['left', 'centered']),
          'title_tag' => $this->allowedValue($shared['title_tag'] ?? null, ['h1', 'h2', 'h3']),
        ]),
      ]),
      'cta' => array_merge($data, [
        'title' => $this->firstString($translated, ['title', 'headline', 'heading']) ?? $sourceText,
        'subtitle' => $this->firstString($translated, ['eyebrow', 'label']),
        'content' => $this->firstString($translated, ['body', 'content', 'intro', 'subtitle']),
        'variant' => $this->allowedValue($shared['variant'] ?? null, ['default', 'muted', 'accent', 'soft']) ?? 'default',
      ]),
      'card' => array_merge($data, [
        'settings' => $this->settingsJson([
          'layout_name' => $this->layoutName($block, 'Converted card'),
        ]),
      ]),
      'card_header', 'card_body', 'card_footer' => array_merge($data, [
        'settings' => $this->settingsJson([
          'layout_name' => $this->layoutName($block, str($converterSlug)->replace('_', ' ')->title()->toString()),
        ]),
      ]),
      'button' => array_merge($data, [
        'title' => $this->firstString($translated, ['label', 'title']) ?? $sourceText ?: 'Open link',
        'url' => $this->safeUrl($shared['url'] ?? null),
        'subtitle' => ($shared['target'] ?? '_self') === '_blank' ? '_blank' : '_self',
        'variant' => $this->allowedValue($shared['variant'] ?? null, ['primary', 'secondary', 'ghost', 'link']) ?? 'primary',
      ]),
      'accordion' => array_merge($data, [
        'title' => $this->firstString($translated, ['title', 'heading']),
        'content' => $this->firstString($translated, ['content', 'intro', 'subtitle']),
      ]),
      'accordion_item' => array_merge($data, [
        'title' => $this->firstString($translated, ['title', 'label']) ?? $sourceText,
        'content' => $this->firstString($translated, ['content', 'body']),
      ]),
      default => null,
    };
  }

  private function blockSlug(array $block): string
  {
    return trim((string) ($block['block_slug'] ?? $block['block_type'] ?? ''));
  }

  private function createdBlockSlug(string $converterSlug): string
  {
    return match ($converterSlug) {
      'list' => 'rich-text',
      'callout' => 'alert',
      'accordion_item' => 'faq',
      default => $converterSlug,
    };
  }

  private function normalizedSlug(string $path): string
  {
    return trim($path, '/');
  }

  private function stringField(array $fields, string $key): ?string
  {
    $value = trim((string) ($fields[$key] ?? ''));

    return $value !== '' ? $value : null;
  }

  private function firstString(array $fields, array $keys): ?string
  {
    foreach ($keys as $key) {
      $value = $this->stringField($fields, $key);

      if ($value !== null) {
        return $value;
      }
    }

    return null;
  }

  private function allowedValue(mixed $value, array $allowed): ?string
  {
    $value = trim((string) $value);

    return in_array($value, $allowed, true) ? $value : null;
  }

  private function layoutName(array $block, string $fallback): string
  {
    $translated = is_array($block['translated_fields'] ?? null) ? $block['translated_fields'] : [];
    $shared = is_array($block['shared_fields'] ?? null) ? $block['shared_fields'] : [];

    return $this->firstString($shared, ['layout_name', 'name', 'label'])
      ?? $this->firstString($translated, ['title', 'headline', 'heading', 'label'])
      ?? $fallback;
  }

  private function metaItemsJson(mixed $value): ?string
  {
    $items = is_array($value) ? $value : [$value];
    $items = collect($items)
      ->map(fn ($item): string => trim((string) $item))
      ->filter()
      ->values()
      ->all();

    return $items === [] ? null : json_encode($items, JSON_UNESCAPED_SLASHES);
  }

  private function settingsJson(array $settings): ?string
  {
    $settings = array_filter($settings, fn ($value): bool => $value !== null && $value !== '' && $value !== []);

    return $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES);
  }

  private function safeUrl(mixed $value): ?string
  {
    return Block::safePublicUrl($value);
  }

  private function tableContent(string $html): ?string
  {
    if ($html === '') {
      return null;
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $rows = [];

    foreach ((new DOMXPath($document))->query('//tr') ?: [] as $row) {
      if (! $row instanceof DOMElement) {
        continue;
      }

      $cells = [];

      foreach ((new DOMXPath($document))->query('./th|./td', $row) ?: [] as $cell) {
        $cells[] = trim(preg_replace('/\s+/', ' ', (string) $cell->textContent) ?: '');
      }

      $cells = array_values(array_filter($cells, fn (string $cell): bool => $cell !== ''));

      if ($cells !== []) {
        $rows[] = implode(' | ', $cells);
      }
    }

    return $rows === [] ? null : implode("\n", $rows);
  }

  /**
   * @param  array<int, array<string, mixed>>  $blocks
   * @return array<string, array<int, array<string, mixed>>>
   */
  private function childrenByParentKey(array $blocks): array
  {
    $children = [];

    foreach ($blocks as $block) {
      $parentKey = isset($block['parent_key']) ? trim((string) $block['parent_key']) : '';

      if ($parentKey !== '') {
        $children[$parentKey][] = $block;
      }
    }

    return $children;
  }

  /**
   * @param  array<string, array<int, array<string, mixed>>>  $childrenByParentKey
   */
  private function hasCreatableCardChildren(string $blockKey, array $childrenByParentKey): bool
  {
    foreach ($childrenByParentKey[$blockKey] ?? [] as $child) {
      $childSlug = $this->blockSlug($child);

      if (! in_array($childSlug, ['card_header', 'card_body', 'card_footer'], true)) {
        continue;
      }

      if ($this->publishedBlockType($this->createdBlockSlug($childSlug))) {
        return true;
      }
    }

    return false;
  }

  /**
   * @param  array<string, array<int, array<string, mixed>>>  $childrenByParentKey
   */
  private function hasCreatableAccordionChildren(string $blockKey, array $childrenByParentKey): bool
  {
    if (! $this->publishedBlockType('faq')) {
      return false;
    }

    foreach ($childrenByParentKey[$blockKey] ?? [] as $child) {
      if ($this->blockSlug($child) !== 'accordion_item') {
        continue;
      }

      $translated = is_array($child['translated_fields'] ?? null) ? $child['translated_fields'] : [];

      if ($this->firstString($translated, ['title', 'label']) !== null && $this->firstString($translated, ['content', 'body']) !== null) {
        return true;
      }
    }

    return false;
  }

  private function parentAcceptsChild(Block $parent, string $converterSlug): bool
  {
    return $parent->canAcceptChildType($this->createdBlockSlug($converterSlug));
  }

  private function hasActionLikeFields(array $block): bool
  {
    $translated = is_array($block['translated_fields'] ?? null) ? $block['translated_fields'] : [];
    $shared = is_array($block['shared_fields'] ?? null) ? $block['shared_fields'] : [];

    return $this->firstString($translated, ['primary_cta_label', 'secondary_cta_label', 'action_label', 'button_label']) !== null
      || $this->firstString($shared, ['primary_cta_url', 'secondary_cta_url', 'action_url', 'url']) !== null;
  }

  private function publishedBlockType(string $slug): ?BlockType
  {
    return BlockType::query()
      ->where('slug', $slug)
      ->where('status', 'published')
      ->first();
  }

  /**
   * @return array<int, string>
   */
  private function stringList(mixed $value): array
  {
    if (! is_array($value)) {
      return [];
    }

    return collect($value)
      ->map(fn ($item): string => trim((string) $item))
      ->filter()
      ->values()
      ->all();
  }

  public static function supportedBlockSlugs(): array
  {
    return self::SUPPORTED_BLOCK_SLUGS;
  }

  public static function skippedBlockSlugs(): array
  {
    return self::SKIPPED_BLOCK_SLUGS;
  }
}
