<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Icons\IconCatalog;
use WebBlocks\Cms\Support\Navigation\NavigationTree;

class NavigationItemRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  protected function prepareForValidation(): void
  {
    $this->merge([
      'icon' => app(IconCatalog::class)->normalizeSlug($this->input('icon')),
    ]);
  }

  public function rules(): array
  {
    $navigation = $this->route('navigation');
    $siteId = $this->integer('site_id')
      ?: ($navigation?->site_id ?? null)
      ?: Page::query()->whereKey($this->integer('page_id'))->value('site_id')
      ?: Site::primary()?->id;

    $this->merge(['site_id' => $siteId]);

    return [
      'site_id' => ['required', 'integer', 'exists:wbcms_sites,id'],
      'menu_key' => ['required', 'string', Rule::in(NavigationItem::menuKeys())],
      'parent_id' => [
        'nullable',
        'integer',
        'exists:wbcms_navigation_items,id',
        Rule::notIn([$navigation?->id]),
      ],
      'page_id' => ['nullable', 'integer', Rule::exists('wbcms_pages', 'id')->where(fn ($query) => $query->where('page_type', '!=', Page::TYPE_SYSTEM))],
      'title' => ['nullable', 'string', 'max:255'],
      'locale' => ['nullable', 'string', 'regex:'.Locale::CODE_VALIDATION_PATTERN, 'exists:wbcms_locales,code'],
      'link_type' => ['required', 'string', Rule::in(NavigationItem::linkTypes())],
      'url' => ['nullable', 'string', 'max:2048'],
      'target' => ['nullable', 'string', Rule::in(['_self', '_blank'])],
      'icon' => ['nullable', 'string', 'max:255'],
      'position' => ['nullable', 'integer', 'min:1'],
      'visibility' => ['required', 'string', Rule::in(NavigationItem::visibilities())],
    ];
  }

  public function after(): array
  {
    return [function (Validator $validator): void {
      $navigation = $this->route('navigation');
      $parentId = $this->integer('parent_id');
      $pageId = $this->integer('page_id');
      $linkType = (string) $this->input('link_type');
      $title = trim((string) $this->input('title'));
      $url = trim((string) $this->input('url'));
      $menuKey = (string) $this->input('menu_key');
      $siteId = $this->integer('site_id') ?: Site::primary()?->id;
      $icon = app(IconCatalog::class)->normalizeSlug($this->input('icon'));

      $localeCode = Locale::normalizeCode($this->input('locale'));

      if ($localeCode !== null) {
        $site = Site::query()->with('locales')->find($siteId);

        if (! $site || ! $site->hasEnabledLocale($localeCode)) {
          $validator->errors()->add('locale', __('webblocks-cms::admin-feedback.validation.navigation_locale_enabled'));
        }
      }

      if ($linkType === NavigationItem::LINK_PAGE && ! $pageId) {
        $validator->errors()->add('page_id', __('webblocks-cms::admin-feedback.validation.navigation_page_required'));
      }

      if ($pageId && Page::query()->whereKey($pageId)->value('site_id') !== $siteId) {
        $validator->errors()->add('page_id', __('webblocks-cms::admin-feedback.validation.navigation_page_site'));
      }

      if ($linkType === NavigationItem::LINK_CUSTOM_URL && $url === '') {
        $validator->errors()->add('url', __('webblocks-cms::admin-feedback.validation.navigation_url_required'));
      }

      if ($linkType === NavigationItem::LINK_GROUP && $title === '') {
        $validator->errors()->add('title', __('webblocks-cms::admin-feedback.validation.navigation_group_title'));
      }

      if (! in_array($linkType, [NavigationItem::LINK_PAGE, NavigationItem::LINK_COOKIE_SETTINGS], true) && $title === '') {
        $validator->errors()->add('title', __('webblocks-cms::admin-feedback.validation.navigation_link_title'));
      }

      if (! app(IconCatalog::class)->isActiveSelection($icon)) {
        $validator->errors()->add('icon', __('webblocks-cms::admin-feedback.validation.icon_active'));
      }

      if ($linkType === NavigationItem::LINK_COOKIE_SETTINGS && NavigationItem::overridesCookieDestination($url, $pageId, $this->input('target') ?: null)) {
        $validator->errors()->add('link_type', __('webblocks-cms::consent.fixed_destination'));
      }

      if (! $parentId) {
        return;
      }

      $parent = NavigationItem::query()->with('parent')->find($parentId);

      $issue = app(NavigationTree::class)->parentPlacementIssue($parent, (int) $siteId, $menuKey, $navigation);
      $message = match ($issue) {
        'scope' => __('webblocks-cms::admin-feedback.navigation.parent_scope'),
        'group' => __('webblocks-cms::admin-feedback.navigation.parent_group'),
        'depth' => __('webblocks-cms::admin-feedback.navigation.parent_depth'),
        'cycle' => 'A navigation item cannot be moved under its own child tree.',
        default => null,
      };
      if ($message !== null) {
        $validator->errors()->add('parent_id', $message);
      }
    }];
  }

  public function messages(): array
  {
    return [
      'url.max' => 'The URL may not be greater than 2048 characters.',
    ];
  }
}
