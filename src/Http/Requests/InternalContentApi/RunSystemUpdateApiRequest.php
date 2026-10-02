<?php

namespace WebBlocks\Cms\Http\Requests\InternalContentApi;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;

class RunSystemUpdateApiRequest extends FormRequest
{
  public function authorize(): bool
  {
    $token = $this->attributes->get('cms_api_token');

    return $token instanceof CmsApiToken && app(CmsApiTokenCapabilities::class)->has($token, CmsApiTokenCapabilities::SYSTEM_UPDATES_RUN);
  }

  public function rules(): array
  {
    return [
      'idempotency_key' => ['required', 'string', 'max:128', 'regex:/\A[A-Za-z0-9._:-]+\z/'],
      'expected_current_version' => ['required', 'string', 'max:60'],
      'target_version' => ['required', 'string', 'max:60'],
      'checksum_sha256' => ['required', 'string', 'regex:/\A[a-fA-F0-9]{64}\z/'],
      'confirmed' => ['required', 'accepted'],
    ];
  }
}
