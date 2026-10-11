<?php

namespace WebBlocks\Cms\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Search\SearchTextNormalizer;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class PublicSearchRequest extends FormRequest
{
  public const MAX_LENGTH = 200;

  public const MAX_TERMS = 20;

  public function authorize(): bool
  {
    return true;
  }

  public function validationData(): array
  {
    return $this->query->all();
  }

  public function rules(): array
  {
    return [
      'q' => ['bail', 'nullable', 'string', 'max:'.self::MAX_LENGTH, function (string $attribute, mixed $value, Closure $fail): void {
        $query = app(SearchTextNormalizer::class)->query($value);
        $terms = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($terms) > self::MAX_TERMS) {
          $fail($this->validationText('terms', ['count' => self::MAX_TERMS]));
        }
      }],
    ];
  }

  public function messages(): array
  {
    return [
      'q.string' => $this->validationText('string'),
      'q.max' => $this->validationText('max', ['count' => self::MAX_LENGTH]),
    ];
  }

  private function validationText(string $key, array $replacements = []): string
  {
    $locale = app(PageRouteResolver::class)->currentLocale($this)->code;

    return app(CmsTranslator::class)->get('validation.search.'.$key, $locale, $replacements);
  }

  protected function failedValidation(Validator $validator): void
  {
    // An invalid GET must not redirect back to the same invalid search URL.
    throw new ValidationException($validator, response()->json([
      'message' => $validator->errors()->first(),
      'errors' => $validator->errors(),
    ], 422));
  }
}
