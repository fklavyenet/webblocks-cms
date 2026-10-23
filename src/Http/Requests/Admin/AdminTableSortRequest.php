<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminTableSortRequest extends FormRequest
{
  public function rules(): array
  {
    return [];
  }

  public function selection(array $keys, string $sortParam, string $directionParam, string $default, string $defaultDirection): array
  {
    $key = $this->query($sortParam);
    $direction = $this->query($directionParam);
    $values = ['sort' => $key, 'direction' => is_string($direction) ? strtolower($direction) : $direction];
    $sortValid = ! Validator::make($values, ['sort' => ['required', 'string', Rule::in($keys)]])->fails();
    $directionValid = ! Validator::make($values, ['direction' => ['required', 'string', Rule::in(['asc', 'desc'])]])->fails();

    return [$sortValid ? $key : $default, $directionValid ? $values['direction'] : $defaultDirection, $sortValid || ($default !== '' && $directionValid)];
  }
}
