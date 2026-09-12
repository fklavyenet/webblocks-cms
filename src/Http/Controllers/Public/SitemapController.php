<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use WebBlocks\Cms\Support\Sitemap\SitemapGenerator;
use WebBlocks\Cms\Support\Sites\SiteResolver;

class SitemapController
{
  public function __construct(
    private readonly SiteResolver $siteResolver,
    private readonly SitemapGenerator $generator,
  ) {}

  public function __invoke(Request $request, ?int $page = null): Response
  {
    $site = $this->siteResolver->current($request);
    $xml = $this->generator->sitemap($site, $page);

    abort_if($xml === null, 404);

    return response($xml, 200, [
      'Content-Type' => 'application/xml; charset=UTF-8',
      'X-Robots-Tag' => 'noindex',
    ]);
  }
}
