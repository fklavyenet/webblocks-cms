<?php

namespace WebBlocks\Cms\Support\Blocks;

use WebBlocks\Cms\Models\Block;

final class MobileContentOrder
{
  public const OPTIONS = ['default', 'media_first', 'text_first'];

  public static function reverses(?string $order, bool $firstIsMedia): bool
  {
    return match ($order) {
      'media_first' => ! $firstIsMedia,
      'text_first' => $firstIsMedia,
      default => false,
    };
  }

  public static function reversesPair(?string $order, Block $first, Block $second): bool
  {
    if (! in_array($order, ['media_first', 'text_first'], true)) {
      return false;
    }

    $firstIsMedia = $first->hasMediaTextVisualContent();

    return self::reverses($order, $firstIsMedia) && $firstIsMedia !== $second->hasMediaTextVisualContent();
  }
}
