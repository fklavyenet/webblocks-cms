<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\SystemHealthRequest;
use WebBlocks\Cms\Queries\SystemHealthQuery;
use WebBlocks\Cms\Support\System\SchedulerSetup;
use WebBlocks\Cms\Support\System\SystemMaintenanceReports;

class SystemHealthController extends Controller
{
  public function index(SystemHealthRequest $request, SystemHealthQuery $health): View
  {
    return view('webblocks-cms::admin.system.health', ['health' => $health->report($request->siteId()), 'schedulerSetupUrl' => route('admin.system.scheduler.index')]);
  }

  public function scheduler(SystemHealthRequest $request, SchedulerSetup $setup): View
  {
    return view('webblocks-cms::admin.system.scheduler', ['setup' => $setup->instructions()]);
  }

  public function refresh(SystemHealthRequest $request): RedirectResponse
  {
    $redirect = $request->filled('cleanup_tab')
      ? redirect()->route('admin.system.cleanup.index', ['tab' => $request->validated('cleanup_tab')])
      : redirect()->route('admin.system.health.index', array_filter(['site_id' => $request->siteId()]));

    try {
      $refreshed = app(SystemMaintenanceReports::class)->refresh();
    } catch (\Throwable) {
      return $redirect->withErrors(['health' => __('webblocks-cms::maintenance_reports.refresh_failed')]);
    }

    return $refreshed
      ? $redirect->with('status', __('webblocks-cms::maintenance_reports.refresh_completed'))
      : $redirect->with('maintenance_refresh_pending', true);
  }
}
