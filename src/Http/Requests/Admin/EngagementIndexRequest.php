<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\EngagementPolicy;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class EngagementIndexRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(EngagementPolicy::class)->viewAny($this->user());
  }

  public function rules(): array
  {
    $sites = app(AdminAuthorization::class)->scopeSitesForUser(Site::query(), $this->user())->pluck('id')->all();

    return [
      'search' => ['nullable', 'string', 'max:255'],
      'site' => ['nullable', 'integer', Rule::in($sites)],
      'page_id' => ['nullable', 'integer', Rule::exists((new Page)->getTable(), 'id')->where(fn ($query) => $query->whereIn('site_id', $sites)->when($this->filled('site'), fn ($query) => $query->where('site_id', $this->input('site'))))],
      'status' => ['nullable', 'string', 'max:40'],
      'rating' => ['nullable', 'integer', 'min:1', 'max:255'],
      'from' => ['nullable', 'date_format:Y-m-d'],
      'until' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
      'sort' => ['nullable', Rule::in($this->sortKeys())],
      'direction' => ['nullable', Rule::in(['asc', 'desc'])],
      'page' => ['nullable', 'integer', 'min:1'],
    ];
  }

  private function sortKeys(): array
  {
    return $this->routeIs('admin.engagement.comments.index')
      ? ['status', 'spam_score', 'created_at']
      : ($this->routeIs('admin.engagement.ratings.index') ? ['rating_value', 'status', 'created_at', 'updated_at'] : ['pending', 'votes', 'average', 'activity']);
  }

  public function filters(): array
  {
    return array_replace(['search' => '', 'site' => '', 'page_id' => '', 'status' => '', 'rating' => '', 'from' => '', 'until' => '', 'sort' => $this->routeIs('admin.engagement.index') ? 'pending' : 'created_at', 'direction' => 'desc'], array_filter($this->validated(), fn ($value) => $value !== null));
  }
}
