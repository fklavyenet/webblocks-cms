<?php

namespace WebBlocks\Cms\Http\Requests;

use WebBlocks\Cms\Support\Consent\ConsentConfiguration;
use WebBlocks\Cms\Support\Consent\ConsentPreferencesLink;
use WebBlocks\Cms\Support\Sites\SiteResolver;

class PublicConsentFormRequest extends PublicConsentRequest
{
  public function authorize(): bool
  {
    return app(ConsentConfiguration::class)->settings(app(SiteResolver::class)->current($this))['enabled'];
  }

  protected function getRedirectUrl(): string
  {
    return app(ConsentPreferencesLink::class)->url(app(SiteResolver::class)->current($this), $this->route('locale'));
  }

  protected function prepareForValidation(): void
  {
    $this->merge(['services' => $this->input('services', []), 'revisions' => $this->input('revisions', [])]);
  }
}
