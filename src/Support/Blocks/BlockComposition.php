<?php

namespace WebBlocks\Cms\Support\Blocks;

final class BlockComposition
{
  public const DEFAULT_SPLIT_RESPONSIVE = 'stack';

  public static function normalize(array $settings, string $type): array
  {
    if ($type === 'columns' && ! array_key_exists('variant', $settings)) {
      $settings['variant'] = 'plain';
    }

    if ($type === 'split' && ! array_key_exists('responsive', $settings)) {
      $settings['responsive'] = self::DEFAULT_SPLIT_RESPONSIVE;
    }

    if ($type === 'grid') {
      $settings = self::normalizeGrid($settings);
    }

    if ($type === 'hero' && array_key_exists('layout', $settings)) {
      $layout = $settings['layout'];
      if (! in_array($layout, ['left', 'centered', 'split', 'full-bleed'], true)) {
        unset($settings['layout']);
      }
    }

    if ($type === 'section' && array_key_exists('flow', $settings)) {
      $flow = $settings['flow'];
      if (! in_array($flow, ['offset-up', 'overlap-previous'], true)) {
        unset($settings['flow']);
      }
    }

    return $settings;
  }

  public static function normalizeGrid(array $settings): array
  {
    if (($settings['alternate_media_text_sections'] ?? false) !== true) {
      unset($settings['alternate_media_text_sections'], $settings['alternate_start']);
    }
    $ratio = $settings['ratio'] ?? null;
    if (($settings['columns'] ?? null) !== '2' || ! in_array($ratio, ['lead-left', 'lead-right'], true)) {
      unset($settings['ratio']);
    }

    return $settings;
  }
}
