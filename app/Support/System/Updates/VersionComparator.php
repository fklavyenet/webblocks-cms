<?php

namespace App\Support\System\Updates;

use App\Models\SystemRelease;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class VersionComparator
{
    public function compare(string $left, string $right): int
    {
        return SemanticVersion::parse($left)->compare(SemanticVersion::parse($right));
    }

    public function isNewer(string $candidate, string $installed): bool
    {
        return $this->compare($candidate, $installed) > 0;
    }

    public function isCompatible(SystemRelease $release, string $installedVersion, ?string $phpVersion = null, ?string $laravelVersion = null): ReleaseCompatibility
    {
        $reasons = [];

        try {
            SemanticVersion::parse($installedVersion);
        } catch (InvalidArgumentException) {
            return new ReleaseCompatibility('unknown', ['Installed version is not a valid semantic version.']);
        }

        if (is_string($release->supported_from_version) && $release->supported_from_version !== '' && $this->compare($installedVersion, $release->supported_from_version) < 0) {
            $reasons[] = 'Installed version is below the supported upgrade path.';
        }

        if (is_string($release->supported_until_version) && $release->supported_until_version !== '' && $this->compare($installedVersion, $release->supported_until_version) > 0) {
            $reasons[] = 'Installed version is above the supported upgrade path.';
        }

        if (is_string($phpVersion) && $phpVersion !== '' && is_string($release->min_php_version) && $release->min_php_version !== '' && version_compare($phpVersion, $release->min_php_version, '<')) {
            $reasons[] = 'PHP version does not meet the minimum requirement.';
        }

        if (is_string($laravelVersion) && $laravelVersion !== '' && is_string($release->min_laravel_version) && $release->min_laravel_version !== '' && version_compare($laravelVersion, $release->min_laravel_version, '<')) {
            $reasons[] = 'Laravel version does not meet the minimum requirement.';
        }

        return new ReleaseCompatibility($reasons === [] ? 'compatible' : 'incompatible', $reasons);
    }

    public function latest(Collection $releases): ?SystemRelease
    {
        return $releases
            ->sort(fn (SystemRelease $left, SystemRelease $right) => $this->compare($right->version, $left->version))
            ->first();
    }

    public function normalize(string $version): string
    {
        return SemanticVersion::parse($version)->normalized();
    }
}
