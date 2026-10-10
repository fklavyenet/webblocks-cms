<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\SystemHealthPolicy;

class SystemHealthRequest extends FormRequest
{
  public function authorize(): bool
  {
    $policy = app(SystemHealthPolicy::class);

    return $this->routeIs('internal-content-api.*')
      ? $policy->readApi($this->attributes->get('cms_api_token'))
      : $policy->view($this->user());
  }

  public function rules(): array
  {
    return ['site_id' => ['nullable', 'integer', Rule::exists((new Site)->getTable(), 'id')]];
  }

  public function siteId(): ?int
  {
    return $this->filled('site_id') ? $this->integer('site_id') : null;
  }
}
