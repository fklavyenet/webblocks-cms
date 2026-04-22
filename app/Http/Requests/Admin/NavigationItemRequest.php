<?php

namespace App\Http\Requests\Admin;

use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class NavigationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'menu_key' => ['required', 'string', Rule::in(NavigationItem::menuKeys())],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:navigation_items,id',
                Rule::notIn([$navigation?->id]),
            ],
            'page_id' => ['nullable', 'integer', 'exists:pages,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'link_type' => ['required', 'string', Rule::in(NavigationItem::linkTypes())],
            'url' => ['nullable', 'string', 'max:2048'],
            'target' => ['nullable', 'string', Rule::in(['_self', '_blank'])],
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

            if ($linkType === NavigationItem::LINK_PAGE && ! $pageId) {
                $validator->errors()->add('page_id', 'Select a page for page links.');
            }

            if ($pageId && Page::query()->whereKey($pageId)->value('site_id') !== $siteId) {
                $validator->errors()->add('page_id', 'Selected page must belong to the selected site.');
            }

            if ($linkType === NavigationItem::LINK_CUSTOM_URL && $url === '') {
                $validator->errors()->add('url', 'Enter a URL for custom links.');
            }

            if ($linkType === NavigationItem::LINK_GROUP && $title === '') {
                $validator->errors()->add('title', 'A title is required for groups.');
            }

            if ($linkType !== NavigationItem::LINK_PAGE && $title === '') {
                $validator->errors()->add('title', 'A title is required for this link type.');
            }

            if (! $parentId) {
                return;
            }

            $parent = NavigationItem::query()->with('parent')->find($parentId);

            if (! $parent || $parent->menu_key !== $menuKey || (int) $parent->site_id !== (int) $siteId) {
                $validator->errors()->add('parent_id', 'Parent item must belong to the same menu.');

                return;
            }

            $depth = 2;
            $cursor = $parent->parent;

            while ($cursor) {
                $depth++;

                if ($depth > 3) {
                    $validator->errors()->add('parent_id', 'Navigation depth cannot exceed 3 levels.');

                    return;
                }

                if ($navigation && $cursor->id === $navigation->id) {
                    $validator->errors()->add('parent_id', 'A navigation item cannot be moved under its own child tree.');

                    return;
                }

                $cursor = $cursor->parent;
            }
        }];
    }
}
