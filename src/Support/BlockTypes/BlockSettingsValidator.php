<?php

namespace WebBlocks\Cms\Support\BlockTypes;

/** Validates only incoming changes. Reads, cloning and revision restoration stay lossless. */
final class BlockSettingsValidator
{
  public function __construct(private readonly BlockSettingsSchema $schemas) {}

  public function normalize(string $type, array $incoming, string $path, array &$errors, string $operation = 'create', array $existing = []): array
  {
    $schema = $this->schemas->forType($type);
    if ($schema['mode'] === 'legacy_plugin') {
      return $incoming;
    }
    if ($schema['mode'] === 'invalid_plugin_schema') {
      $errors[] = ['path' => $path, 'message' => __('webblocks-cms::block-settings.invalid_schema'), 'code' => 'invalid_block_settings_schema'];

      return $incoming;
    }
    $newValues = array_filter($incoming, fn ($value, $key) => ! array_key_exists($key, $existing) || $existing[$key] !== $value, ARRAY_FILTER_USE_BOTH);
    $nodes = 0;
    if (! $this->bounded($newValues, 0, $nodes) || count($newValues) > 128 || strlen(json_encode($newValues, JSON_PARTIAL_OUTPUT_ON_ERROR)) > 262144) {
      $errors[] = ['path' => $path, 'message' => __('webblocks-cms::block-settings.invalid'), 'code' => 'invalid_block_settings'];

      return $incoming;
    }
    foreach ($incoming as $key => $value) {
      $field = $schema['fields'][$key] ?? null;
      // Retaining historical values is allowed only after comparing server-owned data.
      if ($operation !== 'create' && array_key_exists($key, $existing) && $existing[$key] === $value) {
        continue;
      }
      if ($field === null || ! $field['writes'][$operation]) {
        $errors[] = ['path' => $path.'.'.$key, 'message' => __('webblocks-cms::block-settings.unsupported'), 'code' => 'unsupported_block_settings_fields'];

        continue;
      }
      if (is_string($value) && array_key_exists($value, $field['aliases'])) {
        $value = $field['aliases'][$value];
      }
      $valid = false;
      $normalized = BlockSettingsSchema::normalizeValue($value, $field['rule'], $valid);
      if (! $valid || ($normalized === null && ! $field['nullable'])) {
        $errors[] = ['path' => $path.'.'.$key, 'message' => __('webblocks-cms::block-settings.invalid'), 'code' => 'invalid_block_settings'];
      } else {
        $incoming[$key] = in_array($normalized, $field['clear_values'], true) ? null : $normalized;
      }
    }

    return $incoming;
  }

  private function bounded(mixed $value, int $depth, int &$nodes): bool
  {
    if ($depth > 32 || ++$nodes > 4096) {
      return false;
    }
    if (is_array($value)) {
      foreach ($value as $child) {
        if (! $this->bounded($child, $depth + 1, $nodes)) {
          return false;
        }
      }
    }

    return true;
  }
}
