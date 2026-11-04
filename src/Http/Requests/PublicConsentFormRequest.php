<?php

namespace WebBlocks\Cms\Http\Requests;

use WebBlocks\Cms\Support\Consent\ConsentConfiguration;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Sites\SiteResolver;

class PublicConsentFormRequest extends PublicConsentRequest
{
  public function authorize(): bool
  {
    return app(ConsentConfiguration::class)->settings(app(SiteResolver::class)->current($this))['enabled'];
  }

  protected function getRedirectUrl(): string
  {
    $site = app(SiteResolver::class)->current($this);
    $routes = app(PageRouteResolver::class);
    $home = $routes->homePath($this->route('locale'), $site) ?? $routes->homePath(null, $site);

    return url($home ?? '/').'#cookie-settings';
  }

  protected function prepareForValidation(): void
  {
    $this->merge(['services' => $this->input('services', []), 'revisions' => $this->input('revisions', [])]);
  }
}
