<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Policies\ListingViewPolicy;
use WebBlocks\Cms\Support\ListingViews\ListingViewDefinition;

class ListingViewRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(ListingViewPolicy::class)->listing($this->user(), (string) $this->route('listing'));
  }

  public function rules(): array
  {
    $keys = implode(',', array_keys(app(ListingViewDefinition::class)->defaults((string) $this->route('listing'))));

    return [
      'name' => ['required', 'string', 'max:80'],
      'operation' => ['required', Rule::in(['save', 'rename'])],
      'filters' => ['required_if:operation,save', 'array:'.$keys],
      'columns' => ['present_if:operation,save', 'nullable', 'string', 'max:255'],
    ];
  }
}
