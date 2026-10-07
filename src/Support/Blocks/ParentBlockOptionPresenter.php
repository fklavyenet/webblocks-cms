<?php

namespace WebBlocks\Cms\Support\Blocks;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\Block;

class ParentBlockOptionPresenter
{
  public function __construct(private readonly BlockAdminSummary $summary) {}

  /**
   * Present eligible destinations in tree order using the already resolved slot
   * blocks. Excluded ancestors still contribute to each destination's path.
   */
  public function present(Collection $blocks, Collection $candidates): Collection
  {
    $byId = $blocks->keyBy('id');
    $children = $blocks->groupBy(fn (Block $block) => (int) $block->parent_id)
      ->map(fn (Collection $siblings) => $siblings->sortBy([['sort_order', 'asc'], ['id', 'asc']]));
    $eligibleIds = $candidates->keyBy('id');
    $profiles = [];
    $profiling = [];

    // Cache subtree context once. Headings identify repeated cards more usefully
    // than a shared product name or the first image's filename.
    $profile = function (Block $block) use (&$profile, &$profiles, &$profiling, $children): array {
      if (isset($profiles[$block->id])) {
        return $profiles[$block->id];
      }

      if (isset($profiling[$block->id])) {
        return ['heading' => null, 'content' => null];
      }

      $profiling[$block->id] = true;
      $content = $this->summary->primary($block, 60);
      $heading = in_array($block->typeSlug(), ['header', 'content_header', 'content-header'], true) ? $content : null;

      foreach ($children->get((int) $block->id, collect()) as $child) {
        $childProfile = $profile($child);
        $heading ??= $childProfile['heading'];
        $content ??= $childProfile['content'];
      }

      unset($profiling[$block->id]);

      return $profiles[$block->id] = ['heading' => $heading, 'content' => $content];
    };
    $options = collect();
    $visited = [];
    $walk = function (Block $block, array $path = []) use (&$walk, &$visited, $options, $eligibleIds, $children, $profile): void {
      if (isset($visited[$block->id])) {
        return;
      }

      $visited[$block->id] = true;
      $identity = $block->typeName().' #'.$block->id;

      if ($eligibleIds->has($block->id)) {
        $context = $profile($block);
        $name = $this->clean($block->layoutAdminName())
          ?? $this->summary->primary($block, 60)
          ?? $context['heading']
          ?? $context['content'];
        $label = '#'.$block->id.' '.$block->typeName();

        if ($name !== null && $name !== $block->typeName()) {
          $label .= ' — '.$name;
        }

        if ($path !== []) {
          $label .= ' ['.implode(' › ', $path).']';
        }

        $options->push(['id' => $block->id, 'label' => $label]);
      }

      foreach ($children->get((int) $block->id, collect()) as $child) {
        $walk($child, [...$path, $identity]);
      }
    };

    foreach ($blocks->filter(fn (Block $block) => ! $block->parent_id || ! $byId->has($block->parent_id))
      ->sortBy([['sort_order', 'asc'], ['id', 'asc']]) as $root) {
      $walk($root);
    }

    // Malformed legacy trees must not hide otherwise eligible options or loop.
    foreach ($blocks as $block) {
      $walk($block);
    }

    return $options;
  }

  private function clean(?string $value): ?string
  {
    $text = Str::squish(strip_tags(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

    return $text === '' ? null : Str::limit($text, 60);
  }
}
