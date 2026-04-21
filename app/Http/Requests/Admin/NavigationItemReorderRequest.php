<?php

namespace App\Http\Requests\Admin;

use App\Models\NavigationItem;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NavigationItemReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $siteId = $this->integer('site_id') ?: Site::primary()?->id;

        $this->merge([
            'site_id' => $siteId,
        ]);
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'menu_key' => ['required', 'string', Rule::in(NavigationItem::menuKeys())],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.parent_id' => ['nullable', 'integer'],
            'items.*.position' => ['required', 'integer', 'min:1'],
        ];
    }
}
