<?php

namespace WebBlocks\Cms\Support\Install;

class LaravelWelcomeRouteCleanupResult
{
    public const REMOVED = 'removed';

    public const UNCHANGED = 'unchanged';

    public const CUSTOM = 'custom';

    public const MISSING = 'missing';

    private function __construct(
        public readonly string $status,
        public readonly ?string $backupPath = null,
    ) {}

    public static function removed(string $backupPath): self
    {
        return new self(self::REMOVED, $backupPath);
    }

    public static function unchanged(): self
    {
        return new self(self::UNCHANGED);
    }

    public static function custom(): self
    {
        return new self(self::CUSTOM);
    }

    public static function missing(): self
    {
        return new self(self::MISSING);
    }

    public function removedWelcomeRoute(): bool
    {
        return $this->status === self::REMOVED;
    }

    public function skippedCustomRouteFile(): bool
    {
        return $this->status === self::CUSTOM;
    }
}
