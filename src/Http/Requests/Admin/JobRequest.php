<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\JobRun;
use WebBlocks\Cms\Policies\JobPolicy;

class JobRequest extends FormRequest
{
  public function authorize(): bool
  {
    $policy = app(JobPolicy::class);
    if ($this->route('jobRun') instanceof JobRun) {
      return $policy->run($this, $this->route('jobRun'));
    }
    if (! $this->isMethod('GET')) {
      return $policy->system($this->user());
    }

    return $policy->system($this->user()) || $policy->sites($this) !== [];
  }

  public function rules(): array
  {
    return [
      'tab' => ['sometimes', Rule::in(['jobs', 'history', 'automation'])],
      'job_key' => ['sometimes', 'required', 'string', 'max:120'],
      'confirmed' => ['sometimes', 'accepted'],
      'automatic' => ['sometimes', 'required', 'boolean'],
      'action' => ['sometimes', Rule::in(['pause', 'resume', 'cancel'])],
      'page' => ['sometimes', 'integer', 'min:1'],
    ];
  }
}
