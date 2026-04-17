<?php

namespace App\Support\System;

class UpdateChecker
{
    public function status(): array
    {
        $currentVersion = (string) config('app.version', '0.1.0');
        $latestVersion = (string) config('cms.latest_version', $currentVersion);

        return [
            'up_to_date' => version_compare($currentVersion, $latestVersion, '>='),
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'release_notes' => config('cms.release_notes.'.$latestVersion, []),
        ];
    }
}
