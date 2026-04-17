<?php

namespace App\Http\Requests\Admin;

use App\Models\NavigationItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NavigationItemReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'menu_key' => ['required', 'string', Rule::in(NavigationItem::menuKeys())],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.parent_id' => ['nullable', 'integer'],
            'items.*.position' => ['required', 'integer', 'min:1'],
        ];
    }
}
