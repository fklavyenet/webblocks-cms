<?php

namespace WebBlocks\Cms\Support\Blocks;

use Illuminate\Support\Str;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Locales\LocaleResolver;

class BlockAdminSummary
{
  public function present(Block $block, int $labelMaxLength = 80, int $summaryMaxLength = 120): array
  {
    [$label, $summary] = $this->linesFor($block);

    $resolvedLabel = $this->truncate($label, $labelMaxLength) ?? $block->typeName();
    $resolvedSummary = $this->truncate($summary, $summaryMaxLength);

    if ($resolvedSummary !== null && $resolvedSummary === $resolvedLabel) {
      $resolvedSummary = null;
    }

    return [
      'label' => $resolvedLabel,
      'summary' => $resolvedSummary,
    ];
  }

  public function label(Block $block, int $maxLength = 80): string
  {
    return $this->present($block, $maxLength, self::summaryMaxLength())['label'];
  }

  public function primary(Block $block, int $maxLength = 80): ?string
  {
    [$label] = $this->linesFor($block);

    return $this->truncate($label, $maxLength);
  }

  public function summary(Block $block, int $maxLength = 120): ?string
  {
    return $this->present($block, self::labelMaxLength(), $maxLength)['summary'];
  }

  public static function labelMaxLength(): int
  {
    return 80;
  }

  public static function summaryMaxLength(): int
  {
    return 120;
  }

  private function linesFor(Block $block): array
  {
    return match ($block->typeSlug()) {
      'rich-text', 'plain_text', 'text' => [$this->content($block), null],
      'header' => [$this->title($block) ?? $this->subtitle($block), null],
      'content_header', 'content-header' => $this->contentHeaderLines($block),
      'code' => $this->codeLines($block),
      'button_link', 'button-link', 'button' => $this->buttonLines($block),
      'card', 'alert' => $this->contentBlockLines($block),
      'link-list-item', 'link_list_item' => $this->linkListItemLines($block),
      'section', 'container', 'cluster', 'grid' => $this->layoutLines($block),
      default => $this->fallbackLines($block),
    };
  }

  private function contentHeaderLines(Block $block): array
  {
    $title = $this->title($block);
    $intro = $this->content($block);

    if ($title !== null) {
      return [$title, $intro ?? $this->subtitle($block)];
    }

    return [$intro ?? $this->subtitle($block), null];
  }

  private function codeLines(Block $block): array
  {
    $title = $this->title($block);
    $language = $this->codeLanguage($block);
    $firstLine = $this->firstCodeLine($block);
    $summary = $this->joinParts([$language, $firstLine], ' | ');

    if ($title !== null) {
      return [$title, $summary];
    }

    return [$firstLine !== null ? $summary : null, null];
  }

  private function buttonLines(Block $block): array
  {
    $label = $this->title($block) ?? $this->subtitle($block) ?? $this->buttonUrl($block) ?? 'Button';

    return [$label, $this->buttonUrl($block)];
  }

  private function contentBlockLines(Block $block): array
  {
    $label = $this->title($block) ?? $this->content($block);
    $summary = $block->children->isNotEmpty()
      ? $block->children->count().' '.Str::plural('child block', $block->children->count())
      : ($label === ($this->title($block) ?? null) ? $this->content($block) : null);

    return [$label, $summary];
  }

  private function linkListItemLines(Block $block): array
  {
    $label = $this->title($block) ?? $this->linkListItemUrl($block) ?? 'Link list item';
    $summary = $this->joinParts([
      $this->subtitle($block),
      $this->linkListItemUrl($block),
    ], ' | ');

    return [$label, $summary];
  }

  private function layoutLines(Block $block): array
  {
    return [null, null];
  }

  private function fallbackLines(Block $block): array
  {
    $candidates = [
      $this->title($block),
      $this->subtitle($block),
      $this->content($block),
      $this->sanitize($block->setting('label')),
    ];

    $label = collect($candidates)->first(fn (?string $value) => $value !== null);
    $labelIndex = array_search($label, $candidates, true);
    $summary = collect(array_slice($candidates, is_int($labelIndex) ? $labelIndex + 1 : 0))
      ->first(fn (?string $value) => $value !== null);

    return [$label, $summary];
  }

  private function title(Block $block): ?string
  {
    return $this->field($block, 'title');
  }

  private function subtitle(Block $block): ?string
  {
    return $this->field($block, 'subtitle');
  }

  private function content(Block $block): ?string
  {
    return $this->field($block, 'content');
  }

  private function field(Block $block, string $field, bool $stripHtml = true): ?string
  {
    return $this->sanitize($this->rawField($block, $field), $stripHtml);
  }

  private function rawField(Block $block, string $field): ?string
  {
    $value = $block->{$field} ?? null;

    if ($value !== null && trim((string) $value) !== '') {
      return (string) $value;
    }

    // Slot editors pass locale-resolved block clones. A missing field on one of
    // those clones is intentionally missing for that resolved locale; looking up
    // the default translation again could produce a summary from another locale.
    if ($block->getAttribute('resolved_locale_code') !== null) {
      return null;
    }

    if ($block->translationFamily() !== 'text') {
      return null;
    }

    $translations = $block->relationLoaded('textTranslations')
      ? $block->getRelation('textTranslations')
      : $block->textTranslations()->get();
    $defaultLocaleId = app(LocaleResolver::class)->default()->id;
    $translation = $translations->firstWhere('locale_id', $defaultLocaleId) ?? $translations->first();
    $translated = $translation?->{$field};

    if ($translated === null || trim((string) $translated) === '') {
      return null;
    }

    return (string) $translated;
  }

  private function buttonUrl(Block $block): ?string
  {
    return $this->sanitize(match ($block->typeSlug()) {
      'button_link', 'button-link' => $block->buttonLinkUrl(),
      default => $block->url,
    }, false);
  }

  private function linkListItemUrl(Block $block): ?string
  {
    return $this->sanitize($block->linkListItemUrl() ?? $block->url, false);
  }

  private function codeLanguage(Block $block): ?string
  {
    $language = trim((string) ($block->setting('language') ?? $block->setting('lang') ?? ''));

    return $language !== '' ? Str::upper($language) : null;
  }

  private function firstCodeLine(Block $block): ?string
  {
    $content = $this->rawField($block, 'content');

    if ($content === null) {
      return null;
    }

    foreach (preg_split('/\R/u', $content) ?: [] as $line) {
      $resolved = $this->sanitize($line, false);

      if ($resolved !== null) {
        return $resolved;
      }
    }

    return null;
  }

  private function sanitize(mixed $value, bool $stripHtml = true): ?string
  {
    if ($value === null) {
      return null;
    }

    $resolved = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if ($stripHtml) {
      $resolved = strip_tags($resolved);
    }

    $resolved = html_entity_decode($resolved, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $resolved = preg_replace('/\s+/u', ' ', $resolved) ?? $resolved;
    $resolved = trim($resolved);

    return $resolved !== '' ? $resolved : null;
  }

  private function truncate(?string $value, int $maxLength): ?string
  {
    if ($value === null) {
      return null;
    }

    if (mb_strlen($value) <= $maxLength) {
      return $value;
    }

    if ($maxLength <= 3) {
      return mb_substr($value, 0, $maxLength);
    }

    return rtrim(mb_substr($value, 0, $maxLength - 3)).'...';
  }

  private function joinParts(array $parts, string $separator): ?string
  {
    $resolved = collect($parts)
      ->filter(fn (?string $value) => $value !== null)
      ->implode($separator);

    return $resolved !== '' ? $resolved : null;
  }
}
