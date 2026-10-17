<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContract;

final class DynamicContentAuthoringRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('access-admin') ?? false;
  }

  public function rules(): array
  {
    return [
      'source' => ['required', 'string', 'max:160'],
      'site' => ['nullable', 'integer', 'min:1'],
      'page_id' => ['nullable', 'integer', 'min:1'],
      'block_id' => ['nullable', 'integer', 'min:1'],
      'template_id' => ['nullable', 'integer', 'min:1'],
      'slot_id' => ['nullable', 'integer', 'min:1'],
      'locale' => ['nullable', Rule::in(ContentSourceContract::languages())],
      'version' => ['nullable', 'string', 'max:64'],
      'mappings' => ['nullable', 'array', 'max:200'],
      'mappings.*' => ['array', 'max:10'],
      'mappings.*.*' => ['nullable', 'string', 'max:500'],
      'collection' => ['nullable', 'array:source,template_block_id,limit,filter_field,filter_value,sort_field,sort_direction,paginate,per_page,empty_behavior,error_behavior'],
      'collection.source' => ['nullable', 'string', 'max:160'],
      'collection.template_block_id' => ['nullable', 'integer', 'min:1'],
      'collection.limit' => ['nullable', 'integer', 'min:1', 'max:50'],
      'collection.filter_field' => ['nullable', 'string', 'max:120'],
      'collection.filter_value' => ['nullable', 'string', 'max:255'],
      'collection.sort_field' => ['nullable', 'string', 'max:120'],
      'collection.sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
      'collection.paginate' => ['nullable', 'boolean'],
      'collection.per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
      'collection.empty_behavior' => ['nullable', Rule::in(['hide_template', 'keep_template'])],
      'collection.error_behavior' => ['nullable', Rule::in(['hide_template', 'keep_template'])],
    ];
  }
}
