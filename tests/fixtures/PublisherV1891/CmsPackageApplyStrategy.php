<?php

namespace WebBlocks\Cms\Tests\Fixtures\PublisherV1891;

use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\Updates\Client\Apply\PackageApplyStrategy;
use WebBlocks\Cms\Support\Updates\Client\Apply\PackageArtifactValidator;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;

/** CMS license transition adapter; keep the generated shared runtime untouched. */
class CmsPackageApplyStrategy extends PackageApplyStrategy
{
  public function apply(string $stagedRoot): array
  {
    // Validate the whole legacy tree before materializing any trusted root file.
    // Permit only the license file, never the former documentation subtree.
    $this->assertSafeStagedContents($stagedRoot);
    $rules = config('publisher-client.apply.package_validation');
    (new PackageArtifactValidator(
      allowedRoots: [...$rules['allowed_roots'], 'docs/LICENSE'],
      forbiddenPaths: $rules['forbidden_paths'] ?? [],
      requiredPaths: ['src', 'composer.json'],
    ))->validate($stagedRoot);

    $root = $stagedRoot.'/LICENSE';
    $legacy = $stagedRoot.'/docs/LICENSE';
    if (! File::isFile($root)) {
      if (! File::isFile($legacy) || ! File::copy($legacy, $root)) {
        throw new UpdateException(
          'The downloaded update package does not match the expected package structure.',
          'Required package path missing from staged artifact: LICENSE (or legacy docs/LICENSE).',
        );
      }
    }

    // Root wins if both exist. Remove the packaging shim before strict validation.
    File::delete($legacy);
    if (File::isDirectory($stagedRoot.'/docs')) {
      File::deleteDirectory($stagedRoot.'/docs');
    }

    return parent::apply($stagedRoot);
  }
}
