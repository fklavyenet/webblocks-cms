<?php

namespace App\Http\Controllers\Admin;

use App\Models\SystemBackup as RootSystemBackup;
use App\Support\System\SystemBackupRestoreManager;
use Illuminate\Http\RedirectResponse;
use Throwable;
use WebBlocks\Cms\Http\Requests\Admin\RunSystemBackupRestoreRequest;
use WebBlocks\Cms\Http\Controllers\Admin\SystemBackupController as PackageSystemBackupController;
use WebBlocks\Cms\Models\SystemBackup;

class SystemBackupController extends PackageSystemBackupController
{
    public function restore(SystemBackup $backup, RunSystemBackupRestoreRequest $request): RedirectResponse
    {
        try {
            $rootBackup = RootSystemBackup::query()->findOrFail($backup->getKey());

            app(SystemBackupRestoreManager::class)->restoreFromBackup($rootBackup, $request->user()?->id);

            return redirect()
                ->route('admin.system.backups.index')
                ->with('status', 'System restore completed successfully.');
        } catch (Throwable $throwable) {
            return redirect()
                ->route('admin.system.backups.show', $rootBackup ?? $backup)
                ->withErrors(['system_restore' => $throwable->getMessage()]);
        }
    }
}
