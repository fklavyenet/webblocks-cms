<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use WebBlocks\Cms\Actions\SystemPages\AddSystemPageDefaults;
use WebBlocks\Cms\Actions\SystemPages\PrepareSystemPage;
use WebBlocks\Cms\Http\Requests\Admin\SystemPageDefaultsRequest;
use WebBlocks\Cms\Http\Requests\Admin\SystemPageRequest;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\SystemPages\SystemPageRegistry;

final class SystemPageController
{
  public function defaults(SystemPageDefaultsRequest $request, Page $page, AddSystemPageDefaults $defaults): RedirectResponse
  {
    $defaults->execute($page, $request->user());

    return redirect()->route('admin.pages.edit', $page)->with('status', __('webblocks-cms::system_pages.defaults_added'));
  }

  public function store(SystemPageRequest $request, PrepareSystemPage $prepare, SystemPageRegistry $registry): RedirectResponse
  {
    $page = $prepare->execute(Site::findOrFail($request->validated('site_id')), $registry->find($request->validated('key')), $request->user());

    return redirect()->route('admin.pages.edit', $page)->with('status', __('webblocks-cms::system_pages.prepared'));
  }
}
