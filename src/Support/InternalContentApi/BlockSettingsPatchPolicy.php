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
      // Turning the consent requirement off through a token would remove a
      // legal notice from a live form, which is the operator's decision.
      'consent_required' => self::CLOSED_DELIBERATE,
    ],
    'comments' => [
      // Whether commenter names are shown publicly is a privacy decision about
      // other people's data, not a presentation one, so it stays with the admin.
      'show_author_name' => self::CLOSED_DELIBERATE,
    ],
    'rating' => [
      // The admin form hard-codes scale to 5 and offers no control. Opening it
      // here would let the API store a scale the admin cannot produce or show.
      'scale' => self::CLOSED_PENDING,
    ],
  ];

  /**
   * Value rules for the settings fields PATCH accepts, mapped from block type to
   * field to rule. This is the gate and the sanitizer at once: a field absent
   * here is refused, and a field present here is written only if its value
   * satisfies the rule, otherwise the setting is cleared.
   *
   * Rules mirror what the admin form already allows, because a value the admin
   * cannot produce is one no renderer reads.
   *
   *   ['enum', [...]]  one of the listed values, anything else clears
   *   ['bool']         truthy/falsy, unparseable clears
   *   ['text', max]    trimmed free text capped at max, empty clears
   *   ['int', min, max] integer clamped into range, non-numeric clears
   *   ['menu_key']     one of the navigation menu keys
   *   ['anchor']       same-page anchor ID, admin's format
   *
   * icon_slug, icon_tone, and badge_tone are deliberately absent: they are
   * handled by the normalizers InternalContentApiOperations already owns.
   *
   * @var array<string, array<string, array{0: string, 1?: mixed}>>
   */
  public const PATCHABLE = [
    'alert' => ['variant' => ['enum', ['info', 'success', 'warning', 'danger']]],
    'breadcrumb' => [
      'home_label' => ['text', 255],
      'include_current' => ['bool'],
    ],
    'card' => ['layout_name' => ['text', 255]],
    'card_body' => ['layout_name' => ['text', 255]],
    'card_footer' => ['layout_name' => ['text', 255]],
    'card_header' => ['layout_name' => ['text', 255]],
    'cluster' => [
      'layout_name' => ['text', 255],
      'gap' => ['enum', ['none', 'xs', 'sm', 'md', 'lg']],
      'alignment' => ['enum', ['start', 'center', 'end', 'between']],
      'items_alignment' => ['enum', ['start', 'center', 'end', 'stretch']],
      'wrap' => ['enum', ['wrap', 'nowrap']],
      'width' => ['enum', ['auto', 'full']],
    ],
    'code' => ['language' => ['text', 255]],
    'comments' => [
      'form_enabled' => ['bool'],
      'show_approved' => ['bool'],
      'sort_order' => ['enum', ['newest', 'oldest']],
    ],
    'container' => [
      'layout_name' => ['text', 255],
      'width' => ['enum', ['sm', 'md', 'lg', 'xl', 'full']],
      'flow' => ['enum', ['none', 'stack']],
    ],
    'content_header' => ['alignment' => ['enum', ['left', 'center', 'right']]],
    'grid' => [
      'layout_name' => ['text', 255],
      'columns' => ['enum', ['2', '3', '4']],
      'gap' => ['enum', ['3', '4', '6']],
      'alternate_media_text_sections' => ['bool'],
      'alternate_start' => ['enum', ['media_left', 'text_left']],
    ],
    'header' => [
      'alignment' => ['enum', ['left', 'center', 'right']],
      'anchor' => ['anchor'],
    ],
    'hero' => [
      'layout' => ['enum', ['left', 'centered', 'split']],
      'title_tag' => ['enum', ['h1', 'h2', 'h3']],
    ],
    'link-list' => [
      'row_layout' => ['enum', ['stacked']],
      'list_frame' => ['enum', ['cards']],
      'thumb_size' => ['enum', ['wide']],
    ],
    'navbar-navigation' => [
      'menu_key' => ['menu_key'],
      'active_indicator' => ['enum', ['underline', 'pill', 'dot', 'background', 'none']],
      'active_matching' => ['enum', ['path', 'section', 'current-page', 'exact', 'off']],
    ],
    'navigation-auto' => ['menu_key' => ['menu_key']],
    'page-list' => [
      'scope' => ['enum', ['page_type', 'path_prefix', 'subtree_of_current']],
      'page_type' => ['text', 255],
      'path_prefix' => ['text', 2048],
      'sort' => ['enum', ['published_desc', 'published_asc', 'title_asc', 'path_asc']],
      'limit' => ['int', 1, 48],
      'layout' => ['enum', ['cards', 'links']],
      'columns' => ['enum', ['2', '3', '4']],
      'show_thumbnail' => ['bool'],
      'show_description' => ['bool'],
      'exclude_current' => ['bool'],
    ],
    'plain_text' => ['alignment' => ['enum', ['left', 'center', 'right']]],
    'rating' => [
      'allow_change' => ['bool'],
      'show_summary' => ['bool'],
      'title' => ['text', 120],
    ],
    'search-form' => ['show_button' => ['bool']],
    'section' => [
      'layout_name' => ['text', 255],
      'spacing' => ['enum', ['sm', 'lg']],
    ],
    'sidebar-footer' => ['variant' => ['enum', ['info', 'success', 'warning', 'danger']]],
    'sidebar-nav-group' => [
      'layout_name' => ['text', 255],
      'icon' => ['text', 255],
      'initially_open' => ['bool'],
    ],
    'sidebar-nav-item' => [
      'icon' => ['text', 255],
      'active_mode' => ['enum', ['exact', 'path', 'current-page', 'manual']],
      'manual_active' => ['bool'],
    ],
    'sidebar-navigation' => [
      'layout_name' => ['text', 255],
      'menu_key' => ['menu_key'],
      'show_icons' => ['bool'],
      'active_matching' => ['enum', ['path', 'current-page', 'exact']],
    ],
    'slide' => ['layout_name' => ['text', 255]],
    'slider' => ['layout_name' => ['text', 255]],
    'sticky-navbar' => [
      'layout_name' => ['text', 255],
      'sticky_mode' => ['enum', ['sticky', 'fixed', 'static']],
    ],
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

  /**
   * @return array<string, array{0: string, 1?: mixed}>
   */
  public static function rulesFor(string $type): array
  {
    return self::PATCHABLE[$type] ?? [];
  }
}
