<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use WebBlocks\Cms\Models\IconCatalogItem;
use Illuminate\Foundation\Http\FormRequest;

class IconCatalogItemUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'categories' => ['nullable', 'string', 'max:1000'],
            'contexts' => ['nullable', 'string', 'max:1000'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            '_icon_modal' => ['nullable', 'string'],
            '_icon_id' => ['nullable', 'integer'],
            '_icon_index_url' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function catalogData(): array
    {
        return [
            'label' => trim((string) $this->input('label')),
            'categories' => IconCatalogItem::normalizeTags($this->input('categories')),
            'contexts' => IconCatalogItem::normalizeTags($this->input('contexts')),
            'keywords' => IconCatalogItem::normalizeKeywords($this->input('keywords')),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->integer('sort_order'),
        ];
    }
}
