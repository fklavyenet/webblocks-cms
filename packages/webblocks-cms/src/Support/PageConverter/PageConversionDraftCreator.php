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
  ];

  private const SKIPPED_BLOCK_SLUGS = [
    'image',
    'gallery',
    'card',
    'section',
    'content_header',
    'hero',
    'cta',
    'accordion',
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

      foreach ($this->orderedBlocks($plan) as $block) {
        $warnings = array_merge($warnings, $this->stringList($block['warnings'] ?? []));
        $converterSlug = $this->blockSlug($block);

        if (! in_array($converterSlug, self::SUPPORTED_BLOCK_SLUGS, true)) {
          $skippedSuggestionCount++;
          $warnings[] = 'Skipped unsupported Page Converter suggestion ['.$converterSlug.'].';

          continue;
        }

        $payload = $this->blockPayload($block, $page, $mainSlot, $createdBlockCount);

        if ($payload === null) {
          $skippedSuggestionCount++;
          $warnings[] = 'Skipped Page Converter suggestion ['.$converterSlug.'] because it could not be converted safely.';

          continue;
        }

        $this->blockPayloadWriter->save(new Block, $page, $payload, $locale->code);
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

  private function blockPayload(array $block, Page $page, PageSlot $slot, int $createdBlockCount): ?array
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
      'parent_id' => null,
      'type' => $blockType->slug,
      'block_type_id' => $blockType->id,
      'source_type' => $blockType->source_type ?: 'static',
      'slot' => 'main',
      'slot_type_id' => $slot->slot_type_id,
      'sort_order' => $createdBlockCount,
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
