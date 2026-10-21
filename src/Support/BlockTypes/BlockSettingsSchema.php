<?php

namespace WebBlocks\Cms\Support\BlockTypes;

use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\InternalContentApi\BlockSettingsPatchPolicy;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;
use WebBlocks\Cms\Support\PublicRendering\PublicIconPresenter;

/** Authoring rules, separate from SQL storage types and runtime fallbacks. */
final class BlockSettingsSchema
{
  public function forType(string $type): array
  {
    // Core names always win, including when a plugin declares a catalog alias.
    $core = collect(app(CoreBlockTypeCatalogSyncer::class)->definitions())->firstWhere('slug', $type);
    if ($core === null) {
      $definition = app(PluginBlockCatalog::class)->enabledDefinitionForCatalogSlug($type);
      $rules = $definition?->settingRules();
      if ($rules !== null) {
        $rules += ['content_bindings' => ['object'], 'content_collection' => ['object']];
      }

      return $this->describe($rules ?? [], $rules === null ? 'legacy_plugin' : ($definition->hasInvalidSettingRules() ? 'invalid_plugin_schema' : 'plugin'), $type);
    }

    $rules = BlockSettingsPatchPolicy::rulesFor($type);
    $rules += ['url' => ['url'], 'target' => ['enum', ['_self', '_blank']], 'aria_label' => ['text', 255],
      'content_bindings' => ['object'], 'content_collection' => ['object']];
    if (in_array($type, ['hero', 'section', 'card', 'cta', 'content_header', 'slide'], true)) {
      $rules += ['background_position' => ['enum', ['center', 'top', 'bottom', 'left', 'right']], 'background_overlay' => ['enum', ['none', 'soft', 'medium', 'strong']]];
    }
    if (in_array($type, InternalContentApiOperations::PUBLIC_ICON_BLOCK_TYPES, true)) {
      $rules += ['icon_slug' => ['text', 255], 'icon_tone' => ['enum', PublicIconPresenter::VISUAL_TONES], 'icon_size' => ['enum', PublicIconPresenter::SIZES]];
    }
    if (in_array($type, InternalContentApiOperations::PUBLIC_BADGE_BLOCK_TYPES, true)) {
      $rules['badge_tone'] = ['enum', PublicIconPresenter::BADGE_TONES];
    }
    if (in_array($type, ['slider', 'slide'], true)) {
      $rules += ['content_position' => ['enum', ['center', 'center-left', 'center-right', 'top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right']],
        'content_width' => ['enum', ['narrow', 'medium', 'wide', 'full']], 'text_color' => ['enum', ['auto', 'light', 'dark']], 'background_fit' => ['enum', ['cover', 'contain']]];
    }
    if ($type === 'slider') {
      $rules += ['height' => ['enum', ['auto', 'fill', 'viewport', 'large', 'medium', 'small', 'custom']], 'min_height' => ['pattern', '/^\d{2,4}(px|vh|svh|dvh)$/'],
        'aspect_ratio' => ['enum', ['auto', '16/9', '4/3', '1/1']], 'transition' => ['enum', ['slide']], 'interval_ms' => ['int', 1000, 30000], 'overlay' => ['enum', ['none', 'soft', 'medium', 'dark', 'strong']]];
      foreach (['autoplay', 'pause_on_hover', 'show_arrows', 'show_dots', 'loop', 'swipe', 'keyboard'] as $key) {
        $rules[$key] = ['bool'];
      }
    }
    if ($type === 'header-actions') {
      foreach (['show_mode_toggle', 'show_accent_toggle', 'show_search', 'show_language_switcher'] as $key) {
        $rules[$key] = ['bool'];
      }
    }
    $variants = [
      'header' => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], 'hero' => ['default', 'muted', 'soft', 'accent'], 'cta' => ['default', 'muted', 'soft', 'accent'],
      'columns' => ['cards', 'plain', 'stats'], 'feature-grid' => ['cards'], 'button_link' => ['primary', 'secondary'], 'download' => ['primary', 'secondary', 'ghost'],
      'table' => ['header-row', 'plain'], 'quote' => ['default', 'testimonial'], 'card' => ['flat', 'muted', 'highlight', 'accent'], 'search-form' => ['primary', 'secondary'],
    ];
    if (isset($variants[$type])) {
      $rules['variant'] = ['enum', $variants[$type]];
    }
    if ($type === 'link-list') {
      $rules['row_layout'] = ['enum', ['index', 'stacked']];
      $rules['list_frame'] = ['enum', ['joined', 'cards']];
      $rules['thumb_size'] = ['enum', ['square', 'wide']];
    }
    if ($type === 'contact_form') {
      $rules += ['recipient_email' => ['email'], 'send_email_notification' => ['bool'], 'store_submissions' => ['bool'], 'consent_required' => ['bool'], 'submit_label' => ['text', 255], 'success_message' => ['text', 1000], 'consent_label' => ['text', 1000]];
    }
    if ($type === 'comments') {
      $rules['show_author_name'] = ['bool'];
    }
    if ($type === 'rating') {
      $rules['scale'] = ['enum', ['5']];
    }
    if ($type === 'gallery') {
      $rules += ['variant' => ['enum', ['grid', 'masonry', 'collage']], 'columns' => ['enum', ['2', '3', '4', '5']], 'gap' => ['enum', ['none', 'sm', 'md', 'lg']],
        'aspect_ratio' => ['enum', ['auto', 'square', '4:3', '16:9', 'portrait']], 'captions_mode' => ['enum', ['hidden', 'below', 'overlay', 'on-hover']], 'overlay_mode' => ['enum', ['none', 'gradient', 'solid']], 'lightbox_enabled' => ['bool'], 'viewer_title' => ['text', 255]];
    }
    if ($type === 'webblocks-commerce-buy-button') {
      $rules += ['product_id' => ['int', 1, PHP_INT_MAX], 'commerce_product_id' => ['int', 1, PHP_INT_MAX]];
    }
    // Existing transport aliases are consumed by the media normalizer, never new settings storage.
    $rules += ['media_id' => ['int', 1, PHP_INT_MAX], 'asset_id' => ['int', 1, PHP_INT_MAX],
      'gallery_items' => ['array'], 'gallery_media_ids' => ['array'], 'gallery_asset_ids' => ['array']];

