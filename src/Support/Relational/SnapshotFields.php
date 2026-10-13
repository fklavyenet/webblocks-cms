<?php

namespace WebBlocks\Cms\Support\Relational;

final class SnapshotFields
{
  public static function tables(): array
  {
    static $cached;
    if ($cached !== null) {
      return $cached;
    }
    $text = fn (string $names) => array_fill_keys(explode(' ', $names), 'text');
    $ints = fn (string $names) => array_fill_keys(explode(' ', $names), 'integer');
    $bools = fn (string $names) => array_fill_keys(explode(' ', $names), 'boolean');
    $tables = [
      'snapshot_headers' => ['schema_version' => 'integer', 'captured_at' => 'text'],
      'snapshot_pages' => $text('title slug page_type status published_at review_requested_at') + $ints('page_type_id layout_id'),
      'snapshot_source_pages' => $text('slug page_type') + $ints('slot_type_id'),
      'snapshot_shared_slots' => $text('name handle slot_name public_shell') + $bools('is_active'),
      'snapshot_page_translations' => $ints('locale_id og_image_media_id') + $text('name slug path seo_title seo_description seo_keywords list_excerpt og_title og_description'),
      'snapshot_slots' => $ints('slot_type_id shared_slot_id sort_order') + $text('source_type'),
      'snapshot_assets' => $text('type path load_position') + $ints('sort_order') + $bools('is_defer is_async is_module is_enabled'),
      'snapshot_blocks' => $ints('snapshot_id parent_snapshot_id block_type_id slot_type_id sort_order media_id') + $text('type source_type slot title subtitle content url variant status') + $bools('is_system'),
      'snapshot_block_media' => $ints('media_id position') + $text('role'),
      'snapshot_media_translations' => $ints('locale_id') + $text('alt_text caption overlay_title overlay_text'),
      'snapshot_text_translations' => $ints('locale_id') + $text('title eyebrow subtitle content'),
      'snapshot_button_translations' => $ints('locale_id') + $text('title url'),
      'snapshot_image_translations' => $ints('locale_id') + $text('caption alt_text url'),
      'snapshot_contact_translations' => $ints('locale_id') + $text('title content submit_label success_message consent_label'),
    ];
    foreach (['block_appearance_settings', 'block_media_settings', 'block_navigation_settings', 'block_content_settings', 'block_content_bindings', 'block_content_collections'] as $name) {
      $tables['snapshot_'.$name] = SettingDefinitions::tables()[$name];
    }
    $tables['snapshot_block_applications'] = ['application_handle' => 'text'];
    $tables['snapshot_application_values'] = ['value' => 'variant'];
    $tables['snapshot_page_settings'] = [];
    foreach (DocumentFields::page()['fields'] as [$column, $type]) {
      $tables['snapshot_page_settings'][$column] = $type === 'string' ? 'text' : $type;
    }

    return $cached = $tables;
  }

  public static function binding(array $path, array $positions, array $ancestors, int $nodeIndex): ?array
  {
    if (in_array($path[0] ?? null, ['page', 'source_page'], true) && ($path[1] ?? null) === 'settings') {
      $relative = implode('.', array_slice($path, 2));
      if (isset(DocumentFields::page()['fields'][$relative])) {
        [$column, $type] = DocumentFields::page()['fields'][$relative];

        return ['snapshot_page_settings', 0, $column, $type === 'string' ? 'text' : $type, null];
      }
    }
    if (($path[0] ?? null) === 'blocks' && ($path[2] ?? null) === 'settings') {
      $relative = array_slice($path, 3);
      if (count($relative) === 1) {
        if ($relative[0] === 'application_handle') {
          return ['snapshot_block_applications', $positions[1], 'application_handle', 'text', null];
        }
        foreach (['block_appearance_settings', 'block_media_settings', 'block_navigation_settings', 'block_content_settings'] as $name) {
          $type = SettingDefinitions::tables()[$name][$relative[0]] ?? null;
          if ($type !== null) {
            return ['snapshot_'.$name, $positions[1], $relative[0], $type, null];
          }
        }
      } elseif (count($relative) === 3 && $relative[0] === 'content_bindings') {
        $type = SettingDefinitions::tables()['block_content_bindings'][$relative[2]] ?? null;
        if ($type !== null) {
          return ['snapshot_block_content_bindings', $ancestors[5], $relative[2], $type, (string) $relative[1]];
        }
      } elseif (count($relative) === 2 && $relative[0] === 'content_collection') {
        $type = SettingDefinitions::tables()['block_content_collections'][$relative[1]] ?? null;
        if ($type !== null) {
          return ['snapshot_block_content_collections', $positions[1], $relative[1], $type, null];
        }
      } elseif (count($relative) === 2 && $relative[0] === 'application_settings') {
        return ['snapshot_application_values', $nodeIndex, 'value', 'variant', (string) $relative[1]];
      }
    }
    $table = null;
    $index = 0;
    $field = end($path);
    if (count($path) === 1) {
      $table = 'snapshot_headers';
    } elseif (count($path) === 2) {
      $table = match ($path[0]) {
      'page' => 'snapshot_pages', 'source_page' => 'snapshot_source_pages', 'shared_slot' => 'snapshot_shared_slots', default => null
      };
    } elseif (count($path) === 3) {
      $table = match ($path[0]) {
      'translations' => 'snapshot_page_translations', 'slots' => 'snapshot_slots', 'page_assets' => 'snapshot_assets', 'blocks' => 'snapshot_blocks', default => null
      };
      $index = $positions[1];
    } elseif (count($path) === 5 && $path[0] === 'blocks') {
      $table = match ($path[2]) {
        'block_media' => 'snapshot_block_media', 'text_translations' => 'snapshot_text_translations',
        'button_translations' => 'snapshot_button_translations', 'image_translations' => 'snapshot_image_translations',
        'contact_form_translations' => 'snapshot_contact_translations', default => null,
      };
      $index = $ancestors[4];
    } elseif (count($path) === 7 && $path[0] === 'blocks' && $path[2] === 'block_media' && $path[4] === 'gallery_item_translations') {
      $table = 'snapshot_media_translations';
      $index = $ancestors[6];
    }
    $type = $table === null ? null : (self::tables()[$table][$field] ?? null);

    return $type === null ? null : [$table, $index, $field, $type, null];
  }
}
