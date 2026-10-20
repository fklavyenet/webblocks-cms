<?php

namespace WebBlocks\Cms\Support\EditorRecovery;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Support\Relational\SettingTree;

final class RecoveryFields
{
  public const MAX_BYTES = 262144;

  // Native authoring controls; identity, navigation and operational controls never enter recovery.
  public const PAGE_FIELDS = 'title slug public_shell';

  public const BLOCK_FIELDS = 'action_label alert_variant alignment anchor application_aspect_ratio application_handle application_loading application_min_height application_settings application_show_failure_state application_show_loading_state application_width asset_id attachment_asset_id attachment_media_id background_overlay background_position badge_label badge_tone block_type_id breadcrumb_home_label breadcrumb_include_current card_target card_url card_variant cluster_align cluster_gap cluster_justify cluster_width cluster_wrap column_items comments_data_scope comments_form_enabled comments_show_approved comments_show_author_name comments_sort_order consent_label consent_required container_flow content content_binding_selection content_collection_empty_behavior content_collection_error_behavior content_collection_filter_field content_collection_filter_value content_collection_limit content_collection_paginate content_collection_per_page content_collection_sort_direction content_collection_sort_field content_collection_source content_collection_template_id eyebrow feature_items filter_field gallery_aspect_ratio gallery_asset_ids gallery_captions_mode gallery_columns gallery_gap gallery_items gallery_lightbox_enabled gallery_media_ids gallery_overlay_mode gallery_variant gallery_viewer_title grid_alternate_media_text_sections grid_alternate_start grid_columns grid_gap grid_ratio header_actions_show_accent_toggle header_actions_show_language_switcher header_actions_show_mode_toggle header_actions_show_search heading icon_size icon_slug icon_tone image_align image_alt image_aspect image_caption image_position image_viewer_enabled image_viewer_group intro_text label language layout level link_list_items list_frame media_id meta meta_items mobile_media_id name navbar_brand_aria_label navbar_navigation_active_indicator navbar_navigation_active_matching navbar_navigation_menu_key navigation_menu_key page_list_clickable_card page_list_columns page_list_exclude_current page_list_layout page_list_limit page_list_page_type page_list_path_prefix page_list_scope page_list_show_description page_list_show_thumbnail page_list_sort parent_id plugin_settings rating_allow_change rating_data_scope rating_scale rating_show_summary rating_title recipient_email row_layout section_flow send_email_notification settings show_button sidebar_brand_aria_label sidebar_footer_variant sidebar_nav_group_icon sidebar_nav_group_initially_open sidebar_nav_item_active_mode sidebar_nav_item_icon sidebar_nav_item_manual_active sidebar_navigation_active_matching sidebar_navigation_menu_key sidebar_navigation_show_icons slide_aria_label slide_background_fit slide_content_position slide_content_width slide_text_color slider_aspect_ratio slider_autoplay slider_background_fit slider_content_position slider_content_width slider_height slider_interval_ms slider_keyboard slider_loop slider_min_height slider_overlay slider_pause_on_hover slider_show_arrows slider_show_dots slider_swipe slider_text_color slider_transition slot_type_id sort_field sort_order source spacing split_align split_gap split_responsive split_width stack_spacing status sticky_navbar_mode store_submissions submit_label subtitle success_message target template_block_id text thumb_size title title_level title_tag url variant width';

  public function decode(array $entries, bool $block): array
  {
    $allowed = explode(' ', $block ? self::BLOCK_FIELDS : self::PAGE_FIELDS);
    $bytes = 0;
    $nodeCount = 0;
    $result = [];
    foreach ($entries as $entry) {
      $name = $entry['name'];
      $root = explode('[', $name)[0];
      if (! in_array($root, $allowed, true) || ! preg_match('/^[a-z][a-z0-9_]*(?:\[[a-zA-Z0-9_-]*\])*$/D', $name) || $this->sensitive($name)) {
        $this->invalid();
      }
      $value = $entry['present'] ? base64_decode($entry['value'] ?? '', true) : null;
      if ($value === false || ($value !== null && ! mb_check_encoding($value, 'UTF-8'))) {
        $this->invalid();
      }
      $bytes += strlen($name) + strlen($value ?? '');
      if ($bytes > self::MAX_BYTES) {
        $this->invalid();
      }
      $structured = $value === null || ! in_array($root, ['settings', 'meta', 'content_binding_selection'], true) ? null : json_decode($value, false, 32);
      if (is_object($structured) || is_array($structured)) {
        $this->checkKeys($structured);
        [$nodes] = SettingTree::encode($structured, false, 'editor_recovery', fn () => null);
        $nodeCount += count($nodes);
        if ($nodeCount > 4096) {
          $this->invalid();
        }
      } else {
        // Incomplete JSON is unfinished authoring text; reject credential-like keys as well.
        if (in_array($root, ['settings', 'meta', 'content_binding_selection'], true) && preg_match('/["\'](?:[^"\']*(?:password|secret|token|credential|api_key|private_key)[^"\']*)["\']\s*:/i', $value ?? '')) {
          $this->invalid();
        }
        $nodes = null;
      }
      $result[] = ['name' => $name, 'value' => $value, 'nodes' => $nodes];
    }

    return $result;
  }

  public function input(array $entries): array
  {
    $pairs = [];
    $absent = [];
    foreach ($entries as $entry) {
      if ($entry['value'] === null) {
        $absent[] = $entry['name'];
      } else {
        $pairs[] = rawurlencode($entry['name']).'='.rawurlencode($entry['value']);
      }
    }
    parse_str(implode('&', $pairs), $values);
    foreach ($absent as $name) {
      $path = str_replace(['[', ']'], ['.', ''], rtrim($name, '[]'));
      if (! Arr::has($values, $path)) {
        Arr::set($values, $path, str_ends_with($name, '[]') ? [] : null);
      }
    }

    return $values;
  }

  private function sensitive(string $key): bool
  {
    return (bool) preg_match('/password|secret|token|credential|authorization|api[_-]?key|private[_-]?key|__proto__|constructor|prototype/i', $key);
  }

  private function checkKeys(mixed $value): void
  {
    foreach ($value as $key => $child) {
      if ($this->sensitive((string) $key)) {
        $this->invalid();
      }
      if (is_array($child) || is_object($child)) {
        $this->checkKeys($child);
      }
    }
  }

  private function invalid(): never
  {
    throw ValidationException::withMessages(['entries' => __('webblocks-cms::recovery.invalid')]);
  }
}
