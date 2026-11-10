<?php

namespace WebBlocks\Cms\Support\Locales;

use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\Locale;

final class LocaleAuthoring
{
  public static function rules(?Locale $locale, bool $partial = false): array
  {
    $presence = $partial ? 'nullable' : 'required';

    return [
      'code' => [$presence, 'string', 'regex:'.Locale::CODE_VALIDATION_PATTERN, Rule::unique(Locale::class, 'code')->ignore($locale?->id)],
      'name' => [$presence, 'string', 'max:255'],
      'locale_option' => ['nullable', 'string', 'max:35'],
      'is_default' => ['nullable', 'boolean'],
    ];
  }
}
