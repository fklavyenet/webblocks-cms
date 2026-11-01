<?php

namespace WebBlocks\Cms\Http\Requests\InternalContentApi;

use WebBlocks\Cms\Http\Requests\Admin\ApplyPageRevisionCandidateRequest as AdminRequest;

class ApplyPageRevisionCandidateRequest extends AdminRequest
{
  public function rules(): array
  {
    $rules = parent::rules();
    unset($rules['confirm_apply_candidate']);

    return $rules;
  }
}
