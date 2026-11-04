<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\Response;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\SystemDiagnosticReportRequest;
use WebBlocks\Cms\Queries\SystemDiagnosticReportQuery;
use WebBlocks\Cms\Support\System\SystemDiagnosticReports;

class SystemDiagnosticReportController
{
  public function index(SystemDiagnosticReportRequest $request): View
  {
    return view('webblocks-cms::admin.system.diagnostics', ['categories' => SystemDiagnosticReportQuery::CATEGORIES, 'runId' => $request->validated('run_id')]);
  }

  public function prepare(SystemDiagnosticReportRequest $request, SystemDiagnosticReports $reports): Response
  {
    $envelope = $reports->prepare($request->user(), $request->validated('categories'), $request->filled('run_id') ? $request->integer('run_id') : null);

    return response()->view('webblocks-cms::admin.system.diagnostics', [
      'categories' => SystemDiagnosticReportQuery::CATEGORIES, 'runId' => $request->validated('run_id'),
      'envelope' => $envelope, 'reportText' => $reports->encode($reports->open($request->user(), $envelope)),
    ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
  }

  public function download(SystemDiagnosticReportRequest $request, SystemDiagnosticReports $reports): Response
  {
    return response($reports->encode($reports->open($request->user(), $request->validated('report'))))
      ->header('Content-Type', 'application/json; charset=UTF-8')
      ->header('Content-Disposition', 'attachment; filename="webblocks-diagnostics.json"')
      ->header('X-Content-Type-Options', 'nosniff')->header('Cache-Control', 'private, no-store');
  }
}
