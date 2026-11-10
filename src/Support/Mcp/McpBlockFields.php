<?php

namespace WebBlocks\Cms\Support\Mcp;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Support\Blocks\BlockMediaCompatibility;
use WebBlocks\Cms\Support\Blocks\BlockTranslationRegistry;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeApiAuthoringPolicy;

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
  ];

  private const COPY_LIMITS = ['title' => 255, 'subtitle' => 255, 'eyebrow' => 255, 'caption' => 255, 'alt_text' => 255, 'content' => 8000];

  public function __construct(
    private readonly BlockTranslationResolver $translations,
    private readonly BlockTranslationRegistry $registry,
    private readonly BlockTypeApiAuthoringPolicy $authoring,
  ) {}

  public function textFields(Block|string $block): array
  {
    $type = $block instanceof Block ? $block->typeSlug() : $block;

    if ($type === 'image') {
      return array_keys(BlockTranslationRegistry::IMAGE_BLOCK_FIELDS);
    }

    $fields = self::COPY[$type] ?? [];

    return $fields === [] ? [] : array_values(array_intersect($fields, $this->registry->translatedFieldsFor($block)));
  }

  public function textLimits(): array
  {
    return array_intersect_key(self::COPY_LIMITS, array_flip(BlockTranslationRegistry::coreFields()));
  }

  public function acceptsImage(Block $block): bool
  {
    return in_array(Media::KIND_IMAGE, BlockMediaCompatibility::DIRECT_KINDS[$block->typeSlug()] ?? [], true);
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
      $key = BlockTranslationRegistry::IMAGE_BLOCK_FIELDS[$field] ?? $field;
      $values[$field] = $resolved->{$key};
    }

    return $values;
  }

  public function translationPayload(Block $block, Locale $locale, array $changes): array
  {
    $resolved = $this->translations->resolve($block, $locale);
    if ($block->typeSlug() === 'image') {
      $changes = array_combine(array_map(fn (string $key) => BlockTranslationRegistry::IMAGE_BLOCK_FIELDS[$key], array_keys($changes)), $changes);
    }

    return $changes + $resolved->only(BlockTranslationRegistry::TEXT_FIELDS);
  }
}
