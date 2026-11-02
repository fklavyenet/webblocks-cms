<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\WebBlocks;

/** Guard the manual Composer completion path before executing migrations. */
class CmsComposerState
{
  public function assertCurrent(): void
  {
    try {
      $lock = json_decode(File::get(base_path('composer.lock')), true, flags: JSON_THROW_ON_ERROR);
      $installed = json_decode(File::get(base_path('vendor/composer/installed.json')), true, flags: JSON_THROW_ON_ERROR);
      $locked = collect($lock['packages'] ?? [])->firstWhere('name', 'fklavyenet/webblocks-cms');
      $runtime = collect($installed['packages'] ?? [])->firstWhere('name', 'fklavyenet/webblocks-cms');
      $versions = new VersionParser;
      $consistent = is_array($locked) && is_array($runtime)
        && $versions->normalize($locked['version'] ?? '0.0.0') === $versions->normalize(WebBlocks::version())
        && $versions->normalize($runtime['version'] ?? '0.0.0') === $versions->normalize(WebBlocks::version())
        && ($locked['source'] ?? null) === ($runtime['source'] ?? null)
        && ($locked['dist'] ?? null) === ($runtime['dist'] ?? null);
    } catch (\Throwable) {
      $consistent = false;
    }

    if (! $consistent) {
      throw new UpdateException(__('webblocks-cms::admin.update_cli.inconsistent'));
    }
  }
}
