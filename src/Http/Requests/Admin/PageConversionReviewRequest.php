<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use WebBlocks\Cms\Support\PageConverter\PageConversionPlanSerializer;
use WebBlocks\Cms\Support\PageConverter\PageConversionPlanSigner;
use WebBlocks\Cms\Support\PageConverter\PageConversionPlanValidator;

class PageConversionReviewRequest extends FormRequest
{
  private ?array $conversionPlanPayload = null;

  public function authorize(): bool
  {
    return $this->user()?->canAccessAdmin() ?? false;
  }

  public function rules(): array
  {
    return [
      'plan_payload' => ['required', 'string'],
      'plan_signature' => ['required', 'string', 'size:64'],
    ];
  }

  public function after(): array
  {
    return [function (Validator $validator): void {
      $payload = (string) $this->input('plan_payload', '');
      $signature = (string) $this->input('plan_signature', '');

      if (! app(PageConversionPlanSigner::class)->verify($payload, $signature)) {
        $validator->errors()->add('plan_payload', 'The submitted conversion plan could not be verified. Analyze the source HTML again.');

        return;
      }

      try {
        $plan = app(PageConversionPlanSerializer::class)->deserialize($payload);
      } catch (InvalidArgumentException) {
        $validator->errors()->add('plan_payload', 'The submitted conversion plan could not be read. Analyze the source HTML again.');

        return;
      }

      foreach (app(PageConversionPlanValidator::class)->validate($plan, $this->user()) as $field => $message) {
        $validator->errors()->add($field, $message);
      }

      if ($validator->errors()->isEmpty()) {
        $this->conversionPlanPayload = $plan;
      }
    }];
  }

  /**
   * @return array<string, mixed>
   */
  public function conversionPlanPayload(): array
  {
    return $this->conversionPlanPayload ?? [];
  }
}
