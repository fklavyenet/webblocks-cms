<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\PanelLanguages;

class UseAdminLocale
{
  public function __construct(
    private readonly AdminLocaleResolver $localeResolver,
  ) {}

  public function handle(Request $request, Closure $next, ?string $mode = null): Response
  {
    $locale = $this->localeResolver->locale($request->user());
    if (in_array($locale, AdminLocaleResolver::SUPPORTED_LOCALES, true)) {
      if ($mode !== 'custom-only') {
        app()->setLocale($locale);
      }

      return $next($request);
    }

    $application = app();
    $previousLocale = $application->getLocale();
    $original = $application->make('translator');

    try {
      // Content and mail code still see the packaged application locale.
      if ($mode !== 'custom-only') {
        $application->setLocale($this->localeResolver->packagedLocale($request->user()));
      }

      $translator = clone $original;
      $lines = app(PanelLanguages::class)->translations($locale);
      foreach (array_unique(array_map(fn (string $key) => strstr($key, '.', true), array_keys($lines))) as $group) {
        $translator->load('webblocks-cms', $group, $locale);
      }
      $translator->addLines($lines, $locale, 'webblocks-cms');
      $translator->setLocale($locale);
      $translator->setFallback('en');
      $application->instance('translator', $translator);
      Facade::clearResolvedInstance('translator');
      $request->attributes->set('cms_panel_catalog_locale', $locale);

      return $next($request);
    } finally {
      $application->instance('translator', $original);
      Facade::clearResolvedInstance('translator');
      $request->attributes->remove('cms_panel_catalog_locale');
      if ($application->getLocale() !== $previousLocale) {
        $application->setLocale($previousLocale);
      }
    }
  }
}
