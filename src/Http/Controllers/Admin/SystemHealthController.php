<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\SystemHealthRequest;
use WebBlocks\Cms\Queries\SystemHealthQuery;

class SystemHealthController extends Controller
{
  public function index(SystemHealthRequest $request, SystemHealthQuery $health): View
  {
    return view('webblocks-cms::admin.system.health', ['health' => $health->report($request->siteId())]);
  }

  public function refresh(SystemHealthRequest $request, SystemHealthQuery $health): RedirectResponse
  {
    $health->refresh();

    return redirect()->route('admin.system.health.index', array_filter(['site_id' => $request->siteId()]));
  }
}
