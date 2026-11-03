<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use WebBlocks\Cms\Http\Requests\Admin\PageSeoCheckRequest;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Seo\SeoSourceVersion;

final class PageSeoFreshnessController
{
  public function __invoke(PageSeoCheckRequest $request, Page $page, SeoSourceVersion $version): JsonResponse
  {
    return response()->json(['ok' => true, 'stale' => ! hash_equals($version->current(), $request->validated('source_version') ?? '')])
      ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
  }
}
