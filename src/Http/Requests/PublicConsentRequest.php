<?php

namespace WebBlocks\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Support\Consent\ConsentConfiguration;
use WebBlocks\Cms\Support\Sites\SiteResolver;

class PublicConsentRequest extends FormRequest
{
  public function authorize(): bool
  {
    $site = app(SiteResolver::class)->current($this);

    return (int) $site->id === (int) $this->route('site')?->id && app(ConsentConfiguration::class)->settings($site)['enabled'];
  }

  public function rules(): array
  {
    return ['status' => ['required', Rule::in(['accepted', 'rejected', 'custom'])],
      'services' => ['present', 'array', 'max:64'], 'services.*' => ['boolean'],
      'revisions' => ['present', 'array', 'max:64'], 'revisions.*' => ['string', 'max:40']];
  }
}
