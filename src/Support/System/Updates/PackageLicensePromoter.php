<?php

namespace WebBlocks\Cms\Support\System\Updates;

use RuntimeException;

final class PackageLicensePromoter
{
  public function promote(string $packageRoot): void
  {
    $root = rtrim($packageRoot, DIRECTORY_SEPARATOR);
    $source = $root.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'LICENSE';
    $destination = $root.DIRECTORY_SEPARATOR.'LICENSE';

    if (! is_file($source)) {
      if (is_file($destination)) {
        return;
      }

      throw new RuntimeException('The CMS package contains neither docs/LICENSE nor root LICENSE.');
    }

    $contents = file_get_contents($source);

    if ($contents === false) {
      throw new RuntimeException('The CMS package license notice could not be read.');
    }

    if (is_file($destination)) {
      $installed = file_get_contents($destination);

      if ($installed === $contents) {
        return;
      }

      throw new RuntimeException('The CMS package root LICENSE differs from docs/LICENSE.');
    }

    $temporary = $destination.'.wb-update-new';

    if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
      @unlink($temporary);

      throw new RuntimeException('The CMS package root LICENSE could not be staged.');
    }

    if (! @rename($temporary, $destination)) {
      @unlink($temporary);

      throw new RuntimeException('The CMS package root LICENSE could not be installed.');
    }
  }
}
