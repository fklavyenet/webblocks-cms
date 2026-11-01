<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\Response;
use WebBlocks\Cms\Http\Requests\Admin\PageSeoCheckRequest;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Seo\PageSeoReport;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;

final class PageSeoCheckController
{
  public function __invoke(PageSeoCheckRequest $request, Page $page, PageSeoReport $reports, PageController $pages): Response
  {
    $report = $reports->run($page, $request->validated('locale'), app(AdminLocaleResolver::class)->locale());
    request()->attributes->set('cms_page_seo_report', $report);
    request()->query->set('tab', 'seo');

    return response($pages->edit($page))->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
  }
}
