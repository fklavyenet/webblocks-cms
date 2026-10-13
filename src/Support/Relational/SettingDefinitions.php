<?php

namespace WebBlocks\Cms\Support\Relational;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\CmsModel;
use WebBlocks\Cms\Models\EmbeddedApplication;

final class SettingDefinitions
{
  public const APPLICATION_ATTRIBUTES = ['css_assets', 'js_assets', 'supports', 'settings_schema'];

  public static function attributes(CmsModel $model): array
  {
    return $model instanceof Block ? ['settings'] : ($model instanceof EmbeddedApplication ? self::APPLICATION_ATTRIBUTES : []);
  }

  /** Finite core schemas; historical/plugin extensions have their own typed nodes. */
  public static function tables(): array
  {
    static $tables;

    return $tables ??= [
      'block_appearance_settings' => self::fields(
        'layout layout_name title title_tag alignment anchor flow spacing width wrap columns gap ratio responsive items_alignment alternate_start variant visual_variant icon icon_slug icon_tone icon_size badge_tone text_color',
        '', 'alternate_media_text_sections'
      ),
      'block_media_settings' => self::fields(
        'background_fit background_overlay background_position image_align image_aspect image_position aspect_ratio height min_height transition overlay content_position content_width captions_mode overlay_mode viewer_title viewer_group loading',
        'interval_ms', 'autoplay pause_on_hover show_arrows show_dots loop swipe keyboard lightbox_enabled viewer_enabled'
      ),
      'block_navigation_settings' => self::fields(
        'url target aria_label menu_key location sticky_mode active_indicator active_matching active_mode home_label brand_url logo_path',
        '', 'compact manual_active initially_open include_current show_icons show_search show_language_switcher show_mode_toggle show_accent_toggle'
      ),
      'block_content_settings' => self::fields(
        'scope page_type path_prefix sort language lang data_scope row_layout list_frame thumb_size',
        'limit scale product_id commerce_product_id asset_id media_id',
        'allow_change show_summary show_thumbnail show_description clickable_card exclude_current show_loading_state show_failure_state show_button consent_required send_email_notification form_enabled'
      ) + self::fields('submit_label success_message consent_label recipient_email reply_to_address', '', ''),
      'block_content_bindings' => self::fields('source record field', '', ''),
      'block_content_collections' => self::fields('source filter_field filter_value sort_field sort_direction empty_behavior error_behavior', 'template_block_id limit per_page', 'paginate'),
      'block_application_values' => ['value' => 'variant'],
      'application_assets' => self::fields('path type load_position', '', ''),
      'application_supports' => self::fields('', '', 'locale theme fullscreen'),
      'application_setting_fields' => self::fields('type', 'min max max_length', '') + ['default' => 'variant'],
      'application_setting_options' => ['value' => 'variant'],
    ];
  }

  private static function fields(string $text, string $integer, string $boolean): array
  {
    $fields = [];
    foreach (compact('text', 'integer', 'boolean') as $type => $names) {
      foreach (array_filter(explode(' ', $names)) as $name) {
        $fields[$name] = $type;
      }
    }

    return $fields;
  }

  public static function binding(bool $block, string $attribute, array $path, array $positions, int $nodeIndex): ?array
  {
    $definitions = self::tables();
    $table = null;
    $index = 0;
    $key = null;
    $field = end($path);
    if ($block) {
      if (count($path) === 1) {
        if ($field === 'application_handle') {
          return ['application_reference', 0, 'application_handle', 'text', null];
        }
        foreach (['block_appearance_settings', 'block_media_settings', 'block_navigation_settings', 'block_content_settings'] as $candidate) {
          if (isset($definitions[$candidate][$field])) {
            $table = $candidate;
            break;
          }
        }
      } elseif (count($path) === 3 && $path[0] === 'content_bindings') {
        $table = 'block_content_bindings';
        $index = $positions[1];
        $key = $path[1];
      } elseif (count($path) === 2 && $path[0] === 'content_collection') {
        $table = 'block_content_collections';
      } elseif (count($path) === 2 && $path[0] === 'application_settings') {
        return ['block_application_values', $positions[1], 'value', 'variant', (string) $path[1]];
      }
    } elseif (in_array($attribute, ['css_assets', 'js_assets'], true)) {
      if (count($path) === 1) {
        return ['application_assets', $positions[0], 'path', 'text', (string) $path[0]];
      }
      if (count($path) === 2 && $attribute === 'js_assets') {
        $table = 'application_assets';
        $index = $positions[0];
        $key = (string) $path[0];
      }
    } elseif ($attribute === 'supports' && count($path) === 1) {
      $table = 'application_supports';
    } elseif ($attribute === 'settings_schema') {
      if (count($path) === 2) {
        $table = 'application_setting_fields';
        $index = $positions[0];
        $key = (string) $path[0];
      } elseif (count($path) === 3 && $path[1] === 'values') {
        return ['application_setting_options', $nodeIndex, 'value', 'variant', (string) $path[0]];
      }
    }

    return $table !== null && isset($definitions[$table][$field])
      ? [$table, $index, $field, $definitions[$table][$field], $key] : null;
  }
}
