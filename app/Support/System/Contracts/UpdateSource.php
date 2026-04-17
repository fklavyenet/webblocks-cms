<?php

namespace App\Support\System\Contracts;

interface UpdateSource
{
    public function getLatestVersion(): string;

    public function getReleaseNotes(): array;

    public function getChannel(): string;

    public function getPublishedAt(): ?string;

    public function getSourceName(): string;
}
