<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ApplyPageRevisionCandidateRequest extends FormRequest
{
  public function authorize(): bool
  {
    // Site access and revision-apply permission are checked by the controller.
    return $this->user() !== null;
  }

  public function rules(): array
  {
    return [
      'confirm_apply_candidate' => ['accepted'],
      'confirm_translation_removal' => ['sometimes', 'accepted'],
      'translation_removal_locale_ids' => ['sometimes', 'array'],
      'translation_removal_locale_ids.*' => ['integer', 'min:1', 'distinct'],
    ];
  }

  public function confirmedTranslationRemovalLocaleIds(): array
  {
    return $this->boolean('confirm_translation_removal')
      ? array_map('intval', $this->validated('translation_removal_locale_ids', []))
      : [];
  }
}