    if (isset($rules['layout_name'])) {
      $rules['layout_name'] = ['text', 100];
    }
    if ($type === 'application') {
      $rules = BlockSettingsPatchPolicy::rulesFor($type);
    }

    return $this->describe($rules, $core['status'] === 'published' ? 'core' : 'legacy_core', $type);
  }

  private function describe(array $rules, string $mode, string $type): array
  {
    $fields = [];
    foreach ($rules as $key => $rule) {
      $closed = BlockSettingsPatchPolicy::closedFor($type)[$key] ?? null;
      $relation = in_array($key, ['media_id', 'asset_id', 'mobile_media_id', 'gallery_items', 'gallery_media_ids', 'gallery_asset_ids'], true);
      $closed = $relation ? 'use_media_relationship_fields' : $closed;
      if ($type === 'contact_form' && in_array($key, ['submit_label', 'success_message', 'consent_label'], true)) {
        $closed = 'use_translations';
      }
      $typeName = match ($rule[0]) {
      'bool' => 'boolean', 'int' => 'integer', 'object', 'application_settings' => 'object', 'array' => 'array', default => 'string'
      };
      $constraints = match ($rule[0]) {
        'enum' => ['enum' => $rule[1]], 'int' => ['minimum' => $rule[1], 'maximum' => $rule[2]], 'text' => ['max_length' => $rule[1]], default => [],
      };
      $aliases = match (true) {
        $type === 'slider' && $key === 'transition' => ['fade' => 'slide'],
        $type === 'gallery' && $key === 'variant' => ['masonary' => 'masonry'],
        default => [],
      };
      $clearValues = $type === 'link-list' ? match ($key) {
      'row_layout' => ['index'], 'list_frame' => ['joined'], 'thumb_size' => ['square'], default => []
      } : [];
      $required = $type === 'application' && $key === 'application_handle';
      $invalid = $mode === 'invalid_plugin_schema';
      $closed = $invalid ? 'invalid_plugin_schema' : $closed;
      $fields[$key] = $constraints + ['aliases' => $aliases, 'clear_values' => $clearValues, 'type' => $typeName, 'rule' => $rule, 'required_on_create' => $required, 'nullable' => ! $required, 'empty' => $required ? 'reject' : 'clear', 'missing' => 'preserve_on_update',
        'writes' => ['admin' => ! $invalid, 'create' => ! $invalid && $type !== 'html', 'patch' => $type !== 'html' && $closed === null], 'closed_reason' => $closed];
    }

    return ['revision' => 1, 'mode' => $mode, 'unknown_fields' => $mode === 'legacy_plugin' ? 'plugin_owned' : 'reject_new_preserve_existing', 'fields' => $fields];
  }

  public static function normalizeValue(mixed $value, array $rule, bool &$valid): mixed
  {
    $valid = true;
    if ($value === null || (is_string($value) && trim($value) === '')) {
      return null;
    }
    $scalar = is_string($value) || is_int($value);
    $text = $scalar ? trim((string) $value) : '';
    $valid = match ($rule[0]) {
      'bool' => is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false', 'on', 'off', 'yes', 'no'], true),
      'int' => $scalar && filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value >= $rule[1] && (int) $value <= $rule[2],
      'enum' => $scalar && in_array($text, $rule[1], true),
      'text' => is_string($value) && mb_strlen($text) <= $rule[1],
      'url' => is_string($value) && mb_strlen($text) <= 2048 && preg_match('/^(\/(?!\/)|#|https?:\/\/|mailto:|tel:)/i', $text) === 1,
      'email' => is_string($value) && filter_var($text, FILTER_VALIDATE_EMAIL) !== false,
      'menu_key' => is_string($value) && in_array($text, NavigationItem::menuKeys(), true),
      'anchor' => is_string($value) && strlen($text) <= 255 && preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_:.]*$/', $text) === 1,
      'viewer_group' => is_string($value) && preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $text) === 1,
      'pattern' => is_string($value) && preg_match($rule[1], $text) === 1,
      'object', 'application_settings' => is_array($value) && ($value === [] || ! array_is_list($value)),
      'array' => is_array($value),
      default => false,
    };

    return match ($rule[0]) {
      'bool' => $valid ? filter_var($value, FILTER_VALIDATE_BOOL) : $value,
      'int' => $valid ? (int) $value : $value,
      'enum' => $valid ? $text : $value,
      default => $valid && is_string($value) ? trim($value) : $value,
    };
  }
}
