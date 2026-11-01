<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use WebBlocks\Cms\Http\Requests\Admin\PageSeoCheckRequest;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Seo\PageSeoReport;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;

final class InternalPageSeoCheckController
{
  public function __invoke(PageSeoCheckRequest $request, Page $page, PageSeoReport $reports): JsonResponse
  {
    return response()->json([
      'ok' => true,
      'report' => $reports->run($page, $request->validated('locale'), app(AdminLocaleResolver::class)->locale()),
    ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
  }
}
