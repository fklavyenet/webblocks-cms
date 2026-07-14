<?php

namespace WebBlocks\Cms\Support\InternalContentApi;

/**
 * Which settings fields an existing block accepts through the block PATCH
 * endpoint, and why the rest are refused.
 *
 * The published content contract declares the settings each block type supports.
 * The PATCH endpoint used to keep a separate hand-written allowlist, so the two
 * drifted: the contract advertised fields the endpoint then refused with
 * unsupported_block_settings_fields, which is how the Link List styles shipped
 * unwritable in 1.40.10 and why an existing block's icon still cannot be changed.
 *
 * Every contract-declared settings field must appear either as patchable here or
 * in CLOSED with a reason. BlockPatchSettingsContractTest fails when a field
 * appears in the contract and in neither list, so adding a field to the contract
 * forces the decision instead of silently landing as an API refusal.
 *
 * Being patchable is not enough on its own: the endpoint must also sanitize the
 * field, or it passes the gate and is dropped instead of stored.
 */
final class BlockSettingsPatchPolicy
{
  /**
   * Reason codes for a contract field that PATCH refuses.
   */
  public const CLOSED_DELIBERATE = 'deliberate';

  public const CLOSED_PENDING = 'pending';

  /**
   * Contract-declared settings fields that PATCH refuses, mapped to why.
   *
   * `deliberate` means the field must stay closed: writing it through an API
   * token changes where data is delivered or retained, which is an operator
   * decision rather than a content one.
   *
   * `pending` means the field is only refused because no sanitizer exists yet.
   * These are safe to open once each has a value rule; they are listed so the
   * gap stays visible and reviewable rather than being rediscovered from a 422.
   *
   * @var array<string, array<string, string>>
   */
  public const CLOSED = [
    'contact_form' => [
      'recipient_email' => self::CLOSED_DELIBERATE,
      'send_email_notification' => self::CLOSED_DELIBERATE,
      'store_submissions' => self::CLOSED_DELIBERATE,
    ],
    'alert' => ['variant' => self::CLOSED_PENDING],
    'breadcrumb' => ['home_label' => self::CLOSED_PENDING, 'include_current' => self::CLOSED_PENDING],
    'card' => ['layout_name' => self::CLOSED_PENDING],
    'card_body' => ['layout_name' => self::CLOSED_PENDING],
    'card_footer' => ['layout_name' => self::CLOSED_PENDING],
    'card_header' => ['layout_name' => self::CLOSED_PENDING],
    'cluster' => [
      'layout_name' => self::CLOSED_PENDING,
      'gap' => self::CLOSED_PENDING,
      'alignment' => self::CLOSED_PENDING,
      'items_alignment' => self::CLOSED_PENDING,
      'wrap' => self::CLOSED_PENDING,
      'width' => self::CLOSED_PENDING,
    ],
    'code' => ['language' => self::CLOSED_PENDING],
    'comments' => [
      'form_enabled' => self::CLOSED_PENDING,
      'show_approved' => self::CLOSED_PENDING,
      'show_author_name' => self::CLOSED_PENDING,
      'sort_order' => self::CLOSED_PENDING,
    ],
    'container' => [
      'layout_name' => self::CLOSED_PENDING,
      'width' => self::CLOSED_PENDING,
      'flow' => self::CLOSED_PENDING,
    ],
    'content_header' => ['alignment' => self::CLOSED_PENDING],
    'grid' => [
      'layout_name' => self::CLOSED_PENDING,
      'columns' => self::CLOSED_PENDING,
      'gap' => self::CLOSED_PENDING,
      'alternate_media_text_sections' => self::CLOSED_PENDING,
      'alternate_start' => self::CLOSED_PENDING,
    ],
    'header' => ['alignment' => self::CLOSED_PENDING, 'anchor' => self::CLOSED_PENDING],
    'hero' => ['layout' => self::CLOSED_PENDING, 'title_tag' => self::CLOSED_PENDING],
    'navbar-navigation' => [
      'menu_key' => self::CLOSED_PENDING,
      'active_indicator' => self::CLOSED_PENDING,
      'active_matching' => self::CLOSED_PENDING,
    ],
    'navigation-auto' => ['menu_key' => self::CLOSED_PENDING],
    'plain_text' => ['alignment' => self::CLOSED_PENDING],
    'rating' => [
      'scale' => self::CLOSED_PENDING,
      'allow_change' => self::CLOSED_PENDING,
      'show_summary' => self::CLOSED_PENDING,
      'title' => self::CLOSED_PENDING,
    ],
    'search-form' => ['show_button' => self::CLOSED_PENDING],
    'section' => ['layout_name' => self::CLOSED_PENDING, 'spacing' => self::CLOSED_PENDING],
    'sidebar-footer' => ['variant' => self::CLOSED_PENDING],
    'sidebar-nav-group' => [
      'icon' => self::CLOSED_PENDING,
      'initially_open' => self::CLOSED_PENDING,
      'layout_name' => self::CLOSED_PENDING,
    ],
    'sidebar-nav-item' => [
      'icon' => self::CLOSED_PENDING,
      'active_mode' => self::CLOSED_PENDING,
      'manual_active' => self::CLOSED_PENDING,
    ],
    'sidebar-navigation' => [
      'menu_key' => self::CLOSED_PENDING,
      'layout_name' => self::CLOSED_PENDING,
      'show_icons' => self::CLOSED_PENDING,
      'active_matching' => self::CLOSED_PENDING,
    ],
    'slide' => ['layout_name' => self::CLOSED_PENDING],
    'slider' => ['layout_name' => self::CLOSED_PENDING],
    'sticky-navbar' => ['layout_name' => self::CLOSED_PENDING, 'sticky_mode' => self::CLOSED_PENDING],
  ];

  /**
   * @return array<string, string>
   */
  public static function closedFor(string $type): array
  {
    return self::CLOSED[$type] ?? [];
  }

  public static function isClosed(string $type, string $field): bool
  {
    return array_key_exists($field, self::closedFor($type));
  }
}
