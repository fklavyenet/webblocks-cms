<?php

namespace App\Support\Release;

class ReleaseVersionResolver
{
    public function resolve(?string $explicitVersion, ?string $argumentVersion = null): string
    {
        if (is_string($explicitVersion) && trim($explicitVersion) !== '') {
            return trim($explicitVersion);
        }

        if (is_string($argumentVersion) && trim($argumentVersion) !== '') {
            return trim($argumentVersion);
        }

        $appVersion = (string) config('app.version', '');

        if ($appVersion !== '') {
            return $appVersion;
        }

        throw new ReleaseException('No release version could be resolved. Provide --version explicitly.');
    }
}
