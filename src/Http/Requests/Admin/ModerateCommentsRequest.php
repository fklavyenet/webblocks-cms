<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Policies\EngagementPolicy;

class ModerateCommentsRequest extends FormRequest
{
  public function authorize(): bool
  {
    $policy = app(EngagementPolicy::class);
    $comment = $this->route('commentEntry');

    return $comment instanceof CommentEntry ? $policy->update($this->user(), $comment) : $policy->viewAny($this->user());
  }

  public function rules(): array
  {
    return [
      'status' => ['required', Rule::in(CommentEntry::statuses())],
      'comment_ids' => [$this->route('commentEntry') ? 'sometimes' : 'required', 'array', 'min:1', 'max:100'],
      'comment_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
      'return_query' => ['nullable', 'string', 'max:2000'],
    ];
  }

  public function returnFilters(): array
  {
    parse_str((string) $this->input('return_query', ''), $filters);

    return array_filter(array_intersect_key($filters, array_flip(['search', 'site', 'page_id', 'status', 'rating', 'from', 'until', 'page', 'sort'])), 'is_scalar');
  }
}
