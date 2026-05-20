<?php

namespace App\Support\System\Updates;

use WebBlocks\Cms\Support\System\Updates\UpdatePackageExtractor as PackageUpdatePackageExtractor;

class UpdatePackageExtractor extends PackageUpdatePackageExtractor
{
    public function extract(string $archivePath, string $destinationDirectory): string
    {
        try {
            return parent::extract($archivePath, $destinationDirectory);
        } catch (\WebBlocks\Cms\Support\System\Updates\UpdateException $exception) {
            throw new UpdateException(
                $exception->userMessage(),
                $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }
    }
}
