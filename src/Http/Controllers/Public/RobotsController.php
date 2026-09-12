<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use WebBlocks\Cms\Support\Sitemap\SitemapGenerator;
use WebBlocks\Cms\Support\Sites\SiteResolver;

class RobotsController
{
  public function __construct(
    private readonly SiteResolver $siteResolver,
    private readonly SitemapGenerator $generator,
  ) {}

  public function __invoke(Request $request): Response
  {
    $site = $this->siteResolver->current($request);
    $lines = array_values(array_filter(array_map('trim', (array) config('webblocks-cms.sitemap.robots_lines', ['User-agent: *', 'Allow: /']))));
    $lines[] = 'Sitemap: '.$this->generator->sitemapUrl($site);

    return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
  }
}
