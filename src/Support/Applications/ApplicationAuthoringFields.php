<?php

namespace WebBlocks\Cms\Support\Applications;

use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\EmbeddedApplication;

final class ApplicationAuthoringFields
{
  public const ASSET_PATH_PATTERN = '/^\/(?!\/)[^\s]*$/';

  public const SETTINGS_KEY_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

  public static function identityRules(?EmbeddedApplication $application, bool $partial = false): array
  {
    $presence = $partial ? 'sometimes' : 'required';

    return [
      'handle' => [$presence, 'string', 'max:64', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/', Rule::unique(EmbeddedApplication::class, 'handle')->ignore($application)],
      'name' => [$presence, 'string', 'max:255'],
      'description' => ['nullable', 'string', 'max:5000'],
      'version' => [$presence, 'string', 'max:64'],
      'render_mode' => [$presence, Rule::in(['inline', 'iframe'])],
    ];
  }
}
