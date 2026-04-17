<?php

namespace App\Support\System;

class UpdateChecker
{
    public function __construct(private readonly InstalledVersionStore $installedVersionStore) {}

    public function status(): array
    {
        $currentVersion = $this->installedVersionStore->currentVersion();
        $latestVersion = (string) config('cms.latest_version', $currentVersion);

        return [
            'up_to_date' => version_compare($currentVersion, $latestVersion, '>='),
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'release_notes' => config('cms.release_notes.'.$latestVersion, []),
        ];
    }
}
