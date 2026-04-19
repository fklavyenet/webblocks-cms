<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\System\SystemUpdateInspector;
use App\Support\Updates\PublishReleasePayloadBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SystemUpdateController extends Controller
{
    public function __construct(
        private readonly SystemUpdateInspector $systemUpdateInspector,
        private readonly PublishReleasePayloadBuilder $publishReleasePayloadBuilder,
    ) {}

    public function index(): View
    {
        $report = $this->systemUpdateInspector->report();
        $checkedAt = session('system_updates_checked_at');

        return view('admin.system.updates', [
            'report' => $report,
            'publishDefaults' => [
                'version' => $this->publishReleasePayloadBuilder->resolveVersion(null),
                'channel' => config('webblocks-updates.publish.channel', 'stable'),
            ],
            'checkedAt' => is_string($checkedAt)
                ? now()->parse($checkedAt)
                : ($report['checked_at'] ?? now()),
        ]);
    }

    public function check(): RedirectResponse
    {
        $report = $this->systemUpdateInspector->refreshReport();

        return redirect()
            ->route('admin.system.updates.index')
            ->with('status', 'Update status refreshed.')
            ->with('system_updates_checked_at', ($report['checked_at'] ?? now())->toIso8601String());
    }
}
