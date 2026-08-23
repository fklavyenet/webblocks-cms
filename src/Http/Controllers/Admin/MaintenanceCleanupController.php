<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\MaintenanceCleanupSettingsRequest;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;
use WebBlocks\Cms\Support\System\SystemBackupCleanup;
use WebBlocks\Cms\Support\System\SystemSettings;

class MaintenanceCleanupController extends Controller
{
  public function __construct(
    private readonly SystemSettings $settings,
    private readonly SystemBackupCleanup $backupCleanup,
    private readonly MaintenanceCleanup $cleanup,
  ) {}

  public function index(): View
  {
    return view('webblocks-cms::admin.system.cleanup', [
      'settings' => [...$this->settings->backupCleanupSettings(), ...$this->settings->maintenanceCleanupSettings()],
      'backupPreview' => $this->backupCleanup->preview(),
      'overview' => $this->cleanup->overview(),
    ]);
  }

  public function update(MaintenanceCleanupSettingsRequest $request): RedirectResponse
  {
    $this->settings->save($request->settingsPayload());

    return redirect()->route('admin.system.cleanup.index')->with('status', 'Cleanup settings updated successfully.');
  }

  public function run(string $category): RedirectResponse
  {
    abort_unless($category === 'backups' || in_array($category, MaintenanceCleanup::RUNNABLE, true), 404);

    if ($category === 'backups') {
      $result = $this->backupCleanup->run(force: true);
      $count = $result->deletedCount();
      $bytes = $result->deletedBytes;
    } else {
      $result = $this->cleanup->run($category);
      $count = $result->deletedCount;
      $bytes = $result->deletedBytes;
    }

    return redirect()->route('admin.system.cleanup.index')
      ->with('status', 'Cleanup removed '.$count.' item(s) and freed '.number_format($bytes).' byte(s).');
  }
}
