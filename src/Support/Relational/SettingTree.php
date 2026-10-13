<?php

namespace WebBlocks\Cms\Support\Relational;

use RuntimeException;
use stdClass;

final class SettingTree
{
  public static function encode(mixed $value, bool $block, string $attribute, ?callable $selector = null, bool $expandSerialized = false): array
  {
    $nodes = [];
    $records = [];
    $visit = function (mixed $value, ?int $parent, int $ordinal, mixed $key, array $path, array $positions, array $ancestors) use (&$visit, &$nodes, &$records, $block, $attribute, $selector, $expandSerialized): void {
      $index = count($nodes);
      $node = [
        'node_index' => $index, 'parent_index' => $parent, 'ordinal' => $ordinal,
        'key_kind' => $parent === null ? null : (is_int($key) ? 'integer' : 'string'),
        'key_text' => is_string($key) ? $key : null, 'key_integer' => is_int($key) ? $key : null,
        'kind' => null, 'ref_table' => null, 'ref_index' => null, 'ref_field' => null,
        'value' => null, 'value_kind' => null, 'value_text' => null, 'value_integer' => null, 'value_decimal' => null, 'value_boolean' => null,
      ];
      $serialized = false;
      if ($expandSerialized && is_string($value) && in_array($key, ['settings', 'meta'], true)) {
        $decoded = json_decode($value);
        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || $decoded instanceof stdClass)) {
          $serialized = true;
          $value = $decoded;
        }
      }
      $binding = $selector !== null ? $selector($path, $positions, $ancestors, $index) : SettingDefinitions::binding($block, $attribute, $path, $positions, $index);
      $packed = $binding === null ? null : SettingScalar::pack($binding[2], $binding[3], $value);
      if ($binding !== null && $binding[0] === 'application_reference' && is_string($value) && mb_strlen($value) > 190) {
        $packed = null;
      }
      if ($packed !== null) {
        [$table, $recordIndex, $field, $type, $recordKey] = $binding;
        $node = array_replace($node, ['kind' => 'column', 'ref_table' => $table, 'ref_index' => $recordIndex, 'ref_field' => $field]);
        if ($table !== 'application_reference') {
          $records[$table][$recordIndex] = array_replace($records[$table][$recordIndex] ?? ['item_index' => $recordIndex, 'item_key' => $recordKey], $packed);
        }
      } elseif (is_array($value) || $value instanceof stdClass) {
        $node['kind'] = $serialized ? 'json' : (is_array($value) ? 'array' : 'object');
        if ($serialized) {
          $node['value_kind'] = is_array($value) ? 'array' : 'object';
        }
      } else {
        $scalar = SettingScalar::pack('value', 'variant', $value);
        if ($scalar === null) {
          throw new RuntimeException('A setting contains an unsupported value type.');
        }
        $node = array_replace($node, $scalar, ['kind' => 'value']);
      }
      $nodes[] = $node;
      if (in_array($node['kind'], ['array', 'object', 'json'], true)) {
        if (! $selector && ! $block && $attribute === 'settings_schema' && count($path) === 1) {
          $records['application_setting_fields'][$positions[0]] ??= ['item_index' => $positions[0], 'item_key' => (string) $path[0]];
        }
        $position = 0;
        foreach ($value as $childKey => $child) {
          $visit($child, $index, $position, $childKey, [...$path, $childKey], [...$positions, $position], [...$ancestors, $index]);
          $position++;
        }
      }
      if ($packed !== null && $binding[0] === 'application_setting_options') {
        $records['application_setting_options'][$index]['field_index'] = $positions[0];
        $records['application_setting_options'][$index]['position'] = $ordinal;
      }
    };
    $visit($value, null, 0, null, [], [], []);

    return [$nodes, $records];
  }

  public static function decode(array $nodes, array $records, ?object $applicationReference, ?array $definitions = null): mixed
  {
    if (! isset($nodes[0]) || $nodes[0]->parent_index !== null) {
      throw new RuntimeException('Relational setting data is incomplete.');
    }
    $children = [];
    foreach ($nodes as $node) {
      if ($node->parent_index !== null) {
        if ((int) $node->parent_index >= (int) $node->node_index) {
          throw new RuntimeException('Relational setting data has an invalid parent.');
        }
        $children[(int) $node->parent_index][] = $node;
      }
    }
    $definitions ??= SettingDefinitions::tables();
    $read = function (object $node) use (&$read, $children, $records, $definitions, $applicationReference): mixed {
      if ($node->kind === 'value') {
        return SettingScalar::unpack($node, 'value', 'variant');
      }
      if ($node->kind === 'column') {
        if ($node->ref_table === 'application_reference') {
          if ($applicationReference === null) {
            throw new RuntimeException('Relational application reference is missing.');
          }

          return SettingScalar::unpack($applicationReference, 'application_handle', 'text');
        }
        $row = $records[$node->ref_table][(int) $node->ref_index] ?? null;
        $type = $definitions[$node->ref_table][$node->ref_field] ?? null;
        if ($row === null || $type === null) {
          throw new RuntimeException('Relational setting data is incomplete.');
        }

        return SettingScalar::unpack($row, $node->ref_field, $type);
      }
      if (! in_array($node->kind, ['array', 'object', 'json'], true)) {
        throw new RuntimeException('Relational setting data has an invalid type.');
      }
      $object = $node->kind === 'object' || ($node->kind === 'json' && $node->value_kind === 'object');
      $value = $object ? new stdClass : [];
      foreach ($children[(int) $node->node_index] ?? [] as $child) {
        $key = $child->key_kind === 'integer' ? (int) $child->key_integer : $child->key_text;
        if ($value instanceof stdClass) {
          $value->{(string) $key} = $read($child);
        } else {
          $value[$key] = $read($child);
        }
      }

      return $node->kind === 'json' ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : $value;
    };

    return $read($nodes[0]);
  }
}
