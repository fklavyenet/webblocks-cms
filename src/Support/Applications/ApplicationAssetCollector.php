<?php

namespace WebBlocks\Cms\Support\Applications;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Block;

class ApplicationAssetCollector
{
  public function collect(Collection $slots): array
  {
    $definitions = $slots
      ->flatMap(fn (array $slot): Collection => $this->flatten(collect($slot['blocks'] ?? [])))
      ->filter(fn (Block $block): bool => $block->typeSlug() === 'application')
      ->map(fn (Block $block): ?ApplicationDefinition => $block->readyApplicationDefinition())
      ->filter()
      ->unique(fn (ApplicationDefinition $definition): string => $definition->handle)
      ->values();

    return [
      'css' => $definitions
        ->flatMap(fn (ApplicationDefinition $definition): array => $definition->assets['css'] ?? [])
        ->unique('path')
        ->values(),
      'head_js' => $definitions
        ->flatMap(fn (ApplicationDefinition $definition): array => $definition->assets['js'] ?? [])
        ->filter(fn (array $asset): bool => ($asset['load_position'] ?? 'body_end') === 'head')
        ->unique('path')
        ->values(),
      'body_end_js' => $definitions
        ->flatMap(fn (ApplicationDefinition $definition): array => $definition->assets['js'] ?? [])
        ->reject(fn (array $asset): bool => ($asset['load_position'] ?? 'body_end') === 'head')
        ->unique('path')
        ->values(),
      'has_applications' => $definitions->isNotEmpty(),
    ];
  }

  private function flatten(Collection $blocks): Collection
  {
    return $blocks->flatMap(function (Block $block): Collection {
      $children = $block->relationLoaded('children') ? $block->children : collect();

      return collect([$block])->concat($this->flatten($children));
    });
  }
}
