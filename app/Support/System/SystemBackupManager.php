<?php

namespace App\Support\System;

use App\Models\SystemBackup;
use WebBlocks\Cms\Support\System\SystemBackupManager as PackageSystemBackupManager;

class SystemBackupManager extends PackageSystemBackupManager
{
    public function createManualBackup(?int $triggeredByUserId = null, ?string $label = null): SystemBackup
    {
        return SystemBackup::query()->findOrFail(parent::createManualBackup($triggeredByUserId, $label)->getKey());
    }

    public function createRestoreSafetyBackup(?int $triggeredByUserId = null, ?string $label = null): SystemBackup
    {
        return SystemBackup::query()->findOrFail(parent::createRestoreSafetyBackup($triggeredByUserId, $label)->getKey());
    }

    public function createPreUpdateBackup(?int $triggeredByUserId = null, ?string $label = null): SystemBackup
    {
        return SystemBackup::query()->findOrFail(parent::createPreUpdateBackup($triggeredByUserId, $label)->getKey());
    }

    public function latest(): ?SystemBackup
    {
        $backup = parent::latest();

        if ($backup === null) {
            return null;
        }

        return SystemBackup::query()->find($backup->getKey());
    }

    public function latestSuccessful(): ?SystemBackup
    {
        $backup = parent::latestSuccessful();

        if ($backup === null) {
            return null;
        }

        return SystemBackup::query()->find($backup->getKey());
    }
}
