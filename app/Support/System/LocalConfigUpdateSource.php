<?php

namespace App\Support\System;

use App\Support\System\Contracts\UpdateSource;

class LocalConfigUpdateSource implements UpdateSource
{
    public function getLatestVersion(): string
    {
        return (string) config('cms.latest_version', config('app.version', '0.1.0'));
    }

    public function getReleaseNotes(): array
    {
        return config('cms.release_notes.'.$this->getLatestVersion(), []);
    }

    public function getChannel(): string
    {
        return (string) config('cms.update.channel', 'stable');
    }

    public function getPublishedAt(): ?string
    {
        return null;
    }

    public function getSourceName(): string
    {
        return 'local';
    }
}
