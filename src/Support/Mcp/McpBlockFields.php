<?php

namespace WebBlocks\Cms\Support\Mcp;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeApiAuthoringPolicy;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;

final class McpBlockFields
{
  private const COPY = [
    'header' => ['title'],
    'hero' => ['title', 'subtitle', 'content'],
    'content_header' => ['title', 'eyebrow', 'subtitle'],
    'plain_text' => ['content'],
    'rich-text' => ['content'],
    'alert' => ['title', 'content'],
    'cta' => ['title', 'subtitle', 'content'],
    'button' => ['title'],
    'button_link' => ['title'],
    'image' => ['caption', 'alt_text'],
  ];

  private const IMAGE_KEYS = ['caption' => 'title', 'alt_text' => 'subtitle'];

  public function __construct(
    private readonly BlockTranslationResolver $translations,
    private readonly BlockTypeApiAuthoringPolicy $authoring,
  ) {}

  public function textFields(Block $block): array
  {
    return self::COPY[$block->typeSlug()] ?? [];
  }

  public function acceptsImage(Block $block): bool
  {
    return in_array(Media::KIND_IMAGE, InternalContentApiOperations::DIRECT_MEDIA_KIND_RULES[$block->typeSlug()] ?? [], true);
  }

  public function supports(Block $block): bool
  {
    return ($this->textFields($block) !== [] || $this->acceptsImage($block))
      && $this->authoring->isApiWritable($block->typeSlug());
  }

  public function values(Block $block, Locale $locale): array
  {
    $resolved = $this->translations->resolve($block, $locale);
    $values = [];
    foreach ($this->textFields($block) as $field) {
      $key = self::IMAGE_KEYS[$field] ?? $field;
      $values[$field] = $resolved->{$key};
    }

    return $values;
  }

  public function translationPayload(Block $block, Locale $locale, array $changes): array
  {
    $resolved = $this->translations->resolve($block, $locale);
    if ($block->typeSlug() === 'image') {
      $changes = array_combine(array_map(fn (string $key) => self::IMAGE_KEYS[$key], array_keys($changes)), $changes);
    }

    return $changes + $resolved->only(['title', 'eyebrow', 'subtitle', 'content', 'meta']);
  }
}
