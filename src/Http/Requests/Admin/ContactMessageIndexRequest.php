<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\PanelNotificationPolicy;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class ContactMessageIndexRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(PanelNotificationPolicy::class)->view($this->user());
  }

  public function rules(): array
  {
    return ['site' => ['sometimes', 'nullable', 'integer', Rule::in(
      app(AdminAuthorization::class)->scopeSitesForUser(Site::query(), $this->user())->pluck('id')->all()
    )]];
  }
}
