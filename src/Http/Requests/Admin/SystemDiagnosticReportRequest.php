<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Policies\SystemDiagnosticReportPolicy;
use WebBlocks\Cms\Queries\SystemDiagnosticReportQuery;

class SystemDiagnosticReportRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(SystemDiagnosticReportPolicy::class)->view($this->user());
  }

  public function rules(): array
  {
    if ($this->routeIs('*.download')) {
      return ['report' => ['required', 'string', 'max:140000']];
    }
    if ($this->isMethod('get')) {
      return ['run_id' => ['nullable', 'integer', 'min:1']];
    }

    return [
      'categories' => ['required', 'array', 'min:1', 'max:5'],
      'categories.*' => ['required', 'string', 'distinct', Rule::in(SystemDiagnosticReportQuery::CATEGORIES)],
      'run_id' => ['nullable', 'integer', 'min:1'],
      'prepare_consent' => ['accepted'],
    ];
  }
}
