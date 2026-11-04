<?php

namespace WebBlocks\Cms\Support\SystemPages;

use InvalidArgumentException;

final readonly class SystemPageDefinition
{
  public function __construct(public string $key, public string $labelKey, public array $routes = [], public array $blocks = [])
  {
    if (strlen($key) > 150 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*:[a-z0-9]+(?:-[a-z0-9]+)*$/D', $key)) {
      throw new InvalidArgumentException('Invalid system page key.');
    }
    $remaining = 40;
    $this->validateBlocks($blocks, $remaining);
  }

  private function validateBlocks(array $blocks, int &$remaining, int $depth = 0): void
  {
    if ($depth > 3 || ! array_is_list($blocks)) {
      throw new InvalidArgumentException('System Page defaults require a bounded list of native blocks.');
    }
    foreach ($blocks as $block) {
      if (--$remaining < 0 || ! is_array($block) || ! in_array($block['type'] ?? null, ['hero', 'header', 'plain_text', 'button_link'], true)
        || array_diff(array_keys($block), ['type', 'translations', 'settings', 'variant', 'children']) !== []) {
        throw new InvalidArgumentException('System Page defaults require native editorial blocks.');
      }
      if (! is_array($block['translations'] ?? null) || $block['translations'] === [] || ! is_array($block['settings'] ?? [])) {
        throw new InvalidArgumentException('System Page default text must use translation keys and native settings.');
      }
      foreach ($block['translations'] as $field => $translation) {
        if (! in_array($field, ['title', 'subtitle', 'eyebrow', 'content'], true) || ! is_string($translation)
          || ! preg_match('/^[a-z0-9-]+::[a-zA-Z0-9_.-]+$/D', $translation)) {
          throw new InvalidArgumentException('System Page default text requires namespaced translation keys.');
        }
      }
      foreach ($block['settings'] ?? [] as $field => $value) {
        if (! in_array($field, ['title_tag', 'layout', 'alignment', 'url', 'target'], true) || ! is_string($value)) {
          throw new InvalidArgumentException('System Page default settings must be native typed editorial fields.');
        }
      }
      if (! is_array($block['children'] ?? [])) {
        throw new InvalidArgumentException('System Page children must be native block declarations.');
      }
      if (($block['children'] ?? []) !== [] && ($block['type'] !== 'hero'
        || collect($block['children'])->contains(fn ($child) => ! is_array($child) || ($child['type'] ?? null) !== 'button_link'))) {
        throw new InvalidArgumentException('System Page Hero children must be native Button Link blocks.');
      }
      $this->validateBlocks($block['children'] ?? [], $remaining, $depth + 1);
    }
  }

  public function label(?string $locale = null): string
  {
    return __($this->labelKey, [], $locale);
  }
}
